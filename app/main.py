import os
import datetime
from typing import List, Optional, Any, Dict
from fastapi import FastAPI, Depends, HTTPException, Query, Response, status
from fastapi.staticfiles import StaticFiles
from fastapi.templating import Jinja2Templates
from fastapi.requests import Request
from pydantic import BaseModel, Field
from sqlalchemy.orm import Session

from app.models import (
    init_db, get_db, User, ApplicantProfile, Question, Exam, ExamQuestion,
    ExamAssignment, Attempt, Answer, AssessmentEvent,
    UserRole, QuestionCategory, QuestionDifficulty, QuestionType,
    AssignmentStatus, AttemptStatus
)
from app.engine import QuestionEngine, ScoringEngine, AnalyticsEngine

# Initialize DB on startup
init_db()

app = FastAPI(title="Applicant Aptitude Exam Hub", version="1.0.0")

# Mount static directory and setup templates
app.mount("/static", StaticFiles(directory="app/static"), name="static")
templates = Jinja2Templates(directory="app/templates")

# Simple active user state for demonstration/testing
current_user_id = 1 # Defaults to admin (id=1)


# Pydantic Schemas
from pydantic import ConfigDict

class UserResponse(BaseModel):
    model_config = ConfigDict(from_attributes=True)
    id: int
    email: str
    full_name: str
    role: str

class SwitchUserRequest(BaseModel):
    user_id: int

class ApplicantCreate(BaseModel):
    email: str
    full_name: str
    password: Optional[str] = "applicant123"
    phone: Optional[str] = None
    applied_position: Optional[str] = None
    notes: Optional[str] = None

class QuestionCreate(BaseModel):
    category: str
    subcategory: Optional[str] = None
    difficulty: str = "Medium"
    question_type: str = "multiple_choice"
    question_text: str
    passage_text: Optional[str] = None
    diagram_svg: Optional[str] = None
    points: float = 1.0
    time_limit_seconds: Optional[int] = None
    choices: Optional[List[Dict[str, Any]]] = []
    correct_answer: str
    explanation: Optional[str] = None

class ExamCreate(BaseModel):
    title: str
    description: Optional[str] = None
    instructions: Optional[str] = None
    duration_minutes: int = 30
    is_published: bool = True
    randomize_questions: bool = True
    randomize_choices: bool = True
    show_results_to_applicant: bool = True
    passing_score: Optional[float] = 70.0
    question_ids: Optional[List[int]] = []

class AssignmentCreate(BaseModel):
    exam_id: int
    applicant_id: int
    due_days: Optional[int] = 7

class StartAssessmentRequest(BaseModel):
    access_code: str

class SaveAnswerRequest(BaseModel):
    attempt_id: int
    question_id: int
    selected_choice: Optional[str] = None
    numeric_answer: Optional[str] = None

class ToggleReviewRequest(BaseModel):
    attempt_id: int
    question_id: int
    is_marked_for_review: bool

class SubmitAssessmentRequest(BaseModel):
    attempt_id: int
    is_timeout: Optional[bool] = False


# --- Web Page Routes ---
@app.get("/")
def home(request: Request):
    return templates.TemplateResponse(request=request, name="index.html")


# --- User & Auth API ---
@app.get("/api/users/me", response_model=UserResponse)
def get_current_user(db: Session = Depends(get_db)):
    user = db.query(User).filter(User.id == current_user_id).first()
    if not user:
        user = db.query(User).first()
    return user

@app.post("/api/auth/switch-user")
def switch_user(req: SwitchUserRequest, db: Session = Depends(get_db)):
    global current_user_id
    user = db.query(User).filter(User.id == req.user_id).first()
    if not user:
        raise HTTPException(status_code=404, detail="User not found")
    current_user_id = user.id
    return {"message": f"Switched to user {user.full_name}", "user": UserResponse.model_validate(user)}

@app.get("/api/users", response_model=List[UserResponse])
def list_users(role: Optional[str] = None, db: Session = Depends(get_db)):
    query = db.query(User)
    if role:
        query = query.filter(User.role == role)
    return query.all()


# --- Applicant API ---
@app.get("/api/applicants")
def list_applicants(db: Session = Depends(get_db)):
    users = db.query(User).filter(User.role == UserRole.APPLICANT.value).order_by(User.id.desc()).all()
    results = []
    for u in users:
        prof = u.profile
        assignment_count = len(u.assignments)
        completed_count = sum(1 for a in u.assignments if a.status == AssignmentStatus.COMPLETED.value)
        results.append({
            "id": u.id,
            "email": u.email,
            "full_name": u.full_name,
            "created_at": u.created_at.strftime("%Y-%m-%d"),
            "phone": prof.phone if prof else "",
            "applied_position": prof.applied_position if prof else "",
            "notes": prof.notes if prof else "",
            "assignment_count": assignment_count,
            "completed_count": completed_count
        })
    return results

@app.post("/api/applicants", status_code=status.HTTP_201_CREATED)
def create_applicant(data: ApplicantCreate, db: Session = Depends(get_db)):
    existing = db.query(User).filter(User.email == data.email).first()
    if existing:
        raise HTTPException(status_code=400, detail="User with this email already exists.")

    u = User(
        email=data.email,
        full_name=data.full_name,
        role=UserRole.APPLICANT.value,
        password_hash=data.password or "applicant123"
    )
    db.add(u)
    db.commit()
    db.refresh(u)

    prof = ApplicantProfile(
        user_id=u.id,
        phone=data.phone,
        applied_position=data.applied_position,
        notes=data.notes
    )
    db.add(prof)
    db.commit()

    return {"message": "Applicant created successfully", "id": u.id}


# --- Question Bank API ---
@app.get("/api/questions")
def list_questions(
    category: Optional[str] = None,
    difficulty: Optional[str] = None,
    question_type: Optional[str] = None,
    search: Optional[str] = None,
    db: Session = Depends(get_db)
):
    query = db.query(Question)
    if category:
        query = query.filter(Question.category == category)
    if difficulty:
        query = query.filter(Question.difficulty == difficulty)
    if question_type:
        query = query.filter(Question.question_type == question_type)
    if search:
        query = query.filter(Question.question_text.ilike(f"%{search}%"))

    questions = query.order_by(Question.id.desc()).all()
    out = []
    for q in questions:
        out.append({
            "id": q.id,
            "category": q.category,
            "subcategory": q.subcategory,
            "difficulty": q.difficulty,
            "question_type": q.question_type,
            "question_text": q.question_text,
            "passage_text": q.passage_text,
            "diagram_svg": q.diagram_svg,
            "points": q.points,
            "choices": q.choices,
            "correct_answer": q.correct_answer,
            "explanation": q.explanation
        })
    return out

@app.post("/api/questions", status_code=status.HTTP_201_CREATED)
def create_question(data: QuestionCreate, db: Session = Depends(get_db)):
    q = Question(
        category=data.category,
        subcategory=data.subcategory,
        difficulty=data.difficulty,
        question_type=data.question_type,
        question_text=data.question_text,
        passage_text=data.passage_text,
        diagram_svg=data.diagram_svg,
        points=data.points,
        time_limit_seconds=data.time_limit_seconds,
        choices=data.choices,
        correct_answer=data.correct_answer,
        explanation=data.explanation
    )
    db.add(q)
    db.commit()
    db.refresh(q)
    return {"message": "Question created", "id": q.id}

@app.get("/api/questions/{question_id}")
def get_question(question_id: int, db: Session = Depends(get_db)):
    q = db.query(Question).filter(Question.id == question_id).first()
    if not q:
        raise HTTPException(status_code=404, detail="Question not found")
    return {
        "id": q.id,
        "category": q.category,
        "subcategory": q.subcategory,
        "difficulty": q.difficulty,
        "question_type": q.question_type,
        "question_text": q.question_text,
        "passage_text": q.passage_text,
        "diagram_svg": q.diagram_svg,
        "points": q.points,
        "choices": q.choices,
        "correct_answer": q.correct_answer,
        "explanation": q.explanation
    }

@app.put("/api/questions/{question_id}")
def update_question(question_id: int, data: QuestionCreate, db: Session = Depends(get_db)):
    q = db.query(Question).filter(Question.id == question_id).first()
    if not q:
        raise HTTPException(status_code=404, detail="Question not found")

    q.category = data.category
    q.subcategory = data.subcategory
    q.difficulty = data.difficulty
    q.question_type = data.question_type
    q.question_text = data.question_text
    q.passage_text = data.passage_text
    q.diagram_svg = data.diagram_svg
    q.points = data.points
    q.time_limit_seconds = data.time_limit_seconds
    q.choices = data.choices
    q.correct_answer = data.correct_answer
    q.explanation = data.explanation

    db.commit()
    return {"message": "Question updated successfully"}

@app.delete("/api/questions/{question_id}")
def delete_question(question_id: int, db: Session = Depends(get_db)):
    q = db.query(Question).filter(Question.id == question_id).first()
    if not q:
        raise HTTPException(status_code=404, detail="Question not found")
    db.delete(q)
    db.commit()
    return {"message": "Question deleted"}


# --- Exam Builder API ---
@app.get("/api/exams")
def list_exams(db: Session = Depends(get_db)):
    exams = db.query(Exam).order_by(Exam.id.desc()).all()
    results = []
    for e in exams:
        q_count = len(e.exam_questions)
        assigned_count = len(e.assignments)
        results.append({
            "id": e.id,
            "title": e.title,
            "description": e.description,
            "instructions": e.instructions,
            "duration_minutes": e.duration_minutes,
            "is_published": e.is_published,
            "is_archived": e.is_archived,
            "randomize_questions": e.randomize_questions,
            "randomize_choices": e.randomize_choices,
            "show_results_to_applicant": e.show_results_to_applicant,
            "passing_score": e.passing_score,
            "question_count": q_count,
            "assigned_count": assigned_count,
            "created_at": e.created_at.strftime("%Y-%m-%d")
        })
    return results

@app.post("/api/exams", status_code=status.HTTP_201_CREATED)
def create_exam(data: ExamCreate, db: Session = Depends(get_db)):
    e = Exam(
        title=data.title,
        description=data.description,
        instructions=data.instructions,
        duration_minutes=data.duration_minutes,
        is_published=data.is_published,
        randomize_questions=data.randomize_questions,
        randomize_choices=data.randomize_choices,
        show_results_to_applicant=data.show_results_to_applicant,
        passing_score=data.passing_score
    )
    db.add(e)
    db.commit()
    db.refresh(e)

    if data.question_ids:
        for idx, qid in enumerate(data.question_ids):
            eq = ExamQuestion(exam_id=e.id, question_id=qid, order=idx+1)
            db.add(eq)
        db.commit()

    return {"message": "Exam created", "id": e.id}

@app.get("/api/exams/{exam_id}")
def get_exam(exam_id: int, db: Session = Depends(get_db)):
    e = db.query(Exam).filter(Exam.id == exam_id).first()
    if not e:
        raise HTTPException(status_code=404, detail="Exam not found")

    eqs = db.query(ExamQuestion).filter(ExamQuestion.exam_id == e.id).order_by(ExamQuestion.order.asc()).all()
    questions = []
    for eq in eqs:
        q = eq.question
        if q:
            questions.append({
                "id": q.id,
                "category": q.category,
                "subcategory": q.subcategory,
                "difficulty": q.difficulty,
                "question_type": q.question_type,
                "question_text": q.question_text,
                "points": q.points
            })

    return {
        "id": e.id,
        "title": e.title,
        "description": e.description,
        "instructions": e.instructions,
        "duration_minutes": e.duration_minutes,
        "is_published": e.is_published,
        "is_archived": e.is_archived,
        "randomize_questions": e.randomize_questions,
        "randomize_choices": e.randomize_choices,
        "show_results_to_applicant": e.show_results_to_applicant,
        "passing_score": e.passing_score,
        "questions": questions
    }

@app.put("/api/exams/{exam_id}")
def update_exam(exam_id: int, data: ExamCreate, db: Session = Depends(get_db)):
    e = db.query(Exam).filter(Exam.id == exam_id).first()
    if not e:
        raise HTTPException(status_code=404, detail="Exam not found")

    e.title = data.title
    e.description = data.description
    e.instructions = data.instructions
    e.duration_minutes = data.duration_minutes
    e.is_published = data.is_published
    e.randomize_questions = data.randomize_questions
    e.randomize_choices = data.randomize_choices
    e.show_results_to_applicant = data.show_results_to_applicant
    e.passing_score = data.passing_score

    # Update questions if provided
    if data.question_ids is not None:
        db.query(ExamQuestion).filter(ExamQuestion.exam_id == e.id).delete()
        for idx, qid in enumerate(data.question_ids):
            eq = ExamQuestion(exam_id=e.id, question_id=qid, order=idx+1)
            db.add(eq)

    db.commit()
    return {"message": "Exam updated successfully"}

@app.post("/api/exams/{exam_id}/duplicate")
def duplicate_exam(exam_id: int, db: Session = Depends(get_db)):
    original = db.query(Exam).filter(Exam.id == exam_id).first()
    if not original:
        raise HTTPException(status_code=404, detail="Exam not found")

    new_exam = Exam(
        title=f"{original.title} (Copy)",
        description=original.description,
        instructions=original.instructions,
        duration_minutes=original.duration_minutes,
        is_published=False,
        randomize_questions=original.randomize_questions,
        randomize_choices=original.randomize_choices,
        show_results_to_applicant=original.show_results_to_applicant,
        passing_score=original.passing_score
    )
    db.add(new_exam)
    db.commit()
    db.refresh(new_exam)

    eqs = db.query(ExamQuestion).filter(ExamQuestion.exam_id == original.id).all()
    for eq in eqs:
        new_eq = ExamQuestion(exam_id=new_exam.id, question_id=eq.question_id, order=eq.order)
        db.add(new_eq)
    db.commit()

    return {"message": "Exam duplicated", "id": new_exam.id}

@app.post("/api/exams/{exam_id}/publish")
def toggle_publish_exam(exam_id: int, db: Session = Depends(get_db)):
    e = db.query(Exam).filter(Exam.id == exam_id).first()
    if not e:
        raise HTTPException(status_code=404, detail="Exam not found")
    e.is_published = not e.is_published
    db.commit()
    return {"message": f"Exam published status set to {e.is_published}", "is_published": e.is_published}

@app.post("/api/exams/{exam_id}/archive")
def toggle_archive_exam(exam_id: int, db: Session = Depends(get_db)):
    e = db.query(Exam).filter(Exam.id == exam_id).first()
    if not e:
        raise HTTPException(status_code=404, detail="Exam not found")
    e.is_archived = not e.is_archived
    db.commit()
    return {"message": f"Exam archive status set to {e.is_archived}", "is_archived": e.is_archived}

@app.delete("/api/exams/{exam_id}")
def delete_exam(exam_id: int, db: Session = Depends(get_db)):
    e = db.query(Exam).filter(Exam.id == exam_id).first()
    if not e:
        raise HTTPException(status_code=404, detail="Exam not found")
    db.delete(e)
    db.commit()
    return {"message": "Exam deleted"}


# --- Exam Assignments API ---
@app.get("/api/assignments")
def list_assignments(applicant_id: Optional[int] = None, db: Session = Depends(get_db)):
    query = db.query(ExamAssignment)
    if applicant_id:
        query = query.filter(ExamAssignment.applicant_id == applicant_id)

    assignments = query.order_by(ExamAssignment.id.desc()).all()
    results = []
    for a in assignments:
        exam = a.exam
        app_user = a.applicant
        latest_attempt = db.query(Attempt).filter(Attempt.assignment_id == a.id).order_by(Attempt.id.desc()).first()

        # Extract categories in exam
        categories = list(set([eq.question.category for eq in exam.exam_questions if eq.question])) if exam else []

        results.append({
            "id": a.id,
            "access_code": a.access_code,
            "status": a.status,
            "assigned_at": a.assigned_at.strftime("%Y-%m-%d"),
            "due_date": a.due_date.strftime("%Y-%m-%d") if a.due_date else "No due date",
            "exam_id": exam.id if exam else None,
            "exam_title": exam.title if exam else "N/A",
            "duration_minutes": exam.duration_minutes if exam else 0,
            "question_count": len(exam.exam_questions) if exam else 0,
            "categories": categories,
            "applicant_id": app_user.id if app_user else None,
            "applicant_name": app_user.full_name if app_user else "N/A",
            "attempt_id": latest_attempt.id if latest_attempt else None,
            "percentage_score": latest_attempt.percentage_score if latest_attempt else None,
            "passed": latest_attempt.passed if latest_attempt else None
        })
    return results

@app.post("/api/assignments", status_code=status.HTTP_201_CREATED)
def create_assignment(data: AssignmentCreate, db: Session = Depends(get_db)):
    exam = db.query(Exam).filter(Exam.id == data.exam_id).first()
    if not exam:
        raise HTTPException(status_code=404, detail="Exam not found")

    user = db.query(User).filter(User.id == data.applicant_id).first()
    if not user:
        raise HTTPException(status_code=404, detail="Applicant not found")

    code = f"EVAL-{user.full_name.replace(' ', '').upper()[:4]}-{datetime.datetime.now().strftime('%M%S')}"
    due_date = datetime.datetime.utcnow() + datetime.timedelta(days=data.due_days or 7)

    assignment = ExamAssignment(
        exam_id=exam.id,
        applicant_id=user.id,
        access_code=code,
        status=AssignmentStatus.NOT_STARTED.value,
        due_date=due_date
    )
    db.add(assignment)
    db.commit()
    db.refresh(assignment)

    return {"message": "Assignment created", "id": assignment.id, "access_code": code}

@app.get("/api/assignments/code/{access_code}")
def get_assignment_by_code(access_code: str, db: Session = Depends(get_db)):
    a = db.query(ExamAssignment).filter(ExamAssignment.access_code == access_code).first()
    if not a:
        raise HTTPException(status_code=404, detail="Assignment with this access code not found")

    exam = a.exam
    app_user = a.applicant
    categories = list(set([eq.question.category for eq in exam.exam_questions if eq.question])) if exam else []

    return {
        "id": a.id,
        "access_code": a.access_code,
        "status": a.status,
        "due_date": a.due_date.strftime("%Y-%m-%d") if a.due_date else "N/A",
        "exam_title": exam.title,
        "exam_description": exam.description,
        "exam_instructions": exam.instructions,
        "duration_minutes": exam.duration_minutes,
        "question_count": len(exam.exam_questions),
        "categories": categories,
        "applicant_name": app_user.full_name
    }


# --- Candidate Assessment Taking API ---
@app.post("/api/assessments/start")
def start_assessment(req: StartAssessmentRequest, db: Session = Depends(get_db)):
    assignment = db.query(ExamAssignment).filter(ExamAssignment.access_code == req.access_code).first()
    if not assignment:
        raise HTTPException(status_code=404, detail="Invalid assessment access code.")

    if assignment.status == AssignmentStatus.COMPLETED.value:
        raise HTTPException(status_code=400, detail="This assessment has already been submitted.")

    exam = assignment.exam
    if not exam or not exam.is_published:
        raise HTTPException(status_code=400, detail="Assessment is currently unavailable.")

    # Check for existing in-progress attempt
    attempt = db.query(Attempt).filter(
        Attempt.assignment_id == assignment.id,
        Attempt.status == AttemptStatus.IN_PROGRESS.value
    ).first()

    if not attempt:
        # Prepare randomized order
        q_ids, choices_order = QuestionEngine.prepare_attempt_structure(exam, db)

        attempt = Attempt(
            assignment_id=assignment.id,
            applicant_id=assignment.applicant_id,
            exam_id=exam.id,
            start_time=datetime.datetime.utcnow(),
            status=AttemptStatus.IN_PROGRESS.value,
            question_order_json=q_ids,
            choices_order_json=choices_order
        )
        db.add(attempt)
        assignment.status = AssignmentStatus.IN_PROGRESS.value
        db.commit()
        db.refresh(attempt)

        # Log event
        ev = AssessmentEvent(attempt_id=attempt.id, event_type="start", details={"info": "Assessment session started."})
        db.add(ev)
        db.commit()

    # Calculate remaining time in seconds
    elapsed_seconds = (datetime.datetime.utcnow() - attempt.start_time).total_seconds()
    total_seconds = exam.duration_minutes * 60
    remaining_seconds = max(int(total_seconds - elapsed_seconds), 0)

    # Check auto-timeout on start if overdue
    if remaining_seconds <= 0 and attempt.status == AttemptStatus.IN_PROGRESS.value:
        ScoringEngine.evaluate_attempt(db, attempt)
        assignment.status = AssignmentStatus.COMPLETED.value
        attempt.status = AttemptStatus.EXPIRED.value
        db.commit()
        raise HTTPException(status_code=400, detail="Time limit for this assessment has expired.")

    # Retrieve ordered questions masked for candidate view
    q_ids = attempt.question_order_json or []
    choices_map = attempt.choices_order_json or {}

    questions_db = db.query(Question).filter(Question.id.in_(q_ids)).all() if q_ids else []
    q_dict_db = {q.id: q for q in questions_db}

    masked_questions = []
    for qid in q_ids:
        if qid in q_dict_db:
            q_obj = q_dict_db[qid]
            custom_choices = choices_map.get(str(qid)) or choices_map.get(qid)
            masked_q = QuestionEngine.format_question_for_candidate(q_obj, custom_choices)
            masked_questions.append(masked_q)

    # Saved answers and review flags
    existing_answers = db.query(Answer).filter(Answer.attempt_id == attempt.id).all()
    saved_answers = {}
    marked_for_review = []
    for ans in existing_answers:
        saved_answers[ans.question_id] = {
            "selected_choice": ans.selected_choice,
            "numeric_answer": ans.numeric_answer
        }
        if ans.is_marked_for_review:
            marked_for_review.append(ans.question_id)

    return {
        "attempt_id": attempt.id,
        "exam_title": exam.title,
        "instructions": exam.instructions,
        "duration_minutes": exam.duration_minutes,
        "remaining_seconds": remaining_seconds,
        "total_questions": len(masked_questions),
        "questions": masked_questions,
        "saved_answers": saved_answers,
        "marked_for_review": marked_for_review
    }

@app.get("/api/assessments/attempt/{attempt_id}")
def get_attempt_state(attempt_id: int, db: Session = Depends(get_db)):
    attempt = db.query(Attempt).filter(Attempt.id == attempt_id).first()
    if not attempt:
        raise HTTPException(status_code=404, detail="Attempt not found")

    exam = db.query(Exam).filter(Exam.id == attempt.exam_id).first()
    elapsed = (datetime.datetime.utcnow() - attempt.start_time).total_seconds()
    remaining = max(int((exam.duration_minutes * 60) - elapsed), 0)

    answers = db.query(Answer).filter(Answer.attempt_id == attempt.id).all()
    saved_answers = {}
    marked_review = []
    for a in answers:
        saved_answers[a.question_id] = {
            "selected_choice": a.selected_choice,
            "numeric_answer": a.numeric_answer
        }
        if a.is_marked_for_review:
            marked_review.append(a.question_id)

    return {
        "attempt_id": attempt.id,
        "status": attempt.status,
        "remaining_seconds": remaining,
        "saved_answers": saved_answers,
        "marked_for_review": marked_review
    }

@app.post("/api/assessments/save-answer")
def save_answer(req: SaveAnswerRequest, db: Session = Depends(get_db)):
    attempt = db.query(Attempt).filter(Attempt.id == req.attempt_id).first()
    if not attempt or attempt.status != AttemptStatus.IN_PROGRESS.value:
        raise HTTPException(status_code=400, detail="Cannot save answer for inactive or submitted attempt.")

    ans = db.query(Answer).filter(Answer.attempt_id == attempt.id, Answer.question_id == req.question_id).first()
    if not ans:
        ans = Answer(
            attempt_id=attempt.id,
            question_id=req.question_id,
            selected_choice=req.selected_choice,
            numeric_answer=req.numeric_answer
        )
        db.add(ans)
    else:
        ans.selected_choice = req.selected_choice
        ans.numeric_answer = req.numeric_answer
        ans.answered_at = datetime.datetime.utcnow()

    db.commit()

    # Audit event
    ev = AssessmentEvent(
        attempt_id=attempt.id,
        event_type="answer_change",
        details={"question_id": req.question_id, "choice": req.selected_choice, "numeric": req.numeric_answer}
    )
    db.add(ev)
    db.commit()

    return {"status": "saved"}

@app.post("/api/assessments/toggle-review")
def toggle_review(req: ToggleReviewRequest, db: Session = Depends(get_db)):
    attempt = db.query(Attempt).filter(Attempt.id == req.attempt_id).first()
    if not attempt or attempt.status != AttemptStatus.IN_PROGRESS.value:
        raise HTTPException(status_code=400, detail="Cannot update active attempt.")

    ans = db.query(Answer).filter(Answer.attempt_id == attempt.id, Answer.question_id == req.question_id).first()
    if not ans:
        ans = Answer(
            attempt_id=attempt.id,
            question_id=req.question_id,
            is_marked_for_review=req.is_marked_for_review
        )
        db.add(ans)
    else:
        ans.is_marked_for_review = req.is_marked_for_review

    db.commit()

    ev = AssessmentEvent(
        attempt_id=attempt.id,
        event_type="mark_review",
        details={"question_id": req.question_id, "marked": req.is_marked_for_review}
    )
    db.add(ev)
    db.commit()

    return {"status": "updated", "is_marked_for_review": req.is_marked_for_review}

@app.post("/api/assessments/submit")
def submit_assessment(req: SubmitAssessmentRequest, db: Session = Depends(get_db)):
    attempt = db.query(Attempt).filter(Attempt.id == req.attempt_id).first()
    if not attempt:
        raise HTTPException(status_code=404, detail="Attempt not found")

    assignment = attempt.assignment

    if attempt.status == AttemptStatus.COMPLETED.value:
        # Return existing score breakdown
        summary = ScoringEngine.evaluate_attempt(db, attempt)
        return {"message": "Assessment already submitted.", "summary": summary}

    # Finalize scoring
    summary = ScoringEngine.evaluate_attempt(db, attempt)
    if assignment:
        assignment.status = AssignmentStatus.COMPLETED.value

    event_type = "timeout" if req.is_timeout else "submit"
    ev = AssessmentEvent(
        attempt_id=attempt.id,
        event_type=event_type,
        details={"info": "Assessment finalized.", "score_percentage": summary["percentage_score"]}
    )
    db.add(ev)
    db.commit()

    return {
        "message": "Assessment submitted successfully.",
        "summary": summary
    }


# --- Results & Analytics API ---
@app.get("/api/results")
def list_results(
    exam_id: Optional[int] = None,
    applicant_id: Optional[int] = None,
    db: Session = Depends(get_db)
):
    query = db.query(Attempt).filter(Attempt.status == AttemptStatus.COMPLETED.value)
    if exam_id:
        query = query.filter(Attempt.exam_id == exam_id)
    if applicant_id:
        query = query.filter(Attempt.applicant_id == applicant_id)

    attempts = query.order_by(Attempt.id.desc()).all()
    results = []
    for att in attempts:
        user = att.applicant
        exam = db.query(Exam).filter(Exam.id == att.exam_id).first()

        results.append({
            "attempt_id": att.id,
            "applicant_name": user.full_name if user else "N/A",
            "applicant_email": user.email if user else "N/A",
            "applied_position": user.profile.applied_position if user and user.profile else "N/A",
            "exam_title": exam.title if exam else "N/A",
            "total_score": att.total_score,
            "max_score": att.max_score,
            "percentage_score": att.percentage_score,
            "passed": att.passed,
            "time_taken_seconds": att.time_taken_seconds,
            "submitted_at": att.submitted_at.strftime("%Y-%m-%d %H:%M:%S") if att.submitted_at else "N/A"
        })
    return results

@app.get("/api/results/{attempt_id}")
def get_result_details(attempt_id: int, db: Session = Depends(get_db)):
    attempt = db.query(Attempt).filter(Attempt.id == attempt_id).first()
    if not attempt:
        raise HTTPException(status_code=404, detail="Result attempt not found.")

    user = attempt.applicant
    exam = db.query(Exam).filter(Exam.id == attempt.exam_id).first()
    summary = ScoringEngine.evaluate_attempt(db, attempt)

    # Detailed questions and candidate answers review
    eqs = db.query(ExamQuestion).filter(ExamQuestion.exam_id == attempt.exam_id).all()
    q_ids = [eq.question_id for eq in eqs]
    questions = db.query(Question).filter(Question.id.in_(q_ids)).all() if q_ids else []

    answers_db = db.query(Answer).filter(Answer.attempt_id == attempt.id).all()
    ans_map = {a.question_id: a for a in answers_db}

    question_review = []
    for q in questions:
        ans = ans_map.get(q.id)
        question_review.append({
            "question_id": q.id,
            "category": q.category,
            "difficulty": q.difficulty,
            "question_text": q.question_text,
            "passage_text": q.passage_text,
            "diagram_svg": q.diagram_svg,
            "points": q.points,
            "choices": q.choices,
            "correct_answer": q.correct_answer,
            "explanation": q.explanation,
            "user_choice": ans.selected_choice if ans else None,
            "user_numeric": ans.numeric_answer if ans else None,
            "is_correct": ans.is_correct if ans else False,
            "score_awarded": ans.score_awarded if ans else 0.0
        })

    events = db.query(AssessmentEvent).filter(AssessmentEvent.attempt_id == attempt.id).order_by(AssessmentEvent.timestamp.asc()).all()
    event_logs = []
    for ev in events:
        event_logs.append({
            "event_type": ev.event_type,
            "details": ev.details,
            "timestamp": ev.timestamp.strftime("%H:%M:%S")
        })

    return {
        "attempt_id": attempt.id,
        "applicant_name": user.full_name if user else "N/A",
        "applicant_email": user.email if user else "N/A",
        "exam_title": exam.title if exam else "N/A",
        "summary": summary,
        "submitted_at": attempt.submitted_at.strftime("%Y-%m-%d %H:%M:%S") if attempt.submitted_at else "N/A",
        "question_review": question_review,
        "event_logs": event_logs
    }

@app.get("/api/analytics")
def get_analytics(
    exam_id: Optional[int] = None,
    applicant_id: Optional[int] = None,
    category: Optional[str] = None,
    status: Optional[str] = None,
    db: Session = Depends(get_db)
):
    return AnalyticsEngine.get_dashboard_analytics(
        db, exam_id=exam_id, applicant_id=applicant_id, category_filter=category, status_filter=status
    )

@app.get("/api/results/export/csv")
def export_results_csv(db: Session = Depends(get_db)):
    csv_str = AnalyticsEngine.export_results_csv(db)
    return Response(content=csv_str, media_type="text/csv", headers={"Content-Disposition": "attachment; filename=aptitude_exam_results.csv"})

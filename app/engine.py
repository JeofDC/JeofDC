import random
import csv
import io
import datetime
from typing import List, Dict, Any, Optional
from sqlalchemy.orm import Session
from sqlalchemy import func

from app.models import (
    User, ApplicantProfile, Question, Exam, ExamQuestion,
    ExamAssignment, Attempt, Answer, AssessmentEvent,
    AssignmentStatus, AttemptStatus, QuestionCategory
)

class QuestionEngine:
    """Manages question retrieval, candidate view masking, and randomization."""

    @staticmethod
    def prepare_attempt_structure(exam: Exam, db: Session) -> tuple[List[int], Dict[int, List[Dict[str, Any]]]]:
        """
        Retrieves exam questions and returns (question_order, choices_order) according to exam settings.
        """
        eqs = db.query(ExamQuestion).filter(ExamQuestion.exam_id == exam.id).order_by(ExamQuestion.order.asc()).all()
        questions = [eq.question for eq in eqs if eq.question]

        question_ids = [q.id for q in questions]
        if exam.randomize_questions:
            random.shuffle(question_ids)

        choices_order = {}
        for q in questions:
            q_choices = q.choices if q.choices else []
            if exam.randomize_choices and len(q_choices) > 1:
                # Copy list and shuffle
                c_copy = list(q_choices)
                random.shuffle(c_copy)
                choices_order[q.id] = c_copy
            else:
                choices_order[q.id] = q_choices

        return question_ids, choices_order

    @staticmethod
    def format_question_for_candidate(question: Question, custom_choices: Optional[List[Dict[str, Any]]] = None) -> Dict[str, Any]:
        """
        Formats question dict safe for applicant view (removes correct_answer and explanation).
        """
        choices = custom_choices if custom_choices is not None else (question.choices or [])
        return {
            "id": question.id,
            "category": question.category,
            "subcategory": question.subcategory,
            "difficulty": question.difficulty,
            "question_type": question.question_type,
            "question_text": question.question_text,
            "passage_text": question.passage_text,
            "diagram_svg": question.diagram_svg,
            "points": question.points,
            "time_limit_seconds": question.time_limit_seconds,
            "choices": choices
        }


class ScoringEngine:
    """Handles answer evaluation, score calculation across categories/difficulties, and pass/fail thresholds."""

    @staticmethod
    def evaluate_attempt(db: Session, attempt: Attempt) -> Dict[str, Any]:
        exam = db.query(Exam).filter(Exam.id == attempt.exam_id).first()
        answers = db.query(Answer).filter(Answer.attempt_id == attempt.id).all()
        answers_by_qid = {ans.question_id: ans for ans in answers}

        eqs = db.query(ExamQuestion).filter(ExamQuestion.exam_id == attempt.exam_id).all()
        q_ids = [eq.question_id for eq in eqs]
        questions = db.query(Question).filter(Question.id.in_(q_ids)).all() if q_ids else []

        total_score = 0.0
        max_score = 0.0

        category_stats = {
            QuestionCategory.ABSTRACT.value: {"score": 0.0, "max": 0.0, "correct": 0, "total": 0},
            QuestionCategory.VERBAL.value: {"score": 0.0, "max": 0.0, "correct": 0, "total": 0},
            QuestionCategory.NUMERICAL.value: {"score": 0.0, "max": 0.0, "correct": 0, "total": 0}
        }

        difficulty_stats = {
            "Easy": {"score": 0.0, "max": 0.0, "correct": 0, "total": 0},
            "Medium": {"score": 0.0, "max": 0.0, "correct": 0, "total": 0},
            "Hard": {"score": 0.0, "max": 0.0, "correct": 0, "total": 0}
        }

        num_correct = 0
        num_incorrect = 0
        num_unanswered = 0

        for q in questions:
            q_pts = q.points or 1.0
            max_score += q_pts

            # Setup category & difficulty dicts if custom category exists
            if q.category not in category_stats:
                category_stats[q.category] = {"score": 0.0, "max": 0.0, "correct": 0, "total": 0}
            category_stats[q.category]["max"] += q_pts
            category_stats[q.category]["total"] += 1

            if q.difficulty not in difficulty_stats:
                difficulty_stats[q.difficulty] = {"score": 0.0, "max": 0.0, "correct": 0, "total": 0}
            difficulty_stats[q.difficulty]["max"] += q_pts
            difficulty_stats[q.difficulty]["total"] += 1

            ans = answers_by_qid.get(q.id)
            if not ans:
                num_unanswered += 1
                continue

            user_resp = ans.selected_choice if ans.selected_choice is not None else ans.numeric_answer
            if user_resp is None or str(user_resp).strip() == "":
                ans.is_correct = False
                ans.score_awarded = 0.0
                num_unanswered += 1
                continue

            # Compare answer
            correct = False
            user_resp_clean = str(user_resp).strip().lower()
            correct_clean = str(q.correct_answer).strip().lower()

            if q.question_type in ["multiple_choice", "true_false", "visual_reasoning"]:
                correct = (user_resp_clean == correct_clean)
            elif q.question_type == "numeric":
                try:
                    # Allow float comparison within small tolerance or string match
                    correct = abs(float(user_resp_clean) - float(correct_clean)) < 1e-4
                except ValueError:
                    correct = (user_resp_clean == correct_clean)

            if correct:
                ans.is_correct = True
                ans.score_awarded = q_pts
                total_score += q_pts
                num_correct += 1

                category_stats[q.category]["score"] += q_pts
                category_stats[q.category]["correct"] += 1
                difficulty_stats[q.difficulty]["score"] += q_pts
                difficulty_stats[q.difficulty]["correct"] += 1
            else:
                ans.is_correct = False
                ans.score_awarded = 0.0
                num_incorrect += 1

        percentage = (total_score / max_score * 100.0) if max_score > 0 else 0.0
        passed = None
        if exam and exam.passing_score is not None:
            passed = (percentage >= exam.passing_score)

        attempt.total_score = round(total_score, 2)
        attempt.max_score = round(max_score, 2)
        attempt.percentage_score = round(percentage, 2)
        attempt.passed = passed
        attempt.status = AttemptStatus.COMPLETED.value
        if not attempt.submitted_at:
            attempt.submitted_at = datetime.datetime.utcnow()

        if attempt.start_time:
            time_diff = (attempt.submitted_at - attempt.start_time).total_seconds()
            attempt.time_taken_seconds = max(int(time_diff), 0)

        db.commit()

        # Build percentage results for category and difficulty
        for cat, data in category_stats.items():
            data["percentage"] = round((data["score"] / data["max"] * 100.0), 2) if data["max"] > 0 else 0.0

        for diff, data in difficulty_stats.items():
            data["percentage"] = round((data["score"] / data["max"] * 100.0), 2) if data["max"] > 0 else 0.0

        return {
            "total_score": round(total_score, 2),
            "max_score": round(max_score, 2),
            "percentage_score": round(percentage, 2),
            "passed": passed,
            "num_correct": num_correct,
            "num_incorrect": num_incorrect,
            "num_unanswered": num_unanswered,
            "time_taken_seconds": attempt.time_taken_seconds,
            "category_scores": category_stats,
            "difficulty_scores": difficulty_stats
        }


class AnalyticsEngine:
    """Generates corporate assessment analytics, chart datasets, and CSV export."""

    @staticmethod
    def get_dashboard_analytics(
        db: Session,
        exam_id: Optional[int] = None,
        applicant_id: Optional[int] = None,
        category_filter: Optional[str] = None,
        status_filter: Optional[str] = None
    ) -> Dict[str, Any]:

        # Total applicants
        total_applicants = db.query(User).filter(User.role == "applicant").count()

        # Assignments query
        assignments_query = db.query(ExamAssignment)
        if exam_id:
            assignments_query = assignments_query.filter(ExamAssignment.exam_id == exam_id)
        if applicant_id:
            assignments_query = assignments_query.filter(ExamAssignment.applicant_id == applicant_id)
        if status_filter:
            assignments_query = assignments_query.filter(ExamAssignment.status == status_filter)

        assignments = assignments_query.all()
        total_assigned = len(assignments)

        completed_count = sum(1 for a in assignments if a.status == AssignmentStatus.COMPLETED.value)
        in_progress_count = sum(1 for a in assignments if a.status == AssignmentStatus.IN_PROGRESS.value)
        not_started_count = sum(1 for a in assignments if a.status == AssignmentStatus.NOT_STARTED.value)
        expired_count = sum(1 for a in assignments if a.status == AssignmentStatus.EXPIRED.value)

        completion_rate = round((completed_count / total_assigned * 100.0), 2) if total_assigned > 0 else 0.0

        # Attempts query for completed scores
        attempts_query = db.query(Attempt).filter(Attempt.status == AttemptStatus.COMPLETED.value)
        if exam_id:
            attempts_query = attempts_query.filter(Attempt.exam_id == exam_id)
        if applicant_id:
            attempts_query = attempts_query.filter(Attempt.applicant_id == applicant_id)

        completed_attempts = attempts_query.all()
        avg_score = round(sum(a.percentage_score for a in completed_attempts) / len(completed_attempts), 2) if completed_attempts else 0.0

        pass_count = sum(1 for a in completed_attempts if a.passed is True)
        pass_rate = round((pass_count / len(completed_attempts) * 100.0), 2) if completed_attempts else 0.0

        # Score distribution
        score_dist = {
            "0-50%": 0,
            "51-70%": 0,
            "71-85%": 0,
            "86-100%": 0
        }
        for att in completed_attempts:
            p = att.percentage_score
            if p <= 50.0:
                score_dist["0-50%"] += 1
            elif p <= 70.0:
                score_dist["51-70%"] += 1
            elif p <= 85.0:
                score_dist["71-85%"] += 1
            else:
                score_dist["86-100%"] += 1

        # Calculate category performance averages across answers in completed attempts
        cat_performance = {
            QuestionCategory.ABSTRACT.value: {"total_pts": 0.0, "earned_pts": 0.0, "avg_percentage": 0.0},
            QuestionCategory.VERBAL.value: {"total_pts": 0.0, "earned_pts": 0.0, "avg_percentage": 0.0},
            QuestionCategory.NUMERICAL.value: {"total_pts": 0.0, "earned_pts": 0.0, "avg_percentage": 0.0}
        }

        if completed_attempts:
            attempt_ids = [a.id for a in completed_attempts]
            answers = db.query(Answer).filter(Answer.attempt_id.in_(attempt_ids)).all()
            for ans in answers:
                q = ans.question
                if not q:
                    continue
                cat = q.category
                if cat not in cat_performance:
                    cat_performance[cat] = {"total_pts": 0.0, "earned_pts": 0.0, "avg_percentage": 0.0}
                cat_performance[cat]["total_pts"] += q.points or 1.0
                cat_performance[cat]["earned_pts"] += ans.score_awarded or 0.0

            for cat, c_data in cat_performance.items():
                if c_data["total_pts"] > 0:
                    c_data["avg_percentage"] = round((c_data["earned_pts"] / c_data["total_pts"] * 100.0), 2)

        return {
            "total_applicants": total_applicants,
            "total_assigned": total_assigned,
            "completed_count": completed_count,
            "in_progress_count": in_progress_count,
            "not_started_count": not_started_count,
            "expired_count": expired_count,
            "completion_rate": completion_rate,
            "average_score": avg_score,
            "pass_rate": pass_rate,
            "score_distribution": score_dist,
            "category_performance": cat_performance
        }

    @staticmethod
    def export_results_csv(db: Session) -> str:
        output = io.StringIO()
        writer = csv.writer(output)

        writer.writerow([
            "Attempt ID", "Applicant Name", "Applicant Email", "Applied Position",
            "Exam Title", "Access Code", "Status", "Percentage Score", "Raw Score",
            "Max Score", "Passed", "Time Taken (mins)", "Submitted At"
        ])

        attempts = db.query(Attempt).order_by(Attempt.id.desc()).all()
        for att in attempts:
            user = att.applicant
            profile = user.profile if user else None
            exam = db.query(Exam).filter(Exam.id == att.exam_id).first()
            assignment = att.assignment

            name = user.full_name if user else "N/A"
            email = user.email if user else "N/A"
            pos = profile.applied_position if profile else "N/A"
            exam_title = exam.title if exam else "N/A"
            code = assignment.access_code if assignment else "N/A"
            status = att.status
            pct = att.percentage_score
            raw = att.total_score
            max_s = att.max_score
            passed = "PASSED" if att.passed is True else ("FAILED" if att.passed is False else "N/A")
            mins = round(att.time_taken_seconds / 60.0, 1) if att.time_taken_seconds else 0.0
            sub_at = att.submitted_at.strftime("%Y-%m-%d %H:%M:%S") if att.submitted_at else "N/A"

            writer.writerow([
                att.id, name, email, pos, exam_title, code, status,
                f"{pct}%", raw, max_s, passed, mins, sub_at
            ])

        return output.getvalue()

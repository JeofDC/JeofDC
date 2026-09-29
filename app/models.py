import enum
import datetime
from sqlalchemy import (
    create_engine, Column, Integer, Float, String, Text, Boolean, DateTime, Enum, JSON, ForeignKey
)
from sqlalchemy.orm import declarative_base, sessionmaker, relationship

Base = declarative_base()

class UserRole(str, enum.Enum):
    ADMIN = "admin"
    APPLICANT = "applicant"

class QuestionCategory(str, enum.Enum):
    ABSTRACT = "Abstract Reasoning"
    VERBAL = "Verbal Reasoning"
    NUMERICAL = "Numerical Reasoning"

class QuestionDifficulty(str, enum.Enum):
    EASY = "Easy"
    MEDIUM = "Medium"
    HARD = "Hard"

class QuestionType(str, enum.Enum):
    MULTIPLE_CHOICE = "multiple_choice"
    TRUE_FALSE = "true_false"
    NUMERIC = "numeric"
    VISUAL_REASONING = "visual_reasoning"

class AssignmentStatus(str, enum.Enum):
    NOT_STARTED = "not_started"
    IN_PROGRESS = "in_progress"
    COMPLETED = "completed"
    EXPIRED = "expired"

class AttemptStatus(str, enum.Enum):
    IN_PROGRESS = "in_progress"
    COMPLETED = "completed"
    EXPIRED = "expired"


class User(Base):
    __tablename__ = "users"

    id = Column(Integer, primary_key=True, index=True)
    email = Column(String(150), unique=True, nullable=False, index=True)
    full_name = Column(String(100), nullable=False)
    role = Column(String(20), default=UserRole.APPLICANT, nullable=False)
    password_hash = Column(String(200), nullable=False)
    created_at = Column(DateTime, default=datetime.datetime.utcnow)

    profile = relationship("ApplicantProfile", back_populates="user", uselist=False, cascade="all, delete-orphan")
    assignments = relationship("ExamAssignment", back_populates="applicant", cascade="all, delete-orphan")
    attempts = relationship("Attempt", back_populates="applicant", cascade="all, delete-orphan")


class ApplicantProfile(Base):
    __tablename__ = "applicant_profiles"

    id = Column(Integer, primary_key=True, index=True)
    user_id = Column(Integer, ForeignKey("users.id"), nullable=False, unique=True)
    phone = Column(String(30), nullable=True)
    applied_position = Column(String(100), nullable=True)
    notes = Column(Text, nullable=True)
    created_at = Column(DateTime, default=datetime.datetime.utcnow)

    user = relationship("User", back_populates="profile")


class Question(Base):
    __tablename__ = "questions"

    id = Column(Integer, primary_key=True, index=True)
    category = Column(String(50), nullable=False, index=True) # Abstract Reasoning, Verbal Reasoning, Numerical Reasoning
    subcategory = Column(String(100), nullable=True)
    difficulty = Column(String(20), default=QuestionDifficulty.MEDIUM, nullable=False) # Easy, Medium, Hard
    question_type = Column(String(30), default=QuestionType.MULTIPLE_CHOICE, nullable=False)

    question_text = Column(Text, nullable=False)
    passage_text = Column(Text, nullable=True)
    diagram_svg = Column(Text, nullable=True)

    points = Column(Float, default=1.0, nullable=False)
    time_limit_seconds = Column(Integer, nullable=True)

    # JSON array of choices e.g. [{"id": "a", "text": "Option A"}, ...]
    choices = Column(JSON, nullable=True, default=[])
    correct_answer = Column(String(200), nullable=False) # choice ID e.g. "a" or numeric text e.g. "127"
    explanation = Column(Text, nullable=True)

    created_at = Column(DateTime, default=datetime.datetime.utcnow)
    updated_at = Column(DateTime, default=datetime.datetime.utcnow, onupdate=datetime.datetime.utcnow)

    exam_questions = relationship("ExamQuestion", back_populates="question", cascade="all, delete-orphan")
    answers = relationship("Answer", back_populates="question", cascade="all, delete-orphan")


class Exam(Base):
    __tablename__ = "exams"

    id = Column(Integer, primary_key=True, index=True)
    title = Column(String(200), nullable=False)
    description = Column(Text, nullable=True)
    instructions = Column(Text, nullable=True)
    duration_minutes = Column(Integer, default=30, nullable=False)

    is_published = Column(Boolean, default=True, nullable=False)
    is_archived = Column(Boolean, default=False, nullable=False)

    randomize_questions = Column(Boolean, default=True, nullable=False)
    randomize_choices = Column(Boolean, default=True, nullable=False)
    show_results_to_applicant = Column(Boolean, default=True, nullable=False)
    passing_score = Column(Float, nullable=True, default=70.0) # percentage or points threshold

    created_at = Column(DateTime, default=datetime.datetime.utcnow)
    updated_at = Column(DateTime, default=datetime.datetime.utcnow, onupdate=datetime.datetime.utcnow)

    exam_questions = relationship("ExamQuestion", back_populates="exam", cascade="all, delete-orphan")
    assignments = relationship("ExamAssignment", back_populates="exam", cascade="all, delete-orphan")


class ExamQuestion(Base):
    __tablename__ = "exam_questions"

    id = Column(Integer, primary_key=True, index=True)
    exam_id = Column(Integer, ForeignKey("exams.id"), nullable=False)
    question_id = Column(Integer, ForeignKey("questions.id"), nullable=False)
    order = Column(Integer, default=0, nullable=False)

    exam = relationship("Exam", back_populates="exam_questions")
    question = relationship("Question", back_populates="exam_questions")


class ExamAssignment(Base):
    __tablename__ = "exam_assignments"

    id = Column(Integer, primary_key=True, index=True)
    exam_id = Column(Integer, ForeignKey("exams.id"), nullable=False)
    applicant_id = Column(Integer, ForeignKey("users.id"), nullable=False)
    access_code = Column(String(50), unique=True, nullable=False, index=True)

    status = Column(String(30), default=AssignmentStatus.NOT_STARTED, nullable=False)
    due_date = Column(DateTime, nullable=True)
    assigned_at = Column(DateTime, default=datetime.datetime.utcnow)

    exam = relationship("Exam", back_populates="assignments")
    applicant = relationship("User", back_populates="assignments")
    attempts = relationship("Attempt", back_populates="assignment", cascade="all, delete-orphan")


class Attempt(Base):
    __tablename__ = "attempts"

    id = Column(Integer, primary_key=True, index=True)
    assignment_id = Column(Integer, ForeignKey("exam_assignments.id"), nullable=False)
    applicant_id = Column(Integer, ForeignKey("users.id"), nullable=False)
    exam_id = Column(Integer, ForeignKey("exams.id"), nullable=False)

    start_time = Column(DateTime, default=datetime.datetime.utcnow, nullable=False)
    end_time = Column(DateTime, nullable=True)
    submitted_at = Column(DateTime, nullable=True)
    time_taken_seconds = Column(Integer, default=0, nullable=False)

    status = Column(String(30), default=AttemptStatus.IN_PROGRESS, nullable=False)

    total_score = Column(Float, default=0.0)
    max_score = Column(Float, default=0.0)
    percentage_score = Column(Float, default=0.0)
    passed = Column(Boolean, nullable=True)

    # Question and choice randomization mapping for this attempt
    question_order_json = Column(JSON, nullable=True, default=[]) # list of question IDs
    choices_order_json = Column(JSON, nullable=True, default={}) # dict: q_id -> list of choice objects

    assignment = relationship("ExamAssignment", back_populates="attempts")
    applicant = relationship("User", back_populates="attempts")
    answers = relationship("Answer", back_populates="attempt", cascade="all, delete-orphan")
    events = relationship("AssessmentEvent", back_populates="attempt", cascade="all, delete-orphan")


class Answer(Base):
    __tablename__ = "answers"

    id = Column(Integer, primary_key=True, index=True)
    attempt_id = Column(Integer, ForeignKey("attempts.id"), nullable=False)
    question_id = Column(Integer, ForeignKey("questions.id"), nullable=False)

    selected_choice = Column(String(100), nullable=True)
    numeric_answer = Column(String(100), nullable=True)
    is_marked_for_review = Column(Boolean, default=False, nullable=False)

    is_correct = Column(Boolean, nullable=True)
    score_awarded = Column(Float, default=0.0, nullable=False)
    answered_at = Column(DateTime, default=datetime.datetime.utcnow)

    attempt = relationship("Attempt", back_populates="answers")
    question = relationship("Question", back_populates="answers")


class AssessmentEvent(Base):
    __tablename__ = "assessment_events"

    id = Column(Integer, primary_key=True, index=True)
    attempt_id = Column(Integer, ForeignKey("attempts.id"), nullable=False)
    event_type = Column(String(50), nullable=False) # start, navigate, answer_change, mark_review, submit, timeout
    details = Column(JSON, nullable=True, default={})
    timestamp = Column(DateTime, default=datetime.datetime.utcnow)

    attempt = relationship("Attempt", back_populates="events")


# Database Setup
DB_URL = "sqlite:///./aptitude_hub.db"
engine = create_engine(DB_URL, connect_args={"check_same_thread": False})
SessionLocal = sessionmaker(autocommit=False, autoflush=False, bind=engine)


def get_db():
    db = SessionLocal()
    try:
        yield db
    finally:
        db.close()


def seed_database(db):
    """Seed initial database with users, questions, exams, assignments and sample attempt data."""
    # Check if already seeded
    if db.query(User).first():
        return

    # 1. Create Users
    admin = User(
        email="admin@examhub.com",
        full_name="System Administrator",
        role=UserRole.ADMIN.value,
        password_hash="admin123"
    )
    john = User(
        email="john.doe@example.com",
        full_name="John Doe",
        role=UserRole.APPLICANT.value,
        password_hash="applicant123"
    )
    jane = User(
        email="jane.smith@example.com",
        full_name="Jane Smith",
        role=UserRole.APPLICANT.value,
        password_hash="applicant123"
    )
    alex = User(
        email="alex.jones@example.com",
        full_name="Alex Jones",
        role=UserRole.APPLICANT.value,
        password_hash="applicant123"
    )
    db.add_all([admin, john, jane, alex])
    db.commit()

    # Profiles
    p1 = ApplicantProfile(user_id=john.id, phone="555-0101", applied_position="Senior Data Analyst", notes="Strong numerical background.")
    p2 = ApplicantProfile(user_id=jane.id, phone="555-0102", applied_position="Software Engineer", notes="Logic & Problem solving focus.")
    p3 = ApplicantProfile(user_id=alex.id, phone="555-0103", applied_position="Product Manager", notes="Verbal and strategic reasoning.")
    db.add_all([p1, p2, p3])
    db.commit()

    # 2. Create 15 Questions (5 per category)
    questions = []

    # --- ABSTRACT REASONING QUESTIONS (5) ---
    svg_matrix = '''<svg viewBox="0 0 300 200" xmlns="http://www.w3.org/2000/svg" style="background:#f8fafc; border-radius:8px; width:100%; height:180px;">
      <grid>
        <!-- Row 1 -->
        <circle cx="50" cy="40" r="18" fill="none" stroke="#2563eb" stroke-width="3"/>
        <circle cx="150" cy="40" r="18" fill="#2563eb" stroke="#2563eb" stroke-width="3"/>
        <circle cx="250" cy="40" r="18" fill="none" stroke="#2563eb" stroke-width="3" stroke-dasharray="4"/>

        <!-- Row 2 -->
        <rect x="35" y="85" width="30" height="30" fill="none" stroke="#0d9488" stroke-width="3"/>
        <rect x="135" y="85" width="30" height="30" fill="#0d9488" stroke="#0d9488" stroke-width="3"/>
        <rect x="235" y="85" width="30" height="30" fill="none" stroke="#0d9488" stroke-width="3" stroke-dasharray="4"/>

        <!-- Row 3 -->
        <polygon points="50,145 35,175 65,175" fill="none" stroke="#d97706" stroke-width="3"/>
        <polygon points="150,145 135,175 165,175" fill="#d97706" stroke="#d97706" stroke-width="3"/>
        <text x="242" y="170" font-size="28" font-weight="bold" fill="#64748b">?</text>
      </grid>
    </svg>'''

    q_abs_1 = Question(
        category=QuestionCategory.ABSTRACT.value,
        subcategory="Matrix Reasoning",
        difficulty=QuestionDifficulty.EASY.value,
        question_type=QuestionType.VISUAL_REASONING.value,
        question_text="Examine the 3x3 pattern matrix below. Which option correctly fills the question mark (?) in the bottom right corner?",
        diagram_svg=svg_matrix,
        points=1.0,
        choices=[
            {"id": "a", "text": "Solid outline triangle"},
            {"id": "b", "text": "Dashed outline triangle"},
            {"id": "c", "text": "Solid filled triangle"},
            {"id": "d", "text": "Dashed outline circle"}
        ],
        correct_answer="b",
        explanation="In each row, the sequence follows: 1) Outline shape, 2) Solid shape, 3) Dashed outline shape. Thus row 3 requires a dashed outline triangle."
    )

    svg_sequence = '''<svg viewBox="0 0 320 100" xmlns="http://www.w3.org/2000/svg" style="background:#f8fafc; border-radius:8px; width:100%; height:120px;">
      <g transform="translate(20, 20)">
        <!-- Step 1: 0 deg -->
        <rect x="0" y="0" width="50" height="50" fill="#e2e8f0" stroke="#0f172a" stroke-width="2"/>
        <line x1="25" y1="25" x2="25" y2="5" stroke="#2563eb" stroke-width="4" stroke-linecap="round"/>
        <!-- Step 2: 90 deg -->
        <rect x="75" y="0" width="50" height="50" fill="#e2e8f0" stroke="#0f172a" stroke-width="2"/>
        <line x1="100" y1="25" x2="120" y2="25" stroke="#2563eb" stroke-width="4" stroke-linecap="round"/>
        <!-- Step 3: 180 deg -->
        <rect x="150" y="0" width="50" height="50" fill="#e2e8f0" stroke="#0f172a" stroke-width="2"/>
        <line x1="175" y1="25" x2="175" y2="45" stroke="#2563eb" stroke-width="4" stroke-linecap="round"/>
        <!-- Step 4: ? -->
        <rect x="225" y="0" width="50" height="50" fill="#f1f5f9" stroke="#2563eb" stroke-width="2" stroke-dasharray="4"/>
        <text x="244" y="32" font-size="22" font-weight="bold" fill="#2563eb">?</text>
      </g>
    </svg>'''

    q_abs_2 = Question(
        category=QuestionCategory.ABSTRACT.value,
        subcategory="Shape Sequences",
        difficulty=QuestionDifficulty.MEDIUM.value,
        question_type=QuestionType.VISUAL_REASONING.value,
        question_text="Observe the clockwise rotation of the pointer inside the square frames. What position should the pointer occupy in the 4th frame?",
        diagram_svg=svg_sequence,
        points=1.0,
        choices=[
            {"id": "a", "text": "Pointing Up (12 o'clock)"},
            {"id": "b", "text": "Pointing Left (9 o'clock)"},
            {"id": "c", "text": "Pointing Down (6 o'clock)"},
            {"id": "d", "text": "Pointing Right (3 o'clock)"}
        ],
        correct_answer="b",
        explanation="The pointer rotates 90 degrees clockwise in each frame: 12 o'clock -> 3 o'clock -> 6 o'clock -> 9 o'clock (Left)."
    )

    svg_odd_one = '''<svg viewBox="0 0 320 90" xmlns="http://www.w3.org/2000/svg" style="background:#f8fafc; border-radius:8px; width:100%; height:110px;">
      <!-- A: 4 sides -->
      <polygon points="25,15 55,15 55,65 25,65" fill="#dbeafe" stroke="#2563eb" stroke-width="2"/>
      <text x="35" y="82" font-size="12" font-weight="bold">Option A</text>

      <!-- B: 3 sides -->
      <polygon points="115,15 95,65 135,65" fill="#dbeafe" stroke="#2563eb" stroke-width="2"/>
      <text x="105" y="82" font-size="12" font-weight="bold">Option B</text>

      <!-- C: 5 sides -->
      <polygon points="185,15 205,30 198,65 172,65 165,30" fill="#dbeafe" stroke="#2563eb" stroke-width="2"/>
      <text x="175" y="82" font-size="12" font-weight="bold">Option C</text>

      <!-- D: 6 sides -->
      <polygon points="255,15 275,25 275,55 255,65 235,55 235,25" fill="#dbeafe" stroke="#2563eb" stroke-width="2"/>
      <text x="245" y="82" font-size="12" font-weight="bold">Option D</text>
    </svg>'''

    q_abs_3 = Question(
        category=QuestionCategory.ABSTRACT.value,
        subcategory="Odd-One-Out Patterns",
        difficulty=QuestionDifficulty.MEDIUM.value,
        question_type=QuestionType.VISUAL_REASONING.value,
        question_text="Which of the four shapes is the odd one out based on the number of vertices/edges?",
        diagram_svg=svg_odd_one,
        points=1.0,
        choices=[
            {"id": "a", "text": "Option A (4 sides)"},
            {"id": "b", "text": "Option B (3 sides)"},
            {"id": "c", "text": "Option C (5 sides)"},
            {"id": "d", "text": "Option D (6 sides)"}
        ],
        correct_answer="b",
        explanation="Option B is the only polygon with an odd number of sides (3), whereas Options A, C, and D have even or composite side relationships."
    )

    q_abs_4 = Question(
        category=QuestionCategory.ABSTRACT.value,
        subcategory="Pattern Recognition",
        difficulty=QuestionDifficulty.HARD.value,
        question_type=QuestionType.MULTIPLE_CHOICE.value,
        question_text="If a black circle moves 2 steps clockwise and a white square moves 1 step counter-clockwise around a 6-node circular network, how many moves will pass before they meet at the same node for the first time?",
        diagram_svg=None,
        points=1.5,
        choices=[
            {"id": "a", "text": "1 move"},
            {"id": "b", "text": "2 moves"},
            {"id": "c", "text": "3 moves"},
            {"id": "d", "text": "4 moves"}
        ],
        correct_answer="b",
        explanation="Relative closing speed per move is 2 + 1 = 3 nodes per turn. On a 6-node network, they meet every 6 / 3 = 2 moves."
    )

    q_abs_5 = Question(
        category=QuestionCategory.ABSTRACT.value,
        subcategory="Spatial Relationships",
        difficulty=QuestionDifficulty.HARD.value,
        question_type=QuestionType.MULTIPLE_CHOICE.value,
        question_text="A 3D cube has its top face painted red, bottom blue, front green, back yellow, left orange, and right purple. If the cube is rolled forward once and then right once, which color is now on the top face?",
        diagram_svg=None,
        points=1.5,
        choices=[
            {"id": "a", "text": "Yellow"},
            {"id": "b", "text": "Orange"},
            {"id": "c", "text": "Green"},
            {"id": "d", "text": "Blue"}
        ],
        correct_answer="b",
        explanation="Initial: Top=Red, Front=Green, Back=Yellow, Left=Orange, Right=Purple, Bottom=Blue.\n1. Roll forward: Top becomes Yellow, Front becomes Red.\n2. Roll right: Left face (Orange) rolls onto the Top. Thus, Orange is on top."
    )

    questions.extend([q_abs_1, q_abs_2, q_abs_3, q_abs_4, q_abs_5])

    # --- VERBAL REASONING QUESTIONS (5) ---
    passage_1 = "Global Remote Work Study 2024: Distributed teams report a 22% increase in self-assessed productivity. However, cross-departmental coordination overhead has risen by 14%. Organizations that implemented structured asynchronous communication channels mitigated coordination delays by 80% compared to those relying solely on real-time messaging."

    q_verb_1 = Question(
        category=QuestionCategory.VERBAL.value,
        subcategory="Reading Comprehension",
        difficulty=QuestionDifficulty.EASY.value,
        question_type=QuestionType.MULTIPLE_CHOICE.value,
        question_text="Based on the passage provided, what was the primary effect of adopting structured asynchronous channels in remote organizations?",
        passage_text=passage_1,
        points=1.0,
        choices=[
            {"id": "a", "text": "It completely eliminated all cross-departmental communication."},
            {"id": "b", "text": "It reduced coordination delays by 80% compared to real-time messaging relying teams."},
            {"id": "c", "text": "It increased self-assessed productivity by an additional 14%."},
            {"id": "d", "text": "It caused a 22% drop in message volume."}
        ],
        correct_answer="b",
        explanation="The passage explicitly states that structured asynchronous channels mitigated coordination delays by 80% compared to those relying solely on real-time messaging."
    )

    q_verb_2 = Question(
        category=QuestionCategory.VERBAL.value,
        subcategory="Analogies",
        difficulty=QuestionDifficulty.EASY.value,
        question_type=QuestionType.MULTIPLE_CHOICE.value,
        question_text="Complete the analogy:\nARCHITECT is to BLUEPRINT as SCULPTOR is to ___?",
        passage_text=None,
        points=1.0,
        choices=[
            {"id": "a", "text": "Museum"},
            {"id": "b", "text": "Maquette (Model)"},
            {"id": "c", "text": "Chisel"},
            {"id": "d", "text": "Marble"}
        ],
        correct_answer="b",
        explanation="An architect creates a blueprint as a preliminary structural plan; a sculptor creates a maquette (or scale model) as a preliminary visual design."
    )

    q_verb_3 = Question(
        category=QuestionCategory.VERBAL.value,
        subcategory="Sentence Completion",
        difficulty=QuestionDifficulty.MEDIUM.value,
        question_type=QuestionType.MULTIPLE_CHOICE.value,
        question_text="Select the word that best completes the sentence:\n'Although the initial fiscal forecasts were pessimistic, the company's Q3 revenue demonstrated remarkable ___, surpassing market expectations.'",
        passage_text=None,
        points=1.0,
        choices=[
            {"id": "a", "text": "resilience"},
            {"id": "b", "text": "redundancy"},
            {"id": "c", "text": "stagnation"},
            {"id": "d", "text": "ambiguity"}
        ],
        correct_answer="a",
        explanation="'Resilience' indicates the ability to recover quickly from difficult conditions, contrasting directly with 'pessimistic' forecasts."
    )

    q_verb_4 = Question(
        category=QuestionCategory.VERBAL.value,
        subcategory="True/False/Cannot Determine",
        difficulty=QuestionDifficulty.MEDIUM.value,
        question_type=QuestionType.TRUE_FALSE.value,
        question_text="Statement: 'All project managers at TechCorp are certified Scrum Masters. Sarah is a Senior Manager at TechCorp. Therefore, Sarah is a certified Scrum Master.'\n\nIs the conclusion True, False, or Cannot Determine based strictly on the premise?",
        passage_text=None,
        points=1.0,
        choices=[
            {"id": "a", "text": "True"},
            {"id": "b", "text": "False"},
            {"id": "c", "text": "Cannot Determine"}
        ],
        correct_answer="c",
        explanation="Cannot Determine. The premise mentions 'project managers'. Sarah is a 'Senior Manager', which may or may not be a project manager position. We cannot assume Senior Manager equals Project Manager without explicit context."
    )

    q_verb_5 = Question(
        category=QuestionCategory.VERBAL.value,
        subcategory="Identifying Assumptions",
        difficulty=QuestionDifficulty.HARD.value,
        question_type=QuestionType.MULTIPLE_CHOICE.value,
        question_text="Argument: 'Switching our corporate fleet to electric vehicles will reduce our total annual carbon emissions by 40%.'\nWhich of the following is an underlying assumption of this argument?",
        passage_text=None,
        points=1.5,
        choices=[
            {"id": "a", "text": "Electric vehicles cost less to maintain than gasoline vehicles."},
            {"id": "b", "text": "The electricity grid used to charge the vehicles does not produce equivalent or higher emissions."},
            {"id": "c", "text": "Employees prefer driving electric vehicles over gasoline vehicles."},
            {"id": "d", "text": "Gasoline prices will continue to increase next year."}
        ],
        correct_answer="b",
        explanation="For net emissions to drop by 40%, the charging electricity source must not generate offsetting carbon emissions."
    )

    questions.extend([q_verb_1, q_verb_2, q_verb_3, q_verb_4, q_verb_5])

    # --- NUMERICAL REASONING QUESTIONS (5) ---
    q_num_1 = Question(
        category=QuestionCategory.NUMERICAL.value,
        subcategory="Percentages & Data Interpretation",
        difficulty=QuestionDifficulty.EASY.value,
        question_type=QuestionType.MULTIPLE_CHOICE.value,
        question_text="A department's annual software budget increased from $80,000 in 2022 to $98,000 in 2023. What was the percentage increase in the budget?",
        passage_text=None,
        points=1.0,
        choices=[
            {"id": "a", "text": "18.0%"},
            {"id": "b", "text": "22.5%"},
            {"id": "c", "text": "20.0%"},
            {"id": "d", "text": "25.0%"}
        ],
        correct_answer="b",
        explanation="Increase = $98,000 - $80,000 = $18,000. Percentage increase = (18,000 / 80,000) * 100 = 0.225 * 100 = 22.5%."
    )

    q_num_2 = Question(
        category=QuestionCategory.NUMERICAL.value,
        subcategory="Ratios & Proportions",
        difficulty=QuestionDifficulty.MEDIUM.value,
        question_type=QuestionType.MULTIPLE_CHOICE.value,
        question_text="In a tech team of 35 members, the ratio of software developers to QA engineers is 4:1. How many software developers are in the team?",
        passage_text=None,
        points=1.0,
        choices=[
            {"id": "a", "text": "21"},
            {"id": "b", "text": "25"},
            {"id": "c", "text": "28"},
            {"id": "d", "text": "30"}
        ],
        correct_answer="c",
        explanation="Total parts = 4 + 1 = 5 parts. Each part = 35 / 5 = 7. Developers = 4 * 7 = 28."
    )

    q_num_3 = Question(
        category=QuestionCategory.NUMERICAL.value,
        subcategory="Number Sequences",
        difficulty=QuestionDifficulty.MEDIUM.value,
        question_type=QuestionType.NUMERIC.value,
        question_text="Identify the missing number in the sequence:\n3, 7, 15, 31, 63, [ ? ]",
        passage_text=None,
        points=1.0,
        choices=[],
        correct_answer="127",
        explanation="Each number is calculated as (previous number * 2) + 1. (63 * 2) + 1 = 126 + 1 = 127."
    )

    q_num_4 = Question(
        category=QuestionCategory.NUMERICAL.value,
        subcategory="Tables and Charts",
        difficulty=QuestionDifficulty.HARD.value,
        question_type=QuestionType.MULTIPLE_CHOICE.value,
        question_text="Regional Sales Data:\n- North Region: $120,000 (Margin: 25%)\n- South Region: $180,000 (Margin: 15%)\n- East Region: $200,000 (Margin: 20%)\nWhat is the total gross profit generated across all three regions combined?",
        passage_text=None,
        points=1.5,
        choices=[
            {"id": "a", "text": "$97,000"},
            {"id": "b", "text": "$87,000"},
            {"id": "c", "text": "$102,000"},
            {"id": "d", "text": "$95,000"}
        ],
        correct_answer="a",
        explanation="North profit = $120,000 * 0.25 = $30,000.\nSouth profit = $180,000 * 0.15 = $27,000.\nEast profit = $200,000 * 0.20 = $40,000.\nTotal = $30,000 + $27,000 + $40,000 = $97,000."
    )

    q_num_5 = Question(
        category=QuestionCategory.NUMERICAL.value,
        subcategory="Averages & Word Problems",
        difficulty=QuestionDifficulty.HARD.value,
        question_type=QuestionType.NUMERIC.value,
        question_text="A candidate scored 80, 85, and 90 on their first three aptitude modules. What score must they achieve on the fourth module to raise their overall average score to 88?",
        passage_text=None,
        points=1.5,
        choices=[],
        correct_answer="97",
        explanation="Target total score for 4 modules = 88 * 4 = 352.\nCurrent sum = 80 + 85 + 90 = 255.\nRequired score = 352 - 255 = 97."
    )

    questions.extend([q_num_1, q_num_2, q_num_3, q_num_4, q_num_5])

    db.add_all(questions)
    db.commit()

    # 3. Create Sample Exams
    exam1 = Exam(
        title="Comprehensive Aptitude Assessment",
        description="Standard pre-employment assessment testing Abstract, Verbal, and Numerical reasoning skills.",
        instructions="Read each question carefully. You may mark questions for review and navigate between them. The timer will automatically submit your assessment when time expires.",
        duration_minutes=30,
        is_published=True,
        randomize_questions=True,
        randomize_choices=True,
        show_results_to_applicant=True,
        passing_score=70.0
    )

    exam2 = Exam(
        title="Software Engineering Logic & Problem Solving",
        description="Focused assessment on pattern recognition and logical deduction.",
        instructions="Complete all questions within the allocated 20 minutes.",
        duration_minutes=20,
        is_published=True,
        randomize_questions=True,
        randomize_choices=False,
        show_results_to_applicant=True,
        passing_score=75.0
    )

    db.add_all([exam1, exam2])
    db.commit()

    # Link all 15 questions to exam1
    for idx, q in enumerate(questions):
        eq = ExamQuestion(exam_id=exam1.id, question_id=q.id, order=idx + 1)
        db.add(eq)

    # Link abstract questions to exam2
    for idx, q in enumerate([q for q in questions if q.category == QuestionCategory.ABSTRACT.value]):
        eq = ExamQuestion(exam_id=exam2.id, question_id=q.id, order=idx + 1)
        db.add(eq)

    db.commit()

    # 4. Create Exam Assignments
    assign_john = ExamAssignment(
        exam_id=exam1.id,
        applicant_id=john.id,
        access_code="EVAL-JOHN-2024",
        status=AssignmentStatus.COMPLETED.value,
        due_date=datetime.datetime.utcnow() + datetime.timedelta(days=7)
    )

    assign_jane = ExamAssignment(
        exam_id=exam1.id,
        applicant_id=jane.id,
        access_code="EVAL-JANE-2024",
        status=AssignmentStatus.NOT_STARTED.value,
        due_date=datetime.datetime.utcnow() + datetime.timedelta(days=5)
    )

    assign_alex = ExamAssignment(
        exam_id=exam2.id,
        applicant_id=alex.id,
        access_code="EVAL-ALEX-2024",
        status=AssignmentStatus.NOT_STARTED.value,
        due_date=datetime.datetime.utcnow() + datetime.timedelta(days=10)
    )

    db.add_all([assign_john, assign_jane, assign_alex])
    db.commit()

    # 5. Create a completed Attempt for John Doe to populate analytics
    start_time = datetime.datetime.utcnow() - datetime.timedelta(hours=2)
    end_time = start_time + datetime.timedelta(minutes=24, seconds=30)

    attempt_john = Attempt(
        assignment_id=assign_john.id,
        applicant_id=john.id,
        exam_id=exam1.id,
        start_time=start_time,
        end_time=end_time,
        submitted_at=end_time,
        time_taken_seconds=1470,
        status=AttemptStatus.COMPLETED.value,
        total_score=15.0,
        max_score=17.0,
        percentage_score=88.2,
        passed=True,
        question_order_json=[q.id for q in questions]
    )
    db.add(attempt_john)
    db.commit()

    # Add Answers for John Doe
    for q in questions:
        # Correct for most, maybe 2 wrong
        is_corr = True
        score = q.points
        ans_choice = q.correct_answer
        num_ans = None

        if q.id in [q_abs_4.id, q_num_4.id]:
            is_corr = False
            score = 0.0
            ans_choice = "a" if q.choices else None

        if q.question_type == QuestionType.NUMERIC.value:
            num_ans = q.correct_answer if is_corr else "100"
            ans_choice = None

        ans = Answer(
            attempt_id=attempt_john.id,
            question_id=q.id,
            selected_choice=ans_choice,
            numeric_answer=num_ans,
            is_marked_for_review=False,
            is_correct=is_corr,
            score_awarded=score,
            answered_at=start_time + datetime.timedelta(minutes=1)
        )
        db.add(ans)

    # Add sample AssessmentEvents for John
    ev1 = AssessmentEvent(attempt_id=attempt_john.id, event_type="start", details={"info": "Assessment started"}, timestamp=start_time)
    ev2 = AssessmentEvent(attempt_id=attempt_john.id, event_type="submit", details={"info": "Submitted via user click"}, timestamp=end_time)
    db.add_all([ev1, ev2])
    db.commit()


def init_db():
    Base.metadata.create_all(bind=engine)
    db = SessionLocal()
    try:
        seed_database(db)
    finally:
        db.close()

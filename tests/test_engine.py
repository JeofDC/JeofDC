import pytest
from app.models import (
    init_db, Base, engine, SessionLocal, Question, Exam, ExamAssignment, Attempt, Answer, User
)
from app.engine import QuestionEngine, ScoringEngine, AnalyticsEngine

@pytest.fixture(autouse=True)
def clean_db():
    Base.metadata.drop_all(bind=engine)
    init_db()

def test_question_engine_masking_and_randomization():
    db = SessionLocal()
    try:
        exam = db.query(Exam).first()
        q_ids, choices_order = QuestionEngine.prepare_attempt_structure(exam, db)
        assert len(q_ids) > 0
        assert isinstance(choices_order, dict)

        q = db.query(Question).first()
        masked = QuestionEngine.format_question_for_candidate(q)
        assert "correct_answer" not in masked
        assert "explanation" not in masked
        assert masked["question_text"] == q.question_text
    finally:
        db.close()

def test_scoring_engine():
    db = SessionLocal()
    try:
        attempt = db.query(Attempt).first()
        result = ScoringEngine.evaluate_attempt(db, attempt)
        assert "total_score" in result
        assert "percentage_score" in result
        assert "category_scores" in result
        assert result["percentage_score"] >= 0.0
        assert result["passed"] is True
    finally:
        db.close()

def test_analytics_and_csv_export():
    db = SessionLocal()
    try:
        analytics = AnalyticsEngine.get_dashboard_analytics(db)
        assert analytics["total_applicants"] >= 1
        assert "category_performance" in analytics
        assert "score_distribution" in analytics

        csv_str = AnalyticsEngine.export_results_csv(db)
        assert "Attempt ID" in csv_str
        assert "John Doe" in csv_str
    finally:
        db.close()

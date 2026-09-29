<?php
// public/reports.php - Examination & Category Performance Reporting View
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Helpers/Auth.php';
require_once __DIR__ . '/../app/Helpers/Sanitize.php';

requirePermission('reports.view');

$pageTitle = 'Examination Reports';
$activeNav = 'reports';

$pdo = getDbConnection();

// Fetch Exam Summary
$stmtExams = $pdo->query("
    SELECT e.id, e.title, e.exam_code,
           COUNT(ea.id) as total_candidates,
           COUNT(CASE WHEN ea.status = 'Completed' THEN 1 END) as completed,
           COUNT(CASE WHEN ea.passed = TRUE THEN 1 END) as passed,
           COUNT(CASE WHEN ea.passed = FALSE AND ea.status = 'Completed' THEN 1 END) as failed,
           COALESCE(ROUND(AVG(ea.percentage), 2), 0) as avg_percentage,
           COALESCE(MAX(ea.percentage), 0) as max_percentage,
           COALESCE(MIN(ea.percentage), 0) as min_percentage
    FROM exams e
    LEFT JOIN exam_attempts ea ON ea.exam_id = e.id AND ea.status IN ('Completed', 'Timed Out')
    GROUP BY e.id
    ORDER BY e.id DESC
");
$examSummary = $stmtExams->fetchAll();

// Fetch Category Performance
$stmtCategories = $pdo->query("
    SELECT c.id, c.name, c.code,
           COUNT(DISTINCT aq.attempt_id) as total_attempts,
           COALESCE(ROUND(AVG(CASE WHEN ca.is_correct = TRUE THEN 100.0 ELSE 0.0 END), 2), 0) as average_percentage
    FROM categories c
    LEFT JOIN attempt_questions aq ON aq.category_id = c.id
    LEFT JOIN exam_attempts ea ON ea.id = aq.attempt_id AND ea.status IN ('Completed', 'Timed Out')
    LEFT JOIN candidate_answers ca ON ca.attempt_question_id = aq.id
    GROUP BY c.id
    ORDER BY c.display_order ASC
");
$categorySummary = $stmtCategories->fetchAll();

require_once __DIR__ . '/../app/Views/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1">Analytics & Examination Reports</h3>
        <p class="text-muted mb-0">System performance summaries and candidate aptitude metrics</p>
    </div>
    <button onclick="exportCSV()" class="btn btn-outline-success"><i class="fa-solid fa-download me-2"></i>Export CSV Report</button>
</div>

<!-- Exam Summary Card -->
<div class="card card-mega shadow-sm mb-4">
    <div class="card-header bg-white fw-bold">
        <i class="fa-solid fa-chart-column me-2 text-success"></i>Examination Performance Summary
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="reportTable">
                <thead class="table-light">
                    <tr>
                        <th>Exam Code</th>
                        <th>Exam Title</th>
                        <th>Completed</th>
                        <th>Passed</th>
                        <th>Failed</th>
                        <th>Average Score</th>
                        <th>Highest Score</th>
                        <th>Lowest Score</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($examSummary as $ex): ?>
                    <tr>
                        <td><code><?= e($ex['exam_code']) ?></code></td>
                        <td class="fw-bold text-dark"><?= e($ex['title']) ?></td>
                        <td><?= e($ex['completed']) ?></td>
                        <td><span class="badge badge-success"><?= e($ex['passed']) ?></span></td>
                        <td><span class="badge badge-danger"><?= e($ex['failed']) ?></span></td>
                        <td><strong class="text-primary"><?= e($ex['avg_percentage']) ?>%</strong></td>
                        <td><span class="text-success fw-bold"><?= e($ex['max_percentage']) ?>%</span></td>
                        <td><span class="text-danger fw-bold"><?= e($ex['min_percentage']) ?>%</span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Category Performance Card -->
<div class="card card-mega shadow-sm mb-4">
    <div class="card-header bg-white fw-bold">
        <i class="fa-solid fa-chart-pie me-2 text-primary"></i>Performance by Aptitude Category
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Category Name</th>
                        <th>Code</th>
                        <th>Total Attempts</th>
                        <th>Average Correctness %</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categorySummary as $cat): ?>
                    <tr>
                        <td class="fw-bold text-dark"><?= e($cat['name']) ?></td>
                        <td><span class="badge badge-info"><?= e($cat['code']) ?></span></td>
                        <td><?= e($cat['total_attempts']) ?></td>
                        <td>
                            <div class="d-flex align-items-center gap-3">
                                <div class="progress flex-grow-1" style="height: 10px;">
                                    <div class="progress-bar bg-success" style="width: <?= e($cat['average_percentage']) ?>%"></div>
                                </div>
                                <span class="fw-bold"><?= e($cat['average_percentage']) ?>%</span>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function exportCSV() {
    let csv = [];
    let rows = document.querySelectorAll("#reportTable tr");
    for (let i = 0; i < rows.length; i++) {
        let row = [], cols = rows[i].querySelectorAll("td, th");
        for (let j = 0; j < cols.length; j++)
            row.push('"' + cols[j].innerText.trim().replace(/"/g, '""') + '"');
        csv.push(row.join(","));
    }
    let csvFile = new Blob([csv.join("\n")], {type: "text/csv"});
    let downloadLink = document.createElement("a");
    downloadLink.download = "aptitude_exam_summary_report.csv";
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.style.display = "none";
    document.body.appendChild(downloadLink);
    downloadLink.click();
}
</script>

<?php require_once __DIR__ . '/../app/Views/footer.php'; ?>

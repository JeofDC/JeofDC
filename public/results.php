<?php
// public/results.php - Results Management & Search/Filtering Page
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Helpers/Auth.php';
require_once __DIR__ . '/../app/Helpers/Sanitize.php';

requireLogin();

$pageTitle = 'Examination Results';
$activeNav = 'results';

require_once __DIR__ . '/../app/Views/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1">Examination Results</h3>
        <p class="text-muted mb-0">View, search, and analyze candidate examination performance</p>
    </div>
    <?php if (hasPermission('results.export')): ?>
        <a href="/api/reports/summary.php" class="btn btn-outline-success"><i class="fa-solid fa-file-csv me-2"></i>Export Summary</a>
    <?php endif; ?>
</div>

<!-- Filter Bar -->
<div class="card card-mega shadow-sm mb-4">
    <div class="card-body p-3">
        <form id="filterForm" class="row g-3" onsubmit="event.preventDefault(); loadResults();">
            <div class="col-md-5">
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                    <input type="text" id="searchInput" class="form-control" placeholder="Search candidate name or exam title...">
                </div>
            </div>
            <div class="col-md-4">
                <select id="statusSelect" class="form-select" onchange="loadResults()">
                    <option value="">All Results (Passed & Failed)</option>
                    <option value="passed">Passed Only</option>
                    <option value="failed">Failed Only</option>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary w-100 fw-semibold"><i class="fa-solid fa-filter me-2"></i>Apply Filters</button>
            </div>
        </form>
    </div>
</div>

<!-- Results Table -->
<div class="card card-mega shadow-sm">
    <div class="card-header bg-white fw-bold">
        <i class="fa-solid fa-list-check me-2 text-success"></i>Results Records
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Candidate</th>
                        <th>Exam Title</th>
                        <th>Date Completed</th>
                        <th>Questions</th>
                        <th>Score %</th>
                        <th>Result</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody id="resultsTableBody">
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">
                            <i class="fa-solid fa-spinner fa-spin me-2"></i>Loading results...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', loadResults);

async function loadResults() {
    const tbody = document.getElementById('resultsTableBody');
    const search = document.getElementById('searchInput').value;
    const status = document.getElementById('statusSelect').value;

    tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted"><i class="fa-solid fa-spinner fa-spin me-2"></i>Loading results...</td></tr>';

    try {
        const res = await fetch(`/api/results/list.php?search=${encodeURIComponent(search)}&status=${encodeURIComponent(status)}`);
        const json = await res.json();

        if (!json.success || !json.data || json.data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted"><i class="fa-solid fa-folder-open display-6 d-block mb-2 text-secondary"></i>No examination results found matching the filters.</td></tr>';
            return;
        }

        tbody.innerHTML = json.data.map(r => `
            <tr>
                <td>
                    <div class="fw-bold text-dark">${escapeHtml(r.first_name + ' ' + r.last_name)}</div>
                    <small class="text-muted">@${escapeHtml(r.username)}</small>
                </td>
                <td>
                    <div class="fw-medium">${escapeHtml(r.exam_title)}</div>
                    <small class="text-muted">Code: <code>${escapeHtml(r.exam_code)}</code></small>
                </td>
                <td>${r.submitted_at ? new Date(r.submitted_at).toLocaleString() : 'N/A'}</td>
                <td>${r.answered_questions} / ${r.total_questions}</td>
                <td><strong class="fs-6">${r.percentage}%</strong></td>
                <td>
                    ${r.passed ? '<span class="badge badge-success px-2 py-1">PASSED</span>' : '<span class="badge badge-danger px-2 py-1">FAILED</span>'}
                </td>
                <td class="text-end">
                    <a href="/result.php?attempt_id=${r.id}" class="btn btn-sm btn-outline-success">
                        <i class="fa-solid fa-eye me-1"></i>View Detailed Result
                    </a>
                </td>
            </tr>
        `).join('');

    } catch(e) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-danger"><i class="fa-solid fa-triangle-exclamation me-2"></i>Error loading results.</td></tr>';
    }
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
</script>

<?php require_once __DIR__ . '/../app/Views/footer.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Assessment History | DISC Personality Center</title>
    <link rel="stylesheet" href="/assets/css/ui-library.css">
</head>
<body>

    <header class="navbar">
        <a href="/" class="navbar-brand">
            <span class="brand-badge">DISC</span>
            <span>Personality Center</span>
        </a>
        <nav class="nav-links">
            <a href="/" class="nav-link">Home</a>
            <a href="/quiz.php" class="nav-link">Take Assessment</a>
            <a href="/history.php" class="nav-link active">Search Results</a>
        </nav>
    </header>

    <main class="container">
        <div class="card">
            <h2 style="font-size: 1.5rem; margin-bottom: 0.5rem;">Find Past DISC Reports</h2>
            <p style="color: var(--text-secondary); margin-bottom: 1.5rem;">
                Search past assessment submissions by candidate email address or name to view detailed personality reports.
            </p>

            <form id="search-form" style="display: flex; gap: 0.75rem; margin-bottom: 1.5rem;">
                <input type="text" id="search-query" class="form-control" placeholder="Search by email or name..." style="flex: 1;">
                <button type="submit" class="btn btn-primary">Search</button>
            </form>

            <div id="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Candidate</th>
                            <th>Email</th>
                            <th>Primary Trait</th>
                            <th>Top Scores (D / I / S / C)</th>
                            <th>Date Taken</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="results-body">
                        <tr>
                            <td colspan="6" style="text-align: center; color: var(--text-muted);">Loading recent assessments...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <footer>
        <p>&copy; <?php echo date('Y'); ?> DISC Personality Center. All rights reserved.</p>
    </footer>

    <script>
        async function loadHistory(query = '') {
            const tbody = document.getElementById('results-body');
            tbody.innerHTML = '<tr><td colspan="6" style="text-align: center;">Searching...</td></tr>';

            try {
                const response = await fetch(`/api/quiz/history.php?query=${encodeURIComponent(query)}`);
                const res = await response.json();

                if (!res.success || !res.data || res.data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; color: var(--text-muted);">No assessment records found.</td></tr>';
                    return;
                }

                tbody.innerHTML = '';
                res.data.forEach(item => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td style="font-weight: 600;">${escapeHtml(item.user_name)}</td>
                        <td>${escapeHtml(item.user_email)}</td>
                        <td>
                            <span class="brand-badge" style="background: var(--disc-${(item.primary_dimension || 'd').toLowerCase()});">
                                ${item.primary_name || item.primary_dimension}
                            </span>
                        </td>
                        <td>${item.percentage_d}% / ${item.percentage_i}% / ${item.percentage_s}% / ${item.percentage_c}%</td>
                        <td>${new Date(item.created_at).toLocaleDateString()}</td>
                        <td>
                            <a href="/result.php?id=${item.id}" class="btn btn-secondary" style="padding: 0.35rem 0.75rem; font-size: 0.85rem;">View Report</a>
                        </td>
                    `;
                    tbody.appendChild(tr);
                });

            } catch (err) {
                console.error(err);
                tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; color: var(--disc-d);">Error loading history records.</td></tr>';
            }
        }

        function escapeHtml(str) {
            return str ? str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;') : '';
        }

        document.getElementById('search-form').addEventListener('submit', (e) => {
            e.preventDefault();
            loadHistory(document.getElementById('search-query').value);
        });

        document.addEventListener('DOMContentLoaded', () => loadHistory());
    </script>
</body>
</html>

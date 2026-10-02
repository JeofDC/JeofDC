<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DISC Personality Report | DISC Personality Center</title>
    <link rel="stylesheet" href="/assets/css/ui-library.css">
    <style>
        .report-header {
            text-align: center;
            padding: 2rem 1rem;
            background: #ffffff;
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            margin-bottom: 2rem;
            box-shadow: var(--shadow-md);
        }
        .badge-primary-trait {
            display: inline-block;
            padding: 0.5rem 1.25rem;
            border-radius: var(--radius-full);
            color: white;
            font-weight: 700;
            font-size: 1.1rem;
            margin-bottom: 0.75rem;
        }
        .trait-d { background-color: var(--disc-d); }
        .trait-i { background-color: var(--disc-i); }
        .trait-s { background-color: var(--disc-s); }
        .trait-c { background-color: var(--disc-c); }

        .section-title {
            font-size: 1.25rem;
            font-weight: 700;
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 0.5rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
    </style>
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
            <a href="/history.php" class="nav-link">Search Results</a>
        </nav>
    </header>

    <main class="container">

        <div id="loading-state" class="card" style="text-align: center; padding: 3rem;">
            <h3>Loading your personality report...</h3>
        </div>

        <div id="report-content" style="display: none;">
            <!-- Header Summary -->
            <div class="report-header">
                <p style="color: var(--text-muted); font-size: 0.95rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.25rem;">
                    Official DISC Assessment Report
                </p>
                <h1 id="user-name-title" style="font-size: 2rem; font-weight: 800; margin-bottom: 0.5rem;">Candidate Name</h1>
                <p id="user-email-text" style="color: var(--text-secondary); margin-bottom: 1.25rem;">user@example.com</p>

                <div id="primary-badge" class="badge-primary-trait trait-d">
                    Primary Profile: Dominance (The Driver)
                </div>
                <p id="primary-tagline" style="font-size: 1.15rem; color: var(--text-secondary); max-width: 600px; margin: 0 auto;">
                    Tagline summary...
                </p>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 2rem; margin-bottom: 2rem;">

                <!-- Score Chart Breakdown -->
                <div class="card" style="margin-bottom: 0;">
                    <h3 class="section-title">📊 DISC Dimensions Breakdown</h3>

                    <div class="chart-bar-group">
                        <div class="chart-label">
                            <span>Dominance (D)</span>
                            <span id="pct-d">0%</span>
                        </div>
                        <div class="chart-bar-bg">
                            <div id="bar-d" class="chart-bar-fill d" style="width: 0%;"></div>
                        </div>
                    </div>

                    <div class="chart-bar-group">
                        <div class="chart-label">
                            <span>Influence (I)</span>
                            <span id="pct-i">0%</span>
                        </div>
                        <div class="chart-bar-bg">
                            <div id="bar-i" class="chart-bar-fill i" style="width: 0%;"></div>
                        </div>
                    </div>

                    <div class="chart-bar-group">
                        <div class="chart-label">
                            <span>Steadiness (S)</span>
                            <span id="pct-s">0%</span>
                        </div>
                        <div class="chart-bar-bg">
                            <div id="bar-s" class="chart-bar-fill s" style="width: 0%;"></div>
                        </div>
                    </div>

                    <div class="chart-bar-group">
                        <div class="chart-label">
                            <span>Conscientiousness (C)</span>
                            <span id="pct-c">0%</span>
                        </div>
                        <div class="chart-bar-bg">
                            <div id="bar-c" class="chart-bar-fill c" style="width: 0%;"></div>
                        </div>
                    </div>
                </div>

                <!-- Primary Personality Profile Details -->
                <div class="card" style="margin-bottom: 0;">
                    <h3 class="section-title">💡 Core Profile Description</h3>
                    <p id="primary-desc" style="color: var(--text-primary); font-size: 1.05rem; line-height: 1.7; margin-bottom: 1rem;">
                        Description details...
                    </p>
                    <div style="background: #f8fafc; border-left: 4px solid var(--primary); padding: 1rem; border-radius: 0 var(--radius-md) var(--radius-md) 0;">
                        <h4 style="font-size: 0.95rem; text-transform: uppercase; color: var(--text-muted); margin-bottom: 0.25rem;">Ideal Work Style</h4>
                        <p id="primary-workstyle" style="font-weight: 600; color: var(--text-primary);"></p>
                    </div>
                </div>

            </div>

            <!-- Deep Dive Analysis -->
            <div class="card">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem;">
                    <div>
                        <h4 style="color: #16a34a; font-size: 1.1rem; margin-bottom: 0.5rem;">✨ Key Strengths</h4>
                        <p id="primary-strengths" style="color: var(--text-secondary); line-height: 1.6;"></p>
                    </div>
                    <div>
                        <h4 style="color: #d97706; font-size: 1.1rem; margin-bottom: 0.5rem;">🎯 Potential Growth Areas</h4>
                        <p id="primary-growth" style="color: var(--text-secondary); line-height: 1.6;"></p>
                    </div>
                    <div>
                        <h4 style="color: var(--primary); font-size: 1.1rem; margin-bottom: 0.5rem;">💬 Communication Tips</h4>
                        <p id="primary-comm" style="color: var(--text-secondary); line-height: 1.6;"></p>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div style="display: flex; justify-content: center; gap: 1rem; margin-top: 2rem;">
                <a href="/quiz.php" class="btn btn-primary">Retake Assessment</a>
                <button onclick="window.print();" class="btn btn-outline">Print / Save PDF</button>
            </div>

        </div>

    </main>

    <footer>
        <p>&copy; <?php echo date('Y'); ?> DISC Personality Center. All rights reserved.</p>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', async () => {
            const urlParams = new URLSearchParams(window.location.search);
            const attemptId = urlParams.get('id');

            if (!attemptId) {
                alert("No assessment attempt specified.");
                window.location.href = '/quiz.php';
                return;
            }

            try {
                const response = await fetch(`/api/quiz/result.php?id=${attemptId}`);
                const res = await response.json();

                if (!res.success || !res.data) {
                    alert(res.message || "Failed to load attempt result.");
                    return;
                }

                const data = res.data;
                const attempt = data.attempt;
                const primary = data.primary_dimension;
                const percentages = data.percentages;

                document.getElementById('user-name-title').textContent = attempt.user_name;
                document.getElementById('user-email-text').textContent = attempt.user_email + ' • Taken on ' + new Date(attempt.created_at).toLocaleDateString();

                // Trait badge styling
                const primaryBadge = document.getElementById('primary-badge');
                primaryBadge.textContent = `Primary Profile: ${primary.name} (${primary.title})`;
                primaryBadge.className = `badge-primary-trait trait-${primary.code.toLowerCase()}`;

                document.getElementById('primary-tagline').textContent = primary.tagline;
                document.getElementById('primary-desc').textContent = primary.description;
                document.getElementById('primary-workstyle').textContent = primary.work_style;
                document.getElementById('primary-strengths').textContent = primary.strengths;
                document.getElementById('primary-growth').textContent = primary.growth_areas;
                document.getElementById('primary-comm').textContent = primary.communication_tips;

                // Chart progress bars
                document.getElementById('pct-d').textContent = `${percentages.D}%`;
                document.getElementById('pct-i').textContent = `${percentages.I}%`;
                document.getElementById('pct-s').textContent = `${percentages.S}%`;
                document.getElementById('pct-c').textContent = `${percentages.C}%`;

                setTimeout(() => {
                    document.getElementById('bar-d').style.width = `${percentages.D}%`;
                    document.getElementById('bar-i').style.width = `${percentages.I}%`;
                    document.getElementById('bar-s').style.width = `${percentages.S}%`;
                    document.getElementById('bar-c').style.width = `${percentages.C}%`;
                }, 100);

                document.getElementById('loading-state').style.display = 'none';
                document.getElementById('report-content').style.display = 'block';

            } catch (err) {
                console.error(err);
                alert("Error connecting to server.");
            }
        });
    </script>
</body>
</html>

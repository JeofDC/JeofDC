<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Take DISC Assessment | DISC Personality Center</title>
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
            <a href="/quiz.php" class="nav-link active">Take Assessment</a>
            <a href="/history.php" class="nav-link">Search Results</a>
        </nav>
    </header>

    <main class="container">

        <!-- User Information Setup Screen -->
        <div id="user-info-card" class="card">
            <h2 style="font-size: 1.5rem; margin-bottom: 0.5rem;">Welcome to your DISC Personality Assessment</h2>
            <p style="color: var(--text-secondary); margin-bottom: 1.5rem;">
                Please enter your details below so we can generate your customized personality profile.
            </p>

            <form id="user-info-form">
                <div class="form-group">
                    <label for="user_name" class="form-label">Full Name *</label>
                    <input type="text" id="user_name" class="form-control" placeholder="e.g. Alex Morgan" required>
                </div>
                <div class="form-group">
                    <label for="user_email" class="form-label">Email Address *</label>
                    <input type="email" id="user_email" class="form-control" placeholder="e.g. alex.morgan@example.com" required>
                </div>
                <button type="submit" class="btn btn-primary btn-lg" style="width: 100%; margin-top: 1rem;">
                    Begin 12-Question Test &rarr;
                </button>
            </form>
        </div>

        <!-- Assessment Screen -->
        <div id="quiz-card" class="card" style="display: none;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                <span id="question-counter" style="font-weight: 700; color: var(--primary);">Question 1 of 12</span>
                <span id="progress-percentage" style="color: var(--text-muted); font-size: 0.9rem;">0% Completed</span>
            </div>

            <div class="progress-container">
                <div id="progress-bar" class="progress-bar"></div>
            </div>

            <div id="question-container" style="margin-top: 1.5rem;">
                <h3 id="question-text" style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1rem;">
                    Loading question...
                </h3>
                <div id="options-grid" class="options-grid">
                    <!-- Dynamic option tiles -->
                </div>
            </div>

            <div style="display: flex; justify-content: space-between; margin-top: 2rem;">
                <button id="btn-prev" class="btn btn-secondary" disabled>&larr; Previous</button>
                <button id="btn-next" class="btn btn-primary">Next &rarr;</button>
                <button id="btn-submit" class="btn btn-primary" style="display: none; background-color: var(--disc-s);">
                    Submit Assessment &rarr;
                </button>
            </div>
        </div>

    </main>

    <footer>
        <p>&copy; <?php echo date('Y'); ?> DISC Personality Center. All rights reserved.</p>
    </footer>

    <script src="/assets/js/quiz.js"></script>
</body>
</html>

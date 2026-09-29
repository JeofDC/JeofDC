<?php
// public/login.php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../app/Helpers/Auth.php';

if (isLoggedIn()) {
    header('Location: /dashboard.php');
    exit();
}

$pageTitle = 'Login';
$hideSidebar = true;
require_once __DIR__ . '/../app/Views/header.php';
?>

<div class="row justify-content-center align-items-center" style="min-height: 80vh;">
    <div class="col-md-5 col-lg-4">
        <div class="card card-mega shadow-sm">
            <div class="card-header text-center py-3 bg-white border-bottom">
                <h4 class="mb-0 text-success fw-bold">
                    <i class="fa-solid fa-graduation-cap me-2"></i>Aptitude System
                </h4>
                <small class="text-muted">Sign in to access your portal</small>
            </div>
            <div class="card-body p-4">
                <div id="alertContainer"></div>

                <form id="loginForm" onsubmit="handleLogin(event)">
                    <div class="mb-3">
                        <label for="username" class="form-label fw-medium">Username or Email</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="fa-solid fa-user text-muted"></i></span>
                            <input type="text" id="username" class="form-control" placeholder="Enter username or email" required>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="password" class="form-label fw-medium">Password</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="fa-solid fa-lock text-muted"></i></span>
                            <input type="password" id="password" class="form-control" placeholder="Enter password" required>
                        </div>
                    </div>

                    <button type="submit" id="loginBtn" class="btn btn-success w-100 py-2 fw-semibold">
                        <i class="fa-solid fa-right-to-bracket me-2"></i>Sign In
                    </button>
                </form>

                <div class="mt-4 text-center border-top pt-3 text-muted small">
                    <p class="mb-1"><strong>Development Credentials:</strong></p>
                    <p class="mb-0">Admin: <code>admin</code> / <code>Admin123!</code></p>
                    <p class="mb-0">Examiner: <code>examiner</code> / <code>Examiner123!</code></p>
                    <p class="mb-0">Candidate: <code>candidate</code> / <code>Candidate123!</code></p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
async function handleLogin(e) {
    e.preventDefault();
    const btn = document.getElementById('loginBtn');
    const alertBox = document.getElementById('alertContainer');
    alertBox.innerHTML = '';
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Authenticating...';

    const username = document.getElementById('username').value;
    const password = document.getElementById('password').value;

    try {
        const res = await fetch('/api/auth/login.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ username, password })
        });
        const data = await res.json();

        if (data.success) {
            window.location.href = '/dashboard.php';
        } else {
            alertBox.innerHTML = `<div class="alert alert-danger py-2 small" role="alert"><i class="fa-solid fa-triangle-exclamation me-2"></i>${data.message}</div>`;
        }
    } catch (err) {
        alertBox.innerHTML = '<div class="alert alert-danger py-2 small"><i class="fa-solid fa-triangle-exclamation me-2"></i>Network error occurred. Please try again.</div>';
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-right-to-bracket me-2"></i>Sign In';
    }
}
</script>

<?php require_once __DIR__ . '/../app/Views/footer.php'; ?>

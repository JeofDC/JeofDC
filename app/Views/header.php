<?php
// app/Views/header.php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../Helpers/Auth.php';
require_once __DIR__ . '/../Helpers/Sanitize.php';

$currentUser = getCurrentUser();
$pageTitle = $pageTitle ?? 'Aptitude Examination System';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> - Aptitude System</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome 6 Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Mega UI Library CSS -->
    <link href="/assets/css/ui-library.css" rel="stylesheet">
    <style>
        body {
            background-color: var(--theme-background-light, #f8f9fa);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            color: var(--theme-text, #333);
        }
        .navbar-brand {
            font-weight: 700;
            color: var(--theme-primary, #28a745) !important;
            letter-spacing: -0.5px;
        }
        .sidebar {
            min-height: calc(100vh - 56px);
            background: #ffffff;
            border-right: 1px solid var(--theme-border, #e9ecef);
            padding-top: 1rem;
        }
        .sidebar .nav-link {
            color: var(--theme-text-light, #666);
            padding: 0.75rem 1.25rem;
            font-weight: 500;
            border-radius: 6px;
            margin: 0.2rem 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            transition: all 0.2s ease;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            color: var(--theme-primary, #28a745);
            background-color: rgba(40, 167, 69, 0.08);
        }
        .stat-card {
            background: #ffffff;
            border-radius: 10px;
            padding: 1.25rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.04);
            border: 1px solid var(--theme-border, #e9ecef);
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-2px);
        }
        .stat-card .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            background: rgba(40, 167, 69, 0.12);
            color: var(--theme-primary, #28a745);
        }
        .card-mega {
            background: #ffffff;
            border-radius: 10px;
            border: 1px solid var(--theme-border, #e9ecef);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.03);
            margin-bottom: 1.5rem;
        }
        .card-mega .card-header {
            background: #ffffff;
            border-bottom: 1px solid var(--theme-border, #e9ecef);
            padding: 1rem 1.25rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .btn-primary {
            background-color: var(--theme-primary, #28a745) !important;
            border-color: var(--theme-primary, #28a745) !important;
        }
        .btn-primary:hover {
            background-color: var(--theme-primary-dark, #1e7e34) !important;
            border-color: var(--theme-primary-dark, #1e7e34) !important;
        }
        .badge-success { background-color: var(--theme-success, #28a745); }
        .badge-warning { background-color: var(--theme-warning, #ffc107); color: #333; }
        .badge-danger { background-color: var(--theme-danger, #dc3545); }
        .badge-info { background-color: var(--theme-info, #17a2b8); }
        .badge-secondary { background-color: var(--theme-secondary, #6c757d); }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom sticky-top">
    <div class="container-fluid px-4">
        <a class="navbar-brand" href="/dashboard.php">
            <i class="fa-solid fa-graduation-cap me-2"></i>Aptitude Assessment System
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarContent">
            <ul class="navbar-list ms-auto d-flex align-items-center mb-0 list-unstyled gap-3">
                <?php if ($currentUser): ?>
                    <li class="nav-item">
                        <span class="text-muted small">Welcome,</span>
                        <strong class="ms-1"><?= e($currentUser['first_name'] . ' ' . $currentUser['last_name']) ?></strong>
                        <span class="badge bg-light text-dark border ms-1"><?= e($currentUser['roles'][0] ?? 'User') ?></span>
                    </li>
                    <li class="nav-item">
                        <button onclick="logoutUser()" class="btn btn-outline-danger btn-sm">
                            <i class="fa-solid fa-right-from-bracket me-1"></i> Logout
                        </button>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<div class="container-fluid">
    <div class="row">
        <?php if ($currentUser && !isset($hideSidebar)): ?>
        <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse">
            <div class="position-sticky">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link <?= ($activeNav ?? '') === 'dashboard' ? 'active' : '' ?>" href="/dashboard.php">
                            <i class="fa-solid fa-chart-line"></i> Dashboard
                        </a>
                    </li>

                    <?php if (hasRole('Candidate') && !hasRole('Administrator') && !hasRole('Examiner')): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= ($activeNav ?? '') === 'candidate_exams' ? 'active' : '' ?>" href="/candidate-dashboard.php">
                                <i class="fa-solid fa-file-pen"></i> My Examinations
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= ($activeNav ?? '') === 'results' ? 'active' : '' ?>" href="/results.php">
                                <i class="fa-solid fa-square-poll-vertical"></i> My Results
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php if (hasPermission('questions.view')): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= ($activeNav ?? '') === 'questions' ? 'active' : '' ?>" href="/questions.php">
                                <i class="fa-solid fa-database"></i> Question Bank
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php if (hasPermission('categories.view')): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= ($activeNav ?? '') === 'categories' ? 'active' : '' ?>" href="/categories.php">
                                <i class="fa-solid fa-layer-group"></i> Categories
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php if (hasPermission('exams.view')): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= ($activeNav ?? '') === 'exams' ? 'active' : '' ?>" href="/exams.php">
                                <i class="fa-solid fa-file-signature"></i> Examinations
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php if (hasPermission('exam.assign')): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= ($activeNav ?? '') === 'assignments' ? 'active' : '' ?>" href="/assignments.php">
                                <i class="fa-solid fa-user-check"></i> Assignments
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php if (hasPermission('results.view')): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= ($activeNav ?? '') === 'results' ? 'active' : '' ?>" href="/results.php">
                                <i class="fa-solid fa-chart-pie"></i> Results
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php if (hasPermission('reports.view')): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= ($activeNav ?? '') === 'reports' ? 'active' : '' ?>" href="/reports.php">
                                <i class="fa-solid fa-file-invoice"></i> Reports
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php if (hasPermission('users.view')): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= ($activeNav ?? '') === 'users' ? 'active' : '' ?>" href="/users.php">
                                <i class="fa-solid fa-users"></i> Users & Roles
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php if (hasPermission('audit.view')): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= ($activeNav ?? '') === 'audit' ? 'active' : '' ?>" href="/audit.php">
                                <i class="fa-solid fa-shield-halved"></i> Audit Logs
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </nav>
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
        <?php else: ?>
        <main class="col-12 px-md-4 py-4">
        <?php endif; ?>

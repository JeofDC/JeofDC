<?php
// public/index.php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../app/Helpers/Auth.php';

if (isLoggedIn()) {
    header('Location: /dashboard.php');
} else {
    header('Location: /login.php');
}
exit();

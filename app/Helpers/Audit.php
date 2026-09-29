<?php
// app/Helpers/Audit.php - System Audit Logging Helper

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/Auth.php';

function logAudit($action, $module, $recordId = null, $description = null) {
    try {
        $pdo = getDbConnection();
        $currentUser = getCurrentUser();
        $userId = $currentUser ? $currentUser['id'] : null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        $stmt = $pdo->prepare("
            INSERT INTO audit_logs (user_id, action, module, record_id, description, ip_address)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $userId,
            $action,
            $module,
            $recordId ? (string)$recordId : null,
            $description,
            $ip
        ]);
    } catch (Exception $e) {
        error_log("Failed to write audit log: " . $e->getMessage());
    }
}

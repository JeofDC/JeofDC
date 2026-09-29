<?php
// public/audit.php - Audit Log Viewer
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Helpers/Auth.php';
require_once __DIR__ . '/../app/Helpers/Sanitize.php';

requirePermission('audit.view');

$pageTitle = 'Audit Logs';
$activeNav = 'audit';

$pdo = getDbConnection();

$logs = $pdo->query("
    SELECT a.*, u.username, u.first_name, u.last_name
    FROM audit_logs a
    LEFT JOIN users u ON u.id = a.user_id
    ORDER BY a.id DESC LIMIT 100
")->fetchAll();

require_once __DIR__ . '/../app/Views/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1">Audit Trail & Security Logs</h3>
        <p class="text-muted mb-0">Track system actions, user activities, and administrative operations</p>
    </div>
</div>

<div class="card card-mega shadow-sm">
    <div class="card-header bg-white fw-bold">
        <i class="fa-solid fa-shield-halved me-2 text-success"></i>Audit Log Records (Latest 100)
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 80px;">ID</th>
                        <th style="width: 170px;">Timestamp</th>
                        <th style="width: 160px;">User</th>
                        <th style="width: 120px;">Module</th>
                        <th style="width: 160px;">Action</th>
                        <th>Description</th>
                        <th style="width: 130px;">IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">No audit logs recorded yet.</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $l): ?>
                        <tr>
                            <td><code>#<?= e($l['id']) ?></code></td>
                            <td><small><?= date('M d, Y H:i:s', strtotime($l['timestamp'])) ?></small></td>
                            <td>
                                <?php if ($l['username']): ?>
                                    <div class="fw-bold text-dark"><?= e($l['first_name'] . ' ' . $l['last_name']) ?></div>
                                    <small class="text-muted">@<?= e($l['username']) ?></small>
                                <?php else: ?>
                                    <span class="text-muted">System / Anonymous</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge badge-info"><?= e($l['module']) ?></span></td>
                            <td><span class="badge bg-light text-dark border"><?= e($l['action']) ?></span></td>
                            <td><small class="text-dark"><?= e($l['description']) ?></small></td>
                            <td><code><?= e($l['ip_address']) ?></code></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../app/Views/footer.php'; ?>

<?php
// public/users.php - User & RBAC Management View
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Helpers/Auth.php';
require_once __DIR__ . '/../app/Helpers/Sanitize.php';

requirePermission('users.view');

$pageTitle = 'Users & Roles';
$activeNav = 'users';

$pdo = getDbConnection();
$roles = $pdo->query("SELECT * FROM roles ORDER BY id ASC")->fetchAll();

require_once __DIR__ . '/../app/Views/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1">User & Role Management</h3>
        <p class="text-muted mb-0">Create, edit, activate/deactivate system accounts and assign RBAC roles</p>
    </div>
    <?php if (hasPermission('users.create')): ?>
        <button class="btn btn-primary" onclick="openUserModal()"><i class="fa-solid fa-user-plus me-2"></i>Create New User</button>
    <?php endif; ?>
</div>

<!-- Filter Bar -->
<div class="card card-mega shadow-sm mb-4">
    <div class="card-body p-3">
        <form id="filterForm" class="row g-3" onsubmit="event.preventDefault(); loadUsers();">
            <div class="col-md-5">
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                    <input type="text" id="searchInput" class="form-control" placeholder="Search name, email, or employee number...">
                </div>
            </div>
            <div class="col-md-3">
                <select id="roleSelect" class="form-select" onchange="loadUsers()">
                    <option value="">All Roles</option>
                    <?php foreach ($roles as $r): ?>
                        <option value="<?= e($r['name']) ?>"><?= e($r['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select id="statusSelect" class="form-select" onchange="loadUsers()">
                    <option value="">All Statuses</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100 fw-semibold"><i class="fa-solid fa-filter me-1"></i>Filter</button>
            </div>
        </form>
    </div>
</div>

<!-- Users Table -->
<div class="card card-mega shadow-sm">
    <div class="card-header bg-white fw-bold">
        <i class="fa-solid fa-users me-2 text-success"></i>System Users
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Username</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Employee / Candidate No.</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="usersTableBody">
                    <tr><td colspan="7" class="text-center py-4 text-muted"><i class="fa-solid fa-spinner fa-spin me-2"></i>Loading users...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Create / Edit User Modal -->
<div class="modal fade" id="userModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold text-success" id="userModalTitle"><i class="fa-solid fa-user-gear me-2"></i>User Editor</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="userForm" onsubmit="saveUser(event)">
                <div class="modal-body p-4">
                    <input type="hidden" id="userId">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Username *</label>
                            <input type="text" id="uUsername" class="form-control" required placeholder="johndoe">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Email *</label>
                            <input type="email" id="uEmail" class="form-control" required placeholder="john@domain.com">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">First Name *</label>
                            <input type="text" id="uFirstName" class="form-control" required placeholder="John">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Last Name *</label>
                            <input type="text" id="uLastName" class="form-control" required placeholder="Doe">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Emp / Candidate No.</label>
                            <input type="text" id="uEmpNum" class="form-control" placeholder="EMP-001">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Assign Role *</label>
                            <select id="uRole" class="form-select" required>
                                <?php foreach ($roles as $r): ?>
                                    <option value="<?= e($r['name']) ?>"><?= e($r['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Status</label>
                            <select id="uStatus" class="form-select">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" id="passLabel">Password</label>
                            <input type="password" id="uPassword" class="form-control" placeholder="Leave blank to keep unchanged">
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" id="saveUserBtn" class="btn btn-success fw-bold"><i class="fa-solid fa-floppy-disk me-1"></i>Save User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', loadUsers);

async function loadUsers() {
    const tbody = document.getElementById('usersTableBody');
    const search = document.getElementById('searchInput').value;
    const role = document.getElementById('roleSelect').value;
    const status = document.getElementById('statusSelect').value;

    tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted"><i class="fa-solid fa-spinner fa-spin me-2"></i>Loading users...</td></tr>';

    try {
        const res = await fetch(`/api/users/list.php?search=${encodeURIComponent(search)}&role=${encodeURIComponent(role)}&status=${encodeURIComponent(status)}`);
        const json = await res.json();

        if (!json.success || !json.data || json.data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted"><i class="fa-solid fa-folder-open display-6 d-block mb-2 text-secondary"></i>No users found matching filters.</td></tr>';
            return;
        }

        tbody.innerHTML = json.data.map(u => `
            <tr>
                <td class="fw-bold">@${escapeHtml(u.username)}</td>
                <td>${escapeHtml(u.first_name + ' ' + u.last_name)}</td>
                <td>${escapeHtml(u.email)}</td>
                <td><code>${escapeHtml(u.employee_number || 'N/A')}</code></td>
                <td><span class="badge badge-info">${escapeHtml((u.roles || []).join(', ') || 'Candidate')}</span></td>
                <td><span class="badge ${u.status === 'active' ? 'badge-success' : 'badge-secondary'}">${u.status}</span></td>
                <td class="text-end">
                    <button class="btn btn-sm btn-outline-primary" onclick='editUser(${JSON.stringify(u)})'><i class="fa-solid fa-user-pen me-1"></i>Edit</button>
                </td>
            </tr>
        `).join('');

    } catch(e) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-danger"><i class="fa-solid fa-triangle-exclamation me-2"></i>Error loading users.</td></tr>';
    }
}

function openUserModal() {
    document.getElementById('userId').value = '';
    document.getElementById('userForm').reset();
    document.getElementById('userModalTitle').innerText = 'Create New User';
    document.getElementById('passLabel').innerText = 'Password *';
    document.getElementById('uPassword').required = true;
    new bootstrap.Modal(document.getElementById('userModal')).show();
}

function editUser(u) {
    document.getElementById('userId').value = u.id;
    document.getElementById('uUsername').value = u.username;
    document.getElementById('uEmail').value = u.email;
    document.getElementById('uFirstName').value = u.first_name;
    document.getElementById('uLastName').value = u.last_name;
    document.getElementById('uEmpNum').value = u.employee_number || '';
    document.getElementById('uRole').value = (u.roles && u.roles.length > 0) ? u.roles[0] : 'Candidate';
    document.getElementById('uStatus').value = u.status;
    document.getElementById('uPassword').value = '';
    document.getElementById('passLabel').innerText = 'New Password (Optional)';
    document.getElementById('uPassword').required = false;

    document.getElementById('userModalTitle').innerText = `Edit User: @${u.username}`;
    new bootstrap.Modal(document.getElementById('userModal')).show();
}

async function saveUser(e) {
    e.preventDefault();
    const saveBtn = document.getElementById('saveUserBtn');
    saveBtn.disabled = true;
    const csrfToken = await getCsrfToken();

    const payload = {
        csrf_token: csrfToken,
        id: document.getElementById('userId').value,
        username: document.getElementById('uUsername').value,
        email: document.getElementById('uEmail').value,
        first_name: document.getElementById('uFirstName').value,
        last_name: document.getElementById('uLastName').value,
        employee_number: document.getElementById('uEmpNum').value,
        role: document.getElementById('uRole').value,
        status: document.getElementById('uStatus').value,
        password: document.getElementById('uPassword').value
    };

    try {
        const res = await fetch('/api/users/save.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const json = await res.json();

        if (json.success) {
            bootstrap.Modal.getInstance(document.getElementById('userModal')).hide();
            loadUsers();
        } else {
            alert(json.message);
        }
    } catch(err) {
        alert('Error saving user.');
    } finally {
        saveBtn.disabled = false;
    }
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
</script>

<?php require_once __DIR__ . '/../app/Views/footer.php'; ?>

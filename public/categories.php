<?php
// public/categories.php - Aptitude Category Management
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Helpers/Auth.php';
require_once __DIR__ . '/../app/Helpers/Sanitize.php';

requirePermission('categories.view');

$pageTitle = 'Aptitude Categories';
$activeNav = 'categories';

require_once __DIR__ . '/../app/Views/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1">Aptitude Categories</h3>
        <p class="text-muted mb-0">Manage Abstract, Verbal, and Numerical reasoning test domains</p>
    </div>
    <?php if (hasPermission('categories.create')): ?>
        <button class="btn btn-primary" onclick="openCategoryModal()"><i class="fa-solid fa-plus me-2"></i>Add Category</button>
    <?php endif; ?>
</div>

<div class="card card-mega shadow-sm">
    <div class="card-header bg-white fw-bold">
        <i class="fa-solid fa-layer-group me-2 text-success"></i>Category List
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 80px;">Order</th>
                        <th style="width: 120px;">Code</th>
                        <th>Category Name</th>
                        <th>Description</th>
                        <th style="width: 120px;">Active Qs</th>
                        <th style="width: 100px;">Status</th>
                        <th style="width: 100px;" class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody id="catTableBody">
                    <tr><td colspan="7" class="text-center py-4 text-muted"><i class="fa-solid fa-spinner fa-spin me-2"></i>Loading categories...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="categoryModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold text-success" id="catModalTitle"><i class="fa-solid fa-layer-group me-2"></i>Category Editor</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="catForm" onsubmit="saveCategory(event)">
                <div class="modal-body p-4">
                    <input type="hidden" id="catId">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Category Name *</label>
                        <input type="text" id="catName" class="form-control" required placeholder="e.g., Abstract Reasoning">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Category Code *</label>
                        <input type="text" id="catCode" class="form-control" required placeholder="e.g., ABSTRACT" style="text-transform: uppercase;">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Description</label>
                        <textarea id="catDesc" class="form-control" rows="3" placeholder="Category purpose and scope..."></textarea>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Display Order</label>
                            <input type="number" id="catOrder" class="form-control" value="1">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Status</label>
                            <select id="catStatus" class="form-select">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" id="saveCatBtn" class="btn btn-success fw-bold"><i class="fa-solid fa-floppy-disk me-1"></i>Save Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', loadCategories);

async function loadCategories() {
    const tbody = document.getElementById('catTableBody');
    try {
        const res = await fetch('/api/categories/list.php');
        const json = await res.json();

        if (!json.success || !json.data) return;

        tbody.innerHTML = json.data.map(c => `
            <tr>
                <td><code>#${c.display_order}</code></td>
                <td><span class="badge badge-info">${escapeHtml(c.code)}</span></td>
                <td class="fw-bold text-dark">${escapeHtml(c.name)}</td>
                <td class="text-muted small">${escapeHtml(c.description || '')}</td>
                <td><span class="badge bg-light text-dark border">${c.question_count} Questions</span></td>
                <td><span class="badge ${c.status === 'active' ? 'badge-success' : 'badge-secondary'}">${c.status}</span></td>
                <td class="text-end">
                    <button class="btn btn-sm btn-outline-primary" onclick='editCategory(${JSON.stringify(c)})'><i class="fa-solid fa-pen"></i></button>
                </td>
            </tr>
        `).join('');
    } catch(e) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-danger"><i class="fa-solid fa-triangle-exclamation me-2"></i>Error loading categories.</td></tr>';
    }
}

function openCategoryModal() {
    document.getElementById('catId').value = '';
    document.getElementById('catForm').reset();
    document.getElementById('catModalTitle').innerText = 'Add Aptitude Category';
    new bootstrap.Modal(document.getElementById('categoryModal')).show();
}

function editCategory(c) {
    document.getElementById('catId').value = c.id;
    document.getElementById('catName').value = c.name;
    document.getElementById('catCode').value = c.code;
    document.getElementById('catDesc').value = c.description || '';
    document.getElementById('catOrder').value = c.display_order;
    document.getElementById('catStatus').value = c.status;
    document.getElementById('catModalTitle').innerText = `Edit Category: ${c.name}`;
    new bootstrap.Modal(document.getElementById('categoryModal')).show();
}

async function saveCategory(e) {
    e.preventDefault();
    const saveBtn = document.getElementById('saveCatBtn');
    saveBtn.disabled = true;
    const csrfToken = await getCsrfToken();

    const payload = {
        csrf_token: csrfToken,
        id: document.getElementById('catId').value,
        name: document.getElementById('catName').value,
        code: document.getElementById('catCode').value,
        description: document.getElementById('catDesc').value,
        display_order: document.getElementById('catOrder').value,
        status: document.getElementById('catStatus').value
    };

    try {
        const res = await fetch('/api/categories/save.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const json = await res.json();

        if (json.success) {
            bootstrap.Modal.getInstance(document.getElementById('categoryModal')).hide();
            loadCategories();
        } else {
            alert(json.message);
        }
    } catch(err) {
        alert('Error saving category.');
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

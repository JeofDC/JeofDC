// App State Management
let currentRole = 'DEVELOPER'; // DEVELOPER or DBA
let activeTab = 'new-request';
let currentSchema = { tables: [], permissions: [] };

document.addEventListener('DOMContentLoaded', () => {
    // Add initial default column row for create table
    addColumnRow();
    loadSchemaBrowser();
    loadRequestsList();
    generateLiveSQLPreview();
});

function switchRole(role) {
    currentRole = role;
    const devBtn = document.getElementById('dev-role-btn');
    const dbaBtn = document.getElementById('dba-role-btn');
    const devMenu = document.getElementById('developer-side-menu');
    const dbaMenu = document.getElementById('dba-side-menu');

    if (role === 'DEVELOPER') {
        devBtn.className = 'px-3 py-1.5 rounded-md transition-colors bg-indigo-600 text-white shadow';
        dbaBtn.className = 'px-3 py-1.5 rounded-md transition-colors text-slate-300 hover:text-white';
        devMenu.classList.remove('hidden');
        dbaMenu.classList.add('hidden');
        setActiveTab('new-request');
    } else {
        dbaBtn.className = 'px-3 py-1.5 rounded-md transition-colors bg-indigo-600 text-white shadow';
        devBtn.className = 'px-3 py-1.5 rounded-md transition-colors text-slate-300 hover:text-white';
        dbaMenu.classList.remove('hidden');
        devMenu.classList.add('hidden');
        setActiveTab('dba-queue');
    }
}

function setActiveTab(tabId) {
    activeTab = tabId;
    document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));

    const targetTab = document.getElementById(`tab-${tabId}`);
    if (targetTab) targetTab.classList.remove('hidden');

    // Update nav button highlights
    document.querySelectorAll('aside nav button').forEach(btn => {
        btn.classList.remove('bg-slate-700', 'text-white');
        btn.classList.add('text-slate-300');
    });

    const activeNavBtn = document.getElementById(`nav-${tabId}`) || document.getElementById(`nav-dba-${tabId.replace('dba-', '')}`);
    if (activeNavBtn) {
        activeNavBtn.classList.add('bg-slate-700', 'text-white');
        activeNavBtn.classList.remove('text-slate-300');
    }

    if (tabId === 'my-requests' || tabId === 'dba-history') {
        loadRequestsList();
    } else if (tabId === 'dba-queue') {
        loadDBAQueue();
    } else if (tabId === 'schema-browser') {
        loadSchemaBrowser();
    } else if (tabId === 'new-request') {
        loadSchemaBrowser(); // to refresh table dropdowns
    }
}

function onReqTypeChange() {
    const reqType = document.getElementById('req-type').value;
    document.querySelectorAll('.form-section').forEach(el => el.classList.add('hidden'));

    const sec = document.getElementById(`form-section-${reqType}`);
    if (sec) sec.classList.remove('hidden');

    generateLiveSQLPreview();
}

function toggleAlterFields() {
    const action = document.getElementById('alter-action-select').value;
    if (action === 'ADD_COLUMN') {
        document.getElementById('alter-add-col-group').classList.remove('hidden');
        document.getElementById('alter-rename-group').classList.add('hidden');
    } else {
        document.getElementById('alter-add-col-group').classList.add('hidden');
        document.getElementById('alter-rename-group').classList.remove('hidden');
    }
}

function addColumnRow() {
    const container = document.getElementById('columns-container');
    const index = container.children.length;

    const row = document.createElement('div');
    row.className = 'grid grid-cols-12 gap-2 items-center bg-slate-900 p-2 rounded-lg border border-slate-700';
    row.id = `col-row-${index}`;

    row.innerHTML = `
        <div class="col-span-3">
            <input type="text" placeholder="Column Name" oninput="generateLiveSQLPreview()" class="col-name w-full bg-slate-950 border border-slate-700 rounded px-2 py-1 text-xs text-slate-100" required>
        </div>
        <div class="col-span-3">
            <select onchange="generateLiveSQLPreview()" class="col-type w-full bg-slate-950 border border-slate-700 rounded px-2 py-1 text-xs text-slate-100">
                <option value="TEXT">TEXT</option>
                <option value="INTEGER">INTEGER</option>
                <option value="REAL">REAL</option>
                <option value="VARCHAR(255)">VARCHAR(255)</option>
                <option value="DATETIME">DATETIME</option>
                <option value="BOOLEAN">BOOLEAN</option>
            </select>
        </div>
        <div class="col-span-2 flex items-center space-x-1 text-slate-300 text-xs">
            <input type="checkbox" onchange="generateLiveSQLPreview()" class="col-pk rounded bg-slate-950 border-slate-700">
            <span>PK</span>
        </div>
        <div class="col-span-2 flex items-center space-x-1 text-slate-300 text-xs">
            <input type="checkbox" onchange="generateLiveSQLPreview()" class="col-nn rounded bg-slate-950 border-slate-700">
            <span>Not Null</span>
        </div>
        <div class="col-span-2 flex justify-end">
            <button type="button" onclick="removeColumnRow(${index})" class="text-red-400 hover:text-red-300 text-xs px-2 py-1">
                <i class="fa-solid fa-trash"></i>
            </button>
        </div>
    `;

    container.appendChild(row);
    generateLiveSQLPreview();
}

function removeColumnRow(index) {
    const container = document.getElementById('columns-container');
    const row = document.getElementById(`col-row-${index}`);
    if (row && container.children.length > 1) {
        row.remove();
        generateLiveSQLPreview();
    }
}

function extractFormDetails() {
    const reqType = document.getElementById('req-type').value;
    let details = {};

    if (reqType === 'CREATE_TABLE') {
        const table_name = document.getElementById('create-table-name').value.trim();
        const columns = [];
        document.querySelectorAll('#columns-container > div').forEach(row => {
            const name = row.querySelector('.col-name')?.value.trim();
            const type = row.querySelector('.col-type')?.value;
            const pk = row.querySelector('.col-pk')?.checked || false;
            const nn = row.querySelector('.col-nn')?.checked || false;
            if (name) {
                columns.append ? columns.push({
                    name: name,
                    type: type,
                    primary_key: pk,
                    autoincrement: pk && type === 'INTEGER',
                    not_null: nn
                }) : columns.push({
                    name: name,
                    type: type,
                    primary_key: pk,
                    autoincrement: pk && type === 'INTEGER',
                    not_null: nn
                });
            }
        });
        details = { table_name, columns };

    } else if (reqType === 'ALTER_TABLE') {
        const table_name = document.getElementById('alter-table-select').value;
        const action = document.getElementById('alter-action-select').value;
        if (action === 'ADD_COLUMN') {
            const colName = document.getElementById('alter-col-name').value.trim();
            const colType = document.getElementById('alter-col-type').value;
            const colDef = document.getElementById('alter-col-default').value.trim();
            details = {
                table_name,
                action: 'ADD_COLUMN',
                column: { name: colName, type: colType, default: colDef || null }
            };
        } else {
            const newName = document.getElementById('alter-new-table-name').value.trim();
            details = { table_name, action: 'RENAME_TABLE', new_table_name: newName };
        }

    } else if (reqType === 'DROP_TABLE') {
        const table_name = document.getElementById('drop-table-select').value;
        details = { table_name };

    } else if (reqType === 'GRANT_ACCESS') {
        const username = document.getElementById('grant-username').value.trim();
        const target_object = document.getElementById('grant-object').value.trim();
        const permission = document.getElementById('grant-permission').value;
        details = { username, target_object, permission };

    } else if (reqType === 'CUSTOM_SQL') {
        const sql_query = document.getElementById('custom-sql-query').value.trim();
        details = { sql_query };
    }

    return { reqType, details };
}

async function generateLiveSQLPreview() {
    const { reqType, details } = extractFormDetails();
    const previewEl = document.getElementById('live-sql-preview');

    try {
        const resp = await fetch('/api/preview-sql', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ request_type: reqType, details })
        });
        const data = await resp.json();

        if (resp.ok) {
            previewEl.textContent = data.sql || '-- SQL SQL Preview empty';
            previewEl.className = 'bg-slate-950 border border-slate-800 rounded-lg p-3 font-mono text-xs text-emerald-400 overflow-x-auto min-h-[60px]';
        } else {
            previewEl.textContent = `-- SQL Warning: ${data.detail || 'Incomplete form inputs'}`;
            previewEl.className = 'bg-slate-950 border border-amber-950 rounded-lg p-3 font-mono text-xs text-amber-400 overflow-x-auto min-h-[60px]';
        }
    } catch (err) {
        previewEl.textContent = '-- Error generating SQL preview';
    }
}

async function handleRequestSubmit(e) {
    e.preventDefault();
    const dev_name = document.getElementById('dev-name').value;
    const title = document.getElementById('req-title').value;
    const description = document.getElementById('req-description').value;
    const { reqType, details } = extractFormDetails();

    try {
        const resp = await fetch('/api/requests', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                developer_name: dev_name,
                request_type: reqType,
                title: title,
                description: description,
                details: details
            })
        });

        if (resp.ok) {
            alert('DBA Request submitted successfully! View status in Tracker.');
            document.getElementById('req-title').value = '';
            document.getElementById('req-description').value = '';
            setActiveTab('my-requests');
        } else {
            const err = await resp.json();
            alert('Error submitting request: ' + (err.detail || 'Failed'));
        }
    } catch (err) {
        alert('Network error submitting request.');
    }
}

async function loadRequestsList() {
    const statusFilter = document.getElementById('filter-status')?.value || '';
    let url = '/api/requests';
    if (statusFilter) url += `?status=${statusFilter}`;

    try {
        const resp = await fetch(url);
        const requests = await resp.json();

        const tbody = document.getElementById('requests-table-body');
        const historyBody = document.getElementById('dba-history-table-body');
        if (tbody) tbody.innerHTML = '';
        if (historyBody) historyBody.innerHTML = '';

        let pendingCount = 0;

        requests.forEach(req => {
            if (req.status === 'PENDING') pendingCount++;

            // Render in Developer Tracker Table
            if (tbody) {
                const tr = document.createElement('tr');
                tr.className = 'hover:bg-slate-800/50 transition-colors';
                tr.innerHTML = `
                    <td class="p-3 font-mono font-bold text-slate-400">#${req.id}</td>
                    <td class="p-3"><span class="bg-slate-700 text-indigo-300 font-mono text-[10px] px-2 py-0.5 rounded">${req.request_type}</span></td>
                    <td class="p-3">
                        <div class="font-semibold text-slate-200">${escapeHtml(req.title)}</div>
                        <div class="text-[11px] text-slate-400">By ${escapeHtml(req.developer_name)}</div>
                    </td>
                    <td class="p-3">${getStatusBadge(req.status)}</td>
                    <td class="p-3 text-slate-400 text-[11px]">${new Date(req.created_at).toLocaleString()}</td>
                    <td class="p-3 text-right space-x-1">
                        <button onclick="viewRequestDetails(${req.id})" class="bg-indigo-600/20 hover:bg-indigo-600 text-indigo-300 hover:text-white px-2 py-1 rounded text-[11px]">
                            <i class="fa-solid fa-eye mr-1"></i> Details
                        </button>
                        ${req.status === 'PENDING' ? `
                            <button onclick="cancelRequest(${req.id})" class="bg-red-600/20 hover:bg-red-600 text-red-300 hover:text-white px-2 py-1 rounded text-[11px]">
                                Cancel
                            </button>
                        ` : ''}
                    </td>
                `;
                tbody.appendChild(tr);
            }

            // Render in DBA Execution History Table
            if (historyBody && req.status !== 'PENDING') {
                const tr = document.createElement('tr');
                tr.className = 'hover:bg-slate-800/50 transition-colors';
                tr.innerHTML = `
                    <td class="p-3 font-mono font-bold text-slate-400">#${req.id}</td>
                    <td class="p-3 font-medium text-slate-300">${escapeHtml(req.developer_name)}</td>
                    <td class="p-3">
                        <div class="font-semibold text-slate-200">${escapeHtml(req.title)}</div>
                        <div class="text-[10px] text-slate-400 font-mono">${req.request_type}</div>
                    </td>
                    <td class="p-3">${getStatusBadge(req.status)}</td>
                    <td class="p-3 text-slate-400 text-[11px]">${req.executed_at ? new Date(req.executed_at).toLocaleString() : 'N/A'}</td>
                    <td class="p-3 text-right">
                        <button onclick="viewRequestDetails(${req.id})" class="bg-slate-700 hover:bg-slate-600 text-slate-200 px-2.5 py-1 rounded text-[11px]">
                            View Logs
                        </button>
                    </td>
                `;
                historyBody.appendChild(tr);
            }
        });

        // Update pending count badge
        const badge = document.getElementById('pending-badge');
        if (badge) badge.textContent = pendingCount;

    } catch (err) {
        console.error('Error loading requests:', err);
    }
}

async function loadDBAQueue() {
    try {
        const resp = await fetch('/api/requests?status=PENDING');
        const requests = await resp.json();

        const container = document.getElementById('dba-queue-container');
        container.innerHTML = '';

        if (requests.length === 0) {
            container.innerHTML = `
                <div class="text-center py-12 bg-slate-900/50 rounded-xl border border-slate-700/50 text-slate-400">
                    <i class="fa-solid fa-circle-check text-4xl text-emerald-500 mb-3"></i>
                    <p class="text-sm font-semibold">Queue Clear!</p>
                    <p class="text-xs text-slate-500">There are no pending database requests waiting for approval.</p>
                </div>
            `;
            return;
        }

        requests.forEach(req => {
            const card = document.createElement('div');
            card.className = 'bg-slate-900 border border-slate-700 rounded-xl p-5 space-y-4 shadow-md';
            card.innerHTML = `
                <div class="flex justify-between items-start">
                    <div>
                        <div class="flex items-center space-x-2">
                            <span class="bg-indigo-900/80 text-indigo-300 font-mono text-xs px-2 py-0.5 rounded font-bold">#${req.id}</span>
                            <span class="bg-slate-800 text-slate-300 font-mono text-xs px-2 py-0.5 rounded">${req.request_type}</span>
                            <span class="text-slate-400 text-xs">by <strong class="text-white">${escapeHtml(req.developer_name)}</strong></span>
                        </div>
                        <h3 class="text-base font-bold text-white mt-1.5">${escapeHtml(req.title)}</h3>
                        <p class="text-xs text-slate-400 mt-0.5">${escapeHtml(req.description || 'No business description provided.')}</p>
                    </div>
                    <span class="text-[11px] text-slate-500">${new Date(req.created_at).toLocaleString()}</span>
                </div>

                <div class="bg-slate-950 p-3 rounded-lg border border-slate-800">
                    <div class="text-[11px] font-semibold text-slate-400 mb-1 flex justify-between">
                        <span>Target SQL To Execute</span>
                        <span class="text-emerald-400 font-mono">Auto-Validated</span>
                    </div>
                    <pre class="font-mono text-xs text-emerald-400 overflow-x-auto">${escapeHtml(req.generated_sql)}</pre>
                </div>

                <div class="pt-2 flex flex-col md:flex-row justify-between items-center gap-3">
                    <input type="text" id="dba-note-${req.id}" placeholder="Optional DBA Approval / Rejection Note..." class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-1.5 text-xs text-slate-200">

                    <div class="flex space-x-2 w-full md:w-auto shrink-0">
                        <button onclick="approveAndExecute(${req.id})" class="bg-emerald-600 hover:bg-emerald-500 text-white px-4 py-1.5 rounded-lg text-xs font-semibold flex-1 md:flex-initial flex items-center justify-center">
                            <i class="fa-solid fa-circle-check mr-1.5"></i> Approve & Run
                        </button>
                        <button onclick="rejectRequest(${req.id})" class="bg-red-600/80 hover:bg-red-600 text-white px-3 py-1.5 rounded-lg text-xs font-semibold flex-1 md:flex-initial flex items-center justify-center">
                            <i class="fa-solid fa-circle-xmark mr-1.5"></i> Reject
                        </button>
                    </div>
                </div>
            `;
            container.appendChild(card);
        });

    } catch (err) {
        console.error('Error loading DBA queue:', err);
    }
}

async function approveAndExecute(requestId) {
    const notes = document.getElementById(`dba-note-${requestId}`)?.value || 'Approved & automatically executed by DBA.';
    try {
        const resp = await fetch(`/api/requests/${requestId}/approve`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ dba_notes: notes })
        });

        const data = await resp.json();
        if (resp.ok) {
            if (data.status === 'COMPLETED') {
                alert(`Request #${requestId} approved and successfully executed!`);
            } else {
                alert(`Request #${requestId} execution failed. Check error logs.`);
            }
            loadDBAQueue();
            loadRequestsList();
            loadSchemaBrowser();
        } else {
            alert('Error approving request: ' + (data.detail || 'Failed'));
        }
    } catch (err) {
        alert('Network error approving request.');
    }
}

async function rejectRequest(requestId) {
    const notes = document.getElementById(`dba-note-${requestId}`)?.value || 'Rejected by DBA.';
    try {
        const resp = await fetch(`/api/requests/${requestId}/reject`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ dba_notes: notes })
        });

        if (resp.ok) {
            alert(`Request #${requestId} rejected.`);
            loadDBAQueue();
            loadRequestsList();
        } else {
            const err = await resp.json();
            alert('Error rejecting request: ' + (err.detail || 'Failed'));
        }
    } catch (err) {
        alert('Network error rejecting request.');
    }
}

async function cancelRequest(requestId) {
    if (!confirm(`Are you sure you want to cancel request #${requestId}?`)) return;

    try {
        const resp = await fetch(`/api/requests/${requestId}/cancel`, { method: 'POST' });
        if (resp.ok) {
            alert(`Request #${requestId} cancelled.`);
            loadRequestsList();
        } else {
            const err = await resp.json();
            alert('Error cancelling request: ' + (err.detail || 'Failed'));
        }
    } catch (err) {
        alert('Network error cancelling request.');
    }
}

async function viewRequestDetails(requestId) {
    try {
        const resp = await fetch(`/api/requests/${requestId}`);
        const req = await resp.json();

        document.getElementById('modal-title').innerHTML = `
            <span class="font-mono bg-slate-700 px-2 py-0.5 rounded text-indigo-300 text-xs mr-2">#${req.id}</span>
            ${escapeHtml(req.title)}
        `;

        const modalContent = document.getElementById('modal-content');
        modalContent.innerHTML = `
            <div class="grid grid-cols-2 gap-3 bg-slate-900 p-3 rounded-lg border border-slate-700">
                <div><span class="text-slate-400">Developer:</span> <strong class="text-white">${escapeHtml(req.developer_name)}</strong></div>
                <div><span class="text-slate-400">Type:</span> <strong class="text-indigo-400 font-mono">${req.request_type}</strong></div>
                <div><span class="text-slate-400">Status:</span> ${getStatusBadge(req.status)}</div>
                <div><span class="text-slate-400">Created At:</span> ${new Date(req.created_at).toLocaleString()}</div>
            </div>

            ${req.description ? `
                <div>
                    <span class="font-semibold text-slate-300">Justification / Notes:</span>
                    <p class="text-slate-400 mt-1">${escapeHtml(req.description)}</p>
                </div>
            ` : ''}

            <div>
                <span class="font-semibold text-slate-300">Target SQL:</span>
                <pre class="bg-slate-950 p-2.5 rounded border border-slate-800 text-emerald-400 font-mono mt-1 overflow-x-auto">${escapeHtml(req.generated_sql)}</pre>
            </div>

            ${req.dba_notes ? `
                <div class="bg-indigo-950/40 p-3 rounded border border-indigo-800/50">
                    <span class="font-semibold text-indigo-300">DBA Notes:</span>
                    <p class="text-indigo-200 mt-0.5">${escapeHtml(req.dba_notes)}</p>
                </div>
            ` : ''}

            ${req.error_log ? `
                <div class="bg-red-950/50 p-3 rounded border border-red-800">
                    <span class="font-semibold text-red-300">Execution Error Log:</span>
                    <pre class="text-red-200 mt-1 font-mono text-[11px] overflow-x-auto">${escapeHtml(req.error_log)}</pre>
                </div>
            ` : ''}

            <div>
                <span class="font-semibold text-slate-300">Execution Audit Logs:</span>
                <div class="mt-2 space-y-2">
                    ${req.execution_logs.length === 0 ? '<p class="text-slate-500 italic">No execution logs recorded yet.</p>' : ''}
                    ${req.execution_logs.map(log => `
                        <div class="bg-slate-950 p-2.5 rounded border border-slate-800 text-[11px] space-y-1">
                            <div class="flex justify-between items-center text-slate-400">
                                <span class="font-semibold ${log.status === 'SUCCESS' ? 'text-emerald-400' : 'text-red-400'}">${log.status}</span>
                                <span>${new Date(log.executed_at).toLocaleString()}</span>
                            </div>
                            <div class="text-slate-300">${escapeHtml(log.output_message || '')}</div>
                        </div>
                    `).join('')}
                </div>
            </div>
        `;

        document.getElementById('details-modal').classList.remove('hidden');

    } catch (err) {
        alert('Error loading details modal.');
    }
}

function closeModal() {
    document.getElementById('details-modal').classList.add('hidden');
}

async function loadSchemaBrowser() {
    try {
        const resp = await fetch('/api/schema');
        const schema = await resp.json();
        currentSchema = schema;

        // Update widget stats
        document.getElementById('widget-table-count').textContent = schema.tables.length;
        document.getElementById('widget-perm-count').textContent = schema.permissions.length;

        // Populate Table dropdowns in forms
        populateTableDropdowns(schema.tables);

        // Render Schema Browser Tables
        const container = document.getElementById('schema-tables-container');
        if (container) {
            container.innerHTML = '';

            if (schema.tables.length === 0) {
                container.innerHTML = `
                    <div class="col-span-2 text-center py-8 bg-slate-900/50 rounded-xl border border-slate-700/50 text-slate-400">
                        <p class="text-xs">No user tables in target database currently.</p>
                    </div>
                `;
            } else {
                schema.tables.forEach(tbl => {
                    const card = document.createElement('div');
                    card.className = 'bg-slate-900 border border-slate-700 rounded-xl overflow-hidden shadow-sm';
                    card.innerHTML = `
                        <div class="bg-slate-950 px-4 py-2.5 border-b border-slate-800 flex justify-between items-center">
                            <span class="font-mono font-bold text-indigo-300 text-xs flex items-center">
                                <i class="fa-solid fa-table mr-2 text-indigo-400"></i> ${escapeHtml(tbl.table_name)}
                            </span>
                            <span class="text-[10px] bg-slate-800 text-slate-400 px-2 py-0.5 rounded font-mono">${tbl.columns.length} columns</span>
                        </div>
                        <div class="p-3">
                            <table class="w-full text-left text-[11px] text-slate-300">
                                <thead class="text-slate-400 border-b border-slate-800 text-[10px] uppercase">
                                    <tr>
                                        <th class="pb-1">Column</th>
                                        <th class="pb-1">Type</th>
                                        <th class="pb-1 text-right">Attributes</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-800/60">
                                    ${tbl.columns.map(c => `
                                        <tr>
                                            <td class="py-1 font-mono font-medium text-slate-200">${escapeHtml(c.name)}</td>
                                            <td class="py-1 font-mono text-emerald-400">${c.type}</td>
                                            <td class="py-1 text-right font-mono text-[10px]">
                                                ${c.primary_key ? '<span class="bg-amber-900/60 text-amber-300 px-1 rounded mr-1">PK</span>' : ''}
                                                ${!c.nullable ? '<span class="bg-slate-800 text-slate-400 px-1 rounded">NOT NULL</span>' : ''}
                                            </td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                    `;
                    container.appendChild(card);
                });
            }
        }

        // Render Schema Browser Permissions
        const permBody = document.getElementById('schema-permissions-body');
        if (permBody) {
            permBody.innerHTML = '';
            if (schema.permissions.length === 0) {
                permBody.innerHTML = `<tr><td colspan="4" class="p-3 text-center text-slate-500 italic">No permission grants recorded.</td></tr>`;
            } else {
                schema.permissions.forEach(p => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td class="p-2.5 font-semibold text-slate-200">${escapeHtml(p.username)}</td>
                        <td class="p-2.5 font-mono text-indigo-300">${escapeHtml(p.target_object)}</td>
                        <td class="p-2.5"><span class="bg-emerald-900/60 text-emerald-300 font-mono text-[10px] px-2 py-0.5 rounded">${p.permission_level}</span></td>
                        <td class="p-2.5 text-slate-400 text-[11px]">${p.granted_at}</td>
                    `;
                    permBody.appendChild(tr);
                });
            }
        }

    } catch (err) {
        console.error('Error loading schema browser:', err);
    }
}

function populateTableDropdowns(tables) {
    const alterSelect = document.getElementById('alter-table-select');
    const dropSelect = document.getElementById('drop-table-select');
    const exportSelect = document.getElementById('export-table-select');

    const optionsHtml = tables.map(t => `<option value="${escapeHtml(t.table_name)}">${escapeHtml(t.table_name)}</option>`).join('') || '<option value="">(No tables available)</option>';

    if (alterSelect) alterSelect.innerHTML = optionsHtml;
    if (dropSelect) dropSelect.innerHTML = optionsHtml;
    if (exportSelect) exportSelect.innerHTML = optionsHtml;
}

// XLSX Import/Export UI Logic
async function handleXlsxFileSelect() {
    const fileInput = document.getElementById('xlsx-file-input');
    if (!fileInput.files || fileInput.files.length === 0) return;

    const file = fileInput.files[0];
    // Auto-fill table name if empty
    const tableNameInput = document.getElementById('xlsx-table-name');
    if (!tableNameInput.value) {
        const cleanName = file.name.replace(/\.[^/.]+$/, "").replace(/[^a-zA-Z0-9_]/g, "_").toLowerCase();
        tableNameInput.value = cleanName;
    }

    await previewSelectedSheet();
}

async function previewSelectedSheet() {
    const fileInput = document.getElementById('xlsx-file-input');
    if (!fileInput.files || fileInput.files.length === 0) return;

    const file = fileInput.files[0];
    const sheetSelect = document.getElementById('xlsx-sheet-select');
    const selectedSheet = sheetSelect.value || null;

    const formData = new FormData();
    formData.append('file', file);
    if (selectedSheet) formData.append('sheet_name', selectedSheet);

    try {
        const resp = await fetch('/api/xlsx/preview', {
            method: 'POST',
            body: formData
        });

        if (!resp.ok) {
            const err = await resp.json();
            alert('Preview error: ' + (err.detail || 'Failed to preview file'));
            return;
        }

        const data = await resp.json();

        // Handle sheet select dropdown options
        const sheetGroup = document.getElementById('xlsx-sheet-select-group');
        if (data.sheet_names && data.sheet_names.length > 1) {
            sheetGroup.classList.remove('hidden');
            sheetSelect.innerHTML = data.sheet_names.map(s => `<option value="${escapeHtml(s)}" ${s === data.selected_sheet ? 'selected' : ''}>${escapeHtml(s)}</option>`).join('');
        } else {
            sheetGroup.classList.add('hidden');
        }

        // Render preview table
        const previewContainer = document.getElementById('xlsx-preview-container');
        previewContainer.classList.remove('hidden');
        document.getElementById('xlsx-row-count').textContent = `Total rows: ${data.total_rows}`;

        const thead = document.getElementById('xlsx-preview-thead');
        const tbody = document.getElementById('xlsx-preview-tbody');

        thead.innerHTML = `<tr>${data.columns.map(col => `<th class="p-1.5 font-mono">${escapeHtml(col)}</th>`).join('')}</tr>`;
        tbody.innerHTML = data.preview_rows.map(row => `
            <tr>${data.columns.map(col => `<td class="p-1.5 font-mono text-[10px] text-slate-300">${escapeHtml(String(row[col] ?? ''))}</td>`).join('')}</tr>
        `).join('');

    } catch (err) {
        console.error('Error previewing XLSX file:', err);
    }
}

async function handleXlsxImport(e) {
    e.preventDefault();
    const fileInput = document.getElementById('xlsx-file-input');
    const tableName = document.getElementById('xlsx-table-name').value.trim();
    const ifExists = document.getElementById('xlsx-if-exists').value;
    const sheetSelect = document.getElementById('xlsx-sheet-select');
    const selectedSheet = sheetSelect.value || null;

    if (!fileInput.files || fileInput.files.length === 0) {
        alert('Please select an Excel file to import.');
        return;
    }

    const formData = new FormData();
    formData.append('file', fileInput.files[0]);
    formData.append('table_name', tableName);
    formData.append('if_exists', ifExists);
    if (selectedSheet) formData.append('sheet_name', selectedSheet);

    const btn = document.getElementById('xlsx-import-btn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-2"></i> Importing...';

    try {
        const resp = await fetch('/api/xlsx/import', {
            method: 'POST',
            body: formData
        });

        const data = await resp.json();
        if (resp.ok) {
            alert(`Successfully imported ${data.rows_imported} rows into table '${data.table_name}'!`);
            fileInput.value = '';
            document.getElementById('xlsx-table-name').value = '';
            document.getElementById('xlsx-preview-container').classList.add('hidden');
            loadSchemaBrowser();
        } else {
            alert('Import error: ' + (data.detail || 'Failed to import XLSX file'));
        }
    } catch (err) {
        alert('Network error during XLSX import.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-upload mr-2"></i> Import Excel Data to Database';
    }
}

async function handleXlsxExport() {
    const tableSelect = document.getElementById('export-table-select');
    const tableName = tableSelect.value;
    if (!tableName) {
        alert('Please select a table to export.');
        return;
    }

    window.location.href = `/api/xlsx/export/${encodeURIComponent(tableName)}`;
}

function getStatusBadge(status) {
    switch (status) {
        case 'PENDING':
            return `<span class="bg-amber-900/60 text-amber-300 font-semibold text-[10px] px-2 py-0.5 rounded-full border border-amber-700/50"><i class="fa-solid fa-hourglass-half mr-1"></i> PENDING</span>`;
        case 'COMPLETED':
            return `<span class="bg-emerald-900/60 text-emerald-300 font-semibold text-[10px] px-2 py-0.5 rounded-full border border-emerald-700/50"><i class="fa-solid fa-check mr-1"></i> COMPLETED</span>`;
        case 'REJECTED':
            return `<span class="bg-red-900/60 text-red-300 font-semibold text-[10px] px-2 py-0.5 rounded-full border border-red-700/50"><i class="fa-solid fa-xmark mr-1"></i> REJECTED</span>`;
        case 'FAILED':
            return `<span class="bg-rose-950 text-rose-300 font-semibold text-[10px] px-2 py-0.5 rounded-full border border-rose-800"><i class="fa-solid fa-triangle-exclamation mr-1"></i> FAILED</span>`;
        case 'CANCELLED':
            return `<span class="bg-slate-800 text-slate-400 font-semibold text-[10px] px-2 py-0.5 rounded-full"><i class="fa-solid fa-ban mr-1"></i> CANCELLED</span>`;
        default:
            return `<span class="bg-slate-800 text-slate-300 text-[10px] px-2 py-0.5 rounded">${status}</span>`;
    }
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

/* ============================================================
   G-Portal — User Management JavaScript
   ============================================================ */

let deleteUserId = null;

// ── SHOW BANNER ──
function showUsersBanner(message, type = 'success') {
    const banner = document.getElementById('usersBanner');
    banner.className = `users-banner users-banner-${type}`;
    banner.textContent = message;
    banner.style.display = 'block';
    setTimeout(() => { banner.style.display = 'none'; }, 4000);
}

// ── TOGGLE PASSWORD VISIBILITY ──
function togglePassword(inputId, btn) {
    const input = document.getElementById(inputId);
    const isText = input.type === 'text';
    input.type = isText ? 'password' : 'text';
    btn.innerHTML = isText
        ? `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>`
        : `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>`;
}

// ── TOGGLE CHANGE PASSWORD FIELDS ──
function toggleChangePassword(checkbox) {
    const fields = document.getElementById('changePasswordFields');
    fields.style.display = checkbox.checked ? 'block' : 'none';
    if (!checkbox.checked) {
        document.getElementById('editPassword').value = '';
        document.getElementById('editPasswordConfirm').value = '';
    }
}

// ── ADD USER MODAL ──
function openAddUserModal() {
    document.getElementById('addUserForm').reset();
    document.getElementById('addUserError').style.display = 'none';
    document.getElementById('addUserModal').classList.add('show');
}

function closeAddUserModal() {
    document.getElementById('addUserModal').classList.remove('show');
}

function submitAddUser(event) {
    event.preventDefault();
    const btn     = document.getElementById('addUserSubmitBtn');
    const errEl   = document.getElementById('addUserError');
    const errMsg  = document.getElementById('addUserErrorMsg');
    errEl.style.display = 'none';
    btn.disabled  = true;
    btn.textContent = 'Adding...';

    const formData = new FormData(document.getElementById('addUserForm'));

    fetch('../backend/add_user.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                errMsg.textContent      = data.message;
                errEl.style.display     = 'flex';
            }
        })
        .catch(() => {
            errMsg.textContent  = 'Network error. Please try again.';
            errEl.style.display = 'flex';
        })
        .finally(() => {
            btn.disabled    = false;
            btn.textContent = 'Add User';
        });
}

// ── EDIT USER MODAL ──
function openEditUserModal(id, username, role, email) {
    document.getElementById('editUserId').value    = id;
    document.getElementById('editUsername').value  = username;
    document.getElementById('editRole').value      = role;
    document.getElementById('editEmail').value     = email;
    document.getElementById('editUserError').style.display = 'none';
    document.getElementById('changePasswordToggle').checked = false;
    document.getElementById('changePasswordFields').style.display = 'none';
    document.getElementById('editPassword').value        = '';
    document.getElementById('editPasswordConfirm').value = '';
    document.getElementById('editUserModal').classList.add('show');
}

function closeEditUserModal() {
    document.getElementById('editUserModal').classList.remove('show');
}

function submitEditUser(event) {
    event.preventDefault();
    const btn    = document.getElementById('editUserSubmitBtn');
    const errEl  = document.getElementById('editUserError');
    const errMsg = document.getElementById('editUserErrorMsg');
    errEl.style.display = 'none';
    btn.disabled = true;
    btn.textContent = 'Updating...';

    const formData = new FormData(document.getElementById('editUserForm'));

    fetch('../backend/edit_user.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                errMsg.textContent  = data.message;
                errEl.style.display = 'flex';
            }
        })
        .catch(() => {
            errMsg.textContent  = 'Network error. Please try again.';
            errEl.style.display = 'flex';
        })
        .finally(() => {
            btn.disabled    = false;
            btn.textContent = 'Update User';
        });
}

// ── DELETE USER MODAL ──
function openDeleteUserModal(id, username) {
    deleteUserId = id;
    document.getElementById('deleteUserName').textContent = username;
    document.getElementById('deleteUserModal').classList.add('show');
}

function closeDeleteUserModal() {
    deleteUserId = null;
    document.getElementById('deleteUserModal').classList.remove('show');
}

function confirmDeleteUser() {
    if (!deleteUserId) return;
    const formData = new FormData();
    formData.append('id', deleteUserId);

    fetch('../backend/delete_user.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                closeDeleteUserModal();
                showUsersBanner(data.message, 'error');
            }
        })
        .catch(() => {
            closeDeleteUserModal();
            showUsersBanner('Network error. Please try again.', 'error');
        });
}

// ── DOM HELPERS ──
function appendUserRow(user) {
    const tbody = document.getElementById('usersTableBody');
    const rowCount = tbody.querySelectorAll('tr').length + 1;
    const roleClass = user.role === 'Super Admin' ? 'super-admin' : 'admin';
    const emailCell = user.email ? user.email : '<span class="users-no-email">—</span>';

    const tr = document.createElement('tr');
    tr.setAttribute('data-user-id', user.id);
    tr.id = `userRow_${user.id}`;
    tr.innerHTML = `
        <td class="users-td-num">${rowCount}</td>
        <td>
            <div class="users-name-cell">
                <div class="users-avatar">${user.username.charAt(0).toUpperCase()}</div>
                <span class="users-username">${escapeHtml(user.username)}</span>
            </div>
        </td>
        <td><span class="users-role-badge users-role-${roleClass}">${escapeHtml(user.role)}</span></td>
        <td class="users-email">${emailCell}</td>
        <td class="users-date">${user.created_at}</td>
        <td>
            <div class="users-actions">
                <button class="btn-user-edit" onclick="openEditUserModal(${user.id},'${escapeJs(user.username)}','${escapeJs(user.role)}','${escapeJs(user.email || '')}')" title="Edit user">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                    Edit
                </button>
                <button class="btn-user-delete" onclick="openDeleteUserModal(${user.id},'${escapeJs(user.username)}')" title="Delete user">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                    Delete
                </button>
            </div>
        </td>`;
    tbody.appendChild(tr);
    updateTotalCount();
}

function updateUserRow(user) {
    const row = document.getElementById(`userRow_${user.id}`);
    if (!row) return;
    const roleClass = user.role === 'Super Admin' ? 'super-admin' : 'admin';
    const emailCell = user.email ? user.email : '<span class="users-no-email">—</span>';

    row.cells[1].querySelector('.users-avatar').textContent  = user.username.charAt(0).toUpperCase();
    row.cells[1].querySelector('.users-username').textContent = user.username;
    row.cells[2].innerHTML = `<span class="users-role-badge users-role-${roleClass}">${escapeHtml(user.role)}</span>`;
    row.cells[3].innerHTML = emailCell;

    // Update edit button onclick
    const editBtn = row.querySelector('.btn-user-edit');
    if (editBtn) editBtn.setAttribute('onclick', `openEditUserModal(${user.id},'${escapeJs(user.username)}','${escapeJs(user.role)}','${escapeJs(user.email || '')}')`);
}

function removeUserRow(id) {
    const row = document.getElementById(`userRow_${id}`);
    if (row) row.remove();
    // Renumber rows
    document.querySelectorAll('#usersTableBody tr').forEach((tr, i) => {
        tr.cells[0].textContent = i + 1;
    });
    updateTotalCount();
}

function updateTotalCount() {
    const count = document.querySelectorAll('#usersTableBody tr').length;
    const footer = document.querySelector('.users-total');
    if (footer) footer.innerHTML = `Total: <strong>${count}</strong> user${count !== 1 ? 's' : ''}`;
}

// ── SEARCH ──
function searchUsers(query) {
    const q       = query.toLowerCase().trim();
    const rows    = document.querySelectorAll('#usersTableBody tr');
    const clearBtn = document.getElementById('userSearchClear');
    let   visible  = 0;

    rows.forEach(row => {
        const username = row.querySelector('.users-username')?.textContent.toLowerCase() || '';
        const role     = row.querySelector('.users-role-badge')?.textContent.toLowerCase() || '';
        const email    = row.cells[3]?.textContent.toLowerCase() || '';
        const matches  = !q || username.includes(q) || role.includes(q) || email.includes(q);
        row.style.display = matches ? '' : 'none';
        if (matches) visible++;
    });

    // Show/hide clear button
    if (clearBtn) clearBtn.style.display = q ? 'flex' : 'none';

    // Update total count text
    const footer = document.querySelector('.users-total');
    if (footer) {
        const total = rows.length;
        if (q && visible !== total) {
            footer.innerHTML = `Showing <strong>${visible}</strong> of <strong>${total}</strong> user${total !== 1 ? 's' : ''}`;
        } else {
            footer.innerHTML = `Total: <strong>${total}</strong> user${total !== 1 ? 's' : ''}`;
        }
    }

    // Show/hide empty state
    let emptyState = document.getElementById('usersSearchEmpty');
    if (visible === 0 && q) {
        if (!emptyState) {
            emptyState = document.createElement('tr');
            emptyState.id = 'usersSearchEmpty';
            emptyState.innerHTML = `<td colspan="6" style="text-align:center;padding:40px;color:var(--gray-400);font-size:14px;">No users match "<strong>${q}</strong>"</td>`;
            document.getElementById('usersTableBody').appendChild(emptyState);
        } else {
            emptyState.querySelector('td').innerHTML = `No users match "<strong>${q}</strong>"`;
            emptyState.style.display = '';
        }
    } else if (emptyState) {
        emptyState.style.display = 'none';
    }
}

function clearUserSearch() {
    const input = document.getElementById('userSearchInput');
    if (input) input.value = '';
    searchUsers('');
}

// ── UTILS ──
function escapeHtml(str) {
    const d = document.createElement('div');
    d.appendChild(document.createTextNode(str || ''));
    return d.innerHTML;
}

function escapeJs(str) {
    return (str || '').replace(/'/g, "\\'").replace(/"/g, '\\"');
}

// ── CLOSE MODALS ON OUTSIDE CLICK ──
document.addEventListener('click', function(e) {
    ['addUserModal', 'editUserModal'].forEach(id => {
        const modal = document.getElementById(id);
        if (modal && modal.classList.contains('show') && e.target === modal) {
            modal.classList.remove('show');
        }
    });
    const delModal = document.getElementById('deleteUserModal');
    if (delModal && delModal.classList.contains('show') && e.target === delModal) {
        closeDeleteUserModal();
    }
});

// ESC key closes modals
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeAddUserModal();
        closeEditUserModal();
        closeDeleteUserModal();
    }
});
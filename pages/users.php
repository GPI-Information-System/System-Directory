<?php
// G-Portal — User Management Page - Superadmin only: Add, Edit, Delete user

require_once '../config/session.php';
require_once '../config/database.php';

requireLogin();

// Superadmin only
if (!isSuperAdmin()) {
    header('Location: dashboard.php');
    exit();
}

$currentUser = getCurrentUser();

$conn = getDBConnection();
$result = $conn->query("SELECT id, username, role, email, created_at FROM users ORDER BY FIELD(role, 'Super Admin', 'Admin'), username ASC");
$users = [];
while ($row = $result->fetch_assoc()) { $users[] = $row; }
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>G-Portal - User Management</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/users.css">
</head>
<body>
<div class="container">
    <!-- SIDEBAR -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <h1>G-Portal</h1>
            <div class="user-info">
                Welcome, <strong><?php echo htmlspecialchars($currentUser['username']); ?></strong>
                <span class="user-role"><?php echo htmlspecialchars($currentUser['role']); ?></span>
            </div>
        </div>
        <nav>
            <ul class="nav-menu">
                <li>
                    <a href="dashboard.php">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                        Dashboard
                    </a>
                </li>
                <li>
                    <a href="analytics.php">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="20" x2="12" y2="10"></line><line x1="18" y1="20" x2="18" y2="4"></line><line x1="6" y1="20" x2="6" y2="16"></line></svg>
                        Analytics
                    </a>
                </li>
                <li>
                    <a href="users.php" class="active">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                        User Management
                    </a>
                </li>
            </ul>
            <div class="user-profile">
                <div class="user-avatar-large">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                </div>
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($currentUser['username']); ?></div>
                    <div class="user-role"><?php echo htmlspecialchars($currentUser['role']); ?></div>
                </div>
            </div>
            <form action="../backend/logout.php" method="POST">
                <button type="submit" class="btn-logout">Logout</button>
            </form>
        </nav>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="main-content">
        <div class="page-header-users">
            <div>
                <h2 class="page-title-users">User Management</h2>
                <p class="page-subtitle-users">Manage admin accounts for G-Portal</p>
            </div>
            <div class="users-header-right">
                <div class="users-search-wrap">
                    <svg class="users-search-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                    <input type="text" id="userSearchInput" class="users-search-input"
                           placeholder="Search users..."
                           oninput="searchUsers(this.value)">
                    <button class="users-search-clear" id="userSearchClear" onclick="clearUserSearch()" style="display:none;" title="Clear">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                </div>
                <button class="btn-add-user" onclick="openAddUserModal()">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                    Add User
                </button>
            </div>
        </div>

        <!-- ERROR / SUCCESS BANNER -->
        <div id="usersBanner" class="users-banner" style="display:none;"></div>

        <!-- USERS TABLE -->
        <div class="users-table-wrap">
            <table class="users-table" id="usersTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Email</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="usersTableBody">
                    <?php foreach ($users as $i => $user): ?>
                    <tr data-user-id="<?php echo $user['id']; ?>" id="userRow_<?php echo $user['id']; ?>">
                        <td class="users-td-num"><?php echo $i + 1; ?></td>
                        <td>
                            <div class="users-name-cell">
                                <div class="users-avatar">
                                    <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                                </div>
                                <span class="users-username"><?php echo htmlspecialchars($user['username']); ?></span>
                                <?php if ($user['id'] == $currentUser['id']): ?>
                                    <span class="users-you-badge">You</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <span class="users-role-badge users-role-<?php echo strtolower(str_replace(' ', '-', $user['role'])); ?>">
                                <?php echo htmlspecialchars($user['role']); ?>
                            </span>
                        </td>
                        <td class="users-email"><?php echo $user['email'] ? htmlspecialchars($user['email']) : '<span class="users-no-email">—</span>'; ?></td>
                        <td class="users-date"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                        <td>
                            <div class="users-actions">
                                <button class="btn-user-edit" onclick="openEditUserModal(
                                    <?php echo $user['id']; ?>,
                                    '<?php echo addslashes($user['username']); ?>',
                                    '<?php echo addslashes($user['role']); ?>',
                                    '<?php echo addslashes($user['email'] ?? ''); ?>'
                                )" title="Edit user">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                    Edit
                                </button>
                                <?php if ($user['id'] != $currentUser['id']): ?>
                                <button class="btn-user-delete" onclick="openDeleteUserModal(
                                    <?php echo $user['id']; ?>,
                                    '<?php echo addslashes($user['username']); ?>'
                                )" title="Delete user">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                    Delete
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if (empty($users)): ?>
            <div class="users-empty">
                <svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                <h3>No Users Found</h3>
                <p>Add your first user to get started.</p>
            </div>
            <?php endif; ?>

            <div class="users-table-footer">
                <span class="users-total">Total: <strong><?php echo count($users); ?></strong> user<?php echo count($users) !== 1 ? 's' : ''; ?></span>
            </div>
        </div>
    </main>
</div>

<!-- ── ADD USER MODAL ── -->
<div id="addUserModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Add New User</h3>
            <button class="close-modal" onclick="closeAddUserModal()">&times;</button>
        </div>
        <form id="addUserForm" onsubmit="submitAddUser(event)">
            <div class="modal-body">
                <div id="addUserError" class="modal-error-banner" style="display:none;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                    <span id="addUserErrorMsg"></span>
                </div>

                <div class="form-group">
                    <label for="addUsername">Username <span style="color:var(--danger)">*</span></label>
                    <input type="text" id="addUsername" name="username" required placeholder="e.g., john.doe" autocomplete="off">
                </div>

                <div class="form-group">
                    <label for="addRole">Role <span style="color:var(--danger)">*</span></label>
                    <select id="addRole" name="role" required>
                        <option value="">Select a role...</option>
                        <option value="Super Admin">Super Admin</option>
                        <option value="Admin">Admin</option>
                    </select>
                    <div class="field-helper-row">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                        Super Admin: full access · Admin: can edit systems & schedule maintenance
                    </div>
                </div>

                <div class="form-group">
                    <label for="addEmail">Email <span style="font-weight:400;color:var(--gray-400);font-size:12px;">(Optional)</span></label>
                    <input type="email" id="addEmail" name="email" placeholder="e.g., john@glory.com">
                </div>

                <div class="form-group">
                    <label for="addPassword">Password <span style="color:var(--danger)">*</span></label>
                    <div class="password-input-wrap">
                        <input type="password" id="addPassword" name="password" required placeholder="Enter password" autocomplete="new-password">
                        <button type="button" class="btn-toggle-password" onclick="togglePassword('addPassword', this)" tabindex="-1">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        </button>
                    </div>
                </div>

                <div class="form-group">
                    <label for="addPasswordConfirm">Confirm Password <span style="color:var(--danger)">*</span></label>
                    <div class="password-input-wrap">
                        <input type="password" id="addPasswordConfirm" name="password_confirm" required placeholder="Re-enter password" autocomplete="new-password">
                        <button type="button" class="btn-toggle-password" onclick="togglePassword('addPasswordConfirm', this)" tabindex="-1">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        </button>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeAddUserModal()">Cancel</button>
                <button type="submit" class="btn btn-primary" id="addUserSubmitBtn">Add User</button>
            </div>
        </form>
    </div>
</div>

<!-- ── EDIT USER MODAL ── -->
<div id="editUserModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Edit User</h3>
            <button class="close-modal" onclick="closeEditUserModal()">&times;</button>
        </div>
        <form id="editUserForm" onsubmit="submitEditUser(event)">
            <input type="hidden" id="editUserId" name="id">
            <div class="modal-body">
                <div id="editUserError" class="modal-error-banner" style="display:none;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                    <span id="editUserErrorMsg"></span>
                </div>

                <div class="form-group">
                    <label for="editUsername">Username <span style="color:var(--danger)">*</span></label>
                    <input type="text" id="editUsername" name="username" required autocomplete="off">
                </div>

                <div class="form-group">
                    <label for="editRole">Role <span style="color:var(--danger)">*</span></label>
                    <select id="editRole" name="role" required>
                        <option value="Super Admin">Super Admin</option>
                        <option value="Admin">Admin</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="editEmail">Email <span style="font-weight:400;color:var(--gray-400);font-size:12px;">(Optional)</span></label>
                    <input type="email" id="editEmail" name="email" placeholder="e.g., john@glory.com">
                </div>

                <div class="users-password-section">
                    <div class="users-password-toggle-row">
                        <span class="users-password-toggle-label">Change Password</span>
                        <label class="health-check-toggle">
                            <input type="checkbox" id="changePasswordToggle" onchange="toggleChangePassword(this)">
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    <div id="changePasswordFields" style="display:none;">
                        <div class="form-group" style="margin-top:14px;">
                            <label for="editPassword">New Password</label>
                            <div class="password-input-wrap">
                                <input type="password" id="editPassword" name="password" placeholder="Enter new password" autocomplete="new-password">
                                <button type="button" class="btn-toggle-password" onclick="togglePassword('editPassword', this)" tabindex="-1">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                </button>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="editPasswordConfirm">Confirm New Password</label>
                            <div class="password-input-wrap">
                                <input type="password" id="editPasswordConfirm" name="password_confirm" placeholder="Re-enter new password" autocomplete="new-password">
                                <button type="button" class="btn-toggle-password" onclick="togglePassword('editPasswordConfirm', this)" tabindex="-1">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeEditUserModal()">Cancel</button>
                <button type="submit" class="btn btn-primary" id="editUserSubmitBtn">Update User</button>
            </div>
        </form>
    </div>
</div>

<!-- ── DELETE USER MODAL ── -->
<div id="deleteUserModal" class="delete-modal">
    <div class="delete-modal-content">
        <div class="delete-modal-header">
            <div class="delete-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle></svg>
            </div>
            <div class="delete-modal-title">
                <h3>Delete User</h3>
                <p>This action cannot be undone</p>
            </div>
        </div>
        <div class="delete-modal-body">
            <div class="delete-system-name"><strong id="deleteUserName"></strong></div>
            <div class="delete-warning">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                <p>Are you sure you want to delete this user? They will lose all access to G-Portal.</p>
            </div>
        </div>
        <div class="delete-modal-footer">
            <button type="button" class="btn-delete-cancel" onclick="closeDeleteUserModal()">Cancel</button>
            <button type="button" class="btn-delete-confirm" onclick="confirmDeleteUser()">Delete User</button>
        </div>
    </div>
</div>

<script src="../assets/js/users.js"></script>
</body>
</html>
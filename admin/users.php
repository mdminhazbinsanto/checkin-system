<?php
/**
 * Admin Users Page
 */

session_start();

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/lang.php';

// Redirect if not admin
if (!isAdmin()) {
    header('Location: ../index.php');
    exit;
}

$csrfToken = generateCSRFToken();

// Get users
$db = getDB();
$users = [];
if ($db) {
    $stmt = $db->prepare("SELECT * FROM users ORDER BY name");
$stmt->execute();
    $users = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="<?php echo $_SESSION['lang'] ?? DEFAULT_LANG; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - <?php echo t('admin'); ?> Users</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .toast {
            animation: slideIn 0.3s ease-out;
        }
        @keyframes slideIn {
            from { transform: translateY(-100%); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .modal {
            transition: opacity 0.25s ease;
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <?php include __DIR__ . '/../includes/admin_nav.php'; ?>

    <!-- Main Content -->
    <main class="lg:ml-64 pb-20 lg:pb-8">
        <div class="max-w-6xl mx-auto p-4 space-y-6">
            <!-- Page Header -->
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold text-gray-800"><?php echo t('admin'); ?> Users</h1>
                <button id="addUserBtn" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 min-h-[44px]">
                    + Add User
                </button>
            </div>

            <!-- Users Table -->
            <div class="bg-white rounded-xl shadow overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Username</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Role</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Last Login</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="usersBody" class="divide-y divide-gray-200">
                            <?php foreach ($users as $user): ?>
                                <tr id="userRow<?php echo $user['id']; ?>">
                                    <td class="px-4 py-3 text-sm font-medium text-gray-900"><?php echo htmlspecialchars($user['name']); ?></td>
                                    <td class="px-4 py-3 text-sm text-gray-500"><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td class="px-4 py-3">
                                        <span class="px-2 py-1 text-xs font-medium rounded-full <?php echo $user['role'] === 'admin' ? 'bg-purple-100 text-purple-800' : 'bg-gray-100 text-gray-800'; ?>">
                                            <?php echo ucfirst($user['role']); ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="px-2 py-1 text-xs font-medium rounded-full <?php echo $user['status'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                            <?php echo $user['status'] ? 'Active' : 'Disabled'; ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-500"><?php echo $user['last_login'] ? date('d/m/Y H:i', strtotime($user['last_login'])) : 'Never'; ?></td>
                                    <td class="px-4 py-3">
                                        <div class="flex gap-2">
                                            <button onclick="editUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['name']); ?>', '<?php echo $user['role']; ?>')" 
                                                class="px-3 py-1 text-sm bg-blue-100 text-blue-700 rounded hover:bg-blue-200 min-h-[36px]">
                                                Edit
                                            </button>
                                            <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                <button onclick="toggleUser(<?php echo $user['id']; ?>, <?php echo $user['status'] ? 0 : 1; ?>)" 
                                                    class="px-3 py-1 text-sm <?php echo $user['status'] ? 'bg-red-100 text-red-700 hover:bg-red-200' : 'bg-green-100 text-green-700 hover:bg-green-200'; ?> rounded min-h-[36px]">
                                                    <?php echo $user['status'] ? 'Disable' : 'Enable'; ?>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- Add/Edit User Modal -->
    <div id="userModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-md">
            <div class="p-6 border-b">
                <h2 id="modalTitle" class="text-xl font-semibold">Add User</h2>
            </div>
            <form id="userForm" class="p-6 space-y-4">
                <input type="hidden" id="userId" value="">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                    <input type="text" id="userName" name="name" required class="w-full px-3 py-2 border rounded-lg min-h-[44px]">
                </div>
                
                <div id="usernameField">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                    <input type="text" id="userUsername" name="username" class="w-full px-3 py-2 border rounded-lg min-h-[44px]">
                </div>
                
                <div id="passwordField">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                    <input type="password" id="userPassword" name="password" class="w-full px-3 py-2 border rounded-lg min-h-[44px]">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                    <select id="userRole" name="role" class="w-full px-3 py-2 border rounded-lg min-h-[44px]">
                        <option value="user">User</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                
                <div class="flex gap-3 pt-4">
                    <button type="button" onclick="closeModal()" class="flex-1 px-4 py-2 border rounded-lg hover:bg-gray-50 min-h-[44px]">Cancel</button>
                    <button type="submit" class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 min-h-[44px]">Save</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Toast Container -->
    <div id="toastContainer" class="fixed top-4 right-4 z-50"></div>

    <script src="../assets/app.js"></script>
    <script>
        // Add user button
        document.getElementById('addUserBtn').addEventListener('click', function() {
            document.getElementById('modalTitle').textContent = 'Add User';
            document.getElementById('userId').value = '';
            document.getElementById('userForm').reset();
            document.getElementById('usernameField').classList.remove('hidden');
            document.getElementById('userUsername').required = true;
            document.getElementById('passwordField').classList.remove('hidden');
            document.getElementById('userPassword').required = true;
            document.getElementById('userModal').classList.remove('hidden');
            document.getElementById('userModal').classList.add('flex');
        });

        // Edit user
        function editUser(id, name, role) {
            document.getElementById('modalTitle').textContent = 'Edit User';
            document.getElementById('userId').value = id;
            document.getElementById('userName').value = name;
            document.getElementById('userRole').value = role;
            document.getElementById('usernameField').classList.add('hidden');
            document.getElementById('userUsername').required = false;
            document.getElementById('passwordField').classList.add('hidden');
            document.getElementById('userPassword').required = false;
            document.getElementById('userModal').classList.remove('hidden');
            document.getElementById('userModal').classList.add('flex');
        }

        // Close modal
        function closeModal() {
            document.getElementById('userModal').classList.add('hidden');
            document.getElementById('userModal').classList.remove('flex');
        }

        // Toggle user status
        async function toggleUser(id, status) {
            try {
                const result = await postJSON('api/users.php', {
                    action: status ? 'enable' : 'disable',
                    user_id: id
                });
                
                if (result.success) {
                    showToast(result.message, 'success');
                    location.reload();
                } else {
                    showToast(result.message, 'error');
                }
            } catch (error) {
                showToast('Network error', 'error');
            }
        }

        // Form submission
        document.getElementById('userForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const userId = document.getElementById('userId').value;
            const data = {
                name: document.getElementById('userName').value,
                role: document.getElementById('userRole').value
            };
            
            if (!userId) {
                data.action = 'add';
                data.username = document.getElementById('userUsername').value;
                data.password = document.getElementById('userPassword').value;
            } else {
                data.action = 'edit';
                data.user_id = parseInt(userId);
            }
            
            try {
                const result = await postJSON('../api/create_user.php', data);
                
                if (result.success) {
                    showToast(result.message, 'success');
                    closeModal();
                    location.reload();
                } else {
                    showToast(result.message, 'error');
                }
            } catch (error) {
                showToast('Network error', 'error');
            }
        });

        // Close modal on outside click
        document.getElementById('userModal').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });
    </script>
</body>
</html>

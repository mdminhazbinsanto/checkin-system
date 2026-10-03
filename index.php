<?php
/**
 * Main Application Router
 */
ob_start();

// Redirect to installer if not installed
if (!file_exists(__DIR__ . '/install/installed.lock')) {
    header('Location: install.php');
    exit;
}

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/lang.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/config.php';

secureHeaders();
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');

// Define valid pages
$validPages = ['dashboard', 'settings', 'profile', 'login']; // Add other valid page filenames here (without .php)

// Get requested page
$page = $_GET['page'] ?? '';

// Validate page
if (empty($page) || !in_array($page, $validPages)) {
    // Redirect to login if not logged in, or if page is invalid
    if (!isLoggedIn()) {
        header('Location: index.php?page=login');
        exit;
    }
    // If logged in but invalid page, you might want to redirect to dashboard or show a custom 404
    // For now, redirecting to login as per request for "invalid, undefined, or wrong URL"
    header('Location: index.php?page=login');
    exit;
}

// Specific logic for login page
if ($page === 'login') {
    // Redirect if already logged in
    if (isLoggedIn()) {
        header('Location: index.php?page=dashboard');
        exit;
    }

    $csrfToken = generateCSRFToken();
    $error = $_GET['error'] ?? '';
    ?>
    <!DOCTYPE html>
    <html lang="<?php echo htmlspecialchars($_SESSION['lang'] ?? DEFAULT_LANG, ENT_QUOTES, 'UTF-8'); ?>">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="robots" content="noindex, nofollow">
        <meta name="theme-color" content="#2563eb">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <link rel="manifest" href="/manifest.json">
        <title><?php echo APP_NAME; ?> - <?php echo t('login'); ?></title>
        <script src="https://cdn.tailwindcss.com"></script>
        <style>
            .toast {
                animation: slideIn 0.3s ease-out;
            }
            @keyframes slideIn {
                from { transform: translateY(-100%); opacity: 0; }
                to { transform: translateY(0); opacity: 1; }
            }
        </style>
    </head>
    <body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
        <div class="w-full max-w-md">
            <div class="bg-white rounded-2xl shadow-xl p-8">
                <div class="text-center mb-8">
                    <h1 class="text-3xl font-bold text-blue-600"><?php echo APP_NAME; ?></h1>
                    <p class="text-gray-500 mt-2"><?php echo t('login_subtitle'); ?></p>
                </div>

                <div id="errorContainer" class="hidden">
                    <div class="bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded-lg mb-6 text-sm" id="errorMessage">
                    </div>
                </div>

                <form id="loginForm" class="space-y-6">
                    <input type="hidden" name="csrf_token" id="csrf_token" value="<?php echo $csrfToken; ?>">

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2"><?php echo t('username'); ?></label>
                        <input type="text" name="username" id="username" required
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition"
                            placeholder="<?php echo t('username'); ?>">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2"><?php echo t('password'); ?></label>
                        <input type="password" name="password" id="password" required
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition"
                            placeholder="<?php echo t('password'); ?>">
                    </div>

                    <button type="submit" id="loginBtn"
                        class="w-full py-3 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg transition duration-200">
                        <?php echo t('login'); ?>
                    </button>
                </form>
            </div>

            <?php if (defined('SHOW_DEV_FOOTER') && SHOW_DEV_FOOTER): ?>
            <div class="mt-6 text-center text-sm text-gray-500">
                <p>Developed by <a href="<?php echo DEV_WEBSITE; ?>" target="_blank" class="text-blue-600 hover:underline"><?php echo DEV_NAME; ?></a></p>
                <p class="text-xs mt-1"><?php echo DEV_COMPANY; ?> | <a href="mailto:<?php echo DEV_EMAIL; ?>" class="hover:underline"><?php echo DEV_EMAIL; ?></a></p>
            </div>
            <?php endif; ?>
        </div>

        <script>
            // Show error from URL if present
            const urlParams = new URLSearchParams(window.location.search);
            const urlError = urlParams.get('error');
            if (urlError) {
                const errorContainer = document.getElementById('errorContainer');
                const errorMessage = document.getElementById('errorMessage');
                errorContainer.classList.remove('hidden');
                errorMessage.textContent = decodeURIComponent(urlError);
            }

            // Handle login form submission via fetch
            document.getElementById('loginForm').addEventListener('submit', async function(e) {
                e.preventDefault();

                const btn = document.getElementById('loginBtn');
                const errorContainer = document.getElementById('errorContainer');
                const errorMessage = document.getElementById('errorMessage');
                const originalText = btn.textContent;

                btn.disabled = true;
                btn.textContent = '...';
                errorContainer.classList.add('hidden');

                const formData = {
                    username: document.getElementById('username').value.trim(),
                    password: document.getElementById('password').value,
                    csrf_token: document.getElementById('csrf_token').value
                };

                try {
                    const response = await fetch('api/login.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(formData)
                    });

                    const data = await response.json();

                    if (data.success) {
                        window.location.href = data.redirect || 'index.php?page=dashboard';
                    } else {
                        errorContainer.classList.remove('hidden');
                        errorMessage.textContent = data.message || 'Login failed';
                    }
                } catch (error) {
                    errorContainer.classList.remove('hidden');
                    errorMessage.textContent = 'Network error. Please try again.';
                } finally {
                    btn.disabled = false;
                    btn.textContent = originalText;
                }
            });
        </script>
    </body>
    </html>
    <?php
    exit; // Stop further execution after rendering login page
}

// If not login page, check if user is logged in
if (!isLoggedIn()) {
    header('Location: index.php?page=login');
    exit;
}

// Include the requested page
$pageFile = __DIR__ . '/' . $page . '.php';
if (file_exists($pageFile)) {
    include $pageFile;
} else {
    // This case should ideally not be reached due to the in_array check, but as a fallback:
    header('Location: index.php?page=login');
    exit;
}

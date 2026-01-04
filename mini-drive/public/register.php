<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../includes/auth.php';

    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validate
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        header('Location: register.php?error=All fields are required');
        exit;
    }

    if ($password !== $confirm_password) {
        header('Location: register.php?error=Passwords do not match');
        exit;
    }

    $auth = new Auth();
    $result = $auth->register($username, $email, $password);

    if ($result['success']) {
        header('Location: register.php?success=1');
    } else {
        header('Location: register.php?error=' . urlencode($result['message']));
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - MiniDrive</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
    <style type="text/tailwindcss">
      @theme {
        --color-primary: #667eea;
        --color-secondary: #764ba2;
        --color-accent: #667eea;
      }

      @layer components {
        .bg-gradient {
          @apply bg-gradient-to-br from-primary to-secondary;
        }

        .bg-gradient-light {
          @apply bg-gradient-to-br from-primary/5 to-secondary/5;
        }

        .input-focus {
          @apply transition-all duration-300;
        }

        .input-focus:focus {
          @apply -translate-y-0.5;
        }

        .btn-glow {
          @apply hover:shadow-2xl hover:scale-105 transform transition duration-300;
        }
      }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 to-blue-100 min-h-screen">
    <div class="min-h-screen flex items-center justify-center py-12 px-4">
        <div class="w-full max-w-md">
            <!-- Decorative circles -->
            <div class="absolute top-0 left-0 w-72 h-72 bg-purple-300 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-blob"></div>
            <div class="absolute -bottom-8 right-0 w-72 h-72 bg-blue-300 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-blob animation-delay-2000"></div>

            <div class="relative bg-white/90 backdrop-blur-lg rounded-3xl shadow-2xl p-8 border border-white/20">
                <!-- Header -->
                <div class="text-center mb-8">
                    <div class="inline-flex items-center justify-center w-16 h-16 bg-gradient rounded-2xl mb-4 shadow-lg">
                        <i class="fas fa-cloud-upload-alt text-3xl text-white"></i>
                    </div>
                    <h1 class="text-4xl font-bold bg-gradient bg-clip-text text-transparent mb-2">MiniDrive</h1>
                    <p class="text-gray-500">Secure Cloud Storage for Everyone</p>
                </div>

                <!-- Error Message -->
                <?php if (isset($_GET['error'])): ?>
                    <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-xl flex items-start space-x-3 animate-shake">
                        <i class="fas fa-exclamation-circle text-red-600 mt-0.5"></i>
                        <p class="text-red-700 text-sm"><?php echo htmlspecialchars($_GET['error']); ?></p>
                    </div>
                <?php endif; ?>

                <!-- Success Message -->
                <?php if (isset($_GET['success'])): ?>
                    <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-xl flex items-start space-x-3">
                        <i class="fas fa-check-circle text-green-600 mt-0.5"></i>
                        <div>
                            <p class="text-green-700 text-sm font-semibold">Account Created!</p>
                            <p class="text-green-600 text-sm mt-1">Redirecting to login... <a href="login.php" class="underline font-semibold">Click here</a></p>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Form -->
                <form method="POST" action="register.php" class="space-y-4">
                    <div class="group">
                        <label class="block text-sm font-semibold text-gray-700 mb-2 flex items-center">
                            <i class="fas fa-user text-purple-600 mr-2"></i>
                            Username
                        </label>
                        <input type="text" name="username" required minlength="3" maxlength="50"
                               class="input-focus w-full px-4 py-3 border-2 border-gray-200 rounded-lg bg-gray-50 text-gray-800 placeholder-gray-400 focus:border-purple-500 focus:bg-white focus:ring-0"
                               placeholder="Choose your username">
                        <p class="text-xs text-gray-500 mt-1">3-50 characters</p>
                    </div>

                    <div class="group">
                        <label class="block text-sm font-semibold text-gray-700 mb-2 flex items-center">
                            <i class="fas fa-envelope text-purple-600 mr-2"></i>
                            Email Address
                        </label>
                        <input type="email" name="email" required
                               class="input-focus w-full px-4 py-3 border-2 border-gray-200 rounded-lg bg-gray-50 text-gray-800 placeholder-gray-400 focus:border-purple-500 focus:bg-white focus:ring-0"
                               placeholder="your@email.com">
                    </div>

                    <div class="group">
                        <label class="block text-sm font-semibold text-gray-700 mb-2 flex items-center">
                            <i class="fas fa-lock text-purple-600 mr-2"></i>
                            Password
                        </label>
                        <input type="password" name="password" required minlength="6"
                               class="input-focus w-full px-4 py-3 border-2 border-gray-200 rounded-lg bg-gray-50 text-gray-800 placeholder-gray-400 focus:border-purple-500 focus:bg-white focus:ring-0"
                               placeholder="At least 6 characters">
                    </div>

                    <div class="group">
                        <label class="block text-sm font-semibold text-gray-700 mb-2 flex items-center">
                            <i class="fas fa-lock-open text-purple-600 mr-2"></i>
                            Confirm Password
                        </label>
                        <input type="password" name="confirm_password" required minlength="6"
                               class="input-focus w-full px-4 py-3 border-2 border-gray-200 rounded-lg bg-gray-50 text-gray-800 placeholder-gray-400 focus:border-purple-500 focus:bg-white focus:ring-0"
                               placeholder="Confirm your password">
                    </div>

                    <button type="submit" class="w-full bg-gradient text-white font-bold py-3 px-4 rounded-xl hover:shadow-2xl hover:scale-105 transform transition duration-300 flex items-center justify-center space-x-2 mt-6">
                        <i class="fas fa-user-plus"></i>
                        <span>Create Account</span>
                    </button>
                </form>

                <!-- Divider -->
                <div class="flex items-center my-6">
                    <div class="flex-1 border-t border-gray-200"></div>
                    <span class="px-3 text-gray-500 text-sm">or</span>
                    <div class="flex-1 border-t border-gray-200"></div>
                </div>

                <!-- Login Link -->
                <p class="text-center text-gray-600">
                    Already have an account? 
                    <a href="login.php" class="text-purple-600 font-bold hover:text-purple-700 transition">Sign in</a>
                </p>
            </div>

            <!-- Footer -->
            <p class="text-center text-gray-600 text-xs mt-6">
                <i class="fas fa-shield-alt text-green-500"></i> Your data is secure and encrypted
            </p>
        </div>
    </div>

    <script>
        // Client-side validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const password = document.querySelector('input[name="password"]').value;
            const confirm = document.querySelector('input[name="confirm_password"]').value;

            if (password !== confirm) {
                e.preventDefault();
                alert('Passwords do not match!');
            }
        });
    </script>
</body>
</html>

<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../includes/auth.php';

    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validate
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        header('Location: register.php?error=All fields are required');
        exit;
    }

    if ($password !== $confirm_password) {
        header('Location: register.php?error=Passwords do not match');
        exit;
    }

    $auth = new Auth();
    $result = $auth->register($username, $email, $password);

    if ($result['success']) {
        header('Location: register.php?success=1');
    } else {
        header('Location: register.php?error=' . urlencode($result['message']));
    }
    exit;
}
?>


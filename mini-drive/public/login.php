<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../includes/auth.php';

    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        header('Location: login.php?error=All fields are required');
        exit;
    }

    $auth = new Auth();
    $result = $auth->login($email, $password);

    if ($result['success']) {
        header('Location: index.php');
    } else {
        header('Location: login.php?error=' . urlencode($result['message']));
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - MiniDrive</title>
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
                        <i class="fas fa-sign-in-alt text-3xl text-white"></i>
                    </div>
                    <h1 class="text-4xl font-bold bg-gradient bg-clip-text text-transparent mb-2">MiniDrive</h1>
                    <p class="text-gray-500">Welcome Back</p>
                </div>

                <!-- Error Message -->
                <?php if (isset($_GET['error'])): ?>
                    <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-xl flex items-start space-x-3 animate-shake">
                        <i class="fas fa-exclamation-circle text-red-600 mt-0.5"></i>
                        <p class="text-red-700 text-sm"><?php echo htmlspecialchars($_GET['error']); ?></p>
                    </div>
                <?php endif; ?>

                <!-- Form -->
                <form method="POST" action="login.php" class="space-y-4">
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
                        <input type="password" name="password" required
                               class="input-focus w-full px-4 py-3 border-2 border-gray-200 rounded-lg bg-gray-50 text-gray-800 placeholder-gray-400 focus:border-purple-500 focus:bg-white focus:ring-0"
                               placeholder="Enter your password">
                    </div>

                    <button type="submit" class="w-full bg-gradient text-white font-bold py-3 px-4 rounded-xl hover:shadow-2xl hover:scale-105 transform transition duration-300 flex items-center justify-center space-x-2 mt-6">
                        <i class="fas fa-arrow-right"></i>
                        <span>Sign In</span>
                    </button>
                </form>

                <!-- Divider -->
                <div class="flex items-center my-6">
                    <div class="flex-1 border-t border-gray-200"></div>
                    <span class="px-3 text-gray-500 text-sm">or</span>
                    <div class="flex-1 border-t border-gray-200"></div>
                </div>

                <!-- Register Link -->
                <p class="text-center text-gray-600">
                    Don't have an account? 
                    <a href="register.php" class="text-purple-600 font-bold hover:text-purple-700 transition">Sign up</a>
                </p>
            </div>

            <!-- Footer -->
            <p class="text-center text-gray-600 text-xs mt-6">
                <i class="fas fa-lock text-green-500"></i> Secure login with encryption
            </p>
        </div>
    </div>
</body>
</html>


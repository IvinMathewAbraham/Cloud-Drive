<?php
require_once __DIR__ . '/db.php';

class Auth {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function startSession() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function isLoggedIn() {
        $this->startSession();
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }

    public function getCurrentUser() {
        $this->startSession();
        return $_SESSION['user_id'] ?? null;
    }

    public function getUserInfo($user_id) {
        $stmt = $this->db->prepare("SELECT id, username, email, storage_used FROM users WHERE id = ?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }

    public function register($username, $email, $password) {
        // Validate input
        if (strlen($username) < 3 || strlen($username) > 50) {
            return ['success' => false, 'message' => 'Username must be 3-50 characters'];
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Invalid email format'];
        }

        if (strlen($password) < 6) {
            return ['success' => false, 'message' => 'Password must be at least 6 characters'];
        }

        // Check if user already exists
        $stmt = $this->db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param('ss', $username, $email);
        $stmt->execute();
        
        if ($stmt->get_result()->num_rows > 0) {
            return ['success' => false, 'message' => 'Username or email already exists'];
        }

        // Hash password
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);

        // Insert user
        $stmt = $this->db->prepare("INSERT INTO users (username, email, password, created_at, storage_used) VALUES (?, ?, ?, NOW(), 0)");
        $stmt->bind_param('sss', $username, $email, $hashed_password);

        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Registration successful'];
        } else {
            return ['success' => false, 'message' => 'Registration failed'];
        }
    }

    public function login($email, $password) {
        $stmt = $this->db->prepare("SELECT id, username, password FROM users WHERE email = ?");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            return ['success' => false, 'message' => 'Email not found'];
        }

        $user = $result->fetch_assoc();

        if (!password_verify($password, $user['password'])) {
            return ['success' => false, 'message' => 'Incorrect password'];
        }

        // Check rate limiting
        if (!$this->checkRateLimit($user['id'])) {
            return ['success' => false, 'message' => 'Too many login attempts. Try again later.'];
        }

        // Start session
        $this->startSession();
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['login_time'] = time();

        return ['success' => true, 'message' => 'Login successful'];
    }

    public function logout() {
        $this->startSession();
        session_destroy();
        return ['success' => true, 'message' => 'Logged out successfully'];
    }

    private function checkRateLimit($user_id) {
        // Simple rate limiting: max 5 failed attempts per IP per hour
        $ip = $_SERVER['REMOTE_ADDR'];
        $key = 'login_attempt_' . $ip . '_' . date('YmdH');
        
        if (!isset($_SESSION['login_attempts'])) {
            $_SESSION['login_attempts'] = [];
        }

        if (!isset($_SESSION['login_attempts'][$key])) {
            $_SESSION['login_attempts'][$key] = 0;
        }

        $_SESSION['login_attempts'][$key]++;

        return $_SESSION['login_attempts'][$key] <= 5;
    }

    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            header('Location: ' . APP_URL . '/public/login.php');
            exit;
        }
    }

    public function checkSessionTimeout() {
        $this->startSession();
        if ($this->isLoggedIn()) {
            if (time() - $_SESSION['login_time'] > SESSION_TIMEOUT) {
                $this->logout();
                return false;
            }
            $_SESSION['login_time'] = time(); // Reset timeout
            return true;
        }
        return false;
    }
}

$auth = new Auth();
?>

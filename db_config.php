<?php
// db_config.php — Database configuration and security settings
$host   = getenv('DB_HOST') ?: 'localhost';
$user   = getenv('DB_USER') ?: 'root';
$pass   = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';
$dbname = getenv('DB_NAME') ?: 'cheesyBurger';


$conn   = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("DB Connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

// ── Centralized Gemini API Key Configuration ─────────────────────
// Retrieve key from environment variable if present, or fallback to the free tier key.
if (!defined('GEMINI_API_KEY')) {
    define('GEMINI_API_KEY', getenv('GEMINI_API_KEY') ?: 'AIzaSyDpUtnwnqAJrkIxFQyql6hPhQOiTBC-KHM');
}

// ── Global User/Session Tracking ────────────────────────────────
// If a PHP session is active and a user is logged in, propagate their user ID
// into the MySQL connection session variable `@changed_by` so triggers can log it automatically.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (isset($_SESSION['user_id'])) {
    $userId = (int)$_SESSION['user_id'];
    $conn->query("SET @changed_by = $userId");
} else {
    $conn->query("SET @changed_by = NULL");
}
?>

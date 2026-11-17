
<?php
// All PHP routes that use this connection require an authenticated session.
// The login endpoint is the only database-backed route allowed before sign-in.
if (basename($_SERVER["SCRIPT_NAME"] ?? "") !== "login.php") {
    require_once __DIR__ . "/auth.php";
    requireDashboardLogin();
}

/* Shared XAMPP/MySQL connection. Values can be overridden in Apache's
 * environment for installations that do not use the XAMPP defaults. */
mysqli_report(MYSQLI_REPORT_OFF);

$host = getenv("EMERGENCY_DB_HOST") ?: "127.0.0.1";
$username = getenv("EMERGENCY_DB_USER") ?: "root";
$password = getenv("EMERGENCY_DB_PASSWORD");
$password = $password === false ? "" : $password;
$database = getenv("EMERGENCY_DB_NAME") ?: "emergency_dashboard";
$port = (int)(getenv("EMERGENCY_DB_PORT") ?: 3306);

try {
    $conn = @new mysqli($host, $username, $password, $database, $port);
} catch (Throwable $error) {
    error_log("Emergency Dashboard DB connection failed: " . $error->getMessage());
    http_response_code(500);
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=utf-8");
    }
    echo json_encode([
        "success" => false,
        "message" => "Database unavailable. Start MySQL in XAMPP, import setup.sql, and check the database connection settings."
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($conn->connect_errno) {
    error_log("Emergency Dashboard DB connection failed: " . $conn->connect_error);
    http_response_code(500);
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=utf-8");
    }
    echo json_encode([
        "success" => false,
        "message" => "Database unavailable. Start MySQL in XAMPP, import setup.sql, and check the database connection settings."
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!$conn->set_charset("utf8mb4")) {
    error_log("Emergency Dashboard could not set MySQL charset: " . $conn->error);
}

<?php

header("Content-Type: application/json");

require_once __DIR__ . "/auth.php";
require_once "db.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    echo json_encode([
        "success" => false,
        "message" => "Invalid request method"
    ]);

    exit;
}
$data = json_decode(
    file_get_contents("php://input"),
    true
);
$username = trim((string)($data["username"] ?? ""));
$password = (string)($data["password"] ?? "");
if ($username === "" || $password === "") {
    echo json_encode([
        "success" => false,
        "message" => "Username and password are required"
    ]);

    exit;
}
$stmt = $conn->prepare(
    "SELECT id, username, password
     FROM users
     WHERE username = ?
     LIMIT 1"
);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid username or password"
    ]);
    exit;
}
$user = $result->fetch_assoc();

$correctPassword = false;
/*
 * Supports normal text password
 * and password_hash() passwords.
 */
if ($password === $user["password"]) {
    $correctPassword = true;
} elseif (
    password_verify(
        $password,
        $user["password"]
    )
) {
    $correctPassword = true;
}
if (!$correctPassword) {

    echo json_encode([
        "success" => false,
        "message" => "Invalid username or password"
    ]);
    exit;
}

// Upgrade legacy plaintext demo passwords to password_hash() on first login.
if ($password === $user["password"]) {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $upgrade = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    if ($upgrade) {
        $upgrade->bind_param("si", $hash, $user["id"]);
        $upgrade->execute();
        $upgrade->close();
    }
}

session_regenerate_id(true);
$_SESSION["dashboard_user"] = [
    "id" => (int)$user["id"],
    "username" => $user["username"]
];

echo json_encode([
    "success" => true,
    "message" => "Login successful",

    "user" => [
        "id" => $user["id"],
        "username" => $user["username"]
    ]
]);
$stmt->close();
$conn->close();
?>

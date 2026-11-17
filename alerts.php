<?php
header("Content-Type: application/json; charset=utf-8");
require_once __DIR__ . "/auth.php";
requireDashboardLogin();
require_once __DIR__ . "/db.php";

function alertJson($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

$method = $_SERVER["REQUEST_METHOD"] ?? "GET";

if ($method === "GET") {
    $result = $conn->query(
        "SELECT id, type, priority, location, message, created_at
         FROM emergency_alerts ORDER BY id DESC LIMIT 100"
    );
    if (!$result) {
        error_log("Emergency alerts query failed: " . $conn->error);
        alertJson(["success" => false, "message" => "Unable to load emergency alerts. Import the latest setup.sql schema."], 500);
    }
    $items = [];
    while ($row = $result->fetch_assoc()) $items[] = $row;
    alertJson(["success" => true, "data" => $items]);
}

if ($method !== "POST") {
    header("Allow: GET, POST");
    alertJson(["success" => false, "message" => "Method not allowed."], 405);
}

$raw = file_get_contents("php://input");
$data = json_decode($raw, true);
if (!is_array($data)) alertJson(["success" => false, "message" => "Request body must be valid JSON."], 400);

$type = trim((string)($data["type"] ?? "Other"));
$priority = trim((string)($data["priority"] ?? "Medium"));
$location = trim((string)($data["location"] ?? ""));
$message = trim((string)($data["message"] ?? ""));
$allowedTypes = ["Fire", "Road Accident", "Flood", "Medical Emergency", "Building Collapse", "Other"];
$allowedPriorities = ["Critical", "High", "Medium", "Low"];

if (!in_array($type, $allowedTypes, true)) alertJson(["success" => false, "message" => "Select a valid emergency type."], 400);
if (!in_array($priority, $allowedPriorities, true)) alertJson(["success" => false, "message" => "Select a valid priority."], 400);
if ($location === "" || strlen($location) > 1020) alertJson(["success" => false, "message" => "Enter a location of 1 to 255 characters."], 400);
if (strlen($message) > 8000) alertJson(["success" => false, "message" => "Alert message must be 2000 characters or fewer."], 400);

$stmt = $conn->prepare("INSERT INTO emergency_alerts (type, priority, location, message) VALUES (?, ?, ?, ?)");
if (!$stmt) {
    error_log("Emergency alert insert prepare failed: " . $conn->error);
    alertJson(["success" => false, "message" => "Unable to prepare emergency alert."], 500);
}
$stmt->bind_param("ssss", $type, $priority, $location, $message);
if (!$stmt->execute()) {
    error_log("Emergency alert insert failed: " . $stmt->error);
    $stmt->close();
    alertJson(["success" => false, "message" => "Unable to save emergency alert."], 500);
}
$id = $stmt->insert_id;
$stmt->close();

if ($history = $conn->prepare("INSERT INTO activity_history (activity, details) VALUES (?, ?)")) {
    $activity = "Emergency Alert Created";
    $details = $priority . " " . $type . " at " . $location;
    $history->bind_param("ss", $activity, $details);
    if (!$history->execute()) error_log("Emergency alert activity logging failed: " . $history->error);
    $history->close();
}

alertJson(["success" => true, "message" => "Emergency alert saved.", "id" => $id], 201);

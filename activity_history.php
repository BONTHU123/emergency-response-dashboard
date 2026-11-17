<?php
header("Content-Type: application/json; charset=utf-8");
require_once __DIR__ . "/auth.php";
requireDashboardLogin();
require_once __DIR__ . "/db.php";

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    http_response_code(405);
    header("Allow: GET");
    echo json_encode(["success" => false, "message" => "Method not allowed."]);
    exit;
}

$result = $conn->query(
    "SELECT id, activity, details, created_at
     FROM activity_history ORDER BY id DESC LIMIT 100"
);

if (!$result) {
    error_log("Activity history query failed: " . $conn->error);
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Unable to load activity history."]);
    exit;
}

$items = [];
while ($row = $result->fetch_assoc()) {
    $items[] = $row;
}

echo json_encode(["success" => true, "data" => $items], JSON_UNESCAPED_UNICODE);

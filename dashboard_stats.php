<?php
require_once "db.php";

header("Content-Type: application/json; charset=UTF-8");
/* INCIDENT STATISTICS */
$result = $conn->query("
    SELECT
        COUNT(*) AS total,
        SUM(LOWER(TRIM(status)) = 'active') AS active_count,
        SUM(LOWER(TRIM(status)) = 'assigned') AS assigned_count,
        SUM(LOWER(TRIM(status)) = 'resolved') AS resolved_count,
        SUM(LOWER(TRIM(priority)) = 'high') AS high_count
    FROM incidents
");

if (!$result) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "database_error" => $conn->error
    ], JSON_PRETTY_PRINT);
    $conn->close();
    exit;
}

$row = $result->fetch_assoc();

$totalIncidents = (int)$row["total"];
$activeIncidents = (int)$row["active_count"];
$assignedIncidents = (int)$row["assigned_count"];
$resolvedIncidents = (int)$row["resolved_count"];
$highPriority = (int)$row["high_count"];
echo json_encode([
    "success" => true,
    "totalIncidents" => $totalIncidents,
    "activeIncidents" => $activeIncidents,
    "assignedIncidents" => $assignedIncidents,
    "resolvedIncidents" => $resolvedIncidents,
    "highPriority" => $highPriority
], JSON_PRETTY_PRINT);

$conn->close();
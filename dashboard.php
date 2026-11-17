<?php
header("Content-Type: application/json; charset=utf-8");
require_once "db.php";

function countValue($conn, $sql) {
    $result = $conn->query($sql);
    if (!$result) return 0;
    $row = $result->fetch_assoc();
    return (int)($row["n"] ?? 0);
}

$active = countValue($conn, "SELECT COUNT(*) n FROM incidents WHERE status IN ('Active','Assigned')");
$resolved = countValue($conn, "SELECT COUNT(*) n FROM incidents WHERE status='Resolved'");
$total = countValue($conn, "SELECT COUNT(*) n FROM incidents");
$responders = countValue($conn, "SELECT COUNT(*) n FROM response_team WHERE status='Available'");
$ambulances = countValue($conn, "SELECT COALESCE(quantity,0) n FROM resources WHERE name='Ambulances' LIMIT 1");
$history = countValue($conn, "SELECT COUNT(*) n FROM activity_history");
$high = countValue($conn, "SELECT COUNT(*) n FROM incidents WHERE status IN ('Active','Assigned') AND priority='High'");
$rescued = countValue($conn, "SELECT COUNT(*) n FROM victims WHERE `condition`='Recovered'");

$result = $conn->query("SELECT id,title,location,priority,status,responder FROM incidents ORDER BY id DESC LIMIT 5");
$recent = [];
if ($result) while ($row = $result->fetch_assoc()) $recent[] = $row;

echo json_encode([
    "active" => $active,
    "resolved" => $resolved,
    "total" => $total,
    "available_responders" => $responders,
    "ambulances" => $ambulances,
    "history" => $history,
    "high_priority" => $high,
    "rescued" => $rescued,
    "recent" => $recent
]);
?>

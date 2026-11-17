<?php
header("Content-Type: application/json");
require_once "db.php";
$data = [
    "total"=>0,
    "active"=>0,
    "resolved"=>0,
    "pending"=>0,
    "high_priority"=>0,
    "responders"=>0,
    "rescued"=>0,
    "priority"=>["high"=>0,"medium"=>0,"low"=>0],
    "status"=>["active"=>0,"resolved"=>0,"pending"=>0]
];
function countWhere($conn, $sql) {
    $r = $conn->query($sql);
    if (!$r) return 0;
    $row = $r->fetch_assoc();
    return (int)($row["total"] ?? 0);
}
$data["total"] = countWhere($conn,"SELECT COUNT(*) total FROM incidents");
$data["active"] = countWhere($conn,"SELECT COUNT(*) total FROM incidents WHERE LOWER(status)='active'");
$data["resolved"] = countWhere($conn,"SELECT COUNT(*) total FROM incidents WHERE LOWER(status)='resolved'");
$data["pending"] = countWhere($conn,"SELECT COUNT(*) total FROM incidents WHERE LOWER(status)='pending'");
$data["high_priority"] = countWhere($conn,"SELECT COUNT(*) total FROM incidents WHERE LOWER(priority)='high'");
$data["priority"]["high"] = $data["high_priority"];
$data["priority"]["medium"] = countWhere($conn,"SELECT COUNT(*) total FROM incidents WHERE LOWER(priority)='medium'");
$data["priority"]["low"] = countWhere($conn,"SELECT COUNT(*) total FROM incidents WHERE LOWER(priority)='low'");
$data["status"]["active"] = $data["active"];
$data["status"]["resolved"] = $data["resolved"];
$data["status"]["pending"] = $data["pending"];
$data["responders"] = countWhere($conn,"SELECT COUNT(*) total FROM team_members WHERE LOWER(status)='available'");
echo json_encode($data);
?>
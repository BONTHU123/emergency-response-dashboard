<?php
header("Content-Type: application/json");
require_once "db.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["success"=>false,"message"=>"POST request required."]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true) ?: [];

$type = trim($data["alert_type"] ?? "SOS");
$message = trim($data["message"] ?? "Emergency SOS alert");
$lat = ($data["latitude"] ?? "") === "" ? null : (float)$data["latitude"];
$lng = ($data["longitude"] ?? "") === "" ? null : (float)$data["longitude"];
$status = "Active";

$stmt = $conn->prepare(
    "INSERT INTO emergency_alerts(alert_type,message,latitude,longitude,status)
     VALUES(?,?,?,?,?)"
);
$stmt->bind_param("ssdds",$type,$message,$lat,$lng,$status);

if ($stmt->execute()) {
    $log = $conn->prepare("INSERT INTO activity_history(action,module) VALUES (?,?)");
    $action = "Emergency SOS alert received";
    $module = "SOS";
    $log->bind_param("ss",$action,$module);
    $log->execute();
    $log->close();

    echo json_encode(["success"=>true,"message"=>"SOS saved.","id"=>$stmt->insert_id]);
} else {
    echo json_encode(["success"=>false,"message"=>$stmt->error]);
}
$stmt->close();
?>
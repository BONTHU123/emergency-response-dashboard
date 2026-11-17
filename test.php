<?php

header("Content-Type: application/json");
require_once "db.php";

$method = $_SERVER["REQUEST_METHOD"];


/* ==========================================
   GET - LOAD RESPONSE TEAMS
   ========================================== */

if ($method === "GET") {

    $result = $conn->query("
        SELECT
            id,
            name,
            role,
            phone,
            status,
            assigned_incident,
            created_at
        FROM response_team
        ORDER BY id DESC
    ");

    if (!$result) {
        http_response_code(500);

        echo json_encode([
            "success" => false,
            "message" => $conn->error
        ]);

        exit;
    }

    $teams = [];

    while ($row = $result->fetch_assoc()) {
        $teams[] = $row;
    }

    echo json_encode($teams);
    exit;
}


/* ==========================================
   POST - ADD RESPONSE TEAM
   ========================================== */

if ($method === "POST") {

    $data = json_decode(
        file_get_contents("php://input"),
        true
    );

    if (!is_array($data)) {
        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "Invalid JSON"
        ]);

        exit;
    }

    $name = trim($data["name"] ?? "");
    $role = trim($data["role"] ?? "");
    $phone = trim($data["phone"] ?? "");
    $status = trim($data["status"] ?? "Available");

    if ($name === "") {

        echo json_encode([
            "success" => false,
            "message" => "Team name is required"
        ]);

        exit;
    }

    $stmt = $conn->prepare("
        INSERT INTO response_team
        (name, role, phone, status, assigned_incident)
        VALUES (?, ?, ?, ?, '')
    ");

    if (!$stmt) {

        echo json_encode([
            "success" => false,
            "message" => $conn->error
        ]);

        exit;
    }

    $stmt->bind_param(
        "ssss",
        $name,
        $role,
        $phone,
        $status
    );

    if ($stmt->execute()) {

        echo json_encode([
            "success" => true,
            "message" => "Response team added successfully",
            "id" => $stmt->insert_id
        ]);

    } else {

        echo json_encode([
            "success" => false,
            "message" => $stmt->error
        ]);
    }

    $stmt->close();
    exit;
}


/* ==========================================
   PUT - ASSIGN TEAM TO INCIDENT
   ========================================== */

if ($method === "PUT") {

    $data = json_decode(
        file_get_contents("php://input"),
        true
    );

    if (!is_array($data)) {

        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "Invalid JSON"
        ]);

        exit;
    }

    $team_id = intval($data["team_id"] ?? 0);
    $incident_id = intval($data["incident_id"] ?? 0);

    if ($team_id <= 0 || $incident_id <= 0) {

        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "Team ID and Incident ID are required"
        ]);

        exit;
    }


    /* Get team name */

    $teamStmt = $conn->prepare("
        SELECT name
        FROM response_team
        WHERE id = ?
    ");

    $teamStmt->bind_param(
        "i",
        $team_id
    );

    $teamStmt->execute();

    $teamResult = $teamStmt->get_result();

    if ($teamResult->num_rows === 0) {

        echo json_encode([
            "success" => false,
            "message" => "Response team not found"
        ]);

        exit;
    }

    $team = $teamResult->fetch_assoc();

    $teamName = $team["name"];

    $teamStmt->close();


    /* Check incident */

    $incidentStmt = $conn->prepare("
        SELECT id
        FROM incidents
        WHERE id = ?
    ");

    $incidentStmt->bind_param(
        "i",
        $incident_id
    );

    $incidentStmt->execute();

    $incidentResult = $incidentStmt->get_result();

    if ($incidentResult->num_rows === 0) {

        echo json_encode([
            "success" => false,
            "message" => "Incident not found"
        ]);

        exit;
    }

    $incidentStmt->close();


    /*
       Update incident
       responder = team name
       status = Assigned
    */

    $updateIncident = $conn->prepare("
        UPDATE incidents
        SET responder = ?,
            status = 'Assigned'
        WHERE id = ?
    ");

    $updateIncident->bind_param(
        "si",
        $teamName,
        $incident_id
    );

    if (!$updateIncident->execute()) {

        echo json_encode([
            "success" => false,
            "message" => "Could not update incident"
        ]);

        exit;
    }

    $updateIncident->close();


    /*
       Update response team
    */

    $updateTeam = $conn->prepare("
        UPDATE response_team
        SET status = 'On Mission',
            assigned_incident = ?
        WHERE id = ?
    ");

    $incidentText = (string)$incident_id;

    $updateTeam->bind_param(
        "si",
        $incidentText,
        $team_id
    );

    if (!$updateTeam->execute()) {

        echo json_encode([
            "success" => false,
            "message" => "Incident updated but team update failed"
        ]);

        exit;
    }

    $updateTeam->close();


    echo json_encode([
        "success" => true,
        "message" => "Response team assigned successfully",
        "team_id" => $team_id,
        "team_name" => $teamName,
        "incident_id" => $incident_id
    ]);

    exit;
}


/* ==========================================
   DELETE - DELETE TEAM
   ========================================== */

if ($method === "DELETE") {

    $id = intval($_GET["id"] ?? 0);

    if ($id <= 0) {

        echo json_encode([
            "success" => false,
            "message" => "Invalid team ID"
        ]);

        exit;
    }

    $stmt = $conn->prepare("
        DELETE FROM response_team
        WHERE id = ?
    ");

    $stmt->bind_param(
        "i",
        $id
    );

    if ($stmt->execute()) {

        echo json_encode([
            "success" => true,
            "message" => "Team deleted successfully"
        ]);

    } else {

        echo json_encode([
            "success" => false,
            "message" => $stmt->error
        ]);
    }

    $stmt->close();
    exit;
}


http_response_code(405);

echo json_encode([
    "success" => false,
    "message" => "Method not allowed"
]);

?>
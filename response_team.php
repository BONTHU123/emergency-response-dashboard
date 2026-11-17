
<?php

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    exit;
}

require_once __DIR__ . "/auth.php";
requireDashboardLogin();

require_once "db.php";

function respond($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function readJson() {
    $data = json_decode(file_get_contents("php://input"), true);
    return is_array($data) ? $data : null;
}

function validTeamStatus($status) {
    return in_array(
        $status,
        ["Available", "On Mission", "Off Duty"],
        true
    );
}

$method = $_SERVER["REQUEST_METHOD"];

/* ==========================================
   GET - LOAD RESPONSE TEAMS
   ========================================== */

if ($method === "GET") {
    $sql = "
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
    ";

    $result = $conn->query($sql);

    if (!$result) {
        respond([
            "success" => false,
            "message" => "Unable to load response teams.",
            "error" => $conn->error
        ], 500);
    }

    $teams = [];

    while ($row = $result->fetch_assoc()) {
        $teams[] = $row;
    }

    respond([
        "success" => true,
        "data" => $teams
    ]);
}

/* ==========================================
   POST - ADD RESPONSE TEAM
   ========================================== */

if ($method === "POST") {
    $data = readJson();

    if ($data === null) {
        respond([
            "success" => false,
            "message" => "Invalid JSON request."
        ], 400);
    }

    $name = trim($data["name"] ?? "");
    $role = trim($data["role"] ?? $data["leader"] ?? "");
    $phone = trim($data["phone"] ?? "");
    $status = trim($data["status"] ?? "Available");

    if ($name === "") {
        respond([
            "success" => false,
            "message" => "Team name is required."
        ], 400);
    }

    if ($role === "") {
        respond([
            "success" => false,
            "message" => "Team role is required."
        ], 400);
    }

    if (!validTeamStatus($status)) {
        respond([
            "success" => false,
            "message" => "Invalid team status."
        ], 400);
    }

    $stmt = $conn->prepare("
        INSERT INTO response_team
            (name, role, phone, status, assigned_incident)
        VALUES (?, ?, ?, ?, '')
    ");

    if (!$stmt) {
        respond([
            "success" => false,
            "message" => "Unable to prepare team insert.",
            "error" => $conn->error
        ], 500);
    }

    $stmt->bind_param("ssss", $name, $role, $phone, $status);

    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();

        respond([
            "success" => false,
            "message" => "Unable to add response team.",
            "error" => $error
        ], 500);
    }

    $id = $stmt->insert_id;
    $stmt->close();

    respond([
        "success" => true,
        "message" => "Response team added successfully.",
        "id" => $id
    ], 201);
}

/* ==========================================
   PUT - ASSIGN TEAM OR UPDATE TEAM
   ========================================== */

if ($method === "PUT") {
    $data = readJson();

    if ($data === null) {
        respond([
            "success" => false,
            "message" => "Invalid JSON request."
        ], 400);
    }

    /*
     * ASSIGN TEAM TO INCIDENT
     * Expected JSON:
     * {
     *   "team_id": 1,
     *   "incident_id": 2
     * }
     */

    if (
        isset($data["team_id"]) &&
        isset($data["incident_id"])
    ) {
        $teamId = (int)$data["team_id"];
        $incidentId = (int)$data["incident_id"];

        if ($teamId <= 0 || $incidentId <= 0) {
            respond([
                "success" => false,
                "message" => "Valid team and incident IDs are required."
            ], 400);
        }

        try {
            $conn->begin_transaction();

            // Find the selected team.
            $stmt = $conn->prepare("
                SELECT id, name, status
                FROM response_team
                WHERE id = ?
                FOR UPDATE
            ");

            if (!$stmt) {
                throw new Exception($conn->error);
            }

            $stmt->bind_param("i", $teamId);
            $stmt->execute();
            $teamResult = $stmt->get_result();
            $team = $teamResult->fetch_assoc();
            $stmt->close();

            if (!$team) {
                throw new Exception("Response team not found.");
            }

            if ($team["status"] === "Off Duty") {
                throw new Exception(
                    "This team is Off Duty and cannot be assigned."
                );
            }

            // Confirm the incident exists and is not resolved.
            $stmt = $conn->prepare("
                SELECT id, status
                FROM incidents
                WHERE id = ?
                FOR UPDATE
            ");

            if (!$stmt) {
                throw new Exception($conn->error);
            }

            $stmt->bind_param("i", $incidentId);
            $stmt->execute();
            $incidentResult = $stmt->get_result();
            $incident = $incidentResult->fetch_assoc();
            $stmt->close();

            if (!$incident) {
                throw new Exception("Incident not found.");
            }

            if ($incident["status"] === "Resolved") {
                throw new Exception(
                    "A resolved incident cannot be assigned."
                );
            }

            // Assign the responder and update incident status.
            $teamName = $team["name"];

            $stmt = $conn->prepare("
                UPDATE incidents
                SET responder = ?, status = 'Assigned'
                WHERE id = ?
            ");

            if (!$stmt) {
                throw new Exception($conn->error);
            }

            $stmt->bind_param("si", $teamName, $incidentId);

            if (!$stmt->execute()) {
                throw new Exception($stmt->error);
            }

            $stmt->close();

            // Mark the team as on mission.
            $incidentText = (string)$incidentId;

            $stmt = $conn->prepare("
                UPDATE response_team
                SET status = 'On Mission',
                    assigned_incident = ?
                WHERE id = ?
            ");

            if (!$stmt) {
                throw new Exception($conn->error);
            }

            $stmt->bind_param("si", $incidentText, $teamId);

            if (!$stmt->execute()) {
                throw new Exception($stmt->error);
            }

            $stmt->close();
            $conn->commit();

            respond([
                "success" => true,
                "message" => "Response team assigned successfully.",
                "team_id" => $teamId,
                "team_name" => $teamName,
                "incident_id" => $incidentId
            ]);

        } catch (Throwable $e) {
            $conn->rollback();

            respond([
                "success" => false,
                "message" => $e->getMessage()
            ], 400);
        }
    }

    /*
     * UPDATE TEAM DETAILS OR STATUS
     * Accepts id, name, role (or leader), phone, status.
     */

    $id = (int)($data["id"] ?? 0);

    if ($id <= 0) {
        respond([
            "success" => false,
            "message" => "Team ID is required."
        ], 400);
    }

    $name = trim($data["name"] ?? "");
    $role = trim($data["role"] ?? $data["leader"] ?? "");
    $phone = trim($data["phone"] ?? "");
    $status = trim($data["status"] ?? "");

    if ($status !== "" && !validTeamStatus($status)) {
        respond([
            "success" => false,
            "message" => "Invalid team status."
        ], 400);
    }

    // Support status-only updates.
    if (
        $name === "" &&
        $role === "" &&
        $phone === "" &&
        $status !== ""
    ) {
        $stmt = $conn->prepare("
            UPDATE response_team
            SET status = ?
            WHERE id = ?
        ");

        if (!$stmt) {
            respond([
                "success" => false,
                "message" => $conn->error
            ], 500);
        }

        $stmt->bind_param("si", $status, $id);
    } else {
        if ($name === "" || $role === "") {
            respond([
                "success" => false,
                "message" => "Team name and role are required for a details update."
            ], 400);
        }

        if ($status === "") {
            $status = "Available";
        }

        $stmt = $conn->prepare("
            UPDATE response_team
            SET name = ?, role = ?, phone = ?, status = ?
            WHERE id = ?
        ");

        if (!$stmt) {
            respond([
                "success" => false,
                "message" => $conn->error
            ], 500);
        }

        $stmt->bind_param(
            "ssssi",
            $name,
            $role,
            $phone,
            $status,
            $id
        );
    }

    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();

        respond([
            "success" => false,
            "message" => "Unable to update team.",
            "error" => $error
        ], 500);
    }

    $stmt->close();

    respond([
        "success" => true,
        "message" => "Team updated successfully."
    ]);
}

/* ==========================================
   DELETE - DELETE RESPONSE TEAM
   ========================================== */

if ($method === "DELETE") {
    $id = (int)($_GET["id"] ?? 0);

    if ($id <= 0) {
        respond([
            "success" => false,
            "message" => "A valid team ID is required."
        ], 400);
    }

    try {
        $conn->begin_transaction();

        $stmt = $conn->prepare("
            SELECT name
            FROM response_team
            WHERE id = ?
            FOR UPDATE
        ");

        if (!$stmt) {
            throw new Exception($conn->error);
        }

        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $team = $result->fetch_assoc();
        $stmt->close();

        if (!$team) {
            throw new Exception("Response team not found.");
        }

        // Release incidents assigned to this team.
        $teamName = $team["name"];

        $stmt = $conn->prepare("
            UPDATE incidents
            SET responder = NULL,
                status = 'Active'
            WHERE responder = ?
              AND status = 'Assigned'
        ");

        if (!$stmt) {
            throw new Exception($conn->error);
        }

        $stmt->bind_param("s", $teamName);

        if (!$stmt->execute()) {
            throw new Exception($stmt->error);
        }

        $stmt->close();

        $stmt = $conn->prepare("
            DELETE FROM response_team
            WHERE id = ?
        ");

        if (!$stmt) {
            throw new Exception($conn->error);
        }

        $stmt->bind_param("i", $id);

        if (!$stmt->execute()) {
            throw new Exception($stmt->error);
        }

        $stmt->close();
        $conn->commit();

        respond([
            "success" => true,
            "message" => "Response team deleted successfully."
        ]);

    } catch (Throwable $e) {
        $conn->rollback();

        respond([
            "success" => false,
            "message" => $e->getMessage()
        ], 400);
    }
}

respond([
    "success" => false,
    "message" => "Method not allowed."
], 405);
?>

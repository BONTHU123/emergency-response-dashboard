<?php

header("Content-Type: application/json; charset=utf-8");

require_once "db.php";


function response($data)
{
    echo json_encode($data);
    exit;
}


function logActivity($conn, $activity, $details = "")
{
    $stmt = $conn->prepare(
        "INSERT INTO activity_history
        (activity, details)
        VALUES (?, ?)"
    );

    if ($stmt) {

        $stmt->bind_param(
            "ss",
            $activity,
            $details
        );

        $stmt->execute();
        $stmt->close();
    }
}


$action = $_GET["action"] ?? "";

$method = $_SERVER["REQUEST_METHOD"];


// =========================================================
// GET DATA
// =========================================================

if ($method === "GET") {


    // =====================================================
    // INCIDENTS
    // =====================================================

    if ($action === "incidents") {

        $result = $conn->query(
            "SELECT
                id,
                title,
                location,
                priority,
                status,
                responder,
                description,
                people_rescued,
                created_at,
                resolved_at
             FROM incidents
             ORDER BY id DESC"
        );

        if (!$result) {

            response([
                "success" => false,
                "message" => $conn->error
            ]);
        }

        $data = [];

        while ($row = $result->fetch_assoc()) {

            $data[] = $row;
        }

        response([
            "success" => true,
            "data" => $data
        ]);
    }


    // =====================================================
    // RESPONSE TEAM
    // =====================================================

    if ($action === "team") {

        $result = $conn->query(
            "SELECT
                id,
                name,
                role,
                phone,
                status,
                assigned_incident,
                created_at
             FROM response_team
             ORDER BY id ASC"
        );

        if (!$result) {

            response([
                "success" => false,
                "message" => $conn->error
            ]);
        }

        $data = [];

        while ($row = $result->fetch_assoc()) {

            $data[] = $row;
        }

        response([
            "success" => true,
            "data" => $data
        ]);
    }


    // =====================================================
    // RESOURCES
    // =====================================================

    if ($action === "resources") {

        $result = $conn->query(
            "SELECT
                id,
                name,
                quantity,
                unit,
                updated_at
             FROM resources
             ORDER BY id ASC"
        );

        if (!$result) {

            response([
                "success" => false,
                "message" => $conn->error
            ]);
        }

        $data = [];

        while ($row = $result->fetch_assoc()) {

            $data[] = $row;
        }

        response([
            "success" => true,
            "data" => $data
        ]);
    }


    // =====================================================
    // VICTIMS
    // =====================================================

    if ($action === "victims") {

        $result = $conn->query(
            "SELECT
                id,
                name,
                location,
                condition_status,
                rescue_status,
                notes,
                created_at
             FROM victims
             ORDER BY id DESC"
        );

        if (!$result) {

            response([
                "success" => false,
                "message" => $conn->error
            ]);
        }

        $data = [];

        while ($row = $result->fetch_assoc()) {

            $data[] = $row;
        }

        response([
            "success" => true,
            "data" => $data
        ]);
    }


    // =====================================================
    // HISTORY
    // =====================================================

    if ($action === "history") {

        $result = $conn->query(
            "SELECT
                id,
                activity,
                details,
                created_at
             FROM activity_history
             ORDER BY id DESC"
        );

        if (!$result) {

            response([
                "success" => false,
                "message" => $conn->error
            ]);
        }

        $data = [];

        while ($row = $result->fetch_assoc()) {

            $data[] = $row;
        }

        response([
            "success" => true,
            "data" => $data
        ]);
    }


    // =====================================================
    // DASHBOARD
    // =====================================================

    if ($action === "dashboard") {

        $active = 0;
        $assigned = 0;
        $resolved = 0;
        $rescued = 0;

        $result = $conn->query(
            "SELECT
                SUM(status = 'Active') AS active_count,
                SUM(status = 'Assigned') AS assigned_count,
                SUM(status = 'Resolved') AS resolved_count,
                COALESCE(SUM(people_rescued), 0) AS rescued_count
             FROM incidents"
        );

        if ($result) {

            $row = $result->fetch_assoc();

            $active =
                intval($row["active_count"] ?? 0);

            $assigned =
                intval($row["assigned_count"] ?? 0);

            $resolved =
                intval($row["resolved_count"] ?? 0);

            $rescued =
                intval($row["rescued_count"] ?? 0);
        }


        $availableResponders = 0;

        $result = $conn->query(
            "SELECT COUNT(*) AS total
             FROM response_team
             WHERE status = 'Available'"
        );

        if ($result) {

            $row = $result->fetch_assoc();

            $availableResponders =
                intval($row["total"]);
        }


        $ambulances = 0;

        $result = $conn->query(
            "SELECT quantity
             FROM resources
             WHERE name = 'Ambulance'
             LIMIT 1"
        );

        if ($result) {

            $row = $result->fetch_assoc();

            if ($row) {
                $ambulances =
                    intval($row["quantity"]);
            }
        }


        response([
            "success" => true,
            "data" => [
                "active" => $active,
                "assigned" => $assigned,
                "resolved" => $resolved,
                "rescued" => $rescued,
                "available_responders" =>
                    $availableResponders,
                "ambulances" => $ambulances
            ]
        ]);
    }


    response([
        "success" => false,
        "message" => "Invalid GET action"
    ]);
}


// =========================================================
// POST
// =========================================================

if ($method === "POST") {

    $input =
        json_decode(
            file_get_contents("php://input"),
            true
        );

    if (!is_array($input)) {

        response([
            "success" => false,
            "message" => "Invalid JSON"
        ]);
    }


    // =====================================================
    // ADD INCIDENT
    // =====================================================

    if ($action === "add_incident") {

        $title =
            trim($input["title"] ?? "");

        $location =
            trim($input["location"] ?? "");

        $priority =
            trim($input["priority"] ?? "Medium");

        $description =
            trim($input["description"] ?? "");

        if (
            $title === "" ||
            $location === ""
        ) {

            response([
                "success" => false,
                "message" =>
                    "Title and location are required"
            ]);
        }

        $stmt = $conn->prepare(
            "INSERT INTO incidents
            (
                title,
                location,
                priority,
                status,
                responder,
                description,
                people_rescued
            )
            VALUES (?, ?, ?, 'Active', '', ?, 0)"
        );

        $stmt->bind_param(
            "ssss",
            $title,
            $location,
            $priority,
            $description
        );

        if (!$stmt->execute()) {

            response([
                "success" => false,
                "message" => $stmt->error
            ]);
        }

        $id = $stmt->insert_id;

        $stmt->close();

        logActivity(
            $conn,
            "Incident Created",
            "Incident #$id - $title"
        );

        response([
            "success" => true,
            "message" => "Incident created",
            "id" => $id
        ]);
    }


    // =====================================================
    // CHANGE INCIDENT STATUS
    // =====================================================

    if ($action === "update_status") {

        $id =
            intval($input["id"] ?? 0);

        $status =
            trim($input["status"] ?? "");

        $allowed =
            ["Active", "Assigned", "Resolved"];

        if (
            $id <= 0 ||
            !in_array($status, $allowed)
        ) {

            response([
                "success" => false,
                "message" => "Invalid incident status"
            ]);
        }


        if ($status === "Resolved") {

            $peopleRescued =
                max(
                    0,
                    intval(
                        $input["people_rescued"] ?? 0
                    )
                );

            $stmt = $conn->prepare(
                "UPDATE incidents
                 SET
                    status = ?,
                    people_rescued = ?,
                    resolved_at = NOW()
                 WHERE id = ?"
            );

            $stmt->bind_param(
                "sii",
                $status,
                $peopleRescued,
                $id
            );

        } else {

            $stmt = $conn->prepare(
                "UPDATE incidents
                 SET
                    status = ?,
                    resolved_at = NULL
                 WHERE id = ?"
            );

            $stmt->bind_param(
                "si",
                $status,
                $id
            );
        }


        if (!$stmt->execute()) {

            response([
                "success" => false,
                "message" => $stmt->error
            ]);
        }

        $stmt->close();


        logActivity(
            $conn,
            "Incident Status Changed",
            "Incident #$id changed to $status"
        );


        response([
            "success" => true,
            "message" => "Status updated"
        ]);
    }


    // =====================================================
    // ASSIGN RESPONSE TEAM
    // =====================================================

    if ($action === "assign_team") {

        $incidentId =
            intval($input["incident_id"] ?? 0);

        $teamId =
            intval($input["team_id"] ?? 0);

        if (
            $incidentId <= 0 ||
            $teamId <= 0
        ) {

            response([
                "success" => false,
                "message" =>
                    "Incident and team are required"
            ]);
        }


        // Get team
        $stmt = $conn->prepare(
            "SELECT name
             FROM response_team
             WHERE id = ?"
        );

        $stmt->bind_param(
            "i",
            $teamId
        );

        $stmt->execute();

        $result =
            $stmt->get_result();

        $team =
            $result->fetch_assoc();

        $stmt->close();


        if (!$team) {

            response([
                "success" => false,
                "message" => "Team not found"
            ]);
        }


        $teamName =
            $team["name"];


        // Update incident
        $stmt = $conn->prepare(
            "UPDATE incidents
             SET
                responder = ?,
                status = 'Assigned'
             WHERE id = ?"
        );

        $stmt->bind_param(
            "si",
            $teamName,
            $incidentId
        );

        if (!$stmt->execute()) {

            response([
                "success" => false,
                "message" => $stmt->error
            ]);
        }

        $stmt->close();


        // Update team
        $stmt = $conn->prepare(
            "UPDATE response_team
             SET
                status = 'On Mission',
                assigned_incident = ?
             WHERE id = ?"
        );

        $incidentText =
            "Incident #".$incidentId;

        $stmt->bind_param(
            "si",
            $incidentText,
            $teamId
        );

        $stmt->execute();

        $stmt->close();


        logActivity(
            $conn,
            "Response Team Assigned",
            "$teamName assigned to Incident #$incidentId"
        );


        response([
            "success" => true,
            "message" =>
                "$teamName assigned successfully"
        ]);
    }


    // =====================================================
    // ADD TEAM
    // =====================================================

    if ($action === "add_team") {

        $name =
            trim($input["name"] ?? "");

        $role =
            trim($input["role"] ?? "");

        $phone =
            trim($input["phone"] ?? "");

        if ($name === "") {

            response([
                "success" => false,
                "message" => "Team name is required"
            ]);
        }


        $stmt = $conn->prepare(
            "INSERT INTO response_team
            (
                name,
                role,
                phone,
                status,
                assigned_incident
            )
            VALUES (?, ?, ?, 'Available', '')"
        );

        $stmt->bind_param(
            "sss",
            $name,
            $role,
            $phone
        );


        if (!$stmt->execute()) {

            response([
                "success" => false,
                "message" => $stmt->error
            ]);
        }

        $id =
            $stmt->insert_id;

        $stmt->close();


        logActivity(
            $conn,
            "Response Team Added",
            "$name added"
        );


        response([
            "success" => true,
            "id" => $id
        ]);
    }


    // =====================================================
    // UPDATE TEAM STATUS
    // =====================================================

    if ($action === "team_status") {

        $id =
            intval($input["id"] ?? 0);

        $status =
            trim($input["status"] ?? "");

        $allowed = [
            "Available",
            "On Mission",
            "Unavailable"
        ];

        if (
            $id <= 0 ||
            !in_array($status, $allowed)
        ) {

            response([
                "success" => false,
                "message" => "Invalid team status"
            ]);
        }


        $stmt = $conn->prepare(
            "UPDATE response_team
             SET status = ?
             WHERE id = ?"
        );

        $stmt->bind_param(
            "si",
            $status,
            $id
        );

        $stmt->execute();

        $stmt->close();


        logActivity(
            $conn,
            "Team Status Changed",
            "Team #$id changed to $status"
        );


        response([
            "success" => true
        ]);
    }


    // =====================================================
    // CHANGE RESOURCE QUANTITY
    // =====================================================

    if ($action === "resource_update") {

        $id =
            intval($input["id"] ?? 0);

        $change =
            intval($input["change"] ?? 0);


        $stmt = $conn->prepare(
            "UPDATE resources
             SET quantity =
                GREATEST(0, quantity + ?)
             WHERE id = ?"
        );

        $stmt->bind_param(
            "ii",
            $change,
            $id
        );

        if (!$stmt->execute()) {

            response([
                "success" => false,
                "message" => $stmt->error
            ]);
        }

        $stmt->close();


        logActivity(
            $conn,
            "Resource Updated",
            "Resource #$id quantity changed by $change"
        );


        response([
            "success" => true
        ]);
    }


    // =====================================================
    // ADD VICTIM
    // =====================================================

    if ($action === "add_victim") {

        $name =
            trim($input["name"] ?? "");

        $location =
            trim($input["location"] ?? "");

        $condition =
            trim(
                $input["condition_status"]
                ?? "Unknown"
            );

        $notes =
            trim($input["notes"] ?? "");


        if ($name === "") {

            response([
                "success" => false,
                "message" => "Victim name is required"
            ]);
        }


        $stmt = $conn->prepare(
            "INSERT INTO victims
            (
                name,
                location,
                condition_status,
                rescue_status,
                notes
            )
            VALUES (?, ?, ?, 'Pending', ?)"
        );

        $stmt->bind_param(
            "ssss",
            $name,
            $location,
            $condition,
            $notes
        );


        if (!$stmt->execute()) {

            response([
                "success" => false,
                "message" => $stmt->error
            ]);
        }


        $id =
            $stmt->insert_id;

        $stmt->close();


        logActivity(
            $conn,
            "Victim Registered",
            "$name registered"
        );


        response([
            "success" => true,
            "id" => $id
        ]);
    }


    // =====================================================
    // UPDATE VICTIM
    // =====================================================

    if ($action === "update_victim") {

        $id =
            intval($input["id"] ?? 0);

        $condition =
            trim(
                $input["condition_status"]
                ?? "Unknown"
            );

        $rescue =
            trim(
                $input["rescue_status"]
                ?? "Pending"
            );


        $stmt = $conn->prepare(
            "UPDATE victims
             SET
                condition_status = ?,
                rescue_status = ?
             WHERE id = ?"
        );

        $stmt->bind_param(
            "ssi",
            $condition,
            $rescue,
            $id
        );

        if (!$stmt->execute()) {

            response([
                "success" => false,
                "message" => $stmt->error
            ]);
        }

        $stmt->close();


        logActivity(
            $conn,
            "Victim Updated",
            "Victim #$id updated"
        );


        response([
            "success" => true
        ]);
    }


    response([
        "success" => false,
        "message" => "Invalid POST action"
    ]);
}


// =========================================================
// DELETE
// =========================================================

if ($method === "DELETE") {

    $id =
        intval($_GET["id"] ?? 0);


    if ($action === "incident") {

        if ($id <= 0) {

            response([
                "success" => false,
                "message" => "Invalid incident ID"
            ]);
        }


        $stmt = $conn->prepare(
            "DELETE FROM incidents
             WHERE id = ?"
        );

        $stmt->bind_param(
            "i",
            $id
        );


        if (!$stmt->execute()) {

            response([
                "success" => false,
                "message" => $stmt->error
            ]);
        }


        $stmt->close();


        logActivity(
            $conn,
            "Incident Deleted",
            "Incident #$id deleted"
        );


        response([
            "success" => true
        ]);
    }


    if ($action === "victim") {

        if ($id <= 0) {

            response([
                "success" => false,
                "message" => "Invalid victim ID"
            ]);
        }


        $stmt = $conn->prepare(
            "DELETE FROM victims
             WHERE id = ?"
        );

        $stmt->bind_param(
            "i",
            $id
        );

        $stmt->execute();

        $stmt->close();


        logActivity(
            $conn,
            "Victim Deleted",
            "Victim #$id deleted"
        );


        response([
            "success" => true
        ]);
    }


    response([
        "success" => false,
        "message" => "Invalid DELETE action"
    ]);
}


http_response_code(405);

response([
    "success" => false,
    "message" => "Invalid request method"
]);

?>
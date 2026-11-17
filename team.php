<?php

header("Content-Type: application/json");

require_once "db.php";


// ==========================================
// GET TEAMS
// ==========================================

if ($_SERVER["REQUEST_METHOD"] === "GET") {

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


    echo json_encode([
        "success" => true,
        "teams" => $teams
    ]);

    exit;
}


// ==========================================
// POST
// ADD / UPDATE TEAM
// ==========================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $data = json_decode(
        file_get_contents("php://input"),
        true
    );


    if (!is_array($data)) {

        echo json_encode([
            "success" => false,
            "message" => "Invalid JSON"
        ]);

        exit;
    }


    // ======================================
    // UPDATE TEAM STATUS
    // ======================================

    if (
        isset($data["action"]) &&
        $data["action"] === "update_status"
    ) {

        $id = intval(
            $data["id"] ?? 0
        );

        $status = trim(
            $data["status"] ?? "Available"
        );


        if ($id <= 0) {

            echo json_encode([
                "success" => false,
                "message" => "Invalid team ID"
            ]);

            exit;
        }


        $stmt = $conn->prepare("
            UPDATE response_team
            SET status = ?
            WHERE id = ?
        ");

        $stmt->bind_param(
            "si",
            $status,
            $id
        );


        if ($stmt->execute()) {

            echo json_encode([
                "success" => true,
                "message" => "Team status updated"
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


    // ======================================
    // ADD TEAM
    // ======================================

    $name = trim(
        $data["name"] ?? ""
    );

    $role = trim(
        $data["role"] ?? ""
    );

    $phone = trim(
        $data["phone"] ?? ""
    );

    $status = trim(
        $data["status"] ?? "Available"
    );


    if ($name === "") {

        echo json_encode([
            "success" => false,
            "message" => "Team name is required"
        ]);

        exit;
    }


    $stmt = $conn->prepare("
        INSERT INTO response_team
        (
            name,
            role,
            phone,
            status,
            assigned_incident
        )
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
            "message" => "Team added successfully",
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


// ==========================================
// DELETE TEAM
// ==========================================

if ($_SERVER["REQUEST_METHOD"] === "DELETE") {

    $id = intval(
        $_GET["id"] ?? 0
    );


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
            "message" => "Team deleted"
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
    "message" => "Invalid request method"
]);

?>
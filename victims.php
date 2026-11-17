<?php

header("Content-Type: application/json");

require_once __DIR__ . "/auth.php";
requireDashboardLogin();

require_once "db.php";

$method = $_SERVER["REQUEST_METHOD"];


/* =====================================================
   GET VICTIMS
   ===================================================== */

if ($method === "GET") {

    $result = $conn->query("
        SELECT
            id,
            name,
            location,
            condition_status,
            rescue_status,
            notes
        FROM victims
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

    $victims = [];

    while ($row = $result->fetch_assoc()) {
        $victims[] = $row;
    }

    echo json_encode($victims);

    exit;
}


/* =====================================================
   POST - REGISTER VICTIM
   ===================================================== */

if ($method === "POST") {

    $data = json_decode(
        file_get_contents("php://input"),
        true
    );

    $name =
        trim($data["name"] ?? "");

    $location =
        trim($data["location"] ?? "");

    $condition =
        trim(
            $data["condition_status"]
            ?? "Stable"
        );

    $rescue =
        trim(
            $data["rescue_status"]
            ?? "Pending"
        );

    $notes =
        trim(
            $data["notes"]
            ?? ""
        );

    if ($name === "") {

        echo json_encode([
            "success" => false,
            "message" => "Victim name is required"
        ]);

        exit;
    }

    $stmt = $conn->prepare("
        INSERT INTO victims
        (
            name,
            location,
            condition_status,
            rescue_status,
            notes
        )
        VALUES (?, ?, ?, ?, ?)
    ");

    $stmt->bind_param(
        "sssss",
        $name,
        $location,
        $condition,
        $rescue,
        $notes
    );

    if ($stmt->execute()) {

        echo json_encode([
            "success" => true,
            "message" => "Victim registered",
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


/* =====================================================
   PUT - UPDATE VICTIM
   ===================================================== */

if ($method === "PUT") {

    $data = json_decode(
        file_get_contents("php://input"),
        true
    );

    $id = intval($data["id"] ?? 0);

    if ($id <= 0) {

        echo json_encode([
            "success" => false,
            "message" => "Invalid victim ID"
        ]);

        exit;
    }

    $condition =
        trim(
            $data["condition_status"]
            ?? "Stable"
        );

    $rescue =
        trim(
            $data["rescue_status"]
            ?? "Pending"
        );

    $location =
        trim(
            $data["location"]
            ?? ""
        );

    $notes =
        trim(
            $data["notes"]
            ?? ""
        );

    $stmt = $conn->prepare("
        UPDATE victims
        SET
            location = ?,
            condition_status = ?,
            rescue_status = ?,
            notes = ?
        WHERE id = ?
    ");

    $stmt->bind_param(
        "ssssi",
        $location,
        $condition,
        $rescue,
        $notes,
        $id
    );

    if ($stmt->execute()) {

        echo json_encode([
            "success" => true,
            "message" => "Victim updated"
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


/* =====================================================
   DELETE VICTIM
   ===================================================== */

if ($method === "DELETE") {

    $id = intval($_GET["id"] ?? 0);

    $stmt = $conn->prepare(
        "DELETE FROM victims WHERE id = ?"
    );

    $stmt->bind_param(
        "i",
        $id
    );

    if ($stmt->execute()) {

        echo json_encode([
            "success" => true,
            "message" => "Victim deleted"
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

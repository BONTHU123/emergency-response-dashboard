<?php

header("Content-Type: application/json");

require_once "db.php";


// ======================================================
// GET - LOAD ACTIVITY HISTORY
// ======================================================

if ($_SERVER["REQUEST_METHOD"] === "GET") {

    $sql = "
        SELECT
            id,
            activity,
            description,
            created_at
        FROM activity_history
        ORDER BY id DESC
    ";

    $result = $conn->query($sql);

    if (!$result) {

        echo json_encode([
            "success" => false,
            "message" => $conn->error
        ]);

        exit;
    }

    $history = [];

    while ($row = $result->fetch_assoc()) {

        $history[] = [
            "id" => $row["id"],
            "activity" => $row["activity"],
            "description" => $row["description"],
            "date" => $row["created_at"]
        ];
    }

    echo json_encode([
        "success" => true,
        "data" => $history
    ]);

    exit;
}


// ======================================================
// POST - ADD ACTIVITY
// ======================================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $json = file_get_contents("php://input");

    $data = json_decode($json, true);

    if (!is_array($data)) {

        echo json_encode([
            "success" => false,
            "message" => "Invalid JSON data"
        ]);

        exit;
    }

    $activity = trim($data["activity"] ?? "");
    $description = trim($data["description"] ?? "");

    if ($activity === "") {

        echo json_encode([
            "success" => false,
            "message" => "Activity is required"
        ]);

        exit;
    }

    $stmt = $conn->prepare(
        "INSERT INTO activity_history
        (activity, description)
        VALUES (?, ?)"
    );

    if (!$stmt) {

        echo json_encode([
            "success" => false,
            "message" => $conn->error
        ]);

        exit;
    }

    $stmt->bind_param(
        "ss",
        $activity,
        $description
    );

    if ($stmt->execute()) {

        echo json_encode([
            "success" => true,
            "message" => "Activity added successfully",
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


// ======================================================
// INVALID METHOD
// ======================================================

http_response_code(405);

echo json_encode([
    "success" => false,
    "message" => "Invalid request method"
]);

?>
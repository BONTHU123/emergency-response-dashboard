<?php

header("Content-Type: application/json");

require_once "db.php";


// ==========================================
// GET HISTORY
// ==========================================

if ($_SERVER["REQUEST_METHOD"] === "GET") {

    $result = $conn->query("
        SELECT
            id,
            action,
            details,
            created_at
        FROM activity_history
        ORDER BY id DESC
    ");


    if (!$result) {

        echo json_encode([
            "success" => false,
            "message" => $conn->error
        ]);

        exit;
    }


    $history = [];

    while ($row = $result->fetch_assoc()) {
        $history[] = $row;
    }


    echo json_encode([
        "success" => true,
        "history" => $history
    ]);

    exit;
}


http_response_code(405);

echo json_encode([
    "success" => false,
    "message" => "Invalid request method"
]);

?>
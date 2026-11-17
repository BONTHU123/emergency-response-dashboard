
<?php
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . "/auth.php";
requireDashboardLogin();

require_once "db.php";

mysqli_report(MYSQLI_REPORT_OFF);

$method = $_SERVER["REQUEST_METHOD"];

function sendJson($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

function readJson() {
    $raw = file_get_contents("php://input");
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/* GET: LIST RESOURCES */
if ($method === "GET") {
    $sql = "SELECT id, name, quantity FROM resources ORDER BY id ASC";
    $result = $conn->query($sql);

    if (!$result) {
        sendJson([
            "success" => false,
            "message" => "Unable to load resources: " . $conn->error
        ], 500);
    }

    $resources = [];

    while ($row = $result->fetch_assoc()) {
        $resources[] = $row;
    }

    sendJson([
        "success" => true,
        "data" => $resources
    ]);
}

/* POST: ADD, UPDATE QUANTITY, OR DELETE */
if ($method === "POST") {
    $data = readJson();
    $action = strtolower(trim($data["action"] ?? ""));

    /* ADD RESOURCE */
    if ($action === "" || $action === "add") {
        $name = trim($data["name"] ?? "");
        $quantity = filter_var(
            $data["quantity"] ?? 0,
            FILTER_VALIDATE_INT
        );

        if ($name === "") {
            sendJson([
                "success" => false,
                "message" => "Resource name is required"
            ], 400);
        }

        if ($quantity === false || $quantity < 0) {
            sendJson([
                "success" => false,
                "message" => "Quantity must be a non-negative whole number"
            ], 400);
        }

        $stmt = $conn->prepare(
            "INSERT INTO resources (name, quantity) VALUES (?, ?)"
        );

        if (!$stmt) {
            sendJson([
                "success" => false,
                "message" => "Could not prepare resource insert: " . $conn->error
            ], 500);
        }

        $stmt->bind_param("si", $name, $quantity);

        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();

            sendJson([
                "success" => false,
                "message" => "Unable to add resource: " . $error
            ], 500);
        }

        $newId = $conn->insert_id;
        $stmt->close();

        sendJson([
            "success" => true,
            "message" => "Resource added successfully",
            "id" => $newId
        ]);
    }

    /* UPDATE QUANTITY: MATCHES THE CURRENT index.html */
    if ($action === "update") {
        $id = filter_var(
            $data["id"] ?? null,
            FILTER_VALIDATE_INT
        );

        $quantity = filter_var(
            $data["quantity"] ?? null,
            FILTER_VALIDATE_INT
        );

        if ($id === false || $id === null || $id <= 0) {
            sendJson([
                "success" => false,
                "message" => "Invalid resource ID"
            ], 400);
        }

        if ($quantity === false || $quantity === null || $quantity < 0) {
            sendJson([
                "success" => false,
                "message" => "Quantity must be zero or greater"
            ], 400);
        }

        $stmt = $conn->prepare(
            "UPDATE resources SET quantity = ? WHERE id = ?"
        );

        if (!$stmt) {
            sendJson([
                "success" => false,
                "message" => "Could not prepare resource update: " . $conn->error
            ], 500);
        }

        $stmt->bind_param("ii", $quantity, $id);

        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();

            sendJson([
                "success" => false,
                "message" => "Unable to update resource: " . $error
            ], 500);
        }

        $affected = $stmt->affected_rows;
        $stmt->close();

        if ($affected === 0) {
            $check = $conn->prepare(
                "SELECT id FROM resources WHERE id = ?"
            );
            $check->bind_param("i", $id);
            $check->execute();
            $check->store_result();
            $exists = $check->num_rows > 0;
            $check->close();

            if (!$exists) {
                sendJson([
                    "success" => false,
                    "message" => "Resource not found"
                ], 404);
            }
        }

        sendJson([
            "success" => true,
            "message" => "Resource quantity updated successfully"
        ]);
    }

    /* DELETE RESOURCE */
    if ($action === "delete") {
        $id = filter_var(
            $data["id"] ?? null,
            FILTER_VALIDATE_INT
        );

        if ($id === false || $id === null || $id <= 0) {
            sendJson([
                "success" => false,
                "message" => "Invalid resource ID"
            ], 400);
        }

        $stmt = $conn->prepare(
            "DELETE FROM resources WHERE id = ?"
        );

        if (!$stmt) {
            sendJson([
                "success" => false,
                "message" => "Could not prepare resource delete: " . $conn->error
            ], 500);
        }

        $stmt->bind_param("i", $id);

        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();

            sendJson([
                "success" => false,
                "message" => "Unable to delete resource: " . $error
            ], 500);
        }

        $deleted = $stmt->affected_rows;
        $stmt->close();

        if ($deleted === 0) {
            sendJson([
                "success" => false,
                "message" => "Resource not found"
            ], 404);
        }

        sendJson([
            "success" => true,
            "message" => "Resource deleted successfully"
        ]);
    }

    sendJson([
        "success" => false,
        "message" => "Unknown resource action"
    ], 400);
}

/* DELETE METHOD: ALSO SUPPORTS ?id=... */
if ($method === "DELETE") {
    $id = filter_var(
        $_GET["id"] ?? null,
        FILTER_VALIDATE_INT
    );

    if ($id === false || $id === null || $id <= 0) {
        sendJson([
            "success" => false,
            "message" => "Invalid resource ID"
        ], 400);
    }

    $stmt = $conn->prepare(
        "DELETE FROM resources WHERE id = ?"
    );

    if (!$stmt) {
        sendJson([
            "success" => false,
            "message" => "Could not prepare resource delete: " . $conn->error
        ], 500);
    }

    $stmt->bind_param("i", $id);

    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();

        sendJson([
            "success" => false,
            "message" => "Unable to delete resource: " . $error
        ], 500);
    }

    $deleted = $stmt->affected_rows;
    $stmt->close();

    if ($deleted === 0) {
        sendJson([
            "success" => false,
            "message" => "Resource not found"
        ], 404);
    }

    sendJson([
        "success" => true,
        "message" => "Resource deleted successfully"
    ]);
}

sendJson([
    "success" => false,
    "message" => "Method not allowed"
], 405);
?>

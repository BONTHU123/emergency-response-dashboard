
<?php
/*
 * Emergency Response Dashboard
 * File: incidents.php
 * Database: emergency_dashboard
 * MySQL port: 3308
 */

mysqli_report(MYSQLI_REPORT_OFF);

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(204);
    exit;
}

require_once __DIR__ . "/auth.php";
requireDashboardLogin();

require_once __DIR__ . "/db.php";

/*
 * Return JSON and stop execution.
 */
function sendJson($data, $statusCode = 200)
{
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/*
 * Convert a JSON request body into an array.
 */
function getRequestData()
{
    $raw = file_get_contents("php://input");
    $data = json_decode($raw, true);

    return is_array($data) ? $data : [];
}

/*
 * Normalize incident status.
 */
function normalizeStatus($status)
{
    $status = strtolower(trim((string)$status));

    $statuses = [
        "active" => "Active",
        "assigned" => "Assigned",
        "resolved" => "Resolved"
    ];

    return $statuses[$status] ?? null;
}

/*
 * Check the shared database connection.
 */
if (!isset($conn) || $conn->connect_error) {
    error_log(
        "Incidents API database connection failed."
    );

    sendJson([
        "success" => false,
        "message" => "Database connection failed."
    ], 500);
}

$conn->set_charset("utf8mb4");

$method = $_SERVER["REQUEST_METHOD"];

/* =========================================
   GET: LIST INCIDENTS
   ========================================= */

if ($method === "GET") {
    $result = $conn->query(
        "SELECT *
         FROM incidents
         ORDER BY id DESC"
    );

    if (!$result) {
        error_log(
            "Incident query failed: " . $conn->error
        );

        sendJson([
            "success" => false,
            "message" => "Unable to load incidents."
        ], 500);
    }

    $incidents = [];

    while ($row = $result->fetch_assoc()) {
        $incidents[] = $row;
    }

    sendJson($incidents);
}

/* =========================================
   POST: ADD INCIDENT
   ========================================= */

if ($method === "POST") {
    $data = getRequestData();

    $title = trim(
        (string)($data["title"] ??
        $data["incident_type"] ?? "")
    );

    $location = trim(
        (string)($data["location"] ?? "")
    );

    $priority = ucfirst(
        strtolower(trim(
            (string)($data["priority"] ?? "Medium")
        ))
    );

    $status = normalizeStatus(
        $data["status"] ?? "Active"
    );

    $description = trim(
        (string)($data["description"] ?? "")
    );

    $responder = trim(
        (string)($data["responder"] ?? "")
    );

    if ($title === "") {
        sendJson([
            "success" => false,
            "message" => "Incident title is required."
        ], 400);
    }

    if ($location === "") {
        sendJson([
            "success" => false,
            "message" => "Incident location is required."
        ], 400);
    }

    if (!in_array(
        $priority,
        ["Low", "Medium", "High", "Critical"],
        true
    )) {
        sendJson([
            "success" => false,
            "message" => "Invalid incident priority."
        ], 400);
    }

    if ($status === null) {
        sendJson([
            "success" => false,
            "message" => "Invalid incident status."
        ], 400);
    }

    $stmt = $conn->prepare(
        "INSERT INTO incidents
            (title, location, priority, status,
             responder, description)
         VALUES (?, ?, ?, ?, ?, ?)"
    );

    if (!$stmt) {
        error_log(
            "Prepare incident insert failed: " .
            $conn->error
        );

        sendJson([
            "success" => false,
            "message" => "Unable to prepare incident."
        ], 500);
    }

    $stmt->bind_param(
        "ssssss",
        $title,
        $location,
        $priority,
        $status,
        $responder,
        $description
    );

    if (!$stmt->execute()) {
        error_log(
            "Insert incident failed: " .
            $stmt->error
        );

        sendJson([
            "success" => false,
            "message" => "Unable to save incident."
        ], 500);
    }

    $newId = $conn->insert_id;
    $stmt->close();

    sendJson([
        "success" => true,
        "message" => "Incident added successfully.",
        "id" => $newId
    ], 201);
}

/* =========================================
   PUT: UPDATE INCIDENT
   ========================================= */

if ($method === "PUT") {
    $data = getRequestData();

    $id = (int)($data["id"] ?? 0);

    if ($id <= 0) {
        sendJson([
            "success" => false,
            "message" => "A valid incident ID is required."
        ], 400);
    }

    $fields = [];
    $types = "";
    $values = [];

    if (array_key_exists("title", $data)) {
        $title = trim((string)$data["title"]);

        if ($title === "") {
            sendJson([
                "success" => false,
                "message" => "Incident title cannot be empty."
            ], 400);
        }

        $fields[] = "title = ?";
        $types .= "s";
        $values[] = $title;
    }

    if (array_key_exists("location", $data)) {
        $location = trim((string)$data["location"]);

        if ($location === "") {
            sendJson([
                "success" => false,
                "message" => "Location cannot be empty."
            ], 400);
        }

        $fields[] = "location = ?";
        $types .= "s";
        $values[] = $location;
    }

    if (array_key_exists("priority", $data)) {
        $priority = ucfirst(
            strtolower(trim((string)$data["priority"]))
        );

        if (!in_array(
            $priority,
            ["Low", "Medium", "High", "Critical"],
            true
        )) {
            sendJson([
                "success" => false,
                "message" => "Invalid incident priority."
            ], 400);
        }

        $fields[] = "priority = ?";
        $types .= "s";
        $values[] = $priority;
    }

    if (array_key_exists("status", $data)) {
        $status = normalizeStatus($data["status"]);

        if ($status === null) {
            sendJson([
                "success" => false,
                "message" => "Invalid incident status."
            ], 400);
        }

        $fields[] = "status = ?";
        $types .= "s";
        $values[] = $status;

        if ($status === "Resolved") {
            $fields[] = "resolved_at = NOW()";
        } else {
            $fields[] = "resolved_at = NULL";
        }
    }

    if (array_key_exists("responder", $data)) {
        $responder = trim((string)$data["responder"]);

        $fields[] = "responder = ?";
        $types .= "s";
        $values[] = $responder;
    }

    if (array_key_exists("description", $data)) {
        $description = trim(
            (string)$data["description"]
        );

        $fields[] = "description = ?";
        $types .= "s";
        $values[] = $description;
    }

    if (array_key_exists("people_rescued", $data)) {
        $peopleRescued = max(
            0,
            (int)$data["people_rescued"]
        );

        $fields[] = "people_rescued = ?";
        $types .= "i";
        $values[] = $peopleRescued;
    }

    if (count($fields) === 0) {
        sendJson([
            "success" => false,
            "message" => "No supported fields to update."
        ], 400);
    }

    $sql = "UPDATE incidents SET " .
        implode(", ", $fields) .
        " WHERE id = ?";

    $types .= "i";
    $values[] = $id;

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        error_log(
            "Prepare incident update failed: " .
            $conn->error
        );

        sendJson([
            "success" => false,
            "message" => "Unable to prepare incident update."
        ], 500);
    }

    $stmt->bind_param($types, ...$values);

    if (!$stmt->execute()) {
        error_log(
            "Update incident failed: " .
            $stmt->error
        );

        sendJson([
            "success" => false,
            "message" => "Unable to update incident."
        ], 500);
    }

    if ($stmt->affected_rows === 0) {
        $check = $conn->prepare(
            "SELECT id FROM incidents WHERE id = ?"
        );
        $check->bind_param("i", $id);
        $check->execute();
        $exists = $check->get_result()->num_rows > 0;
        $check->close();

        if (!$exists) {
            $stmt->close();

            sendJson([
                "success" => false,
                "message" => "Incident not found."
            ], 404);
        }
    }

    $stmt->close();

    sendJson([
        "success" => true,
        "message" => "Incident updated successfully."
    ]);
}

/* =========================================
   DELETE: DELETE INCIDENT
   ========================================= */

if ($method === "DELETE") {
    $id = (int)($_GET["id"] ?? 0);

    if ($id <= 0) {
        $data = getRequestData();
        $id = (int)($data["id"] ?? 0);
    }

    if ($id <= 0) {
        sendJson([
            "success" => false,
            "message" => "A valid incident ID is required."
        ], 400);
    }

    $stmt = $conn->prepare(
        "DELETE FROM incidents WHERE id = ?"
    );

    if (!$stmt) {
        error_log(
            "Prepare incident delete failed: " .
            $conn->error
        );

        sendJson([
            "success" => false,
            "message" => "Unable to prepare incident deletion."
        ], 500);
    }

    $stmt->bind_param("i", $id);

    if (!$stmt->execute()) {
        error_log(
            "Delete incident failed: " .
            $stmt->error
        );

        sendJson([
            "success" => false,
            "message" => "Unable to delete incident."
        ], 500);
    }

    $deleted = $stmt->affected_rows;
    $stmt->close();

    if ($deleted === 0) {
        sendJson([
            "success" => false,
            "message" => "Incident not found."
        ], 404);
    }

    sendJson([
        "success" => true,
        "message" => "Incident deleted successfully."
    ]);
}

/* =========================================
   UNSUPPORTED REQUEST
   ========================================= */

sendJson([
    "success" => false,
    "message" => "Unsupported request method."
], 405);

<?php
/* Shared session and JSON authentication guard for dashboard endpoints. */
if (session_status() !== PHP_SESSION_ACTIVE) {
    $https = isset($_SERVER["HTTPS"]) && strtolower((string)$_SERVER["HTTPS"]) !== "off";
    session_set_cookie_params([
        "lifetime" => 0,
        "path" => "/emergency-dashboard",
        "secure" => $https,
        "httponly" => true,
        "samesite" => "Lax"
    ]);
    session_start();
}

function requireDashboardLogin() {
    if (!isset($_SESSION["dashboard_user"]["id"])) {
        http_response_code(401);
        header("Content-Type: application/json; charset=utf-8");
        echo json_encode([
            "success" => false,
            "message" => "Please sign in to use the emergency dashboard."
        ]);
        exit;
    }
}

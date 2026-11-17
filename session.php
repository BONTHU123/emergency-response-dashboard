<?php
require_once __DIR__ . "/auth.php";
header("Content-Type: application/json; charset=utf-8");
echo json_encode([
    "success" => true,
    "authenticated" => isset($_SESSION["dashboard_user"]["id"]),
    "user" => $_SESSION["dashboard_user"] ?? null
]);

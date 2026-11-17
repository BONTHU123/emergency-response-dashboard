<?php
require_once "db.php";

if($_SERVER["REQUEST_METHOD"]!=="GET"){
    jsonResponse(["success"=>false,"message"=>"GET only"],405);
}

jsonResponse([
    "success"=>true,
    "system"=>"Online",
    "database"=>"Connected",
    "server"=>php_uname("s"),
    "php_version"=>PHP_VERSION,
    "time"=>date("Y-m-d H:i:s")
]);
?>
<?php
    $host = "localhost";
    // $host = ["localhost", "ho-cad-exactdb"];
    $username = "root";
    // $username = ["root", "mlcad"];
    $password = "Password1";
    // $password = ["Password1", "CADMLhuillier2023"];
    $database = ["mldb", "masterdata", "support_ticket"];

    date_default_timezone_set('Asia/Manila');

    $connections = [];
    foreach ($database as $db) {
        $connections[] = mysqli_connect($host, $username, $password, $db);
    }

    // keep original variable names for compatibility
    list($conn, $conn1) = $connections;

    // check connections
    foreach ($connections as $i => $connection) {
        if (!$connection) {
            $failedDb = $database[$i];
            die("Connection to '{$failedDb}' failed: " . mysqli_connect_error());
        }
    }

?>
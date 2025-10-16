<?php
require("../../user/layout/Session.php");
require("../../config/db.php");

try {
    // Validate params
    $id   = intval($_GET['id'] ?? 0);
    $part = strtolower($_GET['data'] ?? '');

    if ($id <= 0 || !in_array($part, ['a', 'b'])) {
        throw new Exception("Invalid request.");
    }

    // Fetch firmware row
    $sql = "SELECT data_a, data_b, version, id 
            FROM firmware 
            WHERE id = $id AND is_deleted = 0 AND type = 'ble' 
            LIMIT 1";
    $res = $conn->query($sql);

    if (!$res || $res->num_rows === 0) {
        throw new Exception("Firmware not found.");
    }

    $row = $res->fetch_assoc();
    $conn->close();

    // Decide which binary blob to serve
    $binData = $part === 'a' ? $row['data_a'] : $row['data_b'];
    if (empty($binData)) {
        throw new Exception("No binary data found for Part " . strtoupper($part));
    }

    // Set headers for download
    $filename = "firmware_" . $row['id'] . "_part_" . $part . ".bin";
    header("Content-Type: application/octet-stream");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Content-Length: " . strlen($binData));

    // Output the binary data
    echo $binData;
    exit;

} catch (Exception $e) {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
    header("HTTP/1.1 400 Bad Request");
    echo "Error: " . htmlspecialchars($e->getMessage());
    exit;
}

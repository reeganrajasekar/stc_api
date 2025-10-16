<?php
require("../../user/layout/Session.php");
require("../../config/db.php");

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['csv_file'])) {
        throw new Exception("Invalid request.");
    }

    if ($_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("File upload error. Please try again.");
    }

    $file = $_FILES['csv_file']['tmp_name'];
    $handle = fopen($file, "r");
    if ($handle === false) {
        throw new Exception("Failed to open uploaded file.");
    }

    $product_id = intval($_POST['product_id'] ?? 0);
    if ($product_id <= 0) {
        throw new Exception("Invalid product selected.");
    }

    // Skip header row
    fgetcsv($handle, 1000, ",", '"', "\\");

    $inserted = 0;
    while (($row = fgetcsv($handle, 1000, ",", '"', "\\")) !== FALSE) {
        // Expect: title,uin,mac,wifi_version,ble_version,registration_user_limit
        if (count($row) < 6) continue;

        $title    = $conn->real_escape_string(trim($row[0]));
        $uin      = $conn->real_escape_string(trim($row[1]));
        $mac      = $conn->real_escape_string(trim($row[2]));
        $wifi     = $conn->real_escape_string(trim($row[3]));
        $ble      = $conn->real_escape_string(trim($row[4]));
        $regLimit = intval($row[5]);

        $sql = "INSERT INTO devices 
                    (title, uin, mac, wifi_version, ble_version, registration_user_limit, alarm, product_id, is_deleted)
                VALUES 
                    ('$title', '$uin', '$mac', '$wifi', '$ble', '$regLimit', 1, '$product_id', 0)";

        if ($conn->query($sql)) {
            $inserted++;
        }
    }

    fclose($handle);
    $conn->close();

    header("Location: /admin/user/settings/devices.php?msg=Uploaded $inserted devices successfully");
    exit;

} catch (Exception $e) {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
    header("Location: /admin/user/settings/devices.php?err=" . urlencode($e->getMessage()));
    exit;
}

<?php
require("../../user/layout/Session.php");
require("../../config/db.php");

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Invalid request.");
    }

    $product_id  = intval($_POST['product_id'] ?? 0);
    $version     = trim($_POST['version'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($product_id <= 0 || empty($version) || empty($description)) {
        throw new Exception("All fields are required.");
    }

    // Validate file uploads
    if (!isset($_FILES['bin_a']) || !isset($_FILES['bin_b'])) {
        throw new Exception("Both firmware files are required.");
    }

    if ($_FILES['bin_a']['error'] !== UPLOAD_ERR_OK || $_FILES['bin_b']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("File upload error.");
    }

    // Read binary data
    $data_a = file_get_contents($_FILES['bin_a']['tmp_name']);
    $data_b = file_get_contents($_FILES['bin_b']['tmp_name']);

    if (!$data_a || !$data_b) {
        throw new Exception("Failed to read uploaded files.");
    }

    // Escape binary data for MySQL
    $data_a = $conn->real_escape_string($data_a);
    $data_b = $conn->real_escape_string($data_b);

    // Insert into firmware table
    $sql = "INSERT INTO firmware (product_id, version, description, type, data_a, data_b, is_deleted, created_at)
            VALUES ('$product_id', '$version', '$description', 'ble', '$data_a', '$data_b', 0, NOW())";

    if (!$conn->query($sql)) {
        throw new Exception("Database error: " . $conn->error);
    }

    $conn->close();
    header("Location: /admin/user/firmware/ble.php?msg=Firmware uploaded successfully");
    exit;

} catch (Exception $e) {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
    header("Location: /admin/user/firmware/ble.php?err=" . urlencode($e->getMessage()));
    exit;
}

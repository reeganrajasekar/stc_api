<?php
require("../../user/layout/Session.php");
require("../../config/db.php");

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception("Invalid request.");
    }

    $id = intval($_GET['id'] ?? 0);
    if ($id <= 0) {
        throw new Exception("Invalid firmware ID.");
    }

    // 1. Get the product_id for this firmware
    $res = $conn->query("SELECT product_id FROM firmware WHERE id = $id LIMIT 1");
    if (!$res || $res->num_rows === 0) {
        throw new Exception("Firmware not found.");
    }
    $row = $res->fetch_assoc();
    $product_id = intval($row['product_id']);

    // 2. Count how many firmware records exist for this product
    $resCount = $conn->query("SELECT COUNT(*) AS cnt FROM firmware WHERE product_id = $product_id AND type = 'ble'");
    $countRow = $resCount->fetch_assoc();
    $totalFirmware = intval($countRow['cnt']);

    if ($totalFirmware <= 1) {
        throw new Exception("Cannot delete this firmware. Product must have at least one firmware.");
    }

    // 3. Hard delete firmware
    $sql = "DELETE FROM firmware WHERE id = $id LIMIT 1";
    if (!$conn->query($sql)) {
        throw new Exception("Database error: " . $conn->error);
    }

    $conn->close();
    header("Location: /admin/user/firmware/ble.php?msg=Firmware deleted successfully");
    exit;

} catch (Exception $e) {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
    header("Location: /admin/user/firmware/ble.php?err=" . urlencode($e->getMessage()));
    exit;
}
?>

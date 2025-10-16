<?php
require("./config/db.php");
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: /admin/?err=Invalid request.");
    exit;
}

$email = trim($_POST["email"] ?? '');
$password = $_POST["password"] ?? '';

// Validate input
if (empty($email) || empty($password)) {
    header("Location: /admin/?err=Email and password are required.");
    exit;
}

// Use prepared statement
$stmt = $conn->prepare("SELECT id, username, password_hash, admin_role, full_name, is_active FROM admin_users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $row = $result->fetch_assoc();

    // Check if user is active
    if (!$row['is_active']) {
        $stmt->close();
        $conn->close();
        header("Location: /admin/?err=Account is deactivated.");
        exit;
    }

    if (password_verify($password, $row['password_hash'])) {
        // Secure session
        session_regenerate_id(true);

        $_SESSION['user'] = $row['username'];
        $_SESSION['user_id'] = $row['id'];
        $_SESSION['role'] = $row['admin_role'];
        $_SESSION['full_name'] = $row['full_name'];
        $_SESSION['lock'] = "1";
 
        $updateStmt = $conn->prepare("UPDATE admin_users SET last_login = CURRENT_TIMESTAMP WHERE id = ?");
        $updateStmt->bind_param("i", $row['id']);
        $updateStmt->execute();
        $updateStmt->close();

        $stmt->close();
        $conn->close();

        header("Location: /admin/user?msg=Welcome+" . urlencode($row['full_name']));
        exit;
    } else {
        $stmt->close();
        $conn->close();
        header("Location: /admin/?err=Incorrect password.");
        exit;
    }
} else {
    $stmt->close();
    $conn->close();
    header("Location: /admin/?err=User not found.");
    exit;
}

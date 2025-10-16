<?php
require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../../config/Env.php';

$conn = new mysqli($_ENV['DB_HOST'], $_ENV['DB_USER'], $_ENV['DB_PASS'], $_ENV['DB_NAME']);
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

function test_input($data)
{
  $data = trim($data);
  $data = stripslashes($data);
  $data = htmlspecialchars($data);
  return $data;
}

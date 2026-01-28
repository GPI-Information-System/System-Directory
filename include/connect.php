<?php
$db_host      = '';
$db_user      = '';
$db_database  = '';
$db_pass      = '';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_database);
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

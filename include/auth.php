<?php
require 'connect.php';
session_start();
date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['SESS_USERNAME'])) {
  header('location: ../index.php');
  exit();
} else {
  // Retrieve session ariables
  $username = $_SESSION['SESS_USERNAME'];
  $password = $_SESSION['SESS_PASSWORD'];
  $fullname = $_SESSION['SESS_FULLNAME'];
  $syslevel = $_SESSION['SESS_LEVEL'];
}

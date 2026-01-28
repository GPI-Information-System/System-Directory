<?php
include 'connect.php';

$username = strtolower(trim($_POST['user_email']));
$password = trim($_POST['password']);

// Check in users table
$query = "SELECT * FROM tbl_users WHERE username = '$username'";
$result = mysqli_query($conn, $query);

if (mysqli_num_rows($result) > 0) {
  $user = mysqli_fetch_assoc($result);
  if (password_verify($password, $user['password'])) {
    session_start();
    $_SESSION['SESS_USERNAME']    = $username;
    $_SESSION['SESS_PASSWORD']    = $password;
    $_SESSION['SESS_FULLNAME']    = $user['fullname'];
    $_SESSION['SESS_LEVEL']       = $user['level'];
    $_SESSION['SESS_ID']          = $user['id'];
    session_write_close();
    echo "Success";
  } else {
    echo "Invalid";
  }
} else {
  echo "NA";
}

mysqli_close($conn);

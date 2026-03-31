<?php

require_once '../config/session.php';

// Destroy all session data
session_unset();
session_destroy();

// Redirect to login page
header('Location: ../index.php');
exit();
?>
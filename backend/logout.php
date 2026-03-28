<?php
// Use config/session.php so the correct session path is set
// before we attempt to destroy the session.
// Direct session_start() won't work after the session path change.
require_once '../config/session.php';

// Destroy all session data
session_unset();
session_destroy();

// Redirect to login page
header('Location: ../index.php');
exit();
?>
<?php
session_start(); // Start the session
session_destroy();
header("Location: login.php");
exit();

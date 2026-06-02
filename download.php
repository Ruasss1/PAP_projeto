<?php
session_start();
require_once __DIR__ . '/includes/auth_middleware.php';
header('Location: /export.php');
exit;

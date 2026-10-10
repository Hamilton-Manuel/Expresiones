<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
startWebSession();
header('Content-Type: text/html; charset=UTF-8');
$csrf = $_SESSION['csrf'];
session_write_close();
require dirname(__DIR__) . '/views/laboratory.php';

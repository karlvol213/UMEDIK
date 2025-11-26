<?php
// Proxy loader so requests to /admin/admin_unlock_user.php work
// We chdir to project root so relative includes in the original file resolve correctly
chdir(__DIR__ . '/..');
require_once __DIR__ . '/../admin_unlock_user.php';

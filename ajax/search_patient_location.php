<?php
require_once '../includes/config.php';
require_once '../functions/ward_functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

if (!isset($_POST['search']) || empty(trim($_POST['search']))) {
    echo json_encode([]);
    exit();
}

$search_term = trim($_POST['search']);

try {
    $patients = searchPatientsLocation($conn, $search_term);
    echo json_encode($patients);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Search failed: ' . $e->getMessage()]);
}
?>
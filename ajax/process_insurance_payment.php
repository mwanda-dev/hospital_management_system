<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';

// Ensure user is logged in and is a patient
checkAuth();
if (!isPatient()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit();
}

header('Content-Type: application/json');

$patient_id = $_SESSION['user_id'];

$invoice_id = isset($_POST['invoice_id']) ? intval($_POST['invoice_id']) : 0;
if ($invoice_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid invoice ID']);
    exit();
}

// Fetch patient insurance info
$sql = "SELECT insurance_provider, insurance_policy_number FROM patients WHERE patient_id = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $patient_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows !== 1) {
    echo json_encode(['success' => false, 'message' => 'Patient not found']);
    exit();
}
$patient = $res->fetch_assoc();

if (empty(trim($patient['insurance_provider'])) || empty(trim($patient['insurance_policy_number']))) {
    echo json_encode(['success' => false, 'message' => 'Insurance provider or policy number missing. Please add your insurance information in your profile.']);
    exit();
}

// Fetch invoice and ensure it belongs to this patient
$sql = "SELECT invoice_id, total_amount, paid_amount, status FROM billing WHERE invoice_id = ? AND patient_id = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param('ii', $invoice_id, $patient_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows !== 1) {
    echo json_encode(['success' => false, 'message' => 'Invoice not found']);
    exit();
}
$invoice = $res->fetch_assoc();

if ($invoice['status'] === 'paid') {
    echo json_encode(['success' => true, 'message' => 'Invoice already marked paid']);
    exit();
}

// Update invoice: mark as paid by insurance
$new_paid_amount = $invoice['total_amount'];
$payment_details = "Insurance: " . $conn->real_escape_string($patient['insurance_provider']) . " | Policy: " . $conn->real_escape_string($patient['insurance_policy_number']);

$update_sql = "UPDATE billing SET paid_amount = ?, status = 'paid', payment_method = 'insurance', payment_details = ? WHERE invoice_id = ? AND patient_id = ?";
$update_stmt = $conn->prepare($update_sql);
$update_stmt->bind_param('dsii', $new_paid_amount, $payment_details, $invoice_id, $patient_id);

if ($update_stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Invoice successfully marked as paid via insurance']);
    exit();
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update invoice: ' . $conn->error]);
    exit();
}

?>

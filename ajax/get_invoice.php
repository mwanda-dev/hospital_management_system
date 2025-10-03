<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';

// Ensure user is logged in and is a patient
requireAuth();
if (!isPatient()) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit();
}

header('Content-Type: application/json');

$patient_id = $_SESSION['user_id'];

$invoice_id = 0;
if (isset($_GET['invoice_id'])) {
    $invoice_id = intval($_GET['invoice_id']);
} elseif (isset($_POST['invoice_id'])) {
    $invoice_id = intval($_POST['invoice_id']);
}

if ($invoice_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid invoice ID']);
    exit();
}

// Fetch invoice header
$sql = "SELECT invoice_id, patient_id, invoice_date, due_date, total_amount, paid_amount, status, payment_method, payment_details, notes FROM billing WHERE invoice_id = ? AND patient_id = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param('ii', $invoice_id, $patient_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows !== 1) {
    echo json_encode(['success' => false, 'message' => 'Invoice not found']);
    exit();
}
$invoice = $res->fetch_assoc();

// Fetch invoice items if table exists
$items = [];
$items_sql = "SELECT item_id, description, quantity, unit_price FROM billing_items WHERE invoice_id = ?";
if ($items_stmt = $conn->prepare($items_sql)) {
    $items_stmt->bind_param('i', $invoice_id);
    $items_stmt->execute();
    $items_res = $items_stmt->get_result();
    while ($it = $items_res->fetch_assoc()) {
        $items[] = $it;
    }
}

echo json_encode(['success' => true, 'invoice' => $invoice, 'items' => $items]);
exit();
?>

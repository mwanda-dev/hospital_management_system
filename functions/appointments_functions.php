 
<?php
/**
 * Appointments Functions for Hospital Management System
 */

/**
 * Get all appointments with patient and doctor information
 * 
 * @param mysqli $conn Database connection
 * @param string $status Filter by status (optional)
 * @param string $date Filter by date (optional)
 * @return mysqli_result Result set of appointments
 */
function getAllAppointments($conn, $status = null, $date = null) {
    $query = "
        SELECT a.*, 
               p.first_name as patient_first, p.last_name as patient_last,
               d.first_name as doctor_first, d.last_name as doctor_last, d.specialization
        FROM appointments a
        JOIN patients p ON a.patient_id = p.patient_id
        JOIN users d ON a.doctor_id = d.user_id
    ";
    
    $conditions = [];
    $params = [];
    $types = '';
    
    if ($status) {
        $conditions[] = "a.status = ?";
        $params[] = $status;
        $types .= 's';
    }
    
    if ($date) {
        $conditions[] = "a.appointment_date = ?";
        $params[] = $date;
        $types .= 's';
    }
    
    if (!empty($conditions)) {
        $query .= " WHERE " . implode(' AND ', $conditions);
    }
    
    $query .= " ORDER BY a.appointment_date DESC, a.start_time DESC";
    
    $stmt = $conn->prepare($query);
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    return $stmt->get_result();
}

/**
 * Get a single appointment by ID
 * 
 * @param mysqli $conn Database connection
 * @param int $appointment_id Appointment ID
 * @return array|null Appointment data or null if not found
 */
function getAppointmentById($conn, $appointment_id) {
    $stmt = $conn->prepare("
        SELECT a.*, 
               p.first_name as patient_first, p.last_name as patient_last,
               d.first_name as doctor_first, d.last_name as doctor_last, d.specialization
        FROM appointments a
        JOIN patients p ON a.patient_id = p.patient_id
        JOIN users d ON a.doctor_id = d.user_id
        WHERE a.appointment_id = ?
    ");
    $stmt->bind_param("i", $appointment_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

/**
 * Add a new appointment
 * 
 * @param mysqli $conn Database connection
 * @param array $data Appointment data
 * @return int|false Inserted ID or false on failure
 */
function addAppointment($conn, $data) {
    $stmt = $conn->prepare("
        INSERT INTO appointments (
            patient_id, doctor_id, appointment_date, start_time, end_time, 
            purpose, status, notes, created_by
        ) VALUES (?, ?, ?, ?, ?, ?, 'scheduled', ?, ?)
    ");
    
    $stmt->bind_param(
        "iisssssi",
        $data['patient_id'],
        $data['doctor_id'],
        $data['appointment_date'],
        $data['start_time'],
        $data['end_time'],
        $data['purpose'],
        $data['notes'],
        $data['created_by']
    );
    
    if ($stmt->execute()) {
        return $conn->insert_id;
    }
    return false;
}

/**
 * Update an existing appointment
 * 
 * @param mysqli $conn Database connection
 * @param array $data Appointment data
 * @return bool True on success, false on failure
 */
function updateAppointment($conn, $data) {
    $stmt = $conn->prepare("
        UPDATE appointments SET 
            patient_id = ?,
            doctor_id = ?,
            appointment_date = ?,
            start_time = ?,
            end_time = ?,
            purpose = ?,
            status = ?,
            notes = ?
        WHERE appointment_id = ?
    ");
    
    $stmt->bind_param(
        "iissssssi",
        $data['patient_id'],
        $data['doctor_id'],
        $data['appointment_date'],
        $data['start_time'],
        $data['end_time'],
        $data['purpose'],
        $data['status'],
        $data['notes'],
        $data['appointment_id']
    );
    
    return $stmt->execute();
}

/**
 * Delete an appointment
 * 
 * @param mysqli $conn Database connection
 * @param int $appointment_id Appointment ID
 * @return bool True on success, false on failure
 */
function deleteAppointment($conn, $appointment_id) {
    $stmt = $conn->prepare("DELETE FROM appointments WHERE appointment_id = ?");
    $stmt->bind_param("i", $appointment_id);
    return $stmt->execute();
}

/**
 * Change appointment status
 * 
 * @param mysqli $conn Database connection
 * @param int $appointment_id Appointment ID
 * @param string $status New status
 * @return bool True on success, false on failure
 */
function changeAppointmentStatus($conn, $appointment_id, $status) {
    $stmt = $conn->prepare("UPDATE appointments SET status = ? WHERE appointment_id = ?");
    $stmt->bind_param("si", $status, $appointment_id);
    return $stmt->execute();
}

/**
 * Get available time slots for a doctor on a specific date
 * 
 * @param mysqli $conn Database connection
 * @param int $doctor_id Doctor ID
 * @param string $date Date in Y-m-d format
 * @return array Array of available time slots
 */
function getAvailableTimeSlots($conn, $doctor_id, $date) {
    // Default working hours (9am-5pm)
    $start_time = strtotime('09:00');
    $end_time = strtotime('17:00');
    $interval = 30 * 60; // 30 minutes in seconds
    
    // Get existing appointments for this doctor on this date
    $stmt = $conn->prepare("
        SELECT start_time, end_time 
        FROM appointments 
        WHERE doctor_id = ? AND appointment_date = ? AND status != 'canceled'
    ");
    $stmt->bind_param("is", $doctor_id, $date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $booked_slots = [];
    while ($row = $result->fetch_assoc()) {
        $booked_slots[] = [
            'start' => strtotime($row['start_time']),
            'end' => strtotime($row['end_time'])
        ];
    }
    
    // Generate all possible slots
    $all_slots = [];
    for ($time = $start_time; $time < $end_time; $time += $interval) {
        $all_slots[] = $time;
    }
    
    // Filter out booked slots
    $available_slots = [];
    foreach ($all_slots as $slot) {
        $is_available = true;
        foreach ($booked_slots as $booked) {
            if ($slot >= $booked['start'] && $slot < $booked['end']) {
                $is_available = false;
                break;
            }
        }
        if ($is_available) {
            $available_slots[] = date('H:i', $slot);
        }
    }
    
    return $available_slots;
}
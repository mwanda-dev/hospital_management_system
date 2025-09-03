<?php
/**
 * Ward and Bed Management Functions for Hospital Management System
 */

/**
 * Get all wards with bed statistics
 * 
 * @param mysqli $conn Database connection
 * @param int $page Page number for pagination
 * @param int $per_page Records per page
 * @return array Array containing wards data and pagination info
 */
function getWardsWithStats($conn, $page = 1, $per_page = 10) {
    $offset = ($page - 1) * $per_page;
    
    // Get total count for pagination
    $count_query = "SELECT COUNT(*) as total FROM wards";
    $count_result = $conn->query($count_query);
    $total_records = $count_result->fetch_assoc()['total'];
    
    // Get wards with bed statistics
    $query = "
        SELECT w.*, 
               COUNT(b.bed_id) as total_beds,
               SUM(CASE WHEN b.status = 'occupied' THEN 1 ELSE 0 END) as occupied_beds,
               SUM(CASE WHEN b.status = 'available' THEN 1 ELSE 0 END) as available_beds,
               SUM(CASE WHEN b.status = 'maintenance' THEN 1 ELSE 0 END) as maintenance_beds
        FROM wards w
        LEFT JOIN beds b ON w.ward_id = b.ward_id
        GROUP BY w.ward_id
        ORDER BY w.ward_name
        LIMIT ? OFFSET ?
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $per_page, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $wards = [];
    while ($row = $result->fetch_assoc()) {
        $wards[] = $row;
    }
    
    return [
        'wards' => $wards,
        'total_records' => $total_records,
        'total_pages' => ceil($total_records / $per_page),
        'current_page' => $page,
        'per_page' => $per_page
    ];
}

/**
 * Get a single ward by ID
 * 
 * @param mysqli $conn Database connection
 * @param int $ward_id Ward ID
 * @return array|null Ward data or null if not found
 */
function getWardById($conn, $ward_id) {
    $stmt = $conn->prepare("SELECT * FROM wards WHERE ward_id = ?");
    $stmt->bind_param("i", $ward_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

/**
 * Add a new ward and create its beds
 * 
 * @param mysqli $conn Database connection
 * @param array $data Ward data
 * @param int $user_id User ID for audit logging
 * @return int|false Inserted ward ID or false on failure
 */
function addWard($conn, $data, $user_id) {
    $conn->begin_transaction();
    
    try {
        // Insert ward
        $stmt = $conn->prepare("
            INSERT INTO wards (ward_name, ward_type, capacity, charge_per_day) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->bind_param("ssid", $data['ward_name'], $data['ward_type'], $data['capacity'], $data['charge_per_day']);
        $stmt->execute();
        
        $ward_id = $conn->insert_id;
        
        // Create beds for the ward
        createBedsForWard($conn, $ward_id, $data['capacity']);
        
        // Log the action
        logAuditAction($conn, $user_id, 'CREATE', 'wards', $ward_id, null, json_encode($data));
        
        $conn->commit();
        return $ward_id;
        
    } catch (Exception $e) {
        $conn->rollback();
        return false;
    }
}

/**
 * Update an existing ward
 * 
 * @param mysqli $conn Database connection
 * @param array $data Ward data including ward_id
 * @param int $user_id User ID for audit logging
 * @return bool True on success, false on failure
 */
function updateWard($conn, $data, $user_id) {
    $conn->begin_transaction();
    
    try {
        // Get old values for audit
        $old_ward = getWardById($conn, $data['ward_id']);
        
        // Update ward
        $stmt = $conn->prepare("
            UPDATE wards SET 
                ward_name = ?,
                ward_type = ?,
                capacity = ?,
                charge_per_day = ?
            WHERE ward_id = ?
        ");
        $stmt->bind_param("ssidi", $data['ward_name'], $data['ward_type'], 
                          $data['capacity'], $data['charge_per_day'], $data['ward_id']);
        $stmt->execute();
        
        // If capacity changed, adjust beds
        if ($old_ward['capacity'] != $data['capacity']) {
            adjustWardBeds($conn, $data['ward_id'], $data['capacity']);
        }
        
        // Log the action
        logAuditAction($conn, $user_id, 'UPDATE', 'wards', $data['ward_id'], 
                       json_encode($old_ward), json_encode($data));
        
        $conn->commit();
        return true;
        
    } catch (Exception $e) {
        $conn->rollback();
        return false;
    }
}

/**
 * Delete a ward and all its beds
 * 
 * @param mysqli $conn Database connection
 * @param int $ward_id Ward ID
 * @param int $user_id User ID for audit logging
 * @return bool True on success, false on failure
 */
function deleteWard($conn, $ward_id, $user_id) {
    $conn->begin_transaction();
    
    try {
        // Check if ward has any occupied beds
        $occupied_check = $conn->prepare("
            SELECT COUNT(*) as occupied_count 
            FROM beds 
            WHERE ward_id = ? AND status = 'occupied'
        ");
        $occupied_check->bind_param("i", $ward_id);
        $occupied_check->execute();
        $result = $occupied_check->get_result();
        
        if ($result->fetch_assoc()['occupied_count'] > 0) {
            throw new Exception("Cannot delete ward with occupied beds");
        }
        
        // Get old values for audit
        $old_ward = getWardById($conn, $ward_id);
        
        // Delete beds first
        $stmt = $conn->prepare("DELETE FROM beds WHERE ward_id = ?");
        $stmt->bind_param("i", $ward_id);
        $stmt->execute();
        
        // Delete ward
        $stmt = $conn->prepare("DELETE FROM wards WHERE ward_id = ?");
        $stmt->bind_param("i", $ward_id);
        $stmt->execute();
        
        // Log the action
        logAuditAction($conn, $user_id, 'DELETE', 'wards', $ward_id, json_encode($old_ward), null);
        
        $conn->commit();
        return true;
        
    } catch (Exception $e) {
        $conn->rollback();
        return false;
    }
}

/**
 * Create beds for a ward
 * 
 * @param mysqli $conn Database connection
 * @param int $ward_id Ward ID
 * @param int $capacity Number of beds to create
 * @return bool True on success, false on failure
 */
function createBedsForWard($conn, $ward_id, $capacity) {
    for ($i = 1; $i <= $capacity; $i++) {
        $bed_number = str_pad($i, 2, '0', STR_PAD_LEFT);
        $stmt = $conn->prepare("INSERT INTO beds (ward_id, bed_number, status) VALUES (?, ?, 'available')");
        $stmt->bind_param("is", $ward_id, $bed_number);
        
        if (!$stmt->execute()) {
            return false;
        }
    }
    return true;
}

/**
 * Adjust ward beds when capacity changes
 * 
 * @param mysqli $conn Database connection
 * @param int $ward_id Ward ID
 * @param int $new_capacity New capacity
 * @return bool True on success, false on failure
 */
function adjustWardBeds($conn, $ward_id, $new_capacity) {
    // Get current bed count
    $current_beds = $conn->prepare("SELECT COUNT(*) as count FROM beds WHERE ward_id = ?");
    $current_beds->bind_param("i", $ward_id);
    $current_beds->execute();
    $current_count = $current_beds->get_result()->fetch_assoc()['count'];
    
    if ($new_capacity > $current_count) {
        // Add beds
        for ($i = $current_count + 1; $i <= $new_capacity; $i++) {
            $bed_number = str_pad($i, 2, '0', STR_PAD_LEFT);
            $stmt = $conn->prepare("INSERT INTO beds (ward_id, bed_number, status) VALUES (?, ?, 'available')");
            $stmt->bind_param("is", $ward_id, $bed_number);
            $stmt->execute();
        }
    } elseif ($new_capacity < $current_count) {
        // Remove beds (only available ones)
        $stmt = $conn->prepare("
            DELETE FROM beds 
            WHERE ward_id = ? AND status = 'available' 
            ORDER BY bed_number DESC 
            LIMIT ?
        ");
        $to_remove = $current_count - $new_capacity;
        $stmt->bind_param("ii", $ward_id, $to_remove);
        $stmt->execute();
    }
    
    return true;
}

/**
 * Get beds with detailed information
 * 
 * @param mysqli $conn Database connection
 * @param int $page Page number for pagination
 * @param int $per_page Records per page
 * @param array $filters Optional filters
 * @return array Array containing beds data and pagination info
 */
function getBedsWithDetails($conn, $page = 1, $per_page = 20, $filters = []) {
    $offset = ($page - 1) * $per_page;
    
    // Build where clause
    $where_conditions = [];
    $params = [];
    $param_types = '';
    
    if (!empty($filters['ward_id'])) {
        $where_conditions[] = "b.ward_id = ?";
        $params[] = $filters['ward_id'];
        $param_types .= 'i';
    }
    
    if (!empty($filters['status'])) {
        $where_conditions[] = "b.status = ?";
        $params[] = $filters['status'];
        $param_types .= 's';
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // Get total count
    $count_query = "
        SELECT COUNT(*) as total 
        FROM beds b
        JOIN wards w ON b.ward_id = w.ward_id
        $where_clause
    ";
    
    if (!empty($params)) {
        $count_stmt = $conn->prepare($count_query);
        $count_stmt->bind_param($param_types, ...$params);
        $count_stmt->execute();
        $count_result = $count_stmt->get_result();
    } else {
        $count_result = $conn->query($count_query);
    }
    
    $total_records = $count_result->fetch_assoc()['total'];
    
    // Get beds with details
    $query = "
        SELECT b.*, w.ward_name, w.ward_type,
               p.first_name as patient_first, p.last_name as patient_last, p.patient_id,
               a.admission_date, a.admission_id, a.reason,
               u.first_name as doctor_first, u.last_name as doctor_last
        FROM beds b
        JOIN wards w ON b.ward_id = w.ward_id
        LEFT JOIN admissions a ON b.bed_id = a.bed_id AND a.status = 'admitted'
        LEFT JOIN patients p ON a.patient_id = p.patient_id
        LEFT JOIN users u ON a.admitting_doctor_id = u.user_id
        $where_clause
        ORDER BY w.ward_name, b.bed_number
        LIMIT ? OFFSET ?
    ";
    
    // Add pagination parameters
    $params[] = $per_page;
    $params[] = $offset;
    $param_types .= 'ii';
    
    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($param_types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $beds = [];
    while ($row = $result->fetch_assoc()) {
        $beds[] = $row;
    }
    
    return [
        'beds' => $beds,
        'total_records' => $total_records,
        'total_pages' => ceil($total_records / $per_page),
        'current_page' => $page,
        'per_page' => $per_page
    ];
}

/**
 * Add a new bed
 * 
 * @param mysqli $conn Database connection
 * @param array $data Bed data
 * @param int $user_id User ID for audit logging
 * @return int|false Inserted bed ID or false on failure
 */
function addBed($conn, $data, $user_id) {
    try {
        // Check if bed number already exists in ward
        $check_stmt = $conn->prepare("SELECT bed_id FROM beds WHERE ward_id = ? AND bed_number = ?");
        $check_stmt->bind_param("is", $data['ward_id'], $data['bed_number']);
        $check_stmt->execute();
        
        if ($check_stmt->get_result()->num_rows > 0) {
            throw new Exception("Bed number already exists in this ward");
        }
        
        $stmt = $conn->prepare("INSERT INTO beds (ward_id, bed_number, status) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $data['ward_id'], $data['bed_number'], $data['status']);
        $stmt->execute();
        
        $bed_id = $conn->insert_id;
        
        // Log the action
        logAuditAction($conn, $user_id, 'CREATE', 'beds', $bed_id, null, json_encode($data));
        
        return $bed_id;
        
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Update a bed
 * 
 * @param mysqli $conn Database connection
 * @param array $data Bed data including bed_id
 * @param int $user_id User ID for audit logging
 * @return bool True on success, false on failure
 */
function updateBed($conn, $data, $user_id) {
    try {
        // Get old values for audit
        $old_bed = getBedById($conn, $data['bed_id']);
        
        $stmt = $conn->prepare("UPDATE beds SET ward_id = ?, bed_number = ?, status = ? WHERE bed_id = ?");
        $stmt->bind_param("issi", $data['ward_id'], $data['bed_number'], $data['status'], $data['bed_id']);
        $stmt->execute();
        
        // Log the action
        logAuditAction($conn, $user_id, 'UPDATE', 'beds', $data['bed_id'], 
                       json_encode($old_bed), json_encode($data));
        
        return true;
        
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Delete a bed
 * 
 * @param mysqli $conn Database connection
 * @param int $bed_id Bed ID
 * @param int $user_id User ID for audit logging
 * @return bool True on success, false on failure
 */
function deleteBed($conn, $bed_id, $user_id) {
    try {
        // Check if bed is occupied
        $check_stmt = $conn->prepare("SELECT * FROM beds WHERE bed_id = ? AND status = 'occupied'");
        $check_stmt->bind_param("i", $bed_id);
        $check_stmt->execute();
        
        if ($check_stmt->get_result()->num_rows > 0) {
            throw new Exception("Cannot delete occupied bed");
        }
        
        // Get old values for audit
        $old_bed = getBedById($conn, $bed_id);
        
        $stmt = $conn->prepare("DELETE FROM beds WHERE bed_id = ?");
        $stmt->bind_param("i", $bed_id);
        $stmt->execute();
        
        // Log the action
        logAuditAction($conn, $user_id, 'DELETE', 'beds', $bed_id, json_encode($old_bed), null);
        
        return true;
        
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Get a single bed by ID
 * 
 * @param mysqli $conn Database connection
 * @param int $bed_id Bed ID
 * @return array|null Bed data or null if not found
 */
function getBedById($conn, $bed_id) {
    $stmt = $conn->prepare("
        SELECT b.*, w.ward_name 
        FROM beds b 
        JOIN wards w ON b.ward_id = w.ward_id 
        WHERE b.bed_id = ?
    ");
    $stmt->bind_param("i", $bed_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

/**
 * Set bed to maintenance status
 * 
 * @param mysqli $conn Database connection
 * @param int $bed_id Bed ID
 * @param string $reason Maintenance reason
 * @param int $user_id User ID for audit logging
 * @return bool True on success, false on failure
 */
function setBedMaintenance($conn, $bed_id, $reason, $user_id) {
    try {
        // Check if bed is occupied
        $check_stmt = $conn->prepare("SELECT * FROM beds WHERE bed_id = ? AND status = 'occupied'");
        $check_stmt->bind_param("i", $bed_id);
        $check_stmt->execute();
        
        if ($check_stmt->get_result()->num_rows > 0) {
            throw new Exception("Cannot set occupied bed to maintenance");
        }
        
        $old_bed = getBedById($conn, $bed_id);
        
        $stmt = $conn->prepare("UPDATE beds SET status = 'maintenance' WHERE bed_id = ?");
        $stmt->bind_param("i", $bed_id);
        $stmt->execute();
        
        // Log the action with maintenance reason
        $log_data = ['bed_id' => $bed_id, 'reason' => $reason, 'old_status' => $old_bed['status']];
        logAuditAction($conn, $user_id, 'MAINTENANCE', 'beds', $bed_id, 
                       json_encode($old_bed), json_encode($log_data));
        
        return true;
        
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Get available beds for a specific ward or all wards
 * 
 * @param mysqli $conn Database connection
 * @param int|null $ward_id Ward ID (null for all wards)
 * @return array Array of available beds
 */
function getAvailableBeds($conn, $ward_id = null) {
    $query = "
        SELECT b.bed_id, b.bed_number, w.ward_name, w.ward_type
        FROM beds b
        JOIN wards w ON b.ward_id = w.ward_id
        WHERE b.status = 'available'
    ";
    
    if ($ward_id) {
        $query .= " AND b.ward_id = ?";
        $stmt = $conn->prepare($query . " ORDER BY w.ward_name, b.bed_number");
        $stmt->bind_param("i", $ward_id);
    } else {
        $stmt = $conn->prepare($query . " ORDER BY w.ward_name, b.bed_number");
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $beds = [];
    while ($row = $result->fetch_assoc()) {
        $beds[] = $row;
    }
    
    return $beds;
}

/**
 * Check if ward has capacity for new admission
 * 
 * @param mysqli $conn Database connection
 * @param int $ward_id Ward ID
 * @return bool True if has capacity, false otherwise
 */
function wardHasCapacity($conn, $ward_id) {
    $stmt = $conn->prepare("
        SELECT 
            w.capacity,
            COUNT(CASE WHEN b.status = 'occupied' THEN 1 END) as occupied
        FROM wards w
        LEFT JOIN beds b ON w.ward_id = b.ward_id
        WHERE w.ward_id = ?
        GROUP BY w.ward_id, w.capacity
    ");
    $stmt->bind_param("i", $ward_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        return $row['occupied'] < $row['capacity'];
    }
    
    return false;
}

/**
 * Bulk update bed status
 * 
 * @param mysqli $conn Database connection
 * @param array $bed_ids Array of bed IDs
 * @param string $status New status
 * @param int $user_id User ID for audit logging
 * @return int Number of updated beds
 */
function bulkUpdateBedStatus($conn, $bed_ids, $status, $user_id) {
    if (empty($bed_ids)) return 0;
    
    $conn->begin_transaction();
    
    try {
        $updated_count = 0;
        
        foreach ($bed_ids as $bed_id) {
            // Skip if trying to change occupied beds
            if ($status == 'maintenance') {
                $check_stmt = $conn->prepare("SELECT status FROM beds WHERE bed_id = ?");
                $check_stmt->bind_param("i", $bed_id);
                $check_stmt->execute();
                $current_status = $check_stmt->get_result()->fetch_assoc()['status'];
                
                if ($current_status == 'occupied') {
                    continue;
                }
            }
            
            $old_bed = getBedById($conn, $bed_id);
            
            $stmt = $conn->prepare("UPDATE beds SET status = ? WHERE bed_id = ?");
            $stmt->bind_param("si", $status, $bed_id);
            
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                $updated_count++;
                
                // Log the action
                logAuditAction($conn, $user_id, 'BULK_UPDATE', 'beds', $bed_id, 
                               json_encode($old_bed), json_encode(['status' => $status]));
            }
        }
        
        $conn->commit();
        return $updated_count;
        
    } catch (Exception $e) {
        $conn->rollback();
        return 0;
    }
}

/**
 * Log audit action
 * 
 * @param mysqli $conn Database connection
 * @param int $user_id User ID
 * @param string $action Action performed
 * @param string $table_affected Table affected
 * @param int $record_id Record ID
 * @param string|null $old_values Old values JSON
 * @param string|null $new_values New values JSON
 * @return bool True on success, false on failure
 */
function logAuditAction($conn, $user_id, $action, $table_affected, $record_id, $old_values, $new_values) {
    $stmt = $conn->prepare("
        INSERT INTO audit_log (user_id, action, table_affected, record_id, old_values, new_values, ip_address) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $stmt->bind_param("ississs", $user_id, $action, $table_affected, $record_id, $old_values, $new_values, $ip_address);
    
    return $stmt->execute();
}

/**
 * Search patients by location (admitted patients)
 * 
 * @param mysqli $conn Database connection
 * @param string $search_term Search term
 * @return array Array of patients with location info
 */
function searchPatientsLocation($conn, $search_term) {
    $stmt = $conn->prepare("
        SELECT p.patient_id, p.first_name, p.last_name,
               w.ward_name, b.bed_number, a.admission_date
        FROM patients p
        JOIN admissions a ON p.patient_id = a.patient_id AND a.status = 'admitted'
        JOIN beds b ON a.bed_id = b.bed_id
        JOIN wards w ON b.ward_id = w.ward_id
        WHERE p.first_name LIKE ? OR p.last_name LIKE ? OR CONCAT(p.first_name, ' ', p.last_name) LIKE ?
        ORDER BY p.last_name, p.first_name
    ");
    
    $search_term = "%$search_term%";
    $stmt->bind_param("sss", $search_term, $search_term, $search_term);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $patients = [];
    while ($row = $result->fetch_assoc()) {
        $patients[] = $row;
    }
    
    return $patients;
}

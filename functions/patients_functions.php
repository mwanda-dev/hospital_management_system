 
<?php
/**
 * Patients Functions for Hospital Management System
 */

/**
 * Get all patients
 * 
 * @param mysqli $conn Database connection
 * @param string $search Search term (optional)
 * @return mysqli_result Result set of patients
 */
function getAllPatients($conn, $search = null) {
    $query = "SELECT * FROM patients";
    
    if ($search) {
        $query .= " WHERE first_name LIKE ? OR last_name LIKE ? OR phone LIKE ? OR email LIKE ?";
        $search_term = "%$search%";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssss", $search_term, $search_term, $search_term, $search_term);
    } else {
        $stmt = $conn->prepare($query);
    }
    
    $stmt->execute();
    return $stmt->get_result();
}

/**
 * Get a single patient by ID
 * 
 * @param mysqli $conn Database connection
 * @param int $patient_id Patient ID
 * @return array|null Patient data or null if not found
 */
function getPatientById($conn, $patient_id) {
    $stmt = $conn->prepare("SELECT * FROM patients WHERE patient_id = ?");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

/**
 * Add a new patient
 * 
 * @param mysqli $conn Database connection
 * @param array $data Patient data
 * @return int|false Inserted ID or false on failure
 */
function addPatient($conn, $data) {
    $stmt = $conn->prepare("
        INSERT INTO patients (
            first_name, last_name, date_of_birth, gender, blood_type, 
            phone, email, address, emergency_contact_name, 
            emergency_contact_phone, insurance_provider, insurance_policy_number
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->bind_param(
        "ssssssssssss",
        $data['first_name'],
        $data['last_name'],
        $data['date_of_birth'],
        $data['gender'],
        $data['blood_type'],
        $data['phone'],
        $data['email'],
        $data['address'],
        $data['emergency_contact_name'],
        $data['emergency_contact_phone'],
        $data['insurance_provider'],
        $data['insurance_policy_number']
    );
    
    if ($stmt->execute()) {
        return $conn->insert_id;
    }
    return false;
}

/**
 * Update an existing patient
 * 
 * @param mysqli $conn Database connection
 * @param array $data Patient data
 * @return bool True on success, false on failure
 */
function updatePatient($conn, $data) {
    $stmt = $conn->prepare("
        UPDATE patients SET 
            first_name = ?,
            last_name = ?,
            date_of_birth = ?,
            gender = ?,
            blood_type = ?,
            phone = ?,
            email = ?,
            address = ?,
            emergency_contact_name = ?,
            emergency_contact_phone = ?,
            insurance_provider = ?,
            insurance_policy_number = ?
        WHERE patient_id = ?
    ");
    
    $stmt->bind_param(
        "ssssssssssssi",
        $data['first_name'],
        $data['last_name'],
        $data['date_of_birth'],
        $data['gender'],
        $data['blood_type'],
        $data['phone'],
        $data['email'],
        $data['address'],
        $data['emergency_contact_name'],
        $data['emergency_contact_phone'],
        $data['insurance_provider'],
        $data['insurance_policy_number'],
        $data['patient_id']
    );
    
    return $stmt->execute();
}

/**
 * Delete a patient
 * 
 * @param mysqli $conn Database connection
 * @param int $patient_id Patient ID
 * @return bool True on success, false on failure
 */
function deletePatient($conn, $patient_id) {
    $stmt = $conn->prepare("DELETE FROM patients WHERE patient_id = ?");
    $stmt->bind_param("i", $patient_id);
    return $stmt->execute();
}

/**
 * Get patient statistics
 * 
 * @param mysqli $conn Database connection
 * @return array Patient statistics
 */
function getPatientStatistics($conn) {
    $stats = [];
    
    // Total patients
    $result = $conn->query("SELECT COUNT(*) as total FROM patients");
    $stats['total'] = $result->fetch_assoc()['total'];
    
    // Patients by gender
    $result = $conn->query("
        SELECT gender, COUNT(*) as count 
        FROM patients 
        GROUP BY gender
    ");
    $stats['by_gender'] = [];
    while ($row = $result->fetch_assoc()) {
        $stats['by_gender'][$row['gender']] = $row['count'];
    }
    
    // New patients this month
    $result = $conn->query("
        SELECT COUNT(*) as count 
        FROM patients 
        WHERE registration_date >= DATE_FORMAT(NOW(), '%Y-%m-01')
    ");
    $stats['new_this_month'] = $result->fetch_assoc()['count'];
    
    // Patients by age group
    $result = $conn->query("
        SELECT 
            CASE 
                WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) < 18 THEN 'Under 18'
                WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 18 AND 30 THEN '18-30'
                WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 31 AND 50 THEN '31-50'
                WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 51 AND 70 THEN '51-70'
                ELSE 'Over 70'
            END as age_group,
            COUNT(*) as count
        FROM patients
        GROUP BY age_group
    ");
    $stats['by_age'] = [];
    while ($row = $result->fetch_assoc()) {
        $stats['by_age'][$row['age_group']] = $row['count'];
    }
    
    return $stats;
}

/**
 * Search patients by name or other criteria
 * 
 * @param mysqli $conn Database connection
 * @param string $term Search term
 * @return mysqli_result Result set of matching patients
 */
function searchPatients($conn, $term) {
    $stmt = $conn->prepare("
        SELECT * FROM patients 
        WHERE first_name LIKE ? OR last_name LIKE ? OR phone LIKE ? OR email LIKE ?
    ");
    $search_term = "%$term%";
    $stmt->bind_param("ssss", $search_term, $search_term, $search_term, $search_term);
    $stmt->execute();
    return $stmt->get_result();
}
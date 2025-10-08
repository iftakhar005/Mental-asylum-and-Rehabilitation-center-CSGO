<?php
// Test CSV formatting for problematic fields

function generateCSV($data) {
    if (empty($data)) return '';
    
    $output = '';
    
    // Headers
    $headers = array_keys($data[0]);
    $output .= '"' . implode('","', $headers) . '"' . "\n";
    
    // Data rows
    foreach ($data as $row) {
        $escaped_row = [];
        foreach ($headers as $header) {
            $value = $row[$header] ?? '';
            
            // Handle different data types to prevent Excel auto-formatting issues
            if (is_numeric($value) && strlen($value) > 10) {
                // Large numbers (like phone numbers) - prefix with single quote to force text format
                $escaped_row[] = "'" . str_replace('"', '""', $value);
            } elseif (preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
                // Date fields - format properly
                $escaped_row[] = str_replace('"', '""', $value);
            } elseif ($value === null || $value === '') {
                // Handle null/empty values
                $escaped_row[] = '';
            } else {
                // Regular text values
                $escaped_row[] = str_replace('"', '""', $value);
            }
        }
        $output .= '"' . implode('","', $escaped_row) . '"' . "\n";
    }
    
    return $output;
}

// Test data mimicking what we have in database
$test_data = [
    [
        'id' => '1',
        'user_id' => '6',
        'patient_id' => 'ARC-2025',
        'full_name' => 'kader',
        'date_of_birth' => '2024-10-08',
        'gender' => 'Male',
        'contact_number' => '01432221406',
        'emergency_contact' => 'Allergies',
        'address' => 'Napa',
        'medical_history' => 'some history',
        'current_medications' => 'medications',
        'admission_date' => '2024-10-08',
        'room_number' => '1A',
        'type' => 'Asylum',
        'mobility_status' => 'Assisted',
        'meal_plan' => 'admitted',
        'status' => 'active',
        'created_at' => '2024-10-08 12:00:00',
        'updated_at' => '2024-10-08 12:30:00'
    ]
];

echo "Testing CSV format with our improvements:\n\n";
echo generateCSV($test_data);
?>
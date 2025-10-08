<?php
// Test the actual data processing without session complications
require_once 'db.php';

// Get the raw data from database - same query as in secure_export.php
$sql = "SELECT * FROM patients";
$result = $conn->query($sql);

if ($result) {
    $data = $result->fetch_all(MYSQLI_ASSOC);
    
    echo "Raw data from database:\n";
    if (!empty($data)) {
        echo "First row:\n";
        foreach ($data[0] as $key => $value) {
            echo "$key: '$value'\n";
        }
        
        echo "\n--- CSV OUTPUT ---\n";
        
        // Use our improved CSV function
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
        
        echo generateCSV($data);
        
    } else {
        echo "No data found\n";
    }
} else {
    echo "Database query failed: " . $conn->error . "\n";
}
?>
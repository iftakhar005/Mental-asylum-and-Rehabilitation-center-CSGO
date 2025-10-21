<?php
require_once 'db.php';

echo "=== DATA CLASSIFICATION REPORT ===\n\n";

// Check all classifications
$result = $conn->query("SELECT * FROM data_classification ORDER BY 
    CASE classification_level 
        WHEN 'public' THEN 1 
        WHEN 'internal' THEN 2 
        WHEN 'confidential' THEN 3 
        WHEN 'restricted' THEN 4 
    END, 
    table_name, column_name");

if ($result && $result->num_rows > 0) {
    $by_level = [];
    while ($row = $result->fetch_assoc()) {
        $level = strtoupper($row['classification_level']);
        if (!isset($by_level[$level])) {
            $by_level[$level] = [];
        }
        $by_level[$level][] = $row;
    }
    
    foreach (['PUBLIC', 'INTERNAL', 'CONFIDENTIAL', 'RESTRICTED'] as $level) {
        if (isset($by_level[$level])) {
            echo "\n" . str_repeat("=", 80) . "\n";
            echo "$level DATA (" . count($by_level[$level]) . " items)\n";
            echo str_repeat("=", 80) . "\n";
            printf("%-25s %-25s %-20s %-10s\n", "Table", "Column", "Category", "Retention");
            echo str_repeat("-", 80) . "\n";
            
            foreach ($by_level[$level] as $item) {
                printf("%-25s %-25s %-20s %d days\n", 
                    $item['table_name'], 
                    $item['column_name'], 
                    $item['data_category'], 
                    $item['retention_days']
                );
            }
        }
    }
    
    // Summary
    echo "\n" . str_repeat("=", 80) . "\n";
    echo "SUMMARY\n";
    echo str_repeat("=", 80) . "\n";
    foreach (['PUBLIC', 'INTERNAL', 'CONFIDENTIAL', 'RESTRICTED'] as $level) {
        $count = isset($by_level[$level]) ? count($by_level[$level]) : 0;
        echo "$level: $count items\n";
    }
    
} else {
    echo "⚠️ NO DATA CLASSIFICATIONS FOUND!\n\n";
    echo "This means:\n";
    echo "- All data will default to 'internal' classification\n";
    echo "- You need to classify your data using dlp_management.php\n";
    echo "- Or run setup scripts to auto-classify common tables\n";
}

echo "\n";
?>

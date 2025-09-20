<?php
include 'db.php';

echo "<h1>Database Structure Check</h1>";

// Check if database exists and what tables are present
$result = $conn->query("SHOW TABLES");

if ($result) {
    echo "<h2>📋 Existing Tables:</h2>";
    echo "<ul>";
    while ($row = $result->fetch_array()) {
        echo "<li>" . $row[0] . "</li>";
        
        // Show structure of each table
        $table = $row[0];
        $structure = $conn->query("DESCRIBE `$table`");
        if ($structure) {
            echo "<ul>";
            while ($field = $structure->fetch_assoc()) {
                echo "<li><strong>" . $field['Field'] . "</strong>: " . $field['Type'] . 
                     ($field['Null'] == 'NO' ? ' (Required)' : '') . 
                     ($field['Key'] == 'PRI' ? ' (Primary Key)' : '') . "</li>";
            }
            echo "</ul>";
        }
    }
    echo "</ul>";
} else {
    echo "<p>❌ No tables found or error accessing database: " . $conn->error . "</p>";
}

// Check if we need to create test data
echo "<h2>🧪 Test Data Setup</h2>";

// Try to find a users table (could be named differently)
$userTables = ['users', 'staff', 'admin', 'login', 'accounts'];
$foundUserTable = null;

foreach ($userTables as $tableName) {
    $check = $conn->query("SHOW TABLES LIKE '$tableName'");
    if ($check && $check->num_rows > 0) {
        $foundUserTable = $tableName;
        break;
    }
}

if ($foundUserTable) {
    echo "<p>✅ Found user table: <strong>$foundUserTable</strong></p>";
    
    // Check if there are any users
    $userCount = $conn->query("SELECT COUNT(*) as count FROM `$foundUserTable`");
    if ($userCount) {
        $count = $userCount->fetch_assoc()['count'];
        echo "<p>👤 Users in table: <strong>$count</strong></p>";
        
        if ($count == 0) {
            echo "<p>⚠️ No users found. You may need to create a test user.</p>";
        } else {
            // Show existing users (limited info for security)
            $users = $conn->query("SELECT id, email, role FROM `$foundUserTable` LIMIT 5");
            if ($users) {
                echo "<h3>Sample Users:</h3><ul>";
                while ($user = $users->fetch_assoc()) {
                    echo "<li>ID: " . $user['id'] . ", Email: " . htmlspecialchars($user['email']) . 
                         ", Role: " . htmlspecialchars($user['role']) . "</li>";
                }
                echo "</ul>";
            }
        }
    }
} else {
    echo "<p>❌ No user authentication table found. You may need to run your database setup script.</p>";
    echo "<p>💡 <strong>Suggestion:</strong> Run your <code>database.sql</code> file to create the necessary tables.</p>";
}

echo "<h2>📄 Database Setup File</h2>";
if (file_exists('database.sql')) {
    echo "<p>✅ Found database.sql file</p>";
    echo "<p><a href='?setup=1' style='background: #007cba; color: white; padding: 10px 15px; text-decoration: none; border-radius: 5px;'>🚀 Run Database Setup</a></p>";
} else {
    echo "<p>❌ database.sql file not found</p>";
}

// If setup is requested
if (isset($_GET['setup']) && file_exists('database.sql')) {
    echo "<h2>🔧 Running Database Setup...</h2>";
    
    $sql = file_get_contents('database.sql');
    $queries = explode(';', $sql);
    
    $success = 0;
    $errors = 0;
    
    foreach ($queries as $query) {
        $query = trim($query);
        if (!empty($query)) {
            if ($conn->query($query)) {
                $success++;
            } else {
                $errors++;
                echo "<p style='color: red;'>❌ Error: " . $conn->error . "</p>";
            }
        }
    }
    
    echo "<p>✅ Successfully executed: <strong>$success</strong> queries</p>";
    if ($errors > 0) {
        echo "<p>❌ Errors encountered: <strong>$errors</strong></p>";
    }
    echo "<p><a href='database_check.php'>🔄 Refresh to see changes</a></p>";
}
?>
<?php
echo "<h1>PHP Configuration Check</h1>";
echo "<h2>PHP Version:</h2>";
echo "PHP " . phpversion() . "<br>";

echo "<h2>MySQLi Extension Status:</h2>";
if (extension_loaded('mysqli')) {
    echo "✅ MySQLi extension is loaded and available<br>";
} else {
    echo "❌ MySQLi extension is NOT loaded<br>";
    echo "<br><strong>To fix this in XAMPP:</strong><br>";
    echo "1. Open XAMPP Control Panel<br>";
    echo "2. Click 'Config' button next to Apache<br>";
    echo "3. Select 'PHP (php.ini)'<br>";
    echo "4. Find the line: ;extension=mysqli<br>";
    echo "5. Remove the semicolon: extension=mysqli<br>";
    echo "6. Save the file and restart Apache<br>";
}

echo "<h2>All Loaded Extensions:</h2>";
$extensions = get_loaded_extensions();
sort($extensions);
foreach ($extensions as $ext) {
    echo $ext . "<br>";
}

echo "<h2>Database Connection Test:</h2>";
if (extension_loaded('mysqli')) {
    try {
        $conn = new mysqli("localhost", "root", "", "asylum_db");
        if ($conn->connect_error) {
            echo "❌ Database connection failed: " . $conn->connect_error . "<br>";
        } else {
            echo "✅ Database connection successful<br>";
        }
        $conn->close();
    } catch (Exception $e) {
        echo "❌ Database error: " . $e->getMessage() . "<br>";
    }
} else {
    echo "❌ Cannot test database - MySQLi extension not loaded<br>";
}
?>
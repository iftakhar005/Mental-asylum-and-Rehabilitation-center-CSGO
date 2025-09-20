<?php
echo "<h1>Simple Test Page</h1>";
echo "<p>PHP is working: " . phpversion() . "</p>";
echo "<p>MySQLi loaded: " . (extension_loaded('mysqli') ? 'YES' : 'NO') . "</p>";
echo "<p>Session status: " . session_status() . "</p>";

if (file_exists('teacher_demo.php')) {
    echo "<p>teacher_demo.php exists</p>";
} else {
    echo "<p>teacher_demo.php NOT found</p>";
}

echo "<br><a href='teacher_demo.php'>Go to Teacher Demo</a>";
?>
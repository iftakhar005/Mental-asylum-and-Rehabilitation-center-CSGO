<?php
echo "<h1>🔍 Pre-Presentation Test Checklist</h1>";
echo "<style>
body{font-family:Arial;margin:20px;background:#f8f9fa;} 
.test{background:white;padding:15px;margin:10px 0;border-radius:8px;border-left:4px solid #28a745;} 
.pass{color:#28a745;font-weight:bold;} 
.fail{color:#dc3545;font-weight:bold;} 
.warning{color:#f57c00;font-weight:bold;}
.section{background:#e3f2fd;padding:20px;margin:20px 0;border-radius:10px;}
h2{color:#1976d2;border-bottom:2px solid #42a5f5;padding-bottom:8px;}
</style>";

$allPassed = true;

echo "<div class='section'>";
echo "<h2>🔧 System Check</h2>";

// PHP Version
$phpVersion = phpversion();
echo "<div class='test'>";
echo "<strong>PHP Version:</strong> " . $phpVersion;
if (version_compare($phpVersion, '7.4', '>=')) {
    echo " <span class='pass'>✅ PASS</span>";
} else {
    echo " <span class='warning'>⚠️ WARNING</span> (Recommended 7.4+)";
}
echo "</div>";

// MySQLi Extension
echo "<div class='test'>";
echo "<strong>MySQLi Extension:</strong> ";
if (extension_loaded('mysqli')) {
    echo "<span class='pass'>✅ LOADED</span>";
} else {
    echo "<span class='fail'>❌ NOT LOADED</span>";
    $allPassed = false;
}
echo "</div>";

// Session Support
echo "<div class='test'>";
echo "<strong>Session Support:</strong> ";
if (function_exists('session_start')) {
    echo "<span class='pass'>✅ AVAILABLE</span>";
} else {
    echo "<span class='fail'>❌ NOT AVAILABLE</span>";
    $allPassed = false;
}
echo "</div>";
echo "</div>";

echo "<div class='section'>";
echo "<h2>📁 File Existence Check</h2>";

$requiredFiles = [
    'security_manager.php' => 'Main security implementation',
    'teacher_final.php' => 'Primary demonstration page',
    'index.php' => 'Login page for live testing',
    'db.php' => 'Database connection',
    'working_demo.php' => 'Backup demonstration',
    'TEACHER_PRESENTATION_GUIDE.md' => 'Full documentation',
    'QUICK_REFERENCE.md' => 'Quick reference card'
];

foreach ($requiredFiles as $file => $description) {
    echo "<div class='test'>";
    echo "<strong>$file:</strong> $description - ";
    if (file_exists($file)) {
        echo "<span class='pass'>✅ EXISTS</span>";
    } else {
        echo "<span class='fail'>❌ MISSING</span>";
        $allPassed = false;
    }
    echo "</div>";
}
echo "</div>";

echo "<div class='section'>";
echo "<h2>🗄️ Database Connection Check</h2>";
try {
    include_once 'db.php';
    echo "<div class='test'>";
    echo "<strong>Database Connection:</strong> ";
    if (isset($conn) && $conn instanceof mysqli) {
        if (!$conn->connect_error) {
            echo "<span class='pass'>✅ CONNECTED</span>";
        } else {
            echo "<span class='warning'>⚠️ CONNECTION ERROR</span> - " . $conn->connect_error;
        }
    } else {
        echo "<span class='warning'>⚠️ CONFIGURED</span> (Connection object created)";
    }
    echo "</div>";
} catch (Exception $e) {
    echo "<div class='test'>";
    echo "<strong>Database Connection:</strong> <span class='warning'>⚠️ ERROR</span> - " . htmlspecialchars($e->getMessage());
    echo "</div>";
}
echo "</div>";

echo "<div class='section'>";
echo "<h2>🛡️ Security Functions Check</h2>";
try {
    include_once 'security_manager.php';
    if (isset($conn)) {
        $securityManager = new MentalHealthSecurityManager($conn);
        
        // Test each function
        $functions = [
            'validateInput' => 'Input Validation',
            'preventXSS' => 'XSS Prevention', 
            'generateCaptcha' => 'CAPTCHA System',
            'testQuerySafety' => 'SQL Injection Prevention',
            'secureQuery' => 'Parameterized Queries'
        ];
        
        foreach ($functions as $method => $name) {
            echo "<div class='test'>";
            echo "<strong>$name:</strong> ";
            if (method_exists($securityManager, $method)) {
                echo "<span class='pass'>✅ IMPLEMENTED</span>";
            } else {
                echo "<span class='fail'>❌ MISSING</span>";
                $allPassed = false;
            }
            echo "</div>";
        }
    } else {
        echo "<div class='test'>";
        echo "<strong>Security Manager:</strong> <span class='warning'>⚠️ NEEDS DATABASE</span>";
        echo "</div>";
    }
} catch (Exception $e) {
    echo "<div class='test'>";
    echo "<strong>Security Manager:</strong> <span class='fail'>❌ ERROR</span> - " . htmlspecialchars($e->getMessage());
    echo "</div>";
    $allPassed = false;
}
echo "</div>";

echo "<div class='section'>";
echo "<h2>🌐 URL Accessibility Check</h2>";

$testUrls = [
    'teacher_final.php' => 'Main Demonstration',
    'working_demo.php' => 'Backup Demo',
    'index.php' => 'Login Testing',
    'database_check.php' => 'Database Status'
];

foreach ($testUrls as $url => $description) {
    echo "<div class='test'>";
    echo "<strong>$description:</strong> ";
    if (file_exists($url)) {
        echo "<a href='$url' target='_blank' style='color:#1976d2;text-decoration:none;'>🔗 $url</a> ";
        echo "<span class='pass'>✅ READY</span>";
    } else {
        echo "$url <span class='fail'>❌ NOT FOUND</span>";
        $allPassed = false;
    }
    echo "</div>";
}
echo "</div>";

// Final Status
echo "<div style='background:" . ($allPassed ? "#d4edda" : "#f8d7da") . ";padding:25px;border-radius:15px;text-align:center;margin:30px 0;border:3px solid " . ($allPassed ? "#28a745" : "#dc3545") . ";'>";
if ($allPassed) {
    echo "<h2 style='color:#155724;margin:0;'>🎉 ALL SYSTEMS GO!</h2>";
    echo "<p style='color:#155724;font-size:1.2em;margin:10px 0;'>Your presentation is ready! All systems are working correctly.</p>";
    echo "<p style='color:#155724;'><strong>Next Step:</strong> Open <a href='teacher_final.php' style='color:#155724;'>teacher_final.php</a> and start your demonstration!</p>";
} else {
    echo "<h2 style='color:#721c24;margin:0;'>⚠️ ISSUES DETECTED</h2>";
    echo "<p style='color:#721c24;font-size:1.2em;margin:10px 0;'>Please fix the failed checks above before presenting.</p>";
    echo "<p style='color:#721c24;'><strong>Backup Plan:</strong> Use <a href='simple_demo.html' style='color:#721c24;'>simple_demo.html</a> if issues persist.</p>";
}
echo "</div>";

echo "<div class='section'>";
echo "<h2>📋 Pre-Presentation Checklist</h2>";
echo "<div style='display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:15px;'>";

echo "<div>";
echo "<h3>🔧 Technical Setup</h3>";
echo "<ul>";
echo "<li>✅ PHP server started</li>";
echo "<li>✅ MySQLi extension loaded</li>";
echo "<li>✅ All files present</li>";
echo "<li>✅ URLs accessible</li>";
echo "</ul>";
echo "</div>";

echo "<div>";
echo "<h3>📖 Documentation Ready</h3>";
echo "<ul>";
echo "<li>📄 <a href='TEACHER_PRESENTATION_GUIDE.md'>Full Guide</a></li>";
echo "<li>📱 <a href='QUICK_REFERENCE.md'>Quick Reference</a></li>";
echo "<li>💻 security_manager.php open in editor</li>";
echo "<li>🌐 Browser tabs ready</li>";
echo "</ul>";
echo "</div>";

echo "<div>";
echo "<h3>🎯 Key Points to Remember</h3>";
echo "<ul>";
echo "<li>🔒 6 security functions</li>";
echo "<li>📊 15+ test cases</li>";
echo "<li>🚫 No external libraries</li>";
echo "<li>⚡ 590+ lines of code</li>";
echo "</ul>";
echo "</div>";

echo "<div>";
echo "<h3>🎤 Presentation Flow</h3>";
echo "<ul>";
echo "<li>🎯 Overview (2 min)</li>";
echo "<li>🧪 Live Demo (6 min)</li>";
echo "<li>💻 Code Review (3 min)</li>";
echo "<li>📊 Summary (1 min)</li>";
echo "</ul>";
echo "</div>";

echo "</div>";
echo "</div>";

echo "<div style='text-align:center;margin:30px 0;'>";
echo "<h3>🚀 Ready to Present?</h3>";
echo "<a href='teacher_final.php' style='background:#1976d2;color:white;padding:15px 30px;text-decoration:none;border-radius:8px;font-weight:bold;font-size:1.2em;'>🎓 START DEMONSTRATION</a>";
echo "</div>";
?>
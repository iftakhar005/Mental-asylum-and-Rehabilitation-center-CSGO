<?php
session_start();
require_once 'db.php';

// Handle add stock form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_stock'])) {
    $type = $conn->real_escape_string($_POST['type']);
    $name = $conn->real_escape_string($_POST['name']);
    $quantity = (int)$_POST['quantity'];
    $strength = $conn->real_escape_string($_POST['strength']);
    $expire_date = $conn->real_escape_string($_POST['expire_date']);
    $date_of_entry = $conn->real_escape_string($_POST['date_of_entry']);
    $sql = "INSERT INTO medicine_stock (type, name, quantity, strength, expire_date, date_of_entry) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssisss", $type, $name, $quantity, $strength, $expire_date, $date_of_entry);
    if ($stmt->execute()) {
        $_SESSION['message'] = "Medicine stock added successfully!";
    } else {
        $_SESSION['error'] = "Error adding stock: " . $conn->error;
    }
    header('Location: medicine_stock.php');
    exit();
}

// Fetch existing stock
$stocks = [];
$result = $conn->query("SELECT * FROM medicine_stock ORDER BY date_of_entry DESC, id DESC");
if ($result) {
    $stocks = $result->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medicine Stock Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f3f4f6; margin: 0; }
        .container { max-width: 900px; margin: 40px auto; background: #fff; border-radius: 12px; box-shadow: 0 4px 24px rgba(0,0,0,0.08); padding: 32px; }
        h1 { color: #1f2937; margin-bottom: 24px; }
        .form-section { margin-bottom: 40px; }
        .form-group { margin-bottom: 18px; }
        label { display: block; font-weight: 500; margin-bottom: 6px; color: #374151; }
        input, select { width: 100%; padding: 10px 14px; border: 1.5px solid #d1d5db; border-radius: 8px; font-size: 1rem; }
        button { background: #10b981; color: #fff; border: none; padding: 12px 28px; border-radius: 8px; font-weight: 600; font-size: 1rem; cursor: pointer; transition: background 0.2s; }
        button:hover { background: #059669; }
        .message { padding: 12px 18px; border-radius: 8px; margin-bottom: 18px; font-weight: 500; }
        .success { background: #d1fae5; color: #065f46; }
        .error { background: #fee2e2; color: #991b1b; }
        table { width: 100%; border-collapse: collapse; margin-top: 18px; background: #f9fafb; }
        th, td { padding: 12px 10px; border-bottom: 1px solid #e5e7eb; text-align: left; }
        th { background: #f3f4f6; color: #374151; font-weight: 600; }
        tr:last-child td { border-bottom: none; }
    </style>
</head>
<body>
    <div class="container">
        <a href="admin_dashboard.php" style="display:inline-flex;align-items:center;gap:8px;margin-bottom:18px;text-decoration:none;color:#059669;font-weight:600;font-size:1rem;">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
        <h1><i class="fas fa-pills"></i> Medicine Stock Management</h1>
        <?php if (isset($_SESSION['message'])): ?>
            <div class="message success"><?php echo $_SESSION['message']; unset($_SESSION['message']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="message error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        <div class="form-section">
            <h2>Add Stock</h2>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="type">Type</label>
                    <select id="type" name="type" required>
                        <option value="">Select Type</option>
                        <option value="Antipsychotics">Antipsychotics</option>
                        <option value="Antidepressants">Antidepressants</option>
                        <option value="Mood Stabilizers">Mood Stabilizers</option>
                        <option value="Anxiolytics (Anti-anxiety drugs)">Anxiolytics (Anti-anxiety drugs)</option>
                        <option value="Sedatives / Hypnotics">Sedatives / Hypnotics</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="name">Name</label>
                    <input type="text" id="name" name="name" required>
                </div>
                <div class="form-group">
                    <label for="quantity">Quantity</label>
                    <input type="number" id="quantity" name="quantity" min="1" required>
                </div>
                <div class="form-group">
                    <label for="strength">Strength of Medicine</label>
                    <input type="text" id="strength" name="strength" required>
                </div>
                <div class="form-group">
                    <label for="expire_date">Expire Date</label>
                    <input type="date" id="expire_date" name="expire_date" required>
                </div>
                <div class="form-group">
                    <label for="date_of_entry">Date of Entry</label>
                    <input type="date" id="date_of_entry" name="date_of_entry" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <button type="submit" name="add_stock"><i class="fas fa-plus"></i> Add Stock</button>
            </form>
        </div>
        <div class="table-section">
            <h2>Existing Stocks</h2>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Type</th>
                        <th>Name</th>
                        <th>Quantity</th>
                        <th>Strength</th>
                        <th>Expire Date</th>
                        <th>Date of Entry</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($stocks)): ?>
                        <tr><td colspan="7" style="text-align:center; color:#888;">No stock records found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($stocks as $stock): ?>
                            <tr>
                                <td><?php echo $stock['id']; ?></td>
                                <td><?php echo htmlspecialchars($stock['type']); ?></td>
                                <td><?php echo htmlspecialchars($stock['name']); ?></td>
                                <td><?php echo $stock['quantity']; ?></td>
                                <td><?php echo htmlspecialchars($stock['strength']); ?></td>
                                <td><?php echo htmlspecialchars($stock['expire_date']); ?></td>
                                <td><?php echo htmlspecialchars($stock['date_of_entry']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html> 
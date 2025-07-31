<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$dashboard_link = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') ? 'admin_dashboard.php' : 'chief_staff_dashboard.php';
?>
<style>
.topbar {
    background: #fff;
    padding: 18px 30px 18px 30px;
    display: flex;
    align-items: center;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    margin-bottom: 24px;
    gap: 16px;
    justify-content: flex-end;
}
.topbar .btn {
    font-weight: 600;
    font-size: 1rem;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    border: 2px solid #2563eb;
    background: #fff;
    color: #2563eb;
    border-radius: 8px;
    padding: 8px 22px;
    transition: background 0.18s, color 0.18s, box-shadow 0.18s;
    box-shadow: none;
}
.topbar .btn:focus, .topbar .btn:hover {
    background: #2563eb;
    color: #fff;
    box-shadow: 0 2px 8px rgba(37,99,235,0.10);
}
.topbar .btn i {
    color: inherit;
    transition: color 0.18s;
}
</style>
<div class="topbar">
    <a href="index.php" class="btn">
        <i class="fas fa-sign-in-alt"></i> Login
    </a>
    <a href="<?php echo $dashboard_link; ?>" class="btn">
        <i class="fas fa-tachometer-alt"></i> Dashboard
    </a>
    <a href="patient_management.php" class="btn">
        <i class="fas fa-users"></i> Patient Management
    </a>
</div> 
# 📋 AUDIT TRAIL IMPLEMENTATION GUIDE

## Overview

The Audit Trail system provides comprehensive tracking of all database operations, data modifications, and bulk operations in the Mental Health Center application. This implementation follows the specifications from `2FA_and_Audit_Tools_Summary.txt`.

---

## ✅ What's Implemented

### 1. **Data Access Logging**
- Tracks ALL database operations (SELECT, INSERT, UPDATE, DELETE)
- Records user information, timestamps, IP addresses
- Monitors execution time and records affected
- Flags bulk operations and sensitive data access

### 2. **Data Modification History**
- Tracks field-level changes (old value → new value)
- Records who made the change, when, and why
- Supports change reasons for compliance
- Maintains complete audit trail for compliance

### 3. **Bulk Operation Monitoring**
- Alerts on operations exceeding thresholds
- Configurable per-table thresholds
- Alert levels: INFO, WARNING, CRITICAL
- Investigation tracking

### 4. **Role-Based Permissions**
- Granular permissions per table and role
- Read, Create, Update, Delete, Bulk Export controls
- Maximum records per query limits
- Field-level restrictions support

### 5. **Approval Workflows**
- Configurable approval requirements
- Multi-level approval support
- Request tracking and status management
- Approval history logging

---

## 📁 Files Created/Modified

### New Files:
1. **`audit_trail.php`** - Main audit trail dashboard
2. **`AUDIT_TRAIL_IMPLEMENTATION.md`** - This guide

### Existing Files (Already Implemented):
1. **`security_manager.php`** - Core audit functions
2. **`aggregation_monitoring_schema.sql`** - Database tables
3. **`simple_setup_aggregation_monitoring.php`** - Setup script

---

## 🗄️ Database Tables

### 1. `data_access_logs`
Tracks all database access operations:
```sql
- id, user_id, user_role
- table_accessed, operation_type
- query_summary, records_affected
- ip_address, user_agent, session_id
- access_timestamp, execution_time_ms
- is_bulk_operation, is_sensitive_data
```

### 2. `data_modification_history`
Tracks field-level data changes:
```sql
- id, user_id, table_name, record_id
- operation_type, field_name
- old_value, new_value
- change_reason, modification_timestamp
- ip_address, requires_approval, approval_status
```

### 3. `bulk_operation_alerts`
Monitors bulk operations:
```sql
- id, user_id, operation_type
- table_accessed, records_count
- threshold_exceeded, alert_level
- alert_timestamp, is_investigated
```

### 4. `role_data_permissions`
Role-based access control:
```sql
- id, role_name, table_name
- can_read, can_create, can_update, can_delete
- can_bulk_export, max_records_per_query
- restricted_fields, conditions_required
```

### 5. `approval_workflows` + `approval_requests` + `approval_actions`
Approval management system.

---

## 🚀 Setup Instructions

### Step 1: Create Database Tables

Run **ONE** of these options:

**Option A: SQL Script**
```bash
mysql -u root asylum_db < aggregation_monitoring_schema.sql
```

**Option B: PHP Setup Script**
```
http://localhost/CSGO/Mental-asylum-and-Rehabilitation-center-CSGO/simple_setup_aggregation_monitoring.php
```

### Step 2: Verify Tables Created
```php
// Check if tables exist
http://localhost/CSGO/Mental-asylum-and-Rehabilitation-center-CSGO/check_dlp.php
```

### Step 3: Access Audit Trail
```
http://localhost/CSGO/Mental-asylum-and-Rehabilitation-center-CSGO/audit_trail.php
```

**Access Requirements:**
- Must be logged in
- Role must be `admin` or `chief-staff`

---

## 💡 How to Use

### Viewing Audit Logs

1. **Access the Dashboard**
   - Navigate to `audit_trail.php`
   - Login as admin or chief-staff

2. **Filter Logs**
   - By Table: Select specific table
   - By Operation: SELECT, INSERT, UPDATE, DELETE
   - By User: See specific user's actions
   - By Date Range: Custom date filtering

3. **View Tabs**
   - **Data Access Logs**: All database queries
   - **Modifications**: Field-level changes
   - **Bulk Alerts**: Large operation warnings

### Automatic Logging

**All database operations are automatically logged when using:**

```php
// SELECT operations
$result = $securityManager->secureSelect(
    "SELECT * FROM patients WHERE id = ?",
    [$patient_id],
    'i'
);

// INSERT/UPDATE/DELETE operations
$result = $securityManager->secureExecute(
    "UPDATE patients SET status = ? WHERE id = ?",
    [$status, $patient_id],
    'si'
);

// WITH modification history tracking
$result = $securityManager->secureExecuteWithHistory(
    "UPDATE staff SET role = ? WHERE staff_id = ?",
    [$new_role, $staff_id],
    'ss',
    "Role change approved by admin"  // Change reason
);
```

### Tracking Modifications

```php
// Example: Update with full history tracking
$securityManager->secureExecuteWithHistory(
    "UPDATE patients SET diagnosis = ?, treatment_plan = ? WHERE patient_id = ?",
    [$new_diagnosis, $new_plan, $patient_id],
    'ssi',
    "Medical review by Dr. Smith"  // Reason for change
);
```

This will:
1. Record the old values BEFORE update
2. Execute the UPDATE
3. Record the new values AFTER update
4. Log each field change separately
5. Store the change reason

---

## 📊 Key Functions

### In `security_manager.php`:

#### 1. `logDataAccess($sql, $table_name, $operation_type, $records_affected, $execution_time)`
**Line:** ~986
**Purpose:** Logs every database access
**Called by:** `secureSelect()`, `secureExecute()`

#### 2. `secureExecuteWithHistory($sql, $params, $types, $change_reason, $track_changes)`
**Line:** ~1200
**Purpose:** Execute queries WITH modification tracking
**Usage:** Update/Delete operations that need audit trail

#### 3. `logDataModification($table_name, $operation_type, $old_values, $sql, $params, $change_reason)`
**Line:** ~1300
**Purpose:** Logs field-level changes
**Called by:** `secureExecuteWithHistory()`

#### 4. `getModificationHistory($table_name, $record_id, $limit)`
**Line:** ~1426
**Purpose:** Retrieve modification history for a record
**Usage:** View change history in UI

#### 5. `getDataAccessSummary($hours)`
**Line:** ~1131
**Purpose:** Aggregate access statistics
**Usage:** Dashboard summaries

---

## 🎯 Usage Examples

### Example 1: View Patient Modification History

```php
// Get all changes to patient #123
$history = $securityManager->getModificationHistory('patients', 123, 50);

foreach ($history as $change) {
    echo "{$change['field_name']}: ";
    echo "{$change['old_value']} → {$change['new_value']} ";
    echo "by {$change['username']} on {$change['modification_timestamp']}";
}
```

### Example 2: Check Bulk Operations

```php
// Get recent bulk operation alerts
$alerts = $securityManager->getBulkOperationAlerts(10);

foreach ($alerts as $alert) {
    if ($alert['alert_level'] === 'CRITICAL') {
        // Send notification to admin
        notifyAdmin($alert);
    }
}
```

### Example 3: Audit User Activity

```php
// Get all actions by a specific user in last 24 hours
$user_activity = $securityManager->getDataAccessSummary(24);

// Filter by user
$user_logs = array_filter($audit_logs, function($log) use ($user_id) {
    return $log['user_id'] == $user_id;
});
```

---

## 🔒 Security Features

### 1. **Automatic Detection**
- SQL injection attempts logged
- Bulk operations flagged
- Sensitive data access tracked

### 2. **Immutable Logs**
- Audit logs cannot be modified
- Only insert operations allowed
- Automatic cleanup after 6 months

### 3. **Role-Based Access**
- Only admin/chief-staff can view audits
- Logs include user role at time of action
- Permission changes are tracked

### 4. **Compliance Ready**
- Field-level change tracking
- Change reason recording
- Complete audit trail
- HIPAA/GDPR support

---

## 📈 Bulk Operation Thresholds

### Default Thresholds (configured in `security_manager.php`):

| Table | Threshold | Description |
|-------|-----------|-------------|
| patients | 50 | Patient records |
| users | 10 | User accounts |
| staff | 20 | Staff records |
| treatments | 100 | Treatment records |
| medicine_stock | 50 | Medicine inventory |
| appointments | 200 | Appointments |
| default | 100 | All other tables |

**Modify thresholds:**
```php
// In security_manager.php __construct()
$this->bulk_operation_thresholds = [
    'patients' => 100,  // Increase patient threshold
    'custom_table' => 25 // Add custom table
];
```

---

## 🧪 Testing

### Test 1: Verify Logging Works
```php
// Make a query
$result = $securityManager->secureSelect(
    "SELECT * FROM patients LIMIT 10",
    [],
    ''
);

// Check audit_trail.php - should see the SELECT logged
```

### Test 2: Test Modification Tracking
```php
// Update a record
$securityManager->secureExecuteWithHistory(
    "UPDATE patients SET status = ? WHERE patient_id = ?",
    ['active', 123],
    'si',
    "Status update test"
);

// Check Modifications tab - should see old→new values
```

### Test 3: Trigger Bulk Alert
```php
// Query exceeding threshold
$result = $securityManager->secureSelect(
    "SELECT * FROM patients LIMIT 100",
    [],
    ''
);

// Check Bulk Alerts tab - should see warning
```

---

## 🐛 Troubleshooting

### Issue 1: "Table doesn't exist"
**Solution:**
```bash
# Run setup script
http://localhost/.../simple_setup_aggregation_monitoring.php

# OR import SQL
mysql -u root asylum_db < aggregation_monitoring_schema.sql
```

### Issue 2: "No logs appearing"
**Check:**
1. Tables created? Run `SHOW TABLES LIKE '%log%';`
2. Using `secureSelect/secureExecute`? Direct `$conn->query()` won't log
3. User logged in? Logs require active session

### Issue 3: "Permission denied"
**Solution:**
- Only admin and chief-staff can access `audit_trail.php`
- Check `$_SESSION['role']`

### Issue 4: "Trigger errors"
**Solution:**
- Triggers are optional
- System works without triggers
- Run `fix_aggregation_monitoring.sql` if needed

---

## 📚 Integration with Existing Code

### Update Existing Files:

**Example: patient_management.php**

**Before:**
```php
$stmt = $conn->prepare("UPDATE patients SET status = ? WHERE id = ?");
$stmt->bind_param("si", $status, $patient_id);
$stmt->execute();
```

**After:**
```php
$securityManager->secureExecuteWithHistory(
    "UPDATE patients SET status = ? WHERE id = ?",
    [$status, $patient_id],
    'si',
    "Status change by " . $_SESSION['username']
);
```

---

## 📝 Compliance & Reporting

### HIPAA Compliance:
- ✅ Access logging
- ✅ Modification tracking
- ✅ User identification
- ✅ Audit trail integrity
- ✅ Data minimization tracking

### Export Audit Report:
```php
// Generate CSV export
$logs = $securityManager->getDataAccessSummary(168); // 7 days

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="audit_report.csv"');

$output = fopen('php://output', 'w');
fputcsv($output, ['Date', 'User', 'Table', 'Operation', 'Records']);

foreach ($logs as $log) {
    fputcsv($output, [
        $log['access_timestamp'],
        $log['username'],
        $log['table_accessed'],
        $log['operation_type'],
        $log['records_affected']
    ]);
}
```

---

## 🎓 Summary

✅ **Implemented:**
- Complete audit trail system
- Field-level modification tracking
- Bulk operation monitoring
- Role-based permissions
- Approval workflows

✅ **Access:**
- URL: `http://localhost/.../audit_trail.php`
- Roles: admin, chief-staff

✅ **Key Features:**
- Real-time logging
- Historical tracking
- Compliance ready
- Security monitoring

✅ **Next Steps:**
1. Run setup script
2. Test logging functionality
3. Review audit trail dashboard
4. Configure role permissions
5. Set up approval workflows

For questions or issues, refer to:
- `2FA_and_Audit_Tools_Summary.txt`
- `SECURITY_IMPLEMENTATION.md`
- `security_manager.php` (functions documentation)

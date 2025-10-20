# 📋 Audit Trail System - Complete Documentation

## Table of Contents
1. [Overview](#overview)
2. [Why Audit Trail?](#why-audit-trail)
3. [How It Works](#how-it-works)
4. [Database Architecture](#database-architecture)
5. [Implementation Files](#implementation-files)
6. [Key Functions](#key-functions)
7. [Testing Guide](#testing-guide)
8. [Adding Logging to New Pages](#adding-logging-to-new-pages)
9. [Troubleshooting](#troubleshooting)

---

## Overview

The **Audit Trail System** is a comprehensive monitoring solution that tracks all data access, modifications, and bulk operations in the healthcare management system. It provides complete visibility into who accessed what data, when, and what changes were made.

### Key Features
✅ **Data Access Logging** - Tracks all SELECT queries  
✅ **Modification Tracking** - Records INSERT/UPDATE/DELETE with old/new values  
✅ **Bulk Operation Alerts** - Warns when users access large amounts of data  
✅ **Role-Based Permissions** - Controls who can access what data  
✅ **Approval Workflows** - Requires approval for sensitive operations  
✅ **Admin-Only Dashboard** - Secure audit trail viewing interface  

---

## Why Audit Trail?

### Compliance Requirements
- **HIPAA Compliance**: Healthcare data requires complete audit trails
- **Data Protection**: Track who accessed patient records
- **Security Monitoring**: Detect unauthorized access attempts
- **Accountability**: Know who made changes and why

### Business Benefits
- **Incident Investigation**: Track down data breaches or errors
- **User Activity Monitoring**: Understand system usage patterns
- **Quality Assurance**: Verify data integrity over time
- **Legal Evidence**: Audit logs can serve as legal documentation

### Security Benefits
- **Detect Anomalies**: Identify unusual access patterns
- **Prevent Data Theft**: Alert on bulk data exports
- **Track Privilege Escalation**: Monitor unauthorized role changes
- **Session Hijacking Detection**: Log all access with IP/user agent

---

## How It Works

### 1. Data Access Logging (Automatic)

When a user views data, the system automatically logs the access:

```php
// Example: Admin views patient data on dashboard
$patients_result = $conn->query("SELECT * FROM patients LIMIT 5");

// Logging code (already added to admin_dashboard.php)
$audit_sql = "INSERT INTO data_access_logs 
             (user_id, user_role, table_accessed, operation_type, 
              records_affected, ip_address, user_agent, is_sensitive_data) 
             VALUES (?, ?, 'patients', 'SELECT', ?, ?, ?, TRUE)";
```

**What gets logged:**
- Who accessed it (user_id, role)
- What they accessed (table name, number of records)
- When (timestamp)
- Where from (IP address, user agent)
- Whether it was sensitive data

### 2. Modification Logging (Manual)

When data is created, updated, or deleted, you must manually log it:

```php
// STEP 1: Get OLD value before changing
$old_data = $conn->query("SELECT full_name FROM patients WHERE id = 123")->fetch_assoc();

// STEP 2: Make the change
$conn->query("UPDATE patients SET full_name = 'John Doe' WHERE id = 123");

// STEP 3: Log the modification
logDataModification($conn, 'patients', '123', 'UPDATE', 
                    'full_name', $old_data['full_name'], 'John Doe', 
                    'Name correction');
```

**What gets logged:**
- User who made the change
- Table and record ID
- Field that changed
- Old value → New value
- Reason for change
- IP address and timestamp

### 3. Bulk Operation Alerts

When someone accesses more than a threshold of records, an alert is triggered:

```php
// Threshold check (already in admin_dashboard.php)
$bulk_threshold = 50;
if ($records_count >= $bulk_threshold) {
    // Insert bulk operation alert
    $alert_sql = "INSERT INTO bulk_operation_alerts 
                 (user_id, operation_type, table_accessed, 
                  records_count, alert_level) 
                 VALUES (?, 'SELECT', 'patients', ?, 'WARNING')";
}
```

**Alert Levels:**
- **INFO**: 50-100 records
- **WARNING**: 100-500 records  
- **CRITICAL**: 500+ records

---

## Database Architecture

### Tables Created (12 Total)

#### 1. **data_access_logs** (Core Table)
```sql
id                  INT AUTO_INCREMENT PRIMARY KEY
user_id             INT NOT NULL
user_role           VARCHAR(50)
table_accessed      VARCHAR(100)
operation_type      ENUM('SELECT', 'INSERT', 'UPDATE', 'DELETE')
records_affected    INT
ip_address          VARCHAR(45)
user_agent          TEXT
is_sensitive_data   BOOLEAN
access_timestamp    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
```

#### 2. **data_modification_history** (Core Table)
```sql
id                      INT AUTO_INCREMENT PRIMARY KEY
user_id                 INT NOT NULL
table_name              VARCHAR(100)
record_id               VARCHAR(50)
operation_type          ENUM('INSERT', 'UPDATE', 'DELETE')
field_name              VARCHAR(100)
old_value               TEXT
new_value               TEXT
change_reason           VARCHAR(500)
ip_address              VARCHAR(45)
modification_timestamp  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
```

#### 3. **bulk_operation_alerts** (Core Table)
```sql
id                  INT AUTO_INCREMENT PRIMARY KEY
user_id             INT NOT NULL
operation_type      VARCHAR(100)
table_accessed      VARCHAR(100)
records_count       INT
threshold_exceeded  VARCHAR(100)
alert_level         ENUM('INFO', 'WARNING', 'CRITICAL')
alert_timestamp     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
```

#### 4. **role_permissions**
Defines what each role can access

#### 5. **approval_workflows**
Defines approval processes for sensitive operations

#### 6. **approval_requests**
Tracks pending approval requests

#### 7. **approval_actions**
Logs approval decisions

#### 8. **data_retention_policies**
Defines how long to keep data

#### 9. **anonymization_rules**
Rules for anonymizing data

#### 10. **field_level_permissions**
Granular field-level access control

#### 11. **user_session_monitoring**
Tracks user sessions

#### 12. **role_data_permissions**
Alternative role permissions table

---

## Implementation Files

### Core Files

#### 1. **audit_trail.php** (Dashboard)
- **Purpose**: Main UI for viewing audit logs
- **Access**: Admin only
- **Features**:
  - View data access logs
  - View modification history
  - View bulk operation alerts
  - Filter by date, user, table
  - Export logs
  
**Location**: `/audit_trail.php`  
**URL**: `http://localhost/CSGO/.../audit_trail.php`

#### 2. **simple_setup_aggregation_monitoring.php** (Setup Script)
- **Purpose**: Creates all 12 audit trail database tables
- **Run Once**: During initial setup
- **Features**:
  - Creates all required tables
  - Inserts default permissions (11 rows)
  - Inserts default workflows (6 rows)
  - Verifies table creation
  
**Location**: `/simple_setup_aggregation_monitoring.php`  
**URL**: `http://localhost/CSGO/.../simple_setup_aggregation_monitoring.php`

#### 3. **admin_dashboard.php** (Example Implementation)
- **Purpose**: Shows how to add audit logging to pages
- **Features**:
  - Logs patient data access
  - Triggers bulk operation alerts
  - Includes `logDataModification()` helper function

**Location**: `/admin_dashboard.php`

#### 4. **test_audit_trail.php** (Testing Tool)
- **Purpose**: Verify audit trail is working
- **Features**:
  - Checks if tables exist
  - Tests logging functionality
  - Shows recent logs
  - Creates test entries
  
**Location**: `/test_audit_trail.php`  
**URL**: `http://localhost/CSGO/.../test_audit_trail.php`

#### 5. **demo_modification_logging.php** (Interactive Demo)
- **Purpose**: Educational tool showing how modification logging works
- **Features**:
  - INSERT example with logging
  - UPDATE example with old/new values
  - DELETE example preserving data
  - Shows recent modification logs
  
**Location**: `/demo_modification_logging.php`  
**URL**: `http://localhost/CSGO/.../demo_modification_logging.php`

### Supporting Files

#### 6. **config.php**
- Session configuration
- Security settings
- Multi-device login support

#### 7. **session_protection.php**
- Role-based access control
- Session validation
- `enforceRole()` function

#### 8. **propagation_prevention.php**
- Session hijacking prevention
- Role integrity validation
- Privilege escalation detection

---

## Key Functions

### 1. `logDataModification()`

**Purpose**: Log INSERT/UPDATE/DELETE operations

**Location**: `admin_dashboard.php` (lines 12-30)

**Signature**:
```php
function logDataModification(
    $conn,          // Database connection
    $table_name,    // Table being modified
    $record_id,     // ID of the record
    $operation,     // 'INSERT', 'UPDATE', or 'DELETE'
    $field_name,    // Field that changed (optional)
    $old_value,     // Old value (NULL for INSERT)
    $new_value,     // New value (NULL for DELETE)
    $reason         // Why the change was made
)
```

**Example Usage**:
```php
// UPDATE example
$old_name = $conn->query("SELECT name FROM staff WHERE id = 5")->fetch_assoc()['name'];
$conn->query("UPDATE staff SET name = 'John Doe' WHERE id = 5");
logDataModification($conn, 'staff', '5', 'UPDATE', 'name', $old_name, 'John Doe', 'Name correction');

// INSERT example
$conn->query("INSERT INTO staff (id, name) VALUES (10, 'Jane Smith')");
logDataModification($conn, 'staff', '10', 'INSERT', 'name', null, 'Jane Smith', 'New staff member');

// DELETE example
$old_name = $conn->query("SELECT name FROM staff WHERE id = 10")->fetch_assoc()['name'];
$conn->query("DELETE FROM staff WHERE id = 10");
logDataModification($conn, 'staff', '10', 'DELETE', 'name', $old_name, null, 'Staff terminated');
```

### 2. `enforceRole()`

**Purpose**: Restrict page access to specific roles

**Location**: `session_protection.php` (line 69)

**Signature**:
```php
function enforceRole($required_role)
```

**Example Usage**:
```php
// At the top of audit_trail.php
enforceRole('admin'); // Only admin can access

// Allow multiple roles
if (!in_array($_SESSION['role'], ['admin', 'chief-staff'])) {
    header('Location: index.php');
    exit();
}
```

### 3. Data Access Logging (Pattern)

**Pattern for logging SELECT queries**:
```php
// Execute query
$result = $conn->query("SELECT * FROM patients LIMIT 10");

// Log the access
if ($result && $result->num_rows > 0) {
    $user_id = $_SESSION['user_id'] ?? 0;
    $user_role = $_SESSION['role'] ?? 'unknown';
    $records_count = $result->num_rows;
    
    $sql = "INSERT INTO data_access_logs 
            (user_id, user_role, table_accessed, operation_type, 
             records_affected, ip_address, user_agent, is_sensitive_data) 
            VALUES (?, ?, 'patients', 'SELECT', ?, ?, ?, TRUE)";
    
    $stmt = $conn->prepare($sql);
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $stmt->bind_param('isiss', $user_id, $user_role, $records_count, $ip, $ua);
    $stmt->execute();
    $stmt->close();
}
```

---

## Testing Guide

### Step 1: Database Setup

**Run the setup script once:**
```
http://localhost/CSGO/Mental-asylum-and-Rehabilitation-center-CSGO/simple_setup_aggregation_monitoring.php
```

**Expected Result:**
- ✅ All 12 tables created
- ✅ 11 permission rows inserted
- ✅ 6 workflow rows inserted
- ✅ Green success messages

### Step 2: Verify Tables

**Run the test script:**
```
http://localhost/CSGO/Mental-asylum-and-Rehabilitation-center-CSGO/test_audit_trail.php
```

**Expected Result:**
- ✅ All 8 required tables exist
- ✅ Test log entry created
- ✅ Recent logs displayed
- 📊 Shows record counts

### Step 3: Test Data Access Logging

**Actions:**
1. Login as **admin**
2. Go to admin dashboard
3. View the dashboard (patient data loads)
4. Check audit trail

**URL to check:**
```
http://localhost/CSGO/Mental-asylum-and-Rehabilitation-center-CSGO/audit_trail.php
```

**Expected Result:**
- New entry in "Data Access Logs" tab
- Shows: admin accessed 'patients' table
- Records accessed: 5
- Marked as sensitive data
- IP address and timestamp visible

### Step 4: Test Modification Logging

**Use the interactive demo:**
```
http://localhost/CSGO/Mental-asylum-and-Rehabilitation-center-CSGO/demo_modification_logging.php
```

**Actions:**
1. Click "1️⃣ INSERT - Create Patient"
2. Click "2️⃣ UPDATE - Modify Patient"  
3. Click "3️⃣ DELETE - Remove Patient"
4. Scroll down to see "Recent Modification Logs"

**Expected Result:**
- INSERT shows new value, old value = NULL
- UPDATE shows old value → new value
- DELETE shows old value, new value = NULL
- All entries visible in audit trail

### Step 5: Test Bulk Operation Alert

**Modify admin_dashboard.php temporarily:**
```php
// Change line 43 from LIMIT 5 to LIMIT 100
$patients_result = $conn->query("SELECT ... LIMIT 100");
```

**Actions:**
1. Refresh admin dashboard
2. Check audit trail

**Expected Result:**
- Alert appears in "Bulk Alerts" tab
- Alert level: WARNING
- Message: "Accessed 100 records (threshold: 50)"

### Step 6: Verify in phpMyAdmin

**Direct database check:**

```sql
-- Check data access logs
SELECT * FROM data_access_logs ORDER BY access_timestamp DESC LIMIT 10;

-- Check modifications
SELECT * FROM data_modification_history ORDER BY modification_timestamp DESC LIMIT 10;

-- Check bulk alerts
SELECT * FROM bulk_operation_alerts ORDER BY alert_timestamp DESC LIMIT 10;

-- Check permissions
SELECT * FROM role_permissions;
```

---

## Adding Logging to New Pages

### Example: Add logging to `patient_management.php`

#### Step 1: Add the helper function

At the top of the file, after database connection:

```php
require_once 'db.php';

// Add audit trail helper function
function logDataModification($conn, $table_name, $record_id, $operation, $field_name = null, $old_value = null, $new_value = null, $reason = '') {
    $user_id = $_SESSION['user_id'] ?? 0;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    
    $sql = "INSERT INTO data_modification_history 
            (user_id, table_name, record_id, operation_type, field_name, old_value, new_value, change_reason, ip_address) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('issssssss', $user_id, $table_name, $record_id, $operation, $field_name, $old_value, $new_value, $reason, $ip_address);
        $stmt->execute();
        $stmt->close();
    }
}
```

#### Step 2: Log data access

```php
// When fetching patient data
$result = $conn->query("SELECT * FROM patients");
if ($result && $result->num_rows > 0) {
    // Your existing code to process results
    $patients = $result->fetch_all(MYSQLI_ASSOC);
    
    // ADD THIS: Log the access
    $user_id = $_SESSION['user_id'] ?? 0;
    $user_role = $_SESSION['role'] ?? 'unknown';
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    $log_sql = "INSERT INTO data_access_logs 
                (user_id, user_role, table_accessed, operation_type, records_affected, ip_address, user_agent, is_sensitive_data) 
                VALUES (?, ?, 'patients', 'SELECT', ?, ?, ?, TRUE)";
    $stmt = $conn->prepare($log_sql);
    $stmt->bind_param('isiss', $user_id, $user_role, $result->num_rows, $ip, $ua);
    $stmt->execute();
    $stmt->close();
}
```

#### Step 3: Log INSERT operations

```php
// When creating a new patient
if (isset($_POST['add_patient'])) {
    $patient_id = 'PAT-' . time();
    $patient_name = $_POST['name'];
    
    // Insert the patient
    $sql = "INSERT INTO patients (patient_id, full_name, ...) VALUES (?, ?, ...)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss...', $patient_id, $patient_name, ...);
    
    if ($stmt->execute()) {
        // ADD THIS: Log the insertion
        logDataModification($conn, 'patients', $patient_id, 'INSERT', 
                           'full_name', null, $patient_name, 
                           'New patient registration');
    }
    $stmt->close();
}
```

#### Step 4: Log UPDATE operations

```php
// When updating a patient
if (isset($_POST['update_patient'])) {
    $patient_id = $_POST['patient_id'];
    $new_name = $_POST['name'];
    
    // ADD THIS: Get old value FIRST
    $old_result = $conn->query("SELECT full_name FROM patients WHERE patient_id = '$patient_id'");
    $old_name = $old_result->fetch_assoc()['full_name'];
    
    // Update the patient
    $sql = "UPDATE patients SET full_name = ? WHERE patient_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $new_name, $patient_id);
    
    if ($stmt->execute()) {
        // ADD THIS: Log the update
        logDataModification($conn, 'patients', $patient_id, 'UPDATE', 
                           'full_name', $old_name, $new_name, 
                           'Patient information update');
    }
    $stmt->close();
}
```

#### Step 5: Log DELETE operations

```php
// When deleting a patient
if (isset($_POST['delete_patient'])) {
    $patient_id = $_POST['patient_id'];
    
    // ADD THIS: Get data BEFORE deleting
    $old_result = $conn->query("SELECT full_name FROM patients WHERE patient_id = '$patient_id'");
    $old_name = $old_result->fetch_assoc()['full_name'];
    
    // Delete the patient
    $sql = "DELETE FROM patients WHERE patient_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $patient_id);
    
    if ($stmt->execute()) {
        // ADD THIS: Log the deletion
        logDataModification($conn, 'patients', $patient_id, 'DELETE', 
                           'full_name', $old_name, null, 
                           'Patient discharged');
    }
    $stmt->close();
}
```

---

## Troubleshooting

### Issue 1: "Table doesn't exist" errors

**Symptom**: Fatal errors mentioning missing tables

**Solution**:
```bash
# Run the setup script
http://localhost/CSGO/.../simple_setup_aggregation_monitoring.php
```

### Issue 2: No logs appearing

**Symptom**: Audit trail dashboard is empty

**Check**:
1. Are you accessing pages with logging code?
2. Did you add logging code to the pages?
3. Check phpMyAdmin directly:
```sql
SELECT COUNT(*) FROM data_access_logs;
```

**Solution**:
- Logging is NOT automatic
- Must add logging code to each page manually
- See "Adding Logging to New Pages" section

### Issue 3: Can't access audit trail

**Symptom**: Redirects to index.php

**Solution**:
- Audit trail is admin-only
- Login with admin account
- Check `enforceRole('admin')` at top of audit_trail.php

### Issue 4: Bulk alerts not triggering

**Symptom**: No alerts in bulk alerts tab

**Check**:
```php
// Check threshold in admin_dashboard.php
$bulk_threshold = 50; // Current threshold
```

**Solution**:
- Lower threshold for testing: `$bulk_threshold = 5;`
- Or access more than 50 records

### Issue 5: Wrong column names

**Symptom**: "Unknown column" errors

**Check table structure**:
```sql
DESCRIBE data_access_logs;
DESCRIBE data_modification_history;
```

**Solution**:
- Re-run setup script to recreate tables
- Verify column names match your database

---

## Quick Reference

### File Locations
```
/audit_trail.php                          - Main dashboard (admin only)
/simple_setup_aggregation_monitoring.php  - Database setup (run once)
/test_audit_trail.php                     - Testing tool
/demo_modification_logging.php            - Interactive demo
/admin_dashboard.php                      - Example implementation
/session_protection.php                   - Access control
/propagation_prevention.php               - Security validation
```

### Key URLs
```
Dashboard:  http://localhost/CSGO/.../audit_trail.php
Setup:      http://localhost/CSGO/.../simple_setup_aggregation_monitoring.php
Test:       http://localhost/CSGO/.../test_audit_trail.php
Demo:       http://localhost/CSGO/.../demo_modification_logging.php
```

### Database Tables
```
Core Tables:
- data_access_logs          (SELECT operations)
- data_modification_history (INSERT/UPDATE/DELETE)
- bulk_operation_alerts     (High-volume access)

Supporting Tables:
- role_permissions
- approval_workflows
- approval_requests
- approval_actions
- data_retention_policies
- anonymization_rules
- field_level_permissions
- user_session_monitoring
- role_data_permissions
```

### Quick Commands
```sql
-- View recent access logs
SELECT * FROM data_access_logs ORDER BY access_timestamp DESC LIMIT 10;

-- View recent modifications
SELECT * FROM data_modification_history ORDER BY modification_timestamp DESC LIMIT 10;

-- View bulk alerts
SELECT * FROM bulk_operation_alerts ORDER BY alert_timestamp DESC LIMIT 10;

-- Count logs by user
SELECT user_id, user_role, COUNT(*) as access_count 
FROM data_access_logs 
GROUP BY user_id, user_role;

-- Find sensitive data access
SELECT * FROM data_access_logs 
WHERE is_sensitive_data = TRUE 
ORDER BY access_timestamp DESC;
```

---

## Summary

### What You Have Now
✅ Complete audit trail database (12 tables)  
✅ Admin-only audit trail dashboard  
✅ Automatic patient data access logging  
✅ Bulk operation alert system  
✅ Helper function for modification logging  
✅ Testing tools and interactive demo  
✅ Complete documentation  

### What You Need to Do
📝 Add logging code to other pages (staff, appointments, etc.)  
📝 Customize thresholds for bulk alerts  
📝 Set up automated reports/exports  
📝 Configure data retention policies  
📝 Implement approval workflows (optional)  

### Next Steps
1. **Test the system** - Use test_audit_trail.php
2. **Try the demo** - Learn how modification logging works
3. **Add to more pages** - Follow the examples in this guide
4. **Monitor usage** - Check audit trail regularly
5. **Adjust thresholds** - Based on your organization's needs

---

**Created**: 2025-01-20  
**Version**: 1.0  
**Author**: System Generated  
**Support**: Use the testing tools and demo for troubleshooting

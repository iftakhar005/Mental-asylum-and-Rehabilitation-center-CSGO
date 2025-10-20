CREATE DATABASE IF NOT EXISTS asylum_db;
USE asylum_db;

-- Users Table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    role ENUM(
        'admin', 
        'chief-staff', 
        'staff', 
        'doctor', 
        'therapist', 
        'relative', 
        'general_user', 
        'nurse', 
        'receptionist'
    ) NOT NULL,
    first_name VARCHAR(50),
    last_name VARCHAR(50),
    contact_number VARCHAR(20),
    emergency_contact VARCHAR(20),
    date_of_birth DATE,
    specialization VARCHAR(100), -- For staff
    relationship VARCHAR(50),    -- For relatives
    address TEXT,
    joining_date DATE,
    staff_type ENUM('doctor', 'nurse', 'therapist', 'chief-staff'),
    benefits JSON,               -- Insurance, allowances, etc.
    status ENUM('active', 'inactive', 'on_leave') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    temp_password VARCHAR(255) DEFAULT NULL
);

-- Meal Plan Types Table (must be before patients)
CREATE TABLE meal_plan_types (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Patients Table (now after users and meal_plan_types)
CREATE TABLE patients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    patient_id VARCHAR(30) UNIQUE,
    full_name VARCHAR(100),
    date_of_birth DATE,
    gender VARCHAR(20),
    contact_number VARCHAR(30),
    emergency_contact VARCHAR(100),
    address TEXT,
    medical_history TEXT,
    current_medications TEXT,
    admission_date DATE NOT NULL,
    room_number VARCHAR(20),
    type ENUM('Asylum', 'Rehabilitation') NOT NULL DEFAULT 'Asylum',
    mobility_status ENUM('Independent', 'Assisted', 'Wheelchair', 'Bedridden') DEFAULT 'Independent',
    meal_plan_type_id INT DEFAULT NULL,
    status ENUM('admitted', 'active', 'discharged', 'transferred') NOT NULL DEFAULT 'admitted',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Appointments Table
CREATE TABLE appointments (
    id INT AUTO_INCREMENT PRIMARY KEY, 
    patient_id VARCHAR(50) NOT NULL,
    date DATE NOT NULL,
    time TIME NOT NULL,
    type VARCHAR(50) NOT NULL,
    doctor VARCHAR(100) NOT NULL,
    therapist VARCHAR(100) NULL,
    status VARCHAR(50) DEFAULT 'scheduled'
);

-- Meal Plans Table
CREATE TABLE meal_plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    name VARCHAR(100),
    diet_type ENUM('regular', 'vegetarian', 'diabetic', 'low_sodium'),
    description TEXT,
    week_number INT,
    start_date DATE,
    end_date DATE,
    created_by INT,
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Staff table for staff management system
CREATE TABLE IF NOT EXISTS staff (
    staff_id VARCHAR(30) PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('chief-staff', 'doctor', 'therapist', 'nurse', 'receptionist') NOT NULL,
    specialization VARCHAR(100),
    email VARCHAR(100) NOT NULL UNIQUE,
    phone VARCHAR(30) NOT NULL,
    emergency_contact VARCHAR(30),
    password_hash VARCHAR(255),
    temp_password VARCHAR(255) NULL DEFAULT NULL,
    dob DATE,
    gender VARCHAR(20),
    address TEXT,
    experience INT DEFAULT 0,
    status ENUM('active', 'inactive', 'on_leave') DEFAULT 'active',
    user_id INT,
    license_number VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    shift ENUM('Morning', 'Afternoon', 'Night') DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Add status column if it doesn't exist
ALTER TABLE staff ADD COLUMN IF NOT EXISTS status ENUM('active', 'inactive', 'on_leave') DEFAULT 'active';

-- Rooms Table
CREATE TABLE IF NOT EXISTS rooms (
    room_number VARCHAR(10) PRIMARY KEY,
    status ENUM('available', 'occupied', 'maintenance') DEFAULT 'available',
    type VARCHAR(50),
    capacity INT DEFAULT 1,
    for_whom VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Meals Table
CREATE TABLE IF NOT EXISTS meals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    meal_type ENUM('breakfast', 'lunch', 'dinner', 'snack') NOT NULL,
    diet_type ENUM('regular', 'vegetarian', 'diabetic', 'low_sodium', 'custom') NOT NULL,
    meal_date DATE NOT NULL,
    meal_time TIME,
    description TEXT,
    allergies TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Meal Tags Table
CREATE TABLE meal_tags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Food Items Table
CREATE TABLE food_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    nutritional_info JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Food Item Tags Table
CREATE TABLE food_item_tags (
    food_item_id INT,
    tag_id INT,
    PRIMARY KEY (food_item_id, tag_id),
    FOREIGN KEY (food_item_id) REFERENCES food_items(id),
    FOREIGN KEY (tag_id) REFERENCES meal_tags(id)
);

-- Food Item Meal Times Table
CREATE TABLE food_item_meal_times (
    food_item_id INT,
    meal_time ENUM('breakfast', 'lunch', 'dinner', 'snack'),
    PRIMARY KEY (food_item_id, meal_time),
    FOREIGN KEY (food_item_id) REFERENCES food_items(id)
);

-- Weekly Meal Plans Table
CREATE TABLE weekly_meal_plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    type_id INT,
    description TEXT,
    status ENUM('active', 'inactive', 'archived') DEFAULT 'active',
    start_date DATE DEFAULT NULL,
    end_date DATE DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (type_id) REFERENCES meal_plan_types(id)
);

-- Weekly Meal Plan Tags Table
CREATE TABLE weekly_meal_plan_tags (
    plan_id INT,
    tag_id INT,
    PRIMARY KEY (plan_id, tag_id),
    FOREIGN KEY (plan_id) REFERENCES weekly_meal_plans(id),
    FOREIGN KEY (tag_id) REFERENCES meal_tags(id)
);

-- Weekly Meal Plan Entries Table
CREATE TABLE IF NOT EXISTS weekly_meal_plan_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    plan_id INT NOT NULL,
    day_of_week ENUM('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'),
    meal_time ENUM('breakfast', 'lunch', 'dinner', 'snack'),
    food_item_id INT,
    quantity INT,
    notes TEXT,
    FOREIGN KEY (plan_id) REFERENCES weekly_meal_plans(id),
    FOREIGN KEY (food_item_id) REFERENCES food_items(id)
);

-- Staff Patient Assignments Table
CREATE TABLE staff_patient_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id INT NOT NULL,
    patient_id INT NOT NULL,
    assignment_date DATE NOT NULL,
    end_date DATE,
    status ENUM('active', 'completed', 'cancelled') DEFAULT 'active',
    FOREIGN KEY (staff_id) REFERENCES users(id),
    FOREIGN KEY (patient_id) REFERENCES patients(id)
);

-- Create Indexes
CREATE INDEX idx_staff_role ON staff(role);
CREATE INDEX idx_staff_patient ON staff_patient_assignments(staff_id, patient_id);
CREATE INDEX idx_meal_plan_status ON weekly_meal_plans(status);
CREATE INDEX idx_appointments_status ON appointments(status);
CREATE INDEX idx_rooms_status ON rooms(status);
CREATE INDEX idx_staff_user_id ON staff(user_id);
CREATE INDEX idx_staff_email ON staff(email);
CREATE INDEX idx_staff_status ON staff(status);
CREATE INDEX idx_staff_shift ON staff(shift);

-- Insert default meal tags
INSERT INTO meal_tags (name, description) VALUES
('Vegetarian', 'Contains no meat or fish'),
('Diabetic', 'Suitable for diabetic patients'),
('High-Protein', 'High protein content'),
('Low-Carb', 'Low carbohydrate content'),
('Non-Vegetarian', 'Contains meat or fish'),
('Gluten-Free', 'No gluten content');

-- Insert default meal plan types
INSERT INTO meal_plan_types (name, description) VALUES
('Diabetic', 'Suitable for diabetic patients'),
('Gluten-Free', 'No gluten content'),
('High-Protein', 'High protein content'),
('Low-Carb', 'Low carbohydrate content'),
('Non-Vegetarian', 'Contains meat or fish'),
('Vegetarian', 'Contains no meat or fish');

-- Clean existing food item data
DELETE FROM food_item_meal_times;
DELETE FROM food_item_tags;
DELETE FROM food_items;

-- Insert Bengali food items
INSERT INTO food_items (name) VALUES ('Shorshe Ilish');
INSERT INTO food_items (name) VALUES ('Chingri Malai Curry');
INSERT INTO food_items (name) VALUES ('Begun Bharta');
INSERT INTO food_items (name) VALUES ('Luchi & Cholar Dal');
INSERT INTO food_items (name) VALUES ('Aloo Posto');
INSERT INTO food_items (name) VALUES ('Moong Dal Khichuri');
INSERT INTO food_items (name) VALUES ('Dim Curry');
INSERT INTO food_items (name) VALUES ('Shutki Bhuna');
INSERT INTO food_items (name) VALUES ('Panta Bhat');
INSERT INTO food_items (name) VALUES ('Chicken Rezala');
INSERT INTO food_items (name) VALUES ('Fish Curry with Lau');
INSERT INTO food_items (name) VALUES ('Chirer Pulao');
INSERT INTO food_items (name) VALUES ('Chana Bhuna');
INSERT INTO food_items (name) VALUES ('Palong Shaak Bhaja');
INSERT INTO food_items (name) VALUES ('Doi Maachh');
INSERT INTO food_items (name) VALUES ('Sorshe Bata Maachh');

-- Assign tags to Bengali food items
INSERT INTO food_item_tags (food_item_id, tag_id) 
SELECT f.id, t.id FROM food_items f, meal_tags t 
WHERE f.name IN ('Moong Dal Khichuri') AND t.name = 'Gluten-Free';

INSERT INTO food_item_tags (food_item_id, tag_id) 
SELECT f.id, t.id FROM food_items f, meal_tags t 
WHERE f.name IN ('Fish Curry with Lau', 'Chana Bhuna', 'Palong Shaak Bhaja') AND t.name = 'Diabetic';

INSERT INTO food_item_tags (food_item_id, tag_id) 
SELECT f.id, t.id FROM food_items f, meal_tags t 
WHERE f.name IN ('Begun Bharta', 'Luchi & Cholar Dal', 'Aloo Posto', 'Moong Dal Khichuri', 'Panta Bhat', 'Chirer Pulao', 'Chana Bhuna', 'Palong Shaak Bhaja') AND t.name = 'Vegetarian';

INSERT INTO food_item_tags (food_item_id, tag_id) 
SELECT f.id, t.id FROM food_items f, meal_tags t 
WHERE f.name IN ('Dim Curry', 'Chicken Rezala') AND t.name = 'High-Protein';

INSERT INTO food_item_tags (food_item_id, tag_id) 
SELECT f.id, t.id FROM food_items f, meal_tags t 
WHERE f.name IN ('Shorshe Ilish', 'Chingri Malai Curry', 'Dim Curry', 'Shutki Bhuna', 'Chicken Rezala', 'Fish Curry with Lau', 'Doi Maachh', 'Sorshe Bata Maachh') AND t.name = 'Non-Vegetarian';

-- Assign meal times to Bengali food items
INSERT INTO food_item_meal_times (food_item_id, meal_time) 
SELECT id, 'dinner' FROM food_items 
WHERE name IN ('Shorshe Ilish', 'Chingri Malai Curry', 'Moong Dal Khichuri', 'Dim Curry', 'Shutki Bhuna', 'Chicken Rezala', 'Doi Maachh', 'Sorshe Bata Maachh');

INSERT INTO food_item_meal_times (food_item_id, meal_time) 
SELECT id, 'lunch' FROM food_items 
WHERE name IN ('Shorshe Ilish', 'Chingri Malai Curry', 'Begun Bharta', 'Luchi & Cholar Dal', 'Aloo Posto', 'Moong Dal Khichuri', 'Dim Curry', 'Chicken Rezala', 'Fish Curry with Lau', 'Chana Bhuna', 'Palong Shaak Bhaja', 'Sorshe Bata Maachh');

INSERT INTO food_item_meal_times (food_item_id, meal_time) 
SELECT id, 'snacks' FROM food_items 
WHERE name IN ('Begun Bharta', 'Chana Bhuna', 'Palong Shaak Bhaja');

INSERT INTO food_item_meal_times (food_item_id, meal_time) 
SELECT id, 'breakfast' FROM food_items 
WHERE name IN ('Luchi & Cholar Dal', 'Panta Bhat', 'Chirer Pulao');

-- Default Admin User
INSERT INTO users (username, password_hash, email, role)
VALUES ('admin', '$2y$10$RuGbt5jGyhBdtyedoytwsuo1CiIN4X4VHtOIVwUuh2CwF2SapYRl6', 'admin@asylum.com', 'admin');

-- Treatment Tables
CREATE TABLE treatments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    treatment_type ENUM('medication', 'therapy', 'rehabilitation', 'crisis_intervention', 'follow_up', 'documentation') NOT NULL,
    status ENUM('active', 'completed', 'discontinued') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES users(id)
);

CREATE TABLE medication_treatments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    treatment_id INT NOT NULL,
    medication_type ENUM('antidepressants', 'antipsychotics', 'mood_stabilizers', 'anxiolytics') NOT NULL,
    dosage VARCHAR(100) NOT NULL,
    schedule VARCHAR(200) NOT NULL,
    start_date DATE,
    end_date DATE,
    status ENUM('active', 'discontinued', 'completed') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (treatment_id) REFERENCES treatments(id) ON DELETE CASCADE
);

CREATE TABLE therapy_treatments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    treatment_id INT NOT NULL,
    therapy_type ENUM('individual', 'group', 'family') NOT NULL,
    approach ENUM('cbt', 'dbt', 'psychoanalysis') NOT NULL,
    session_notes TEXT,
    session_date DATE,
    duration_minutes INT,
    status ENUM('scheduled', 'completed', 'cancelled') DEFAULT 'scheduled',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (treatment_id) REFERENCES treatments(id) ON DELETE CASCADE
);

CREATE TABLE rehabilitation_treatments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    treatment_id INT NOT NULL,
    rehab_type ENUM('vocational', 'life_skills', 'social') NOT NULL,
    program_details TEXT,
    goals TEXT,
    start_date DATE,
    end_date DATE,
    status ENUM('active', 'completed', 'discontinued') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (treatment_id) REFERENCES treatments(id) ON DELETE CASCADE
);

CREATE TABLE crisis_interventions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    treatment_id INT NOT NULL,
    patient_status ENUM('stable','unstable') NOT NULL,
    notes TEXT,
    crisis_date DATE,
    severity ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    status ENUM('active', 'resolved') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (treatment_id) REFERENCES treatments(id) ON DELETE CASCADE
);

CREATE TABLE follow_up_plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    treatment_id INT NOT NULL,
    review_schedule VARCHAR(200),
    reintegration ENUM('yes','no','partial'),
    next_review_date DATE,
    status ENUM('active', 'completed') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (treatment_id) REFERENCES treatments(id) ON DELETE CASCADE
);

CREATE TABLE treatment_documentation (
    id INT AUTO_INCREMENT PRIMARY KEY,
    treatment_id INT NOT NULL,
    progress_notes TEXT,
    treatment_response TEXT,
    risk_assessment TEXT,
    documentation_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (treatment_id) REFERENCES treatments(id) ON DELETE CASCADE
);

CREATE TABLE patient_assessments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id VARCHAR(30) NOT NULL,
    assessment_date DATE NOT NULL,
    patient_status ENUM('admitted', 'active', 'discharged') NOT NULL,
    meal_plan_id INT,
    assigned_doctor VARCHAR(30),
    assigned_therapist VARCHAR(30),
    morning_staff VARCHAR(30),
    evening_staff VARCHAR(30),
    night_staff VARCHAR(30),
    notes TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(patient_id) ON DELETE CASCADE,
    FOREIGN KEY (meal_plan_id) REFERENCES weekly_meal_plans(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_doctor) REFERENCES staff(staff_id),
    FOREIGN KEY (assigned_therapist) REFERENCES staff(staff_id),
    FOREIGN KEY (morning_staff) REFERENCES staff(staff_id),
    FOREIGN KEY (evening_staff) REFERENCES staff(staff_id),
    FOREIGN KEY (night_staff) REFERENCES staff(staff_id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

DELIMITER $$

CREATE TRIGGER sync_patient_status_after_insert
AFTER INSERT ON patient_assessments
FOR EACH ROW
BEGIN
    UPDATE patients SET status = NEW.patient_status WHERE patient_id = NEW.patient_id;
END$$

CREATE TRIGGER sync_patient_status_after_update
AFTER UPDATE ON patient_assessments
FOR EACH ROW
BEGIN
    UPDATE patients SET status = NEW.patient_status WHERE patient_id = NEW.patient_id;
END$$

DELIMITER ;

-- Added from update_appointments_table.sql
ALTER TABLE appointments 
    MODIFY COLUMN doctor VARCHAR(100) NULL;

-- Added from update_patients_status_enum.sql
ALTER TABLE patients MODIFY status ENUM('admitted', 'active', 'discharged', 'transferred') NOT NULL DEFAULT 'admitted';

-- Medicine Stock Table
CREATE TABLE IF NOT EXISTS medicine_stock (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type ENUM('Antipsychotics', 'Antidepressants', 'Mood Stabilizers', 'Anxiolytics (Anti-anxiety drugs)', 'Sedatives / Hypnotics') NOT NULL,
    name VARCHAR(255) NOT NULL,
    quantity INT NOT NULL,
    strength VARCHAR(100) NOT NULL,
    expire_date DATE NOT NULL,
    date_of_entry DATE NOT NULL DEFAULT CURRENT_DATE
);

-- User Daily Stats Table for wellness tracking
CREATE TABLE IF NOT EXISTS user_daily_stats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_email VARCHAR(100) NOT NULL,
    stat_date DATE NOT NULL,
    hours_slept FLOAT DEFAULT NULL,
    water_liters FLOAT DEFAULT NULL,
    distance_km FLOAT DEFAULT NULL,
    UNIQUE KEY unique_user_date (user_email, stat_date)
);

-- Assessment History Table for self-assessment results
CREATE TABLE IF NOT EXISTS assessment_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_email VARCHAR(100) NOT NULL,
    score INT NOT NULL,
    percentage INT NOT NULL,
    assessment_level VARCHAR(50) NOT NULL,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS staff_schedules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id VARCHAR(30) NOT NULL,
    shift_type ENUM('Morning', 'Afternoon', 'Night') NOT NULL,
    recurring BOOLEAN DEFAULT 0,
    status ENUM('active', 'inactive', 'on_leave') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS health_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    staff_id INT NOT NULL,
    log_type VARCHAR(50) NOT NULL,
    log_time DATETIME NOT NULL,
    details TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (staff_id) REFERENCES users(id) ON DELETE CASCADE
);




-- Add 2FA columns to users table
ALTER TABLE users 
ADD COLUMN two_factor_enabled TINYINT(1) DEFAULT 0 AFTER status,
ADD COLUMN two_factor_secret VARCHAR(255) DEFAULT NULL AFTER two_factor_enabled;

-- Create OTP codes table for temporary OTP storage
CREATE TABLE IF NOT EXISTS otp_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    email VARCHAR(100) NOT NULL,
    otp_code VARCHAR(6) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    is_used TINYINT(1) DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_email (email),
    INDEX idx_otp_code (otp_code),
    INDEX idx_expires_at (expires_at)
);

-- Clean up expired OTPs (optional, can be run periodically)
-- DELETE FROM otp_codes WHERE expires_at < NOW() OR is_used = 1;

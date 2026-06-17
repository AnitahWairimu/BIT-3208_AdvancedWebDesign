USE smart_queue;

-- Run this only if you already created the old SmartQueue database.
-- For a fresh install, use schema.sql instead.

ALTER TABLE users
    CHANGE id user_id INT AUTO_INCREMENT,
    CHANGE password password_hash VARCHAR(255) NOT NULL,
    MODIFY role ENUM('admin', 'staff', 'patient') NOT NULL,
    ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

CREATE TABLE IF NOT EXISTS patients (
    patient_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    full_name VARCHAR(120) NOT NULL,
    phone_number VARCHAR(30) NOT NULL,
    gender ENUM('female', 'male', 'other') NOT NULL,
    date_of_birth DATE NOT NULL,
    medical_id VARCHAR(40) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS staff (
    staff_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    full_name VARCHAR(120) NOT NULL,
    department VARCHAR(80) NOT NULL DEFAULT 'Reception',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

INSERT INTO staff (user_id, full_name, department)
SELECT user_id, name, CASE WHEN role = 'admin' THEN 'Administration' ELSE 'Reception' END
FROM users
WHERE role IN ('admin', 'staff')
ON DUPLICATE KEY UPDATE full_name = VALUES(full_name);

CREATE TABLE IF NOT EXISTS queue_new (
    queue_id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    queue_number VARCHAR(20) NOT NULL UNIQUE,
    status ENUM('waiting', 'called', 'in_consultation', 'completed', 'cancelled') NOT NULL DEFAULT 'waiting',
    arrival_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    service_time TIMESTAMP NULL DEFAULT NULL,
    completed_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (patient_id) REFERENCES patients(patient_id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS announcements (
    announcement_id INT AUTO_INCREMENT PRIMARY KEY,
    message VARCHAR(255) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO announcements (message)
VALUES ('Welcome to SmartQueue. Please watch your queue number and remain near the waiting area.');

RENAME TABLE queue TO queue_old;
RENAME TABLE queue_new TO queue;

-- The previous queue data is preserved in queue_old because the old table did not
-- contain patient_id records compatible with the new role-based dashboards.

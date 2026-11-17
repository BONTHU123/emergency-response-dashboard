CREATE DATABASE IF NOT EXISTS emergency_dashboard
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE emergency_dashboard;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(50) NOT NULL DEFAULT 'Admin',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Demo-only initial account. Change this password before any shared deployment.
INSERT IGNORE INTO users (username, password, role)
VALUES ('admin', 'admin123', 'Admin');

-- =========================================================
-- INCIDENTS TABLE
-- =========================================================

CREATE TABLE IF NOT EXISTS incidents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    location VARCHAR(255) NOT NULL,
    priority VARCHAR(50) NOT NULL DEFAULT 'Medium',
    status VARCHAR(50) NOT NULL DEFAULT 'Active',
    responder VARCHAR(255) DEFAULT '',
    description TEXT,
    people_rescued INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved_at DATETIME NULL
);

-- Add missing incident columns if your old table already exists
ALTER TABLE incidents
ADD COLUMN IF NOT EXISTS responder VARCHAR(255) DEFAULT '';

ALTER TABLE incidents
ADD COLUMN IF NOT EXISTS description TEXT;

ALTER TABLE incidents
ADD COLUMN IF NOT EXISTS people_rescued INT NOT NULL DEFAULT 0;

ALTER TABLE incidents
ADD COLUMN IF NOT EXISTS resolved_at DATETIME NULL;


-- =========================================================
-- RESPONSE TEAM TABLE
-- =========================================================

CREATE TABLE IF NOT EXISTS response_team (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    role VARCHAR(255) NOT NULL,
    phone VARCHAR(50) DEFAULT '',
    status VARCHAR(50) DEFAULT 'Available',
    assigned_incident VARCHAR(255) DEFAULT '',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);


-- =========================================================
-- RESOURCES TABLE
-- =========================================================

CREATE TABLE IF NOT EXISTS resources (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE,
    quantity INT NOT NULL DEFAULT 0,
    unit VARCHAR(100) DEFAULT '',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP
);

ALTER TABLE resources
ADD COLUMN IF NOT EXISTS unit VARCHAR(100) DEFAULT '';

ALTER TABLE resources
ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP;


-- =========================================================
-- VICTIMS TABLE
-- =========================================================

CREATE TABLE IF NOT EXISTS victims (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    location VARCHAR(255) DEFAULT '',
    condition_status VARCHAR(100) DEFAULT 'Unknown',
    rescue_status VARCHAR(100) DEFAULT 'Pending',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

ALTER TABLE victims
ADD COLUMN IF NOT EXISTS condition_status VARCHAR(100)
    DEFAULT 'Unknown';

ALTER TABLE victims
ADD COLUMN IF NOT EXISTS rescue_status VARCHAR(100)
    DEFAULT 'Pending';

ALTER TABLE victims
ADD COLUMN IF NOT EXISTS notes TEXT;


-- =========================================================
-- ACTIVITY HISTORY TABLE
-- =========================================================

CREATE TABLE IF NOT EXISTS activity_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    activity VARCHAR(255) NOT NULL,
    details TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS emergency_alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type VARCHAR(100) NOT NULL DEFAULT 'Other',
    priority VARCHAR(20) NOT NULL DEFAULT 'Medium',
    location VARCHAR(255) NOT NULL,
    message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_emergency_alerts_created_at (created_at)
);


-- =========================================================
-- DEFAULT RESPONSE TEAMS
-- =========================================================

INSERT IGNORE INTO response_team
    (name, role, phone, status, assigned_incident)
VALUES
    ('Team Alpha', 'Rescue Team', '', 'Available', ''),
    ('Team Bravo', 'Medical Team', '', 'Available', ''),
    ('Team Charlie', 'Fire & Rescue', '', 'Available', ''),
    ('Team Delta', 'Emergency Team', '', 'Available', '');


-- =========================================================
-- DEFAULT RESOURCES
-- =========================================================

INSERT IGNORE INTO resources
    (name, quantity, unit)
VALUES
    ('Ambulance', 10, 'vehicles'),
    ('Medical Kit', 25, 'kits'),
    ('Fire Truck', 5, 'vehicles'),
    ('Water', 100, 'litres'),
    ('Rescue Kit', 15, 'kits');


-- =========================================================
-- DEFAULT ACTIVITY
-- =========================================================

INSERT INTO activity_history (activity, details)
SELECT 'System Started', 'Emergency Response Dashboard initialized'
WHERE NOT EXISTS (
    SELECT 1 FROM activity_history
    WHERE activity = 'System Started'
      AND details = 'Emergency Response Dashboard initialized'
);


-- =========================================================
-- CHECK DATA
-- =========================================================

SELECT * FROM incidents;

SELECT * FROM response_team;

SELECT * FROM resources;

SELECT * FROM victims;

SELECT * FROM activity_history;

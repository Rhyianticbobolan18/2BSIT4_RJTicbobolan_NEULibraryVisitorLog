-- 1. Create Departments
CREATE TABLE departments (
    departmentID VARCHAR(10) PRIMARY KEY,
    departmentName VARCHAR(100) NOT NULL
);

-- 2. Create Students (Starts at 2600001)
CREATE TABLE students (
    studentID INT AUTO_INCREMENT PRIMARY KEY,
    firstName VARCHAR(50) NOT NULL,
    lastName VARCHAR(50) NOT NULL,
    institutionalEmail VARCHAR(100) UNIQUE NOT NULL,
    departmentID VARCHAR(10),
    role ENUM('Student') DEFAULT 'Student',
    status ENUM('Active', 'Blocked') DEFAULT 'Active',
    profile_image VARCHAR(255) DEFAULT 'default.png',
    FOREIGN KEY (departmentID) REFERENCES departments(departmentID)
) AUTO_INCREMENT = 2600001;

-- 3. Create Employees (Starts at 2610001)
CREATE TABLE employees (
    emplID INT AUTO_INCREMENT PRIMARY KEY,
    firstName VARCHAR(50) NOT NULL,
    lastName VARCHAR(50) NOT NULL,
    institutionalEmail VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    departmentID VARCHAR(10),
    role ENUM('Faculty/Admin') DEFAULT 'Faculty/Admin',
    status ENUM('Active', 'Blocked') DEFAULT 'Active',
    profile_image VARCHAR(255) DEFAULT 'default.png',
    FOREIGN KEY (departmentID) REFERENCES departments(departmentID)
) AUTO_INCREMENT = 2610001;

-- 4. Create History Logs
CREATE TABLE history_logs (
    logID INT AUTO_INCREMENT PRIMARY KEY,
    user_identifier INT NOT NULL,
    user_type ENUM('Student', 'Employee') NOT NULL,
    date DATE DEFAULT (CURRENT_DATE),
    time TIME DEFAULT (CURRENT_TIME),
    reason VARCHAR(100) NOT NULL 
);

CREATE TABLE problem_reports (
    reportID INT(11) PRIMARY KEY AUTO_INCREMENT,
    user_identifier VARCHAR(20) NOT NULL,
    issue_type VARCHAR(50) NOT NULL,
    description TEXT,
    status ENUM('Pending', 'In Progress', 'Resolved') DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

TRUNCATE TABLE history_logs;

-- Update Students table
ALTER TABLE students 
ADD COLUMN block_reason VARCHAR(255) DEFAULT NULL,
ADD COLUMN date_blocked DATETIME DEFAULT NULL;

-- Update Employees table
ALTER TABLE employees 
ADD COLUMN block_reason VARCHAR(255) DEFAULT NULL,
ADD COLUMN date_blocked DATETIME DEFAULT NULL;
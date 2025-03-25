-- Users table
CREATE TABLE IF NOT EXISTS Users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    role ENUM('admin', 'teacher', 'student') NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    profile_image VARCHAR(255) DEFAULT NULL,
    last_login DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Classes table
CREATE TABLE IF NOT EXISTS Classes (
    class_id INT AUTO_INCREMENT PRIMARY KEY,
    class_name VARCHAR(100) NOT NULL,
    section VARCHAR(10),
    academic_year VARCHAR(20) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Students table
CREATE TABLE IF NOT EXISTS Students (
    student_id VARCHAR(20) PRIMARY KEY,
    user_id INT NOT NULL,
    roll_number VARCHAR(20) NOT NULL UNIQUE,
    registration_number VARCHAR(30) NOT NULL UNIQUE,
    class_id INT,
    batch_year YEAR(4) NOT NULL,
    date_of_birth DATE,
    gender ENUM('male', 'female', 'other'),
    address TEXT,
    phone VARCHAR(20),
    parent_name VARCHAR(100),
    parent_phone VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES Classes(class_id) ON DELETE SET NULL
);

-- Teachers table
CREATE TABLE IF NOT EXISTS Teachers (
    teacher_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    employee_id VARCHAR(20) NOT NULL UNIQUE,
    qualification VARCHAR(100),
    department VARCHAR(50),
    joining_date DATE,
    phone VARCHAR(20),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE
);

-- Subjects table
CREATE TABLE IF NOT EXISTS Subjects (
    subject_id VARCHAR(20) PRIMARY KEY,
    subject_name VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- TeacherSubjects table (many-to-many relationship)
CREATE TABLE IF NOT EXISTS TeacherSubjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    subject_id VARCHAR(20) NOT NULL,
    academic_year VARCHAR(20) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES Teachers(teacher_id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES Subjects(subject_id) ON DELETE CASCADE,
    UNIQUE KEY (teacher_id, subject_id, academic_year)
);

-- Exams table
CREATE TABLE IF NOT EXISTS Exams (
    exam_id INT AUTO_INCREMENT PRIMARY KEY,
    exam_name VARCHAR(100) NOT NULL,
    exam_type ENUM('midterm', 'final', 'quiz', 'assignment', 'project', 'other') NOT NULL,
    class_id INT,
    start_date DATE,
    end_date DATE,
    total_marks INT NOT NULL,
    passing_marks INT NOT NULL,
    academic_year VARCHAR(20) NOT NULL,
    description TEXT,
    status ENUM('upcoming', 'ongoing', 'completed', 'cancelled') DEFAULT 'upcoming',
    exam_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (class_id) REFERENCES Classes(class_id) ON DELETE SET NULL
);

-- ResultUploads table (must be created before Results)
CREATE TABLE IF NOT EXISTS ResultUploads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    file_name VARCHAR(255) NOT NULL,
    description TEXT,
    status ENUM('Draft', 'Published') DEFAULT 'Draft',
    student_count INT DEFAULT 0,
    uploaded_by INT NOT NULL,
    upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (uploaded_by) REFERENCES Users(user_id)
);

-- Results table
CREATE TABLE IF NOT EXISTS Results (
    result_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(20) NOT NULL,
    exam_id INT NOT NULL,
    subject_id VARCHAR(20) NOT NULL,
    theory_marks DECIMAL(5,2) NOT NULL,
    practical_marks DECIMAL(5,2),
    credit_hours DECIMAL(3,1) DEFAULT 4.0,
    grade VARCHAR(5) NOT NULL,
    gpa DECIMAL(3,2) NOT NULL,
    upload_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES Students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES Subjects(subject_id) ON DELETE CASCADE,
    FOREIGN KEY (exam_id) REFERENCES Exams(exam_id) ON DELETE CASCADE,
    FOREIGN KEY (upload_id) REFERENCES ResultUploads(id) ON DELETE SET NULL,
    UNIQUE KEY (student_id, exam_id, subject_id)
);

-- Attendance table
CREATE TABLE IF NOT EXISTS Attendance (
    attendance_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(20) NOT NULL,
    subject_id VARCHAR(20) NOT NULL,
    date DATE NOT NULL,
    status ENUM('present', 'absent', 'late', 'excused') NOT NULL,
    remarks TEXT,
    marked_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES Students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES Subjects(subject_id) ON DELETE CASCADE,
    FOREIGN KEY (marked_by) REFERENCES Users(user_id) ON DELETE CASCADE,
    UNIQUE KEY (student_id, subject_id, date)
);

-- Notifications table
CREATE TABLE IF NOT EXISTS Notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info', 'success', 'warning', 'error') DEFAULT 'info',
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE
);

-- Settings table
CREATE TABLE IF NOT EXISTS Settings (
    setting_id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    description TEXT,
    updated_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES Users(user_id) ON DELETE SET NULL
);

-- ActivityLogs table
CREATE TABLE IF NOT EXISTS ActivityLogs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(255) NOT NULL,
    entity_type VARCHAR(50),
    entity_id INT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE SET NULL
);

-- Insert default admin user
INSERT INTO Users (username, password, full_name, email, role)
VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin@example.com', 'admin');

-- Insert sample subjects
INSERT INTO Subjects (subject_id, subject_name) VALUES
('101', 'COMP. ENGLISH'),
('102', 'COMP. NEPALI'),
('103', 'COMP. MATHEMATICS'),
('104', 'COMP. SCIENCE'),
('105', 'COMP. SOCIAL STUDIES'),
('106', 'COMP. HEALTH, POP & ENV EDU'),
('107', 'OPT.I ECONOMICS'),
('108', 'OPT.II OFFICE MGMT & ACCOUNT');

-- Insert default class
INSERT INTO Classes (class_name, section, academic_year, description)
VALUES ('Class 10', 'A', '2023-2024', 'Default class for testing');

-- Insert default exam
INSERT INTO Exams (exam_name, exam_type, class_id, start_date, end_date, total_marks, passing_marks, academic_year, description)
VALUES ('Midterm Exam', 'midterm', 1, '2023-10-01', '2023-10-10', 100, 40, '2023-2024', 'Midterm examination for Class 10');

-- Insert default student
INSERT INTO Users (username, password, full_name, email, role)
VALUES ('student1', '$2y$10$8zUUpfvHvJqMnJ4gJk.Cj.Z/BvWQS1zNFW9CMhbRvDpRRUL2jEjGK', 'John Doe', 'student1@example.com', 'student');

-- Insert a new student
INSERT INTO Students (
    student_id, user_id, roll_number, registration_number, class_id, batch_year,
    date_of_birth, gender, address, phone, parent_name, parent_phone
) VALUES (
    'S001', 2, 'R001', 'REG001', 1, 2023,
    '2005-01-01', 'male', '123 Street, City', '1234567890', 'Parent Name', '0987654321'
);

-- Insert default teacher
INSERT INTO Users (username, password, full_name, email, role)
VALUES ('teacher1', '$2y$10$8zUUpfvHvJqMnJ4gJk.Cj.Z/BvWQS1zNFW9CMhbRvDpRRUL2jEjGK', 'Jane Smith', 'teacher1@example.com', 'teacher');

INSERT INTO Teachers (user_id, employee_id, department, qualification, joining_date, phone, address)
VALUES (3, 'T001', 'Mathematics', 'M.Sc. in Mathematics', '2020-01-01', '1234567890', '456 Street, City');

-- Assign teacher to subject
INSERT INTO TeacherSubjects (teacher_id, subject_id, academic_year)
VALUES (1, '101', '2023-2024');

-- Insert default result
INSERT INTO Results (student_id, exam_id, subject_id, theory_marks, practical_marks, grade, gpa)
VALUES ('S001', 1, '101', 85, 90, 'A+', 4.0);
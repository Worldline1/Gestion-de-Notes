-- Create database
CREATE DATABASE IF NOT EXISTS ensao_grades CHARACTER SET utf8mb4;

USE ensao_grades;

-- Create teachers table
CREATE TABLE IF NOT EXISTS teachers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id VARCHAR(20) UNIQUE NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100)
);

-- Create students table
CREATE TABLE IF NOT EXISTS students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(20) UNIQUE NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    program VARCHAR(50)
);

-- Create modules table
CREATE TABLE IF NOT EXISTS modules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    module_code VARCHAR(20) UNIQUE NOT NULL,
    module_name VARCHAR(100) NOT NULL
);

-- Create grades table
CREATE TABLE IF NOT EXISTS grades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(20) NOT NULL,
    teacher_id VARCHAR(20) NOT NULL,
    academic_year VARCHAR(10) NOT NULL,
    semester VARCHAR(5) NOT NULL,
    module VARCHAR(20) NOT NULL,
    controle_continu DECIMAL(4,2) NULL CHECK (controle_continu >= 0 AND controle_continu <= 20),
    tp DECIMAL(4,2) NULL CHECK (tp >= 0 AND tp <= 20),
    projet DECIMAL(4,2) NULL CHECK (projet >= 0 AND projet <= 20),
    examen DECIMAL(4,2) NULL CHECK (examen >= 0 AND examen <= 20),
    note_finale DECIMAL(4,2) NULL CHECK (note_finale >= 0 AND note_finale <= 20),
    UNIQUE KEY unique_grade (student_id, academic_year, semester, module),
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES teachers(teacher_id) ON DELETE CASCADE
);

-- Insert sample teachers
INSERT INTO teachers (teacher_id, full_name, email) VALUES
('ENS-2025-423', 'Prof. Michel Dupont', 'michel.dupont@ensao.ac.ma'),
('ENS-2025-424', 'Prof. Sarah Martin', 'sarah.martin@ensao.ac.ma'),
('ENS-2025-425', 'Prof. Ahmed Benali', 'ahmed.benali@ensao.ac.ma')
ON DUPLICATE KEY UPDATE full_name = VALUES(full_name);

-- Insert sample students
INSERT INTO students (student_id, full_name, email, program) VALUES
('GI2023456', 'YOUNES BOUAZZAOUI', 'younes.bouazzaoui@student.ensao.ac.ma', 'Génie Informatique'),
('GI2023457', 'SARA MIMOUNI', 'sara.mimouni@student.ensao.ac.ma', 'Génie Informatique'),
('GI2023458', 'MOHAMED TAZI', 'mohamed.tazi@student.ensao.ac.ma', 'Génie Informatique'),
('GI2023459', 'FATIMA ALAOUI', 'fatima.alaoui@student.ensao.ac.ma', 'Génie Informatique'),
('GI2023460', 'KHALID BENSAID', 'khalid.bensaid@student.ensao.ac.ma', 'Génie Informatique')
ON DUPLICATE KEY UPDATE full_name = VALUES(full_name);

-- Insert sample modules
INSERT INTO modules (module_code, module_name) VALUES
('prog', 'Programmation Orientée Objet'),
('math', 'Mathématiques Appliquées'),
('db', 'Bases de Données'),
('web', 'Développement Web'),
('algo', 'Algorithmes et Structures de Données')
ON DUPLICATE KEY UPDATE module_name = VALUES(module_name);

-- Create a view for complete grade information
CREATE OR REPLACE VIEW grade_details AS
SELECT 
    g.id,
    g.student_id,
    s.full_name as student_name,
    g.teacher_id,
    t.full_name as teacher_name,
    g.academic_year,
    g.semester,
    g.module,
    m.module_name,
    g.controle_continu,
    g.tp,
    g.projet,
    g.examen,
    g.note_finale
FROM grades g
LEFT JOIN students s ON g.student_id = s.student_id
LEFT JOIN teachers t ON g.teacher_id = t.teacher_id
LEFT JOIN modules m ON g.module = m.module_code;

-- Insert some sample grades for testing
INSERT INTO grades (
    student_id, teacher_id, academic_year, semester, module,
    controle_continu, tp, projet, examen
) VALUES
('GI2023456', 'ENS-2025-423', '2024/2025', 'S1', 'prog', 15.5, 14.0, 16.0, 13.5),
('GI2023457', 'ENS-2025-423', '2024/2025', 'S1', 'prog', 12.0, 13.5, 14.0, 11.5),
('GI2023458', 'ENS-2025-423', '2024/2025', 'S1', 'prog', 18.0, 17.5, 19.0, 16.5)
ON DUPLICATE KEY UPDATE
    controle_continu = VALUES(controle_continu),
    tp = VALUES(tp),
    projet = VALUES(projet),
    examen = VALUES(examen);

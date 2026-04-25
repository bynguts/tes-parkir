-- Add staff type to operator table
ALTER TABLE operator ADD COLUMN staff_type ENUM('admin', 'operator') DEFAULT 'operator' AFTER shift;

-- Add 2 new admin staff
INSERT INTO operator (full_name, shift, staff_type, phone) VALUES 
('Andi Admin', 'morning', 'admin', '081234567890'),
('Budi Admin', 'afternoon', 'admin', '081234567891');

-- Create shift_attendance table
CREATE TABLE IF NOT EXISTS shift_attendance (
    attendance_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL, -- FK to admin_users
    staff_id INT NOT NULL, -- FK to operator
    check_in_time DATETIME DEFAULT CURRENT_TIMESTAMP,
    is_active TINYINT(1) DEFAULT 1,
    FOREIGN KEY (user_id) REFERENCES admin_users(id) ON DELETE CASCADE,
    FOREIGN KEY (staff_id) REFERENCES operator(operator_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

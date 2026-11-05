-- =====================================================================
--  BUT FIRST, SYSTEM  -  MySQL / MariaDB reference schema (XAMPP)
--  Inventory, payroll, employee shifts and login accounts.
--
--  Usage (XAMPP shell):  mysql -u root < database/schema.sql
--  then run the app with DB_DRIVER=mysql  (or simply: composer db:seed with DB_DRIVER=mysql,
--  which creates the same tables through Eloquent's schema builder and adds sample data).
-- =====================================================================

CREATE DATABASE IF NOT EXISTS butfirstsystemdb
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE butfirstsystemdb;

DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS employee_shift;
DROP TABLE IF EXISTS payroll;
DROP TABLE IF EXISTS inventory;

-- Inventory
CREATE TABLE inventory (
    product_id    INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    product_name  VARCHAR(100)  NOT NULL,
    category      VARCHAR(50)   NOT NULL,
    price         DECIMAL(10,2) NOT NULL,
    quantity      INT           NOT NULL DEFAULT 0,
    supplier      VARCHAR(100)  NULL,
    date_added    DATE          NOT NULL,
    image_path    VARCHAR(255)  NULL,
    created_at    TIMESTAMP     NULL,
    updated_at    TIMESTAMP     NULL,
    PRIMARY KEY (product_id),
    KEY inventory_category_index (category)
) ENGINE=InnoDB;

-- Payroll  - employee_id is the business key, e.g. 'F-006'
CREATE TABLE payroll (
    id                  INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    employee_id         VARCHAR(50)   NOT NULL,
    name                VARCHAR(100)  NOT NULL,
    daily_rate          DECIMAL(10,2) NOT NULL DEFAULT 550.00,
    days_worked         INT           NOT NULL DEFAULT 0,
    ot_hours            INT           NOT NULL DEFAULT 0,
    late_hours          INT           NOT NULL DEFAULT 0,
    deduction_per_hour  DECIMAL(10,2) NOT NULL DEFAULT 0,
    holiday_present     INT           NOT NULL DEFAULT 0,
    holiday_type        VARCHAR(50)   NULL,
    net_pay             DECIMAL(10,2) NOT NULL DEFAULT 0,
    gross_pay           DECIMAL(10,2) NOT NULL DEFAULT 0,
    total_deductions    DECIMAL(10,2) NOT NULL DEFAULT 0,
    sss                 DECIMAL(10,2) NOT NULL DEFAULT 0,
    philhealth          DECIMAL(10,2) NOT NULL DEFAULT 0,
    pagibig             DECIMAL(10,2) NOT NULL DEFAULT 0,
    others              DECIMAL(10,2) NOT NULL DEFAULT 0,
    created_at          TIMESTAMP     NULL,
    updated_at          TIMESTAMP     NULL,
    PRIMARY KEY (id),
    UNIQUE KEY payroll_employee_id_unique (employee_id)
) ENGINE=InnoDB;

-- EmployeeShift
CREATE TABLE employee_shift (
    id                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    payroll_employee_id  VARCHAR(50)  NULL,
    names                VARCHAR(100) NOT NULL,
    schedule             VARCHAR(100) NULL,
    breaks               VARCHAR(100) NULL,
    duties               VARCHAR(200) NULL,
    day_off              VARCHAR(20)  NULL,
    created_at           TIMESTAMP    NULL,
    updated_at           TIMESTAMP    NULL,
    PRIMARY KEY (id),
    CONSTRAINT employee_shift_payroll_employee_id_foreign
        FOREIGN KEY (payroll_employee_id) REFERENCES payroll (employee_id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Login accounts (admin + one per Employee ID). Rows are created by `composer db:migrate`
-- or `composer db:seed`, which hash the default passwords from config/config.php.
CREATE TABLE users (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username       VARCHAR(50)  NOT NULL,
    password_hash  VARCHAR(255) NOT NULL,
    role           VARCHAR(20)  NOT NULL DEFAULT 'employee',
    employee_id    VARCHAR(50)  NULL,
    created_at     TIMESTAMP    NULL,
    updated_at     TIMESTAMP    NULL,
    PRIMARY KEY (id),
    UNIQUE KEY users_username_unique (username),
    UNIQUE KEY users_employee_id_unique (employee_id),
    CONSTRAINT users_employee_id_foreign FOREIGN KEY (employee_id) REFERENCES payroll (employee_id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Sample rows (a shortened version of database/seed.php)
INSERT INTO inventory (product_name, category, price, quantity, supplier, date_added, image_path, created_at, updated_at) VALUES
('BFC Hot Coffee',            'BEVERAGES', 120.00, 40, 'But First Coffee Commissary', CURDATE(), 'uploads/products/bfc-hot-coffee.jpg', NOW(), NOW()),
('BFC Iced Coffee',           'BEVERAGES', 130.00, 55, 'But First Coffee Commissary', CURDATE(), 'uploads/products/bfc-iced-coffee.jpg', NOW(), NOW()),
('Ham & Cheese Croissant',    'CROISSANT', 115.00, 20, 'Floridablanca Bakehouse',     CURDATE(), 'uploads/products/ham-cheese-croissant.jpg', NOW(), NOW()),
('Crookie',                   'COOKIES BROWNIES', 95.00, 24, 'Floridablanca Bakehouse', CURDATE(), 'uploads/products/crookie.jpg', NOW(), NOW());

-- net_pay = days*550 + holidays*550 + ot*(550/8*1.25) - (sss+philhealth+pagibig+others+late*deduction)
INSERT INTO payroll (employee_id, name, days_worked, ot_hours, late_hours, deduction_per_hour, holiday_present, holiday_type,
                     net_pay, gross_pay, total_deductions, sss, philhealth, pagibig, others, created_at, updated_at) VALUES
('F-001', 'Juan Dela Cruz', 13, 4, 1, 50, 1, 'Regular', 7093.75, 7150.00, 950.00, 450, 250, 200, 0, NOW(), NOW()),
('F-002', 'Maria Santos',   12, 2, 0, 50, 0, 'Regular', 5871.88, 6600.00, 900.00, 450, 250, 200, 0, NOW(), NOW());

INSERT INTO employee_shift (payroll_employee_id, names, schedule, breaks, duties, day_off, created_at, updated_at) VALUES
('F-001', 'Juan Dela Cruz', '6:00 AM - 2:00 PM', '10:00 AM - 11:00 AM', 'Opening Barista / Espresso Bar', 'Monday',  NOW(), NOW()),
('F-002', 'Maria Santos',   '6:00 AM - 2:00 PM', '11:00 AM - 12:00 PM', 'Cashier / Order Taking',         'Tuesday', NOW(), NOW());

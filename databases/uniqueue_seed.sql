-- ============================================================
-- UniQueue Seed Data
-- Generated: 2026-06-05
-- Includes:
--   • 1 Super Admin  → QAM (username: qam, password: admin123)
--   • 3 Offices      → Registrar, Scholarship, Cashier
--   • 3 Office Configs (default schedule 08:00–17:00)
--   • 1 Test Student → Juan Dela Cruz (SR-CODE: 22-12345, password: admin123)
-- ============================================================

-- -----------------------------------------------
-- 1. OFFICES
-- -----------------------------------------------
INSERT INTO `offices` (`id`, `name`, `slug`, `description`, `is_active`) VALUES
(1, 'Registrar',   'registrar',   'Handles student records, enrollment, and official documents.', 1),
(2, 'Scholarship', 'scholarship', 'Manages scholarship applications and grant processing.',        1),
(3, 'Cashier',     'cashier',     'Handles payments, fees, and financial transactions.',           1);

-- -----------------------------------------------
-- 2. OFFICE CONFIGS  (default hours & capacities)
-- -----------------------------------------------
INSERT INTO `office_configs` (`office_id`, `start_time`, `end_time`, `daily_capacity`, `walkin_enabled`, `appointment_enabled`, `priority_enabled`) VALUES
(1, '08:00:00', '17:00:00', 100, 1, 1, 1),
(2, '08:00:00', '17:00:00', 100, 1, 1, 1),
(3, '08:00:00', '17:00:00', 100, 1, 1, 1);

-- -----------------------------------------------
-- 3. SUPER ADMIN  (QAM)
--    password: admin123
-- -----------------------------------------------
INSERT INTO `admin_users` (`username`, `password`, `office_id`, `is_super_admin`) VALUES
('qam', '$2b$10$6Xmn9waP1GCu75Nb8n1qOecFscSIq/q2Gx3zBmG/Uvsr7RBMzIo62', NULL, 1);

-- -----------------------------------------------
-- 4. OFFICE ADMINS
--    password: office123
-- -----------------------------------------------
INSERT INTO `admin_users` (`username`, `password`, `office_id`, `is_super_admin`) VALUES
('registrar',   '$2b$10$pBAKUWgyDoyoOzUEp9yrIuyZkHk8KGsll.hAocpm2wyBtR027Pd16', 1, 0),
('scholarship', '$2b$10$pBAKUWgyDoyoOzUEp9yrIuyZkHk8KGsll.hAocpm2wyBtR027Pd16', 2, 0),
('cashier',     '$2b$10$pBAKUWgyDoyoOzUEp9yrIuyZkHk8KGsll.hAocpm2wyBtR027Pd16', 3, 0);

-- -----------------------------------------------
-- 5. TEST STUDENT
--    SR-Code : 22-12345
--    Name    : Juan Dela Cruz
--    College : CICS (id=1)
--    Program : BSIT (id=1)
--    Year    : 1
--    Password: student123
-- -----------------------------------------------
INSERT INTO `students` (`first_name`, `last_name`, `sr_code`, `college_id`, `program_id`, `year_level`, `password`) VALUES
('Juan', 'Dela Cruz', '22-12345', 1, 1, 1, '$2b$10$.3ATIIY8.YrrbRWmK58c6uGHPgAx1Zuv6GuQExbUVL4lHQn/FvRha');

-- ============================================================
-- QUICK REFERENCE
-- ============================================================
-- Super Admin
--   Username : qam
--   Password : admin123
--
-- Office Admins (password: office123)
--   Username : registrar   → Registrar Office
--   Username : scholarship → Scholarship Office
--   Username : cashier     → Cashier Office
--
-- Test Student
--   SR-Code  : 22-12345
--   Password : student123
--   Name     : Juan Dela Cruz (BSIT, CICS, Year 1)
--
-- Offices
--   ID 1 → Registrar   (slug: registrar)
--   ID 2 → Scholarship (slug: scholarship)
--   ID 3 → Cashier     (slug: cashier)
-- ============================================================

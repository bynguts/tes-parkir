-- Change shift column to be more flexible
ALTER TABLE operator MODIFY COLUMN shift VARCHAR(50);

-- Reset existing data and assign new 24h schedule
-- 4 Operators (6h each)
UPDATE operator SET shift = '06:00 - 12:00' WHERE full_name = 'Rizky Pratama';
UPDATE operator SET shift = '12:00 - 18:00' WHERE full_name = 'Sari Indah';
UPDATE operator SET shift = '18:00 - 00:00' WHERE full_name = 'Tono Budiman';
UPDATE operator SET shift = '00:00 - 06:00' WHERE full_name = 'Ulan Permata';

-- 2 Admins (12h each)
UPDATE operator SET shift = '06:00 - 18:00 (Day)' WHERE full_name = 'Andi Admin';
UPDATE operator SET shift = '18:00 - 06:00 (Night)' WHERE full_name = 'Budi Admin';

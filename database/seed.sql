INSERT INTO rooms (code, name, building, capacity, room_type, is_active) VALUES
('fst-1.1', 'fst 1.1', 'Gedung FST Lt. 1', 40, 'kelas', 1),
('fst-1.2', 'fst 1.2', 'Gedung FST Lt. 1', 40, 'kelas', 1),
('fst-3.4', 'fst 3.4', 'Gedung FST Lt. 3', 45, 'kelas', 1),
('fst-3.5', 'fst 3.5', 'Gedung FST Lt. 3', 45, 'kelas', 1),
('fst-3.6', 'fst 3.6', 'Gedung FST Lt. 3', 45, 'kelas', 1),
('lab-int-1', 'LAB-INT-internet-1', 'Gedung Lab Komputer Lt. 2', 35, 'lab', 1),
('lab-int-2', 'LAB-INT-internet-2', 'Gedung Lab Komputer Lt. 2', 35, 'lab', 1),
('lab-int-3', 'LAB-INT-internet-3', 'Gedung Lab Komputer Lt. 2', 35, 'lab', 1)
ON DUPLICATE KEY UPDATE name=VALUES(name);

INSERT INTO users (username, password_hash, name, role) VALUES
('admin', '$2y$12$I6bk5Y4JsQ8lctz0D2rguu27jjgHbTkruJDM/kAp6JoIIwO7rxkdO', 'Administrator Ruangan', 'admin'),
('komti', '$2y$12$eD6hKVIDipZPCHcpH0UqtuZvcT1V7rO2cZ0psMLoqYr8R6MUJ8gyq', 'Perwakilan Komti TI-3A', 'komti'),
('ormawa', '$2y$12$QnjqwPDNkE6QeM6O7axAjOKVcn8hleMiiCtDAldUYEkLNNVOVp8pG', 'Pengurus Ormawa HMTI', 'ormawa')
ON DUPLICATE KEY UPDATE name=VALUES(name);

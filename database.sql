DROP DATABASE IF EXISTS db_carter_mobil;
CREATE DATABASE db_carter_mobil CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE db_carter_mobil;

CREATE TABLE admin (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    nama_lengkap VARCHAR(150) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO admin (username, password, nama_lengkap)
VALUES ('admin', '$2y$10$B0MjtJxJy3hBYyOB9be58e1CWBZlioHJpkUdkzFxSXMd1biAkwUMS', 'Administrator');

CREATE TABLE settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO settings (setting_key, setting_value)
VALUES 
('admin_whatsapp', '6281234567890'),
('company_name', 'CarterMobil Pro'),
('company_logo', '/uploads/company/default-logo.svg'),
('home_mobil_pribadi_label', 'City Car'),
('home_mobil_pribadi_image', 'https://images.unsplash.com/photo-1544636331-e26879cd4d9b?auto=format&fit=crop&w=900&q=80'),
('home_minibus_label', 'Family Van'),
('home_minibus_image', 'https://images.unsplash.com/photo-1553440569-bcc63803a83d?auto=format&fit=crop&w=900&q=80'),
('home_bus_label', 'Tour Coach'),
('home_bus_image', 'https://images.unsplash.com/photo-1503376780353-7e6692767b70?auto=format&fit=crop&w=900&q=80');

CREATE TABLE armada (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_mobil VARCHAR(150) NOT NULL,
    kategori VARCHAR(50) NOT NULL,
    kapasitas VARCHAR(50) NOT NULL,
    harga_sewa DECIMAL(12,2) NOT NULL DEFAULT 0,
    sisa_kursi INT NOT NULL DEFAULT 0,
    image_url VARCHAR(255) DEFAULT NULL,
    deskripsi TEXT DEFAULT NULL,
    status ENUM('Aktif', 'Nonaktif') NOT NULL DEFAULT 'Aktif',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO armada (nama_mobil, kategori, kapasitas, harga_sewa, sisa_kursi, image_url, deskripsi, status)
VALUES
('Avanza', 'Mobil Pribadi', '4 - 7 Kursi', 600000, 5, 'https://images.unsplash.com/photo-1544636331-e26879cd4d9b?auto=format&fit=crop&w=900&q=80', 'Mobil keluarga nyaman untuk perjalanan harian.', 'Aktif'),
('Xenia', 'Mobil Pribadi', '4 - 7 Kursi', 650000, 4, 'https://images.unsplash.com/photo-1492144534655-ae79c964c9d7?auto=format&fit=crop&w=900&q=80', 'Mobil pribadi dengan ruang kabin yang nyaman.', 'Aktif'),
('Innova', 'Mobil Pribadi', '4 - 7 Kursi', 750000, 3, 'https://images.unsplash.com/photo-1552519507-da3b142c6e3d?auto=format&fit=crop&w=900&q=80', 'Mobil keluarga premium untuk perjalanan jauh.', 'Aktif'),
('Elf', 'Minibus', '10 - 15 Kursi', 1200000, 3, 'https://images.unsplash.com/photo-1553440569-bcc63803a83d?auto=format&fit=crop&w=900&q=80', 'Minibus nyaman untuk rombongan dan travel.', 'Aktif'),
('Hiace', 'Minibus', '10 - 15 Kursi', 1350000, 2, 'https://images.unsplash.com/photo-1549317661-bd32c8ce0db2?auto=format&fit=crop&w=900&q=80', 'Minibus premium untuk kebutuhan grup dan keluarga.', 'Aktif'),
('Medium Bus', 'Bus Wisata Besar', '30 - 45 Kursi', 2500000, 2, 'https://images.unsplash.com/photo-1503376780353-7e6692767b70?auto=format&fit=crop&w=900&q=80', 'Bus wisata medium untuk rombongan besar.', 'Aktif'),
('Big Bus', 'Bus Wisata Besar', '45 - 59 Kursi', 3200000, 1, 'https://images.unsplash.com/photo-1511919884226-fd3cad34687c?auto=format&fit=crop&w=900&q=80', 'Bus wisata besar untuk perjalanan perusahaan maupun wisata.', 'Aktif');

CREATE TABLE pemesanan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    armada_id INT NOT NULL,
    nama_pelanggan VARCHAR(150) NOT NULL,
    no_kontak VARCHAR(30) NOT NULL,
    kota_asal VARCHAR(100) NOT NULL,
    kota_tujuan VARCHAR(100) NOT NULL,
    tanggal_sewa DATE NOT NULL,
    area_operasional ENUM('Jawa Barat', 'Jawa Tengah', 'Jawa Timur', 'Semua Area') NOT NULL,
    kategori_layanan ENUM('Travel', 'Pribadi', 'Antar-Jemput', 'Wisata') NOT NULL,
    status_pesanan ENUM('Pending', 'Dikonfirmasi', 'Selesai', 'Dibatalkan') NOT NULL DEFAULT 'Pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pemesanan_armada FOREIGN KEY (armada_id) REFERENCES armada(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

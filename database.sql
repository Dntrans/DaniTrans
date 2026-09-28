CREATE TABLE IF NOT EXISTS admin (
    id SERIAL PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    nama_lengkap VARCHAR(150) NOT NULL
);

INSERT INTO admin (username, password, nama_lengkap)
VALUES ('admin', '$2y$10$B0MjtJxJy3hBYyOB9be58e1CWBZlioHJpkUdkzFxSXMd1biAkwUMS', 'Administrator')
ON CONFLICT (username) DO NOTHING;

CREATE TABLE IF NOT EXISTS settings (
    id SERIAL PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value VARCHAR(255) NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE OR REPLACE FUNCTION update_settings_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trigger_update_settings_updated_at ON settings;
CREATE TRIGGER trigger_update_settings_updated_at
BEFORE UPDATE ON settings
FOR EACH ROW
EXECUTE FUNCTION update_settings_updated_at();

CREATE TABLE IF NOT EXISTS armada (
    id SERIAL PRIMARY KEY,
    nama_mobil VARCHAR(150) NOT NULL,
    kategori VARCHAR(50) NOT NULL,
    kapasitas VARCHAR(50) NOT NULL,
    harga_sewa NUMERIC(12,2) NOT NULL DEFAULT 0,
    sisa_kursi INT NOT NULL DEFAULT 0,
    image_url VARCHAR(255) DEFAULT NULL,
    deskripsi TEXT DEFAULT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'Aktif' CHECK (status IN ('Aktif', 'Nonaktif')),
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS pemesanan (
    id SERIAL PRIMARY KEY,
    armada_id INT NOT NULL,
    nama_pelanggan VARCHAR(150) NOT NULL,
    no_kontak VARCHAR(30) NOT NULL,
    kota_asal VARCHAR(100) NOT NULL,
    kota_tujuan VARCHAR(100) NOT NULL,
    tanggal_sewa DATE NOT NULL,
    area_operasional VARCHAR(20) NOT NULL CHECK (area_operasional IN ('Jawa Barat', 'Jawa Tengah', 'Jawa Timur', 'Semua Area')),
    kategori_layanan VARCHAR(20) NOT NULL CHECK (kategori_layanan IN ('Travel', 'Pribadi', 'Antar-Jemput', 'Wisata')),
    status_pesanan VARCHAR(20) NOT NULL DEFAULT 'Pending' CHECK (status_pesanan IN ('Pending', 'Dikonfirmasi', 'Selesai', 'Dibatalkan')),
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT fk_pemesanan_armada FOREIGN KEY (armada_id) REFERENCES armada(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
);

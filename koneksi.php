<?php
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'db_carter_mobil';

$conn = mysqli_connect($host, $username, $password, $database);

if (!$conn) {
    error_log('Database connection failed: ' . mysqli_connect_error());
    die(
        '<div style="font-family: Arial, sans-serif; max-width: 700px; margin: 40px auto; padding: 20px; border: 1px solid #f1c0c0; border-radius: 12px; background: #fff5f5; color: #7a1f1f;">' .
        '<h2 style="margin-top:0;">Database tidak terhubung</h2>' .
        '<p>Pastikan MySQL di XAMPP sudah aktif dan database <strong>db_carter_mobil</strong> sudah dibuat.</p>' .
        '<p>Langkah cepat:</p>' .
        '<ol><li>Start MySQL di XAMPP Control Panel</li><li>Import file <strong>database.sql</strong> ke phpMyAdmin</li><li>Reload halaman ini</li></ol>' .
        '<p>Detail koneksi: ' . htmlspecialchars(mysqli_connect_error(), ENT_QUOTES, 'UTF-8') . '</p>' .
        '</div>'
    );
}

mysqli_set_charset($conn, 'utf8mb4');

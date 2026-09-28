<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../koneksi.php';

$projectBasePath = rtrim(str_replace('/admin', '', dirname($_SERVER['SCRIPT_NAME'])), '/');
$normalizeBrandPath = function (string $path) use ($projectBasePath) {
    if ($path === '') {
        return '';
    }

    if (preg_match('#^https?://#', $path)) {
        return $path;
    }

    $cleanPath = str_replace('\\', '/', trim((string) $path));
    $cleanPath = preg_replace('#^/+|/+$#', '', $cleanPath);

    if (str_starts_with($cleanPath, 'DaniTrans/')) {
        $cleanPath = substr($cleanPath, strlen('DaniTrans/'));
    }

    if ($projectBasePath !== '') {
        return $projectBasePath . '/' . $cleanPath;
    }

    return '/' . $cleanPath;
};

$statusOptions = ['Pending', 'Dikonfirmasi', 'Selesai', 'Dibatalkan'];

$companyName = 'CarterMobil Pro';
$companyLogo = $normalizeBrandPath('/uploads/company/default-logo.svg');
$adminWhatsappNumber = '6281234567890';
$errorMessage = '';
$successMessage = '';

$loadSettings = function () use ($conn, &$companyName, &$companyLogo, &$adminWhatsappNumber, $normalizeBrandPath) {
    $settingsResult = $conn->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('admin_whatsapp', 'company_name', 'company_logo')");
    if ($settingsResult) {
        while ($settingRow = $settingsResult->fetch_assoc()) {
            $key = (string) ($settingRow['setting_key'] ?? '');
            $value = trim((string) ($settingRow['setting_value'] ?? ''));

            if ($key === 'admin_whatsapp' && $value !== '') {
                $adminWhatsappNumber = $value;
            }

            if ($key === 'company_name' && $value !== '') {
                $companyName = $value;
            }

            if ($key === 'company_logo' && $value !== '') {
                $companyLogo = $normalizeBrandPath($value);
            }
        }
    }
};

$loadSettings();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_status'])) {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $status = trim($_POST['status'] ?? '');

        if ($id && in_array($status, $statusOptions, true)) {
            $updateStmt = $conn->prepare('UPDATE pemesanan SET status_pesanan = ? WHERE id = ?');

            if ($updateStmt) {
                $updateStmt->bind_param('si', $status, $id);
                $updateStmt->execute();
                $updateStmt->close();
            }
        }

        header('Location: dashboard.php');
        exit;
    }

    if (isset($_POST['save_whatsapp'])) {
        $newNumber = preg_replace('/[^0-9]/', '', trim((string) ($_POST['whatsapp_number'] ?? '')));
        if ($newNumber !== '') {
            $adminWhatsappNumber = $newNumber;

            $saveStmt = $conn->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
            if ($saveStmt) {
                $saveStmt->bind_param('ss', $key, $value);
                $key = 'admin_whatsapp';
                $value = $newNumber;
                $saveStmt->execute();
                $saveStmt->close();
            }
        }
    }

    if (isset($_POST['save_company_settings'])) {
        $newCompanyName = trim((string) ($_POST['company_name'] ?? ''));
        if ($newCompanyName === '') {
            $newCompanyName = 'CarterMobil Pro';
        }

        $uploadDir = dirname(__DIR__) . '/uploads/company';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $newCompanyLogo = $companyLogo;
        if (isset($_FILES['company_logo']) && $_FILES['company_logo']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['company_logo'];
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'svg'];
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if (!in_array($extension, $allowedExtensions, true)) {
                $errorMessage = 'Format logo tidak valid. Gunakan JPG, JPEG, PNG, WEBP, atau SVG.';
            } elseif ($file['size'] > 2 * 1024 * 1024) {
                $errorMessage = 'Ukuran logo terlalu besar. Maksimal 2 MB.';
            } else {
                $fileName = 'logo_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
                $targetPath = $uploadDir . '/' . $fileName;

                if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                    $newCompanyLogo = '/uploads/company/' . $fileName;
                } else {
                    $errorMessage = 'Gagal mengunggah logo perusahaan.';
                }
            }
        }

        if ($errorMessage === '') {
            $updateStmt = $conn->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
            if ($updateStmt) {
                $companyKey = 'company_name';
                $companyValue = $newCompanyName;
                $updateStmt->bind_param('ss', $companyKey, $companyValue);
                $updateStmt->execute();

                $logoKey = 'company_logo';
                $logoValue = $newCompanyLogo;
                $updateStmt->bind_param('ss', $logoKey, $logoValue);
                $updateStmt->execute();
                $updateStmt->close();

                $companyName = $newCompanyName;
                $companyLogo = $normalizeBrandPath($newCompanyLogo);
                $successMessage = 'Pengaturan logo dan nama perusahaan berhasil disimpan.';
            }
        }
    }

    if (isset($_POST['save_home_cards'])) {
        $uploadDir = dirname(__DIR__) . '/uploads/home';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $homeCardConfig = [
            'home_mobil_pribadi_label' => ['field' => 'home_mobil_pribadi_label', 'default' => 'City Car', 'upload_field' => 'home_mobil_pribadi_image'],
            'home_minibus_label' => ['field' => 'home_minibus_label', 'default' => 'Family Van', 'upload_field' => 'home_minibus_image'],
            'home_bus_label' => ['field' => 'home_bus_label', 'default' => 'Tour Coach', 'upload_field' => 'home_bus_image'],
        ];

        $saveCardStmt = $conn->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
        if ($saveCardStmt) {
            foreach ($homeCardConfig as $settingKey => $config) {
                $label = trim((string) ($_POST[$config['field']] ?? $config['default']));
                if ($label === '') {
                    $label = $config['default'];
                }

                $saveCardStmt->bind_param('ss', $settingKey, $label);
                $saveCardStmt->execute();

                $imageKey = str_replace('_label', '_image', $settingKey);
                $imageValue = '';

                if (isset($_FILES[$config['upload_field']]) && $_FILES[$config['upload_field']]['error'] === UPLOAD_ERR_OK) {
                    $file = $_FILES[$config['upload_field']];
                    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'svg'];
                    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

                    if (!in_array($extension, $allowedExtensions, true)) {
                        $errorMessage = 'Format gambar card homepage tidak valid. Gunakan JPG, JPEG, PNG, WEBP, atau SVG.';
                        continue;
                    }

                    if ($file['size'] > 2 * 1024 * 1024) {
                        $errorMessage = 'Ukuran gambar card homepage terlalu besar. Maksimal 2 MB.';
                        continue;
                    }

                    $fileName = 'home_' . str_replace('_label', '', $settingKey) . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
                    $targetPath = $uploadDir . '/' . $fileName;

                    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                        $imageValue = '/uploads/home/' . $fileName;
                    } else {
                        $errorMessage = 'Gagal mengunggah gambar card homepage.';
                    }
                }

                if ($imageValue !== '') {
                    $saveCardStmt->bind_param('ss', $imageKey, $imageValue);
                    $saveCardStmt->execute();
                }
            }

            $saveCardStmt->close();
            $successMessage = 'Pengaturan kartu mobil di homepage berhasil disimpan.';
        }
    }

    $loadSettings();
}

$result = $conn->query('SELECT p.*, a.nama_mobil FROM pemesanan p LEFT JOIN armada a ON a.id = p.armada_id ORDER BY p.created_at DESC');
$orders = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_admin'])) {
    $namaLengkap = trim($_POST['nama_lengkap'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($namaLengkap === '' || $username === '' || $password === '') {
        $errorMessage = 'Nama lengkap, username, dan password wajib diisi.';
    } elseif (strlen($password) < 6) {
        $errorMessage = 'Password minimal 6 karakter.';
    } else {
        $cek = $conn->prepare('SELECT id FROM admin WHERE username = ? LIMIT 1');
        if (!$cek) {
            $errorMessage = 'Terjadi kesalahan saat mengecek username.';
        } else {
            $cek->bind_param('s', $username);
            $cek->execute();
            $cek->store_result();

            if ($cek->num_rows > 0) {
                $errorMessage = 'Username sudah terdaftar.';
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $conn->prepare('INSERT INTO admin (username, password, nama_lengkap) VALUES (?, ?, ?)');
                if (!$stmt) {
                    $errorMessage = 'Terjadi kesalahan saat membuat akun admin baru.';
                } else {
                    $stmt->bind_param('sss', $username, $hash, $namaLengkap);
                    if ($stmt->execute()) {
                        $successMessage = 'Akun admin baru berhasil dibuat.';
                    } else {
                        $errorMessage = 'Gagal membuat akun admin baru.';
                    }
                    $stmt->close();
                }
            }
            $cek->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin | <?= htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8'); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: #f8fafc;
        }
    </style>
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 antialiased">
    <header class="bg-gradient-to-r from-sky-700 to-emerald-700 text-white shadow-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-xl bg-white/10 flex items-center justify-center text-white text-xl backdrop-blur-sm border border-white/20 overflow-hidden">
                    <img src="<?= htmlspecialchars($companyLogo . '?t=' . time(), ENT_QUOTES, 'UTF-8'); ?>" alt="Logo <?= htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8'); ?>" class="w-full h-full object-cover" onerror="this.style.display='none'; this.nextElementSibling.style.display='inline-flex';">
                    <i class="fa-solid fa-car-side hidden"></i>
                </div>
                <div>
                    <h1 class="font-bold text-lg leading-tight tracking-wide"><?= htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8'); ?></h1>
                    <p class="text-xs text-sky-100 opacity-90">Dashboard Admin</p>
                </div>
            </div>
            <div class="flex items-center space-x-3">
                <span class="text-sm text-sky-100">Halo, <?= htmlspecialchars($_SESSION['admin_nama_lengkap'] ?? 'Admin', ENT_QUOTES, 'UTF-8'); ?></span>
                <a href="logout.php" class="px-3 py-2 rounded-lg bg-white/10 hover:bg-white/20 text-white text-sm font-medium transition">
                    <i class="fa-solid fa-right-from-bracket mr-1"></i> Logout
                </a>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        <?php if ($errorMessage !== ''): ?>
            <div class="rounded-xl border border-red-200 bg-red-50 text-red-700 px-4 py-3 text-sm">
                <?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <?php if ($successMessage !== ''): ?>
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 text-emerald-700 px-4 py-3 text-sm">
                <?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
            <div>
                <h2 class="text-2xl font-bold text-slate-900">Dashboard Pemilik Usaha</h2>
                <p class="text-sm text-slate-500 mt-1">Kelola daftar pesanan masuk secara real-time.</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="armada.php" class="px-4 py-2.5 rounded-xl bg-sky-600 hover:bg-sky-500 text-white font-semibold text-sm shadow transition">
                    <i class="fa-solid fa-car-side mr-2"></i> Kelola Armada
                </a>
                <button class="px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-semibold text-sm shadow transition">
                    <i class="fa-solid fa-print mr-2"></i> Cetak Laporan
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 flex items-center space-x-4">
                <div class="w-14 h-14 rounded-2xl bg-sky-100 text-sky-600 flex items-center justify-center text-2xl">
                    <i class="fa-solid fa-clipboard-list"></i>
                </div>
                <div>
                    <p class="text-sm text-slate-500 font-medium">Total Pesanan</p>
                    <h3 class="text-2xl font-bold text-slate-900 mt-1"><?= count($orders); ?></h3>
                </div>
            </div>
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 flex items-center space-x-4">
                <div class="w-14 h-14 rounded-2xl bg-emerald-100 text-emerald-600 flex items-center justify-center text-2xl">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
                <div>
                    <p class="text-sm text-slate-500 font-medium">Dikonfirmasi</p>
                    <h3 class="text-2xl font-bold text-slate-900 mt-1"><?= count(array_filter($orders, fn($order) => $order['status_pesanan'] === 'Dikonfirmasi')); ?></h3>
                </div>
            </div>
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 flex items-center space-x-4">
                <div class="w-14 h-14 rounded-2xl bg-amber-100 text-amber-600 flex items-center justify-center text-2xl">
                    <i class="fa-solid fa-van-shuttle"></i>
                </div>
                <div>
                    <p class="text-sm text-slate-500 font-medium">Pending</p>
                    <h3 class="text-2xl font-bold text-slate-900 mt-1"><?= count(array_filter($orders, fn($order) => $order['status_pesanan'] === 'Pending')); ?></h3>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-[1.1fr_0.9fr] gap-6">
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-4">
                    <div>
                        <h3 class="font-bold text-lg text-slate-900">Tambah Admin Baru</h3>
                        <p class="text-sm text-slate-500 mt-1">Buat akun admin tambahan agar operasional tidak tergantung satu orang saja.</p>
                    </div>
                </div>
                <form method="POST" action="dashboard.php" class="space-y-4">
                    <input type="hidden" name="create_admin" value="1">
                    <div>
                        <label for="new_nama_lengkap" class="block text-sm font-medium text-slate-700 mb-2">Nama Lengkap</label>
                        <input type="text" id="new_nama_lengkap" name="nama_lengkap" class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-sky-500 focus:border-sky-500 outline-none transition text-sm" placeholder="Masukkan nama lengkap" required>
                    </div>
                    <div>
                        <label for="new_username" class="block text-sm font-medium text-slate-700 mb-2">Username</label>
                        <input type="text" id="new_username" name="username" class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-sky-500 focus:border-sky-500 outline-none transition text-sm" placeholder="Buat username admin" required>
                    </div>
                    <div>
                        <label for="new_password" class="block text-sm font-medium text-slate-700 mb-2">Password</label>
                        <input type="password" id="new_password" name="password" class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-sky-500 focus:border-sky-500 outline-none transition text-sm" placeholder="Minimal 6 karakter" required>
                    </div>
                    <button type="submit" class="w-full px-4 py-3 rounded-xl bg-sky-600 hover:bg-sky-500 text-white font-semibold text-sm shadow transition">
                        <i class="fa-solid fa-user-plus mr-2"></i> Simpan Admin Baru
                    </button>
                </form>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-4">
                    <div>
                        <h3 class="font-bold text-lg text-slate-900">Pengaturan Branding</h3>
                        <p class="text-sm text-slate-500 mt-1">Atur nama usaha dan logo yang tampil di website dan halaman login.</p>
                    </div>
                </div>

                <form method="POST" action="dashboard.php" enctype="multipart/form-data" class="space-y-4">
                    <input type="hidden" name="save_company_settings" value="1">
                    <div>
                        <label for="company_name" class="block text-sm font-medium text-slate-700 mb-2">Nama Perusahaan</label>
                        <input type="text" id="company_name" name="company_name" value="<?= htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8'); ?>" class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-sky-500 focus:border-sky-500 outline-none transition text-sm" placeholder="CarterMobil Pro">
                    </div>

                    <div>
                        <label for="company_logo" class="block text-sm font-medium text-slate-700 mb-2">Logo Perusahaan</label>
                        <input type="file" id="company_logo" name="company_logo" accept="image/jpeg,image/png,image/webp,image/svg+xml" class="w-full px-3 py-2.5 rounded-xl border border-slate-300 bg-white text-sm">
                        <div class="mt-3 rounded-xl border border-slate-200 bg-slate-50 p-3 flex items-center gap-3">
                            <img src="<?= htmlspecialchars($companyLogo . '?t=' . time(), ENT_QUOTES, 'UTF-8'); ?>" alt="Logo perusahaan" class="w-14 h-14 object-cover rounded-xl border border-slate-200 bg-white" onerror="this.src='/DaniTrans/uploads/company/default-logo.svg?t=<?= time(); ?>'">
                            <span class="text-xs text-slate-500">Logo saat ini</span>
                        </div>
                    </div>

                    <button type="submit" class="w-full px-4 py-3 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-semibold text-sm shadow transition">
                        <i class="fa-solid fa-pen-to-square mr-2"></i> Simpan Branding
                    </button>
                </form>

                <div class="mt-6 pt-5 border-t border-slate-200">
                    <h4 class="font-bold text-base text-slate-900 mb-3">Pengaturan Kartu Homepage</h4>
                    <form method="POST" action="dashboard.php" class="space-y-4">
                        <input type="hidden" name="save_home_cards" value="1">
                        <div class="grid grid-cols-1 gap-3">
                            <div class="rounded-xl border border-slate-200 p-3">
                                <p class="text-sm font-semibold text-slate-800 mb-2">Mobil Pribadi</p>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                    <input type="text" name="home_mobil_pribadi_label" value="City Car" class="w-full px-3 py-2.5 rounded-lg border border-slate-300 focus:ring-2 focus:ring-sky-500 outline-none text-sm" placeholder="Label mobil pribadi">
                                    <input type="file" name="home_mobil_pribadi_image" accept="image/jpeg,image/png,image/webp,image/svg+xml" class="w-full px-3 py-2.5 rounded-lg border border-slate-300 bg-white text-sm">
                                </div>
                            </div>
                            <div class="rounded-xl border border-slate-200 p-3">
                                <p class="text-sm font-semibold text-slate-800 mb-2">Minibus</p>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                    <input type="text" name="home_minibus_label" value="Family Van" class="w-full px-3 py-2.5 rounded-lg border border-slate-300 focus:ring-2 focus:ring-sky-500 outline-none text-sm" placeholder="Label minibus">
                                    <input type="file" name="home_minibus_image" accept="image/jpeg,image/png,image/webp,image/svg+xml" class="w-full px-3 py-2.5 rounded-lg border border-slate-300 bg-white text-sm">
                                </div>
                            </div>
                            <div class="rounded-xl border border-slate-200 p-3">
                                <p class="text-sm font-semibold text-slate-800 mb-2">Bus Wisata Besar</p>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                    <input type="text" name="home_bus_label" value="Tour Coach" class="w-full px-3 py-2.5 rounded-lg border border-slate-300 focus:ring-2 focus:ring-sky-500 outline-none text-sm" placeholder="Label bus wisata besar">
                                    <input type="file" name="home_bus_image" accept="image/jpeg,image/png,image/webp,image/svg+xml" class="w-full px-3 py-2.5 rounded-lg border border-slate-300 bg-white text-sm">
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="w-full px-4 py-3 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-semibold text-sm shadow transition">
                            <i class="fa-solid fa-image mr-2"></i> Simpan Kartu Homepage
                        </button>
                    </form>

                    <div class="mt-6 pt-5 border-t border-slate-200">
                        <h4 class="font-bold text-base text-slate-900 mb-3">Pengaturan Kontak WhatsApp</h4>
                        <form method="POST" action="dashboard.php" class="flex flex-col sm:flex-row gap-3 items-start sm:items-center">
                            <input type="hidden" name="save_whatsapp" value="1">
                            <div class="flex-1 w-full">
                                <label for="whatsapp_number" class="sr-only">Nomor WhatsApp</label>
                                <input type="text" id="whatsapp_number" name="whatsapp_number" value="<?= htmlspecialchars($adminWhatsappNumber, ENT_QUOTES, 'UTF-8'); ?>" class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-sky-500 focus:border-sky-500 outline-none transition text-sm" placeholder="Contoh: 6281234567890">
                            </div>
                            <button type="submit" class="px-4 py-3 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-semibold text-sm shadow transition">
                                <i class="fa-brands fa-whatsapp mr-2"></i> Simpan Nomor
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="p-6 border-b border-slate-100 flex justify-between items-center">
                <h3 class="font-bold text-lg text-slate-900">Daftar Pemesanan Carter Mobil</h3>
                <span class="text-xs px-3 py-1 rounded-full bg-slate-100 text-slate-600 font-medium">Real-Time Sync</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-sm">
                    <thead>
                        <tr class="bg-slate-50 text-slate-600 border-b border-slate-200 text-xs font-semibold uppercase tracking-wider">
                            <th class="p-4">Pelanggan</th>
                            <th class="p-4">Rute</th>
                            <th class="p-4">Tanggal</th>
                            <th class="p-4">Area & Layanan</th>
                            <th class="p-4">Armada</th>
                            <th class="p-4 text-center">Status</th>
                            <th class="p-4 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if (empty($orders)): ?>
                            <tr>
                                <td colspan="7" class="p-8 text-center text-slate-400">Belum ada data pemesanan masuk.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($orders as $order): ?>
                                <tr class="hover:bg-slate-50/80 transition">
                                    <td class="p-4">
                                        <div class="font-semibold text-slate-900"><?= htmlspecialchars($order['nama_pelanggan'], ENT_QUOTES, 'UTF-8'); ?></div>
                                        <div class="text-xs text-slate-500"><i class="fa-brands fa-whatsapp text-emerald-600 mr-1"></i><?= htmlspecialchars($order['no_kontak'], ENT_QUOTES, 'UTF-8'); ?></div>
                                    </td>
                                    <td class="p-4">
                                        <div class="text-slate-800 font-medium"><?= htmlspecialchars($order['kota_asal'], ENT_QUOTES, 'UTF-8'); ?> <i class="fa-solid fa-arrow-right text-xs text-slate-400 mx-1"></i> <?= htmlspecialchars($order['kota_tujuan'], ENT_QUOTES, 'UTF-8'); ?></div>
                                    </td>
                                    <td class="p-4 text-slate-600 whitespace-nowrap"><?= htmlspecialchars(date('d-m-Y', strtotime($order['tanggal_sewa'])), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td class="p-4">
                                        <span class="inline-block px-2.5 py-1 rounded-md text-xs font-semibold bg-sky-50 text-sky-700 border border-sky-100 mb-1"><?= htmlspecialchars($order['area_operasional'], ENT_QUOTES, 'UTF-8'); ?></span>
                                        <div class="text-xs text-slate-500">Layanan: <?= htmlspecialchars($order['kategori_layanan'], ENT_QUOTES, 'UTF-8'); ?></div>
                                    </td>
                                    <td class="p-4 text-slate-700 font-medium"><?= htmlspecialchars($order['nama_mobil'] ?? 'Tidak diketahui', ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td class="p-4 text-center">
                                        <span class="px-2.5 py-1 rounded-full text-xs font-semibold
                                            <?php
                                                $status = $order['status_pesanan'];
                                                if ($status === 'Dikonfirmasi') {
                                                    echo 'bg-emerald-100 text-emerald-700';
                                                } elseif ($status === 'Selesai') {
                                                    echo 'bg-sky-100 text-sky-700';
                                                } elseif ($status === 'Dibatalkan') {
                                                    echo 'bg-rose-100 text-rose-700';
                                                } else {
                                                    echo 'bg-amber-100 text-amber-700';
                                                }
                                            ?>
                                        ">
                                            <?= htmlspecialchars($order['status_pesanan'], ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    </td>
                                    <td class="p-4 text-center">
                                        <form method="POST" action="dashboard.php" class="flex flex-wrap items-center justify-center gap-2">
                                            <input type="hidden" name="update_status" value="1">
                                            <input type="hidden" name="id" value="<?= (int) $order['id']; ?>">
                                            <select name="status" class="rounded-lg border border-slate-200 bg-white px-2 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-sky-500">
                                                <?php foreach ($statusOptions as $status): ?>
                                                    <option value="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>" <?= $order['status_pesanan'] === $status ? 'selected' : ''; ?>><?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit" class="px-3 py-2 rounded-lg bg-sky-600 text-white text-xs font-semibold hover:bg-sky-500 transition">Update</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</body>
</html>

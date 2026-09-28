<?php
session_start();
require_once __DIR__ . '/koneksi.php';

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

$companyName = 'CarterMobil Pro';
$companyLogo = $normalizeBrandPath('/uploads/company/default-logo.svg');

$companySettings = $conn->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('company_name', 'company_logo')");
if ($companySettings) {
    while ($companySetting = $companySettings->fetch_assoc()) {
        $key = (string) ($companySetting['setting_key'] ?? '');
        $value = trim((string) ($companySetting['setting_value'] ?? ''));

        if ($key === 'company_name' && $value !== '') {
            $companyName = $value;
        }

        if ($key === 'company_logo' && $value !== '') {
            $companyLogo = $normalizeBrandPath($value);
        }
    }
}

$successMessage = '';
$errorMessage = '';

$armadaQuery = $conn->query('SELECT * FROM armada WHERE status = "Aktif" ORDER BY kategori, nama_mobil');
$armadaList = $armadaQuery ? $armadaQuery->fetch_all(MYSQLI_ASSOC) : [];

$adminWhatsappNumber = '6281234567890';
$settingsResult = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'admin_whatsapp' LIMIT 1");
if ($settingsResult && $settingsResult->num_rows > 0) {
    $settingRow = $settingsResult->fetch_assoc();
    $adminWhatsappNumber = trim((string) ($settingRow['setting_value'] ?? '6281234567890'));
}
$waMessage = '';

$bookedSeatCache = [];
$bookingCounts = [];
$bookingQuery = $conn->query('SELECT armada_id, COUNT(*) AS total FROM pemesanan GROUP BY armada_id');
if ($bookingQuery) {
    while ($row = $bookingQuery->fetch_assoc()) {
        $bookingCounts[(int) $row['armada_id']] = (int) $row['total'];
    }
}

$armadaByCategory = [];
$armadaMap = [];
foreach ($armadaList as $mobil) {
    $kapasitas = preg_match('/(\d+)/', (string) ($mobil['kapasitas'] ?? ''), $numbers) ? (int) max($numbers) : 0;
    $booked = $bookingCounts[(int) ($mobil['id'] ?? 0)] ?? 0;
    $mobil['available_seats'] = max(0, $kapasitas - $booked);

    $kategori = $mobil['kategori'];
    if (!isset($armadaByCategory[$kategori])) {
        $armadaByCategory[$kategori] = [];
    }
    $armadaByCategory[$kategori][] = $mobil;
    if (isset($mobil['id'])) {
        $armadaMap[(int) $mobil['id']] = $mobil['nama_mobil'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $namaPelanggan = trim($_POST['nama_pelanggan'] ?? '');
    $noKontak = trim($_POST['no_kontak'] ?? '');
    $kotaAsal = trim($_POST['kota_asal'] ?? '');
    $kotaTujuan = trim($_POST['kota_tujuan'] ?? '');
    $tanggalSewa = trim($_POST['tanggal_sewa'] ?? '');
    $areaOperasional = trim($_POST['area_operasional'] ?? '');
    $kategoriLayanan = trim($_POST['kategori_layanan'] ?? '');
    $kategoriArmada = trim($_POST['kategori_armada'] ?? '');
    $armadaId = filter_input(INPUT_POST, 'armada_id', FILTER_VALIDATE_INT);

    $allowedAreas = ['Jawa Barat', 'Jawa Tengah', 'Jawa Timur', 'Semua Area'];
    $allowedServices = ['Travel', 'Pribadi', 'Antar-Jemput', 'Wisata'];

    $errors = [];

    if ($namaPelanggan === '' || strlen($namaPelanggan) < 2) {
        $errors[] = 'Nama lengkap wajib diisi minimal 2 karakter.';
    }

    if ($noKontak === '' || !preg_match('/^[0-9+\-\s()]{8,20}$/', $noKontak)) {
        $errors[] = 'Nomor kontak/WhatsApp tidak valid.';
    }

    if ($kotaAsal === '' || strlen($kotaAsal) < 2) {
        $errors[] = 'Kota asal wajib diisi.';
    }

    if ($kotaTujuan === '' || strlen($kotaTujuan) < 2) {
        $errors[] = 'Kota tujuan wajib diisi.';
    }

    if ($tanggalSewa === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggalSewa)) {
        $errors[] = 'Tanggal sewa wajib diisi.';
    } else {
        $tanggalTimestamp = strtotime($tanggalSewa);
        if ($tanggalTimestamp === false || $tanggalTimestamp < strtotime(date('Y-m-d'))) {
            $errors[] = 'Tanggal sewa tidak boleh sebelum hari ini.';
        }
    }

    if (!in_array($areaOperasional, $allowedAreas, true)) {
        $errors[] = 'Area operasional belum dipilih dengan benar.';
    }

    if (!in_array($kategoriLayanan, $allowedServices, true)) {
        $errors[] = 'Kategori layanan belum dipilih dengan benar.';
    }

    if ($kategoriArmada === '' || !isset($armadaByCategory[$kategoriArmada])) {
        $errors[] = 'Kategori armada belum dipilih dengan benar.';
    }

    $validArmadaIds = [];
    foreach ($armadaByCategory[$kategoriArmada] ?? [] as $item) {
        $validArmadaIds[] = (int) $item['id'];
    }

    if ($armadaId === null || !in_array((int) $armadaId, $validArmadaIds, true)) {
        $errors[] = 'Mobil yang dipilih tidak tersedia di kategori ini.';
    }

    if (empty($errors)) {
        $stmt = $conn->prepare('INSERT INTO pemesanan (armada_id, nama_pelanggan, no_kontak, kota_asal, kota_tujuan, tanggal_sewa, area_operasional, kategori_layanan, status_pesanan) VALUES (?, ?, ?, ?, ?, ?, ?, ?, "Pending")');

        if (!$stmt) {
            $errorMessage = 'Terjadi kesalahan saat memproses data booking.';
        } else {
            $stmt->bind_param('isssssss', $armadaId, $namaPelanggan, $noKontak, $kotaAsal, $kotaTujuan, $tanggalSewa, $areaOperasional, $kategoriLayanan);

            if ($stmt->execute()) {
                $successMessage = 'Pemesanan berhasil dikirim. Admin akan mengonfirmasi jadwal dan armada Anda.';
                $waMessage = urlencode(
                    "Halo Admin, ada pesanan baru dari {$namaPelanggan}.\n" .
                    "Tujuan: {$kotaTujuan}\n" .
                    "Area: {$areaOperasional}\n" .
                    "Layanan: {$kategoriLayanan}\n" .
                    "Mobil: " . ($armadaMap[$armadaId] ?? 'Belum ditentukan') . "\n" .
                    "Tanggal: {$tanggalSewa}\n" .
                    "Mohon segera dikonfirmasi."
                );
                $_POST = [];
            } else {
                $errorMessage = 'Pemesanan gagal tersimpan. Silakan coba lagi.';
            }

            $stmt->close();
        }
    } else {
        $errorMessage = implode('<br>', $errors);
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Mobil | CarterMobil Pro</title>
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
    <header class="sticky top-0 z-50 bg-gradient-to-r from-sky-700 to-emerald-700 text-white shadow-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <a href="index.php" class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-xl bg-white/10 flex items-center justify-center text-white text-xl backdrop-blur-sm border border-white/20 overflow-hidden">
                    <img src="<?= htmlspecialchars($companyLogo . '?t=' . time(), ENT_QUOTES, 'UTF-8'); ?>" alt="Logo <?= htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8'); ?>" class="w-full h-full object-cover" onerror="this.style.display='none'; this.nextElementSibling.style.display='inline-flex';">
                    <i class="fa-solid fa-car-side hidden"></i>
                </div>
                <div>
                    <h1 class="font-bold text-lg leading-tight tracking-wide"><?= htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8'); ?></h1>
                    <p class="text-xs text-sky-100 opacity-90">Booking Mobil</p>
                </div>
            </a>
            <nav class="flex items-center space-x-2 sm:space-x-4">
                <a href="index.php" class="px-3 py-2 rounded-lg text-sm font-medium hover:bg-white/10 text-sky-100 transition">
                    <i class="fa-solid fa-house mr-1.5"></i> Beranda
                </a>
                <a href="booking.php" class="px-3 py-2 rounded-lg text-sm font-medium bg-white/20 text-white transition">
                    <i class="fa-solid fa-ticket mr-1.5"></i> Booking
                </a>
            </nav>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="mb-8 text-center">
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-sky-100 text-sky-700 border border-sky-200 mb-3">
                <i class="fa-solid fa-car-side mr-2"></i> Form Booking Baru
            </span>
            <h2 class="text-3xl font-extrabold text-slate-900">Pesan kendaraan sesuai kebutuhan perjalanan Anda</h2>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-[1.1fr_1.2fr] gap-8 items-start">
            <div class="space-y-5">
                <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-5">
                    <h3 class="text-xl font-bold text-slate-900 mb-4">Armada Tersedia</h3>
                    <?php if (empty($armadaByCategory)): ?>
                        <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-6 text-center text-sm text-slate-500">
                            Belum ada armada yang diinput admin.
                        </div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($armadaByCategory as $kategori => $items): ?>
                                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-3">
                                    <div class="flex items-center justify-between mb-3">
                                        <span class="font-bold text-slate-800"><?= htmlspecialchars($kategori, ENT_QUOTES, 'UTF-8'); ?></span>
                                        <span class="text-xs text-slate-500"><?= count($items); ?> mobil</span>
                                    </div>
                                    <div class="grid grid-cols-1 gap-3">
                                        <?php foreach ($items as $item): ?>
                                            <div class="flex items-center gap-3 bg-white rounded-xl border border-slate-200 p-2">
                                                <?php $itemImage = trim((string) ($item['image_url'] ?? '')); ?>
                                                <?php if ($itemImage !== ''): ?>
                                                    <img src="<?= htmlspecialchars($itemImage, ENT_QUOTES, 'UTF-8'); ?>" alt="<?= htmlspecialchars($item['nama_mobil'], ENT_QUOTES, 'UTF-8'); ?>" class="w-20 h-16 object-cover rounded-lg" onerror="this.style.display='none';">
                                                <?php else: ?>
                                                    <div class="w-20 h-16 rounded-lg bg-slate-200 flex items-center justify-center text-slate-400">
                                                        <i class="fa-solid fa-car-side"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="flex-1">
                                                    <div class="font-semibold text-slate-900"><?= htmlspecialchars($item['nama_mobil'], ENT_QUOTES, 'UTF-8'); ?></div>
                                                    <div class="text-xs text-slate-500"><?= htmlspecialchars($item['kapasitas'], ENT_QUOTES, 'UTF-8'); ?></div>
                                                </div>
                                                <div class="text-right">
                                                    <div class="text-xs text-emerald-600 font-semibold">Sisa <?= (int) ($item['available_seats'] ?? 0); ?> kursi</div>
                                                    <div class="text-xs text-slate-500">Rp <?= number_format((float) ($item['harga_sewa'] ?? 0), 0, ',', '.'); ?></div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6">
                <?php if ($successMessage !== ''): ?>
                    <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 text-emerald-700 px-4 py-3 text-sm">
                        <?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                    <?php if ($waMessage !== ''): ?>
                        <div class="mb-4">
                            <a href="https://wa.me/<?= htmlspecialchars($adminWhatsappNumber, ENT_QUOTES, 'UTF-8'); ?>?text=<?= $waMessage; ?>" target="_blank" rel="noopener noreferrer" class="inline-flex items-center justify-center w-full rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-semibold px-4 py-3 transition">
                                <i class="fa-brands fa-whatsapp mr-2"></i> Konfirmasi via WhatsApp
                            </a>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if ($errorMessage !== ''): ?>
                    <div class="mb-4 rounded-xl border border-red-200 bg-red-50 text-red-700 px-4 py-3 text-sm">
                        <?= $errorMessage; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="booking.php" class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Nama Lengkap</label>
                            <input type="text" name="nama_pelanggan" value="<?= htmlspecialchars($_POST['nama_pelanggan'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-sky-500 focus:border-sky-500 outline-none transition text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">WhatsApp</label>
                            <input type="tel" name="no_kontak" value="<?= htmlspecialchars($_POST['no_kontak'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-sky-500 focus:border-sky-500 outline-none transition text-sm">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Kota Asal</label>
                            <input type="text" name="kota_asal" value="<?= htmlspecialchars($_POST['kota_asal'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-sky-500 focus:border-sky-500 outline-none transition text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Kota Tujuan</label>
                            <input type="text" name="kota_tujuan" value="<?= htmlspecialchars($_POST['kota_tujuan'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-sky-500 focus:border-sky-500 outline-none transition text-sm">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Tanggal Sewa</label>
                            <input type="date" name="tanggal_sewa" value="<?= htmlspecialchars($_POST['tanggal_sewa'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-sky-500 focus:border-sky-500 outline-none transition text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Area Operasional</label>
                            <select name="area_operasional" required class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-sky-500 focus:border-sky-500 outline-none transition text-sm bg-white">
                                <option value="">-- Pilih Area --</option>
                                <?php foreach (['Jawa Barat', 'Jawa Tengah', 'Jawa Timur', 'Semua Area'] as $area): ?>
                                    <option value="<?= htmlspecialchars($area, ENT_QUOTES, 'UTF-8'); ?>" <?= (($_POST['area_operasional'] ?? '') === $area) ? 'selected' : ''; ?>><?= htmlspecialchars($area, ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Kategori</label>
                            <select id="kategori_armada" name="kategori_armada" required class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-sky-500 focus:border-sky-500 outline-none transition text-sm bg-white">
                                <option value="">-- Pilih Kategori --</option>
                                <?php foreach (array_keys($armadaByCategory) as $kategori): ?>
                                    <option value="<?= htmlspecialchars($kategori, ENT_QUOTES, 'UTF-8'); ?>" <?= (($_POST['kategori_armada'] ?? '') === $kategori) ? 'selected' : ''; ?>><?= htmlspecialchars($kategori, ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Mobil</label>
                            <select id="unit_armada" name="armada_id" required class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-sky-500 focus:border-sky-500 outline-none transition text-sm bg-white">
                                <option value="">-- Pilih Mobil --</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Kategori Layanan</label>
                            <select name="kategori_layanan" required class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-sky-500 focus:border-sky-500 outline-none transition text-sm bg-white">
                                <option value="">-- Pilih Layanan --</option>
                                <?php foreach (['Travel', 'Pribadi', 'Antar-Jemput', 'Wisata'] as $service): ?>
                                    <option value="<?= htmlspecialchars($service, ENT_QUOTES, 'UTF-8'); ?>" <?= (($_POST['kategori_layanan'] ?? '') === $service) ? 'selected' : ''; ?>><?= htmlspecialchars($service, ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="rounded-2xl border border-sky-200 bg-sky-50 p-3 flex flex-col justify-center">
                            <div class="text-xs uppercase tracking-wide text-sky-700 font-semibold">Estimasi Harga</div>
                            <div id="selectedPrice" class="text-xl font-bold text-slate-900 mt-1">Pilih mobil</div>
                        </div>
                    </div>

                    <button type="submit" class="w-full py-4 rounded-xl bg-gradient-to-r from-sky-600 to-emerald-600 hover:from-sky-500 hover:to-emerald-500 text-white font-bold shadow-lg shadow-sky-900/20 transition flex items-center justify-center space-x-2">
                        <i class="fa-solid fa-paper-plane"></i>
                        <span>Pesan Sekarang</span>
                    </button>
                </form>
            </div>
        </div>
    </main>

    <script>
        const fleetData = <?= json_encode($armadaByCategory, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
        const categorySelect = document.getElementById('kategori_armada');
        const unitSelect = document.getElementById('unit_armada');
        const selectedPrice = document.getElementById('selectedPrice');

        function formatRupiah(value) {
            return new Intl.NumberFormat('id-ID', {
                style: 'currency',
                currency: 'IDR',
                maximumFractionDigits: 0
            }).format(value);
        }

        function updateUnitOptions() {
            const category = categorySelect.value;
            const items = fleetData[category] || [];
            unitSelect.innerHTML = '<option value="">-- Pilih Mobil --</option>';

            items.forEach((item) => {
                const option = document.createElement('option');
                option.value = item.id;
                option.textContent = item.nama_mobil + ' (' + item.kapasitas + ')';
                option.dataset.price = item.harga_sewa;
                unitSelect.appendChild(option);
            });

            if (items.length > 0) {
                const first = items[0];
                selectedPrice.textContent = formatRupiah(Number(first.harga_sewa));
            } else {
                selectedPrice.textContent = 'Pilih mobil';
            }
        }

        categorySelect.addEventListener('change', updateUnitOptions);

        unitSelect.addEventListener('change', function () {
            const selected = unitSelect.options[unitSelect.selectedIndex];
            if (selected && selected.dataset.price) {
                selectedPrice.textContent = formatRupiah(Number(selected.dataset.price));
            } else {
                selectedPrice.textContent = 'Pilih mobil';
            }
        });

        const initialCategory = categorySelect.value;
        if (initialCategory) {
            updateUnitOptions();
            const selectedValue = <?= json_encode((string) ($_POST['armada_id'] ?? '')); ?>;
            if (selectedValue) {
                Array.from(unitSelect.options).forEach((option) => {
                    if (String(option.value) === String(selectedValue)) {
                        option.selected = true;
                    }
                });
            }
        }
    </script>
</body>
</html>

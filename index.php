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

$areaOperasionalList = ['Jawa Barat', 'Jawa Tengah', 'Jawa Timur', 'Semua Area'];
$kategoriLayananList = ['Travel', 'Pribadi', 'Antar-Jemput', 'Wisata'];

$armadaQuery = $conn->query('SELECT * FROM armada WHERE status = "Aktif" ORDER BY kategori, nama_mobil');
$armadaList = $armadaQuery ? $armadaQuery->fetch_all(MYSQLI_ASSOC) : [];

$fleetStats = [];
$armadaByCategory = [];
foreach ($armadaList as $armadaItem) {
    $category = trim((string) ($armadaItem['kategori'] ?? ''));
    if ($category === '') {
        continue;
    }

    if (!isset($armadaByCategory[$category])) {
        $armadaByCategory[$category] = [];
    }

    $armadaByCategory[$category][] = $armadaItem;
}

$bookingCounts = [];
$result = $conn->query('SELECT armada_id, COUNT(*) AS total FROM pemesanan GROUP BY armada_id');
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $bookingCounts[(int) $row['armanda_id']] = (int) $row['total'];
    }
}

foreach ($armadaByCategory as $category => $items) {
    $booked = 0;
    $available = 0;
    $firstImage = '';

    foreach ($items as $item) {
        $imageUrl = trim((string) ($item['image_url'] ?? ''));
        if ($firstImage === '' && $imageUrl !== '') {
            $firstImage = preg_match('#^https?://#', $imageUrl) ? $imageUrl : $normalizeBrandPath($imageUrl);
        }

        $capacity = preg_match('/(\d+)/', (string) ($item['kapasitas'] ?? ''), $numbers) ? (int) max($numbers) : 0;
        $itemBooked = $bookingCounts[(int) ($item['id'] ?? 0)] ?? 0;
        $available += max(0, $capacity - $itemBooked);
        $booked += $itemBooked;
    }

    $fleetStats[$category] = [
        'capacity' => 'Kapasitas sesuai data',
        'image' => $firstImage,
        'label' => $category,
        'booked' => $booked,
        'available' => $available,
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $namaPelanggan = trim($_POST['nama_pelanggan'] ?? '');
    $noKontak = trim($_POST['no_kontak'] ?? '');
    $kotaAsal = trim($_POST['kota_asal'] ?? '');
    $kotaTujuan = trim($_POST['kota_tujuan'] ?? '');
    $tanggalSewa = trim($_POST['tanggal_sewa'] ?? '');
    $areaOperasional = trim($_POST['area_operasional'] ?? '');
    $kategoriLayanan = trim($_POST['kategori_layanan'] ?? '');
    $armadaId = filter_input(INPUT_POST, 'armada_id', FILTER_VALIDATE_INT);

    $errors = [];

    if ($namaPelanggan === '' || strlen($namaPelanggan) < 2) {
        $errors[] = 'Nama lengkap wajib diisi minimal 2 karakter.';
    }

    if ($noKontak === '' || !preg_match('/^[0-9+\-\s()]{8,20}$/', $noKontak)) {
        $errors[] = 'Nomor kontak/WhatsApp tidak valid. Gunakan format yang benar.';
    }

    if ($kotaAsal === '' || strlen($kotaAsal) < 2) {
        $errors[] = 'Kota asal wajib diisi.';
    }

    if ($kotaTujuan === '' || strlen($kotaTujuan) < 2) {
        $errors[] = 'Kota tujuan wajib diisi.';
    }

    if ($tanggalSewa === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggalSewa)) {
        $errors[] = 'Tanggal pelaksanaan sewa harus diisi dengan format yang benar.';
    } else {
        $tanggalTimestamp = strtotime($tanggalSewa);
        if ($tanggalTimestamp === false || $tanggalTimestamp < strtotime(date('Y-m-d'))) {
            $errors[] = 'Tanggal sewa tidak boleh kurang dari hari ini.';
        }
    }

    if (!in_array($areaOperasional, $areaOperasionalList, true)) {
        $errors[] = 'Area operasional belum dipilih dengan benar.';
    }

    if (!in_array($kategoriLayanan, $kategoriLayananList, true)) {
        $errors[] = 'Kategori layanan belum dipilih dengan benar.';
    }

    if ($armadaId === null || $armadaId === false) {
        $errors[] = 'Armada belum dipilih dengan benar.';
    }

    if (empty($errors)) {
        $stmt = $conn->prepare('INSERT INTO pemesanan (armada_id, nama_pelanggan, no_kontak, kota_asal, kota_tujuan, tanggal_sewa, area_operasional, kategori_layanan, status_pesanan) VALUES (?, ?, ?, ?, ?, ?, ?, ?, "Pending")');

        if (!$stmt) {
            $errorMessage = 'Terjadi kesalahan saat mempersiapkan penyimpanan data.';
        } else {
            $stmt->bind_param(
                'isssssss',
                $armadaId,
                $namaPelanggan,
                $noKontak,
                $kotaAsal,
                $kotaTujuan,
                $tanggalSewa,
                $areaOperasional,
                $kategoriLayanan
            );

            if ($stmt->execute()) {
                $successMessage = 'Pemesanan Anda berhasil dikirim. Tim kami akan segera menghubungi Anda untuk konfirmasi.';
                $_POST = [];
                $namaPelanggan = '';
                $noKontak = '';
                $kotaAsal = '';
                $kotaTujuan = '';
                $tanggalSewa = '';
                $areaOperasional = '';
                $kategoriLayanan = '';
                $armadaId = null;
            } else {
                $errorMessage = 'Pemesanan gagal tersimpan. Silakan coba lagi atau hubungi admin.';
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
    <title>CarterMobil Pro | Booking Mobil</title>
    <meta name="description" content="Sistem informasi booking carter mobil profesional untuk kebutuhan travel, antar-jemput, pribadi, dan wisata.">
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
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-xl bg-white/10 flex items-center justify-center text-white text-xl backdrop-blur-sm border border-white/20 overflow-hidden">
                    <img src="<?= htmlspecialchars($companyLogo . '?t=' . time(), ENT_QUOTES, 'UTF-8'); ?>" alt="Logo <?= htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8'); ?>" class="w-full h-full object-cover" onerror="this.style.display='none'; this.nextElementSibling.style.display='inline-flex';">
                    <i class="fa-solid fa-car-side hidden"></i>
                </div>
                <div>
                    <h1 class="font-bold text-lg leading-tight tracking-wide"><?= htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8'); ?></h1>
                    <p class="text-xs text-sky-100 opacity-90">Sistem Booking & Manajemen Armada</p>
                </div>
            </div>
            <nav class="flex items-center space-x-2 sm:space-x-4">
                <a href="#home" class="px-3 py-2 rounded-lg text-sm font-medium bg-white/20 text-white transition">
                    <i class="fa-solid fa-house mr-1.5"></i> Beranda
                </a>
                <a href="#booking" class="px-3 py-2 rounded-lg text-sm font-medium hover:bg-white/10 text-sky-100 transition">
                    <i class="fa-solid fa-ticket mr-1.5"></i> Form Booking
                </a>
            </nav>
        </div>
    </header>

    <main class="max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <section id="home" class="space-y-8">
            <div class="relative rounded-3xl overflow-hidden bg-gradient-to-br from-sky-800 via-sky-700 to-emerald-800 text-white p-8 sm:p-12 shadow-xl">
                <div class="absolute -right-10 -bottom-10 opacity-10 text-9xl">
                    <i class="fa-solid fa-car"></i>
                </div>
                <div class="relative z-10 max-w-2xl space-y-4">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-emerald-500/30 text-emerald-200 border border-emerald-400/30">
                        <i class="fa-solid fa-shield-halved mr-1.5"></i> Solusi Transportasi Digital Perorangan
                    </span>
                    <h2 class="text-3xl sm:text-4xl font-extrabold tracking-tight">Sistem Informasi Pemesanan & Carter Mobil Cepat & Terstruktur</h2>
                    <p class="text-sky-100 text-sm sm:text-base leading-relaxed">
                        Atasi kendala double booking dan pencatatan manual. Pilih area operasional Anda di Jawa Barat, Jawa Tengah, Jawa Timur, atau Seluruh Area dengan armada pribadi hingga bus wisata besar.
                    </p>
                    <div class="pt-2 flex flex-wrap gap-3">
                        <a href="#booking" class="px-6 py-3 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-semibold shadow-lg shadow-emerald-900/25 transition flex items-center space-x-2">
                            <span>Mulai Booking</span>
                            <i class="fa-solid fa-arrow-right text-xs"></i>
                        </a>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 hover:shadow-md transition">
                    <div class="w-12 h-12 rounded-xl bg-sky-100 text-sky-600 flex items-center justify-center text-xl mb-4">
                        <i class="fa-solid fa-map-location-dot"></i>
                    </div>
                    <h3 class="font-bold text-lg text-slate-900 mb-2">Area Operasional Luas</h3>
                    <p class="text-sm text-slate-600 leading-relaxed">Mendukung perjalanan lintas wilayah meliputi Jawa Barat, Jawa Tengah, Jawa Timur, maupun Semua Area.</p>
                </div>
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 hover:shadow-md transition">
                    <div class="w-12 h-12 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center text-xl mb-4">
                        <i class="fa-solid fa-van-shuttle"></i>
                    </div>
                    <h3 class="font-bold text-lg text-slate-900 mb-2">Variasi Armada Lengkap</h3>
                    <p class="text-sm text-slate-600 leading-relaxed">Pilihan armada mulai dari mobil pribadi, minibus nyaman, hingga bus wisata besar berkapasitas tinggi.</p>
                </div>
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 hover:shadow-md transition">
                    <div class="w-12 h-12 rounded-xl bg-sky-100 text-sky-600 flex items-center justify-center text-xl mb-4">
                        <i class="fa-solid fa-clipboard-check"></i>
                    </div>
                    <h3 class="font-bold text-lg text-slate-900 mb-2">Manajemen Transparan</h3>
                    <p class="text-sm text-slate-600 leading-relaxed">Pencatatan pesanan real-time dan rekapitulasi laporan bulanan otomatis bagi pemilik usaha (admin).</p>
                </div>
            </div>
        </section>

        <section class="mt-10">
            <div class="mb-6 text-center">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-sky-100 text-sky-700 border border-sky-200 mb-3">
                    <i class="fa-solid fa-car-side mr-2"></i> Armada Tersedia
                </span>
                <h3 class="text-3xl font-extrabold text-slate-900">Pilih kategori mobil sesuai kebutuhan Anda</h3>
            </div>

            <?php if (empty($fleetStats)): ?>
                <div class="rounded-3xl border border-dashed border-slate-300 bg-slate-50 p-8 text-center text-slate-500">
                    Belum ada armada yang diinput admin. Setelah data armada ditambahkan, daftar mobil akan muncul di sini.
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <?php foreach ($fleetStats as $armada => $meta): ?>
                        <button type="button" class="fleet-category-btn bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden hover:shadow-lg transition text-left" data-category="<?= htmlspecialchars($armada, ENT_QUOTES, 'UTF-8'); ?>">
                            <img src="<?= htmlspecialchars((string) ($meta['image'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" alt="<?= htmlspecialchars($armada, ENT_QUOTES, 'UTF-8'); ?>" class="w-full h-52 object-cover" onerror="this.src='https://images.unsplash.com/photo-1544636331-e26879cd4d9b?auto=format&fit=crop&w=900&q=80'">
                            <div class="p-5">
                                <div class="flex items-center justify-between mb-3">
                                    <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-sky-100 text-sky-700"><?= htmlspecialchars($meta['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    <span class="text-xs font-medium text-slate-500"><?= htmlspecialchars((string) $meta['booked'], ENT_QUOTES, 'UTF-8'); ?> booked</span>
                                </div>
                                <h4 class="text-xl font-bold text-slate-900 mb-1"><?= htmlspecialchars($armada, ENT_QUOTES, 'UTF-8'); ?></h4>
                                <p class="text-sm text-slate-500 mb-3">Kapasitas: <?= htmlspecialchars($meta['capacity'], ENT_QUOTES, 'UTF-8'); ?></p>
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-emerald-600 font-semibold"><?= htmlspecialchars((string) $meta['available'], ENT_QUOTES, 'UTF-8'); ?> tersedia</span>
                                    <span class="text-slate-500"><?= htmlspecialchars((string) $meta['booked'], ENT_QUOTES, 'UTF-8'); ?> sudah booking</span>
                                </div>
                            </div>
                        </button>
                    <?php endforeach; ?>
                </div>

                <div class="mt-8 bg-white rounded-3xl border border-slate-200 shadow-sm p-6">
                    <div class="flex items-center justify-between mb-5">
                        <h4 class="text-2xl font-bold text-slate-900">Pilihan mobil ready</h4>
                        <span class="text-xs px-3 py-1 rounded-full bg-sky-100 text-sky-700 font-semibold">Klik kategori untuk melihat mobil</span>
                    </div>

                    <div id="fleet-detail" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                        <?php foreach ($armadaByCategory as $kategori => $items): ?>
                            <div class="fleet-detail-item <?= $kategori === 'Mobil Pribadi' ? '' : 'hidden'; ?>" data-category="<?= htmlspecialchars($kategori, ENT_QUOTES, 'UTF-8'); ?>">
                                <?php foreach ($items as $item): ?>
                                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                        <div class="flex items-center justify-between mb-2">
                                            <span class="font-bold text-slate-900"><?= htmlspecialchars($item['nama_mobil'], ENT_QUOTES, 'UTF-8'); ?></span>
                                            <span class="px-2 py-1 rounded-full text-[10px] font-semibold bg-emerald-100 text-emerald-700">
                                                Ready
                                            </span>
                                        </div>
                                        <div class="text-sm text-slate-600 mb-2">Kapasitas: <?= htmlspecialchars($item['kapasitas'], ENT_QUOTES, 'UTF-8'); ?></div>
                                        <div class="flex justify-between items-center text-sm text-slate-500">
                                            <span>Sisa kursi: <strong class="text-slate-800"><?= (int) max(0, preg_match('/(\d+)/', (string) ($item['kapasitas'] ?? ''), $numbers) ? (int) max($numbers) - (($bookingCounts[(int) ($item['id'] ?? 0)] ?? 0)) : 0); ?></strong></span>
                                            <span>Rp <?= number_format((float) ($item['harga_sewa'] ?? 0), 0, ',', '.'); ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </section>

        <section class="mt-10">
            <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-6 sm:p-10 max-w-4xl mx-auto text-center">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-sky-100 text-sky-700 border border-sky-200 mb-3">
                    <i class="fa-solid fa-car-side mr-2"></i> Siap Melayani
                </span>
                <h3 class="text-3xl font-extrabold text-slate-900 mb-4">Pilih armada terbaik untuk perjalanan Anda</h3>
                <p class="text-slate-600 mb-6 max-w-2xl mx-auto">Semua pilihan mobil tersedia di halaman booking yang terpisah, agar homepage tetap bersih, modern, dan fokus pada promosi armada.</p>
                <a href="booking.php" class="inline-flex items-center justify-center px-6 py-3 rounded-xl bg-gradient-to-r from-sky-600 to-emerald-600 hover:from-sky-500 hover:to-emerald-500 text-white font-bold shadow-lg shadow-sky-900/20 transition">
                    <span>Mulai Booking</span>
                    <i class="fa-solid fa-arrow-right ml-2"></i>
                </a>
            </div>
        </section>
    </main>

    <footer class="bg-slate-900 text-slate-200 py-6 mt-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col md:flex-row justify-between items-center gap-3 text-sm">
            <div>
                <span class="font-bold text-white"><?= htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="text-slate-400 ml-2">Layanan mobil profesional di seluruh Jawa.</span>
            </div>
            <div class="flex flex-col md:flex-row items-center gap-2 text-slate-400">
                <span>WhatsApp: 0812-3456-7890 | Email: admin@cartermobilpro.com</span>
                <a href="admin/login.php" class="text-sky-300 hover:text-white transition">Admin Login</a>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const categoryButtons = document.querySelectorAll('.fleet-category-btn');
        const detailItems = document.querySelectorAll('.fleet-detail-item');

        categoryButtons.forEach((button) => {
            button.addEventListener('click', () => {
                const category = button.dataset.category;

                detailItems.forEach((item) => {
                    const isMatch = item.dataset.category === category;
                    item.classList.toggle('hidden', !isMatch);
                    item.classList.toggle('grid', isMatch);
                    if (isMatch) {
                        item.classList.add('md:grid-cols-2', 'xl:grid-cols-3');
                    }
                });

                categoryButtons.forEach((btn) => {
                    btn.classList.toggle('ring-2', btn === button);
                    btn.classList.toggle('ring-sky-300', btn === button);
                    btn.classList.toggle('shadow-lg', btn === button);
                });
            });
        });
    </script>
</body>
</html>

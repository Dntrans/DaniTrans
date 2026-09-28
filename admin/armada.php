<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../koneksi.php';

function getKapasitasMobil(string $kapasitas): int {
    if ($kapasitas === '') {
        return 0;
    }

    preg_match_all('/(\d+)/', $kapasitas, $matches);
    $numbers = array_map('intval', $matches[1] ?? []);

    return $numbers ? max($numbers) : 0;
}

$message = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_armada'])) {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $namaMobil = trim($_POST['nama_mobil'] ?? '');
        $kategori = trim($_POST['kategori'] ?? '');
        $kapasitas = trim($_POST['kapasitas'] ?? '');
        $hargaSewa = (float) ($_POST['harga_sewa'] ?? 0);
        $deskripsi = trim($_POST['deskripsi'] ?? '');
        $status = trim($_POST['status'] ?? 'Aktif');
        $imageUrl = '';
        $kapasitasKursi = getKapasitasMobil($kapasitas);

        if ($id) {
            $existingImageStmt = $conn->prepare('SELECT image_url FROM armada WHERE id = ? LIMIT 1');
            if ($existingImageStmt) {
                $existingImageStmt->bind_param('i', $id);
                $existingImageStmt->execute();
                $existingImageResult = $existingImageStmt->get_result();
                if ($existingImageResult && $existingImageResult->num_rows > 0) {
                    $existingImageRow = $existingImageResult->fetch_assoc();
                    $imageUrl = trim((string) ($existingImageRow['image_url'] ?? ''));
                }
                $existingImageStmt->close();
            }
        }

        $uploadDir = dirname(__DIR__) . '/uploads/armada';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['image_file'];
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if (!in_array($extension, $allowedExtensions, true)) {
                $errorMessage = 'Format file tidak valid. Gunakan JPG, JPEG, PNG, atau WEBP.';
            } elseif ($file['size'] > 2 * 1024 * 1024) {
                $errorMessage = 'Ukuran file terlalu besar. Maksimal 2 MB.';
            } else {
                $fileName = 'armada_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
                $targetPath = $uploadDir . '/' . $fileName;

                if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                    $imageUrl = '/uploads/armada/' . $fileName;
                } else {
                    $errorMessage = 'Gagal mengunggah gambar mobil.';
                }
            }
        }

        if ($namaMobil === '' || $kategori === '' || $kapasitas === '') {
            $errorMessage = 'Nama mobil, kategori, dan kapasitas wajib diisi.';
        } elseif ($errorMessage === '') {
            if ($id) {
                $stmt = $conn->prepare('UPDATE armada SET nama_mobil = ?, kategori = ?, kapasitas = ?, harga_sewa = ?, sisa_kursi = ?, image_url = ?, deskripsi = ?, status = ? WHERE id = ?');
                $stmt->bind_param('sssidsssi', $namaMobil, $kategori, $kapasitas, $hargaSewa, $kapasitasKursi, $imageUrl, $deskripsi, $status, $id);
            } else {
                $stmt = $conn->prepare('INSERT INTO armada (nama_mobil, kategori, kapasitas, harga_sewa, sisa_kursi, image_url, deskripsi, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                $stmt->bind_param('sssidsss', $namaMobil, $kategori, $kapasitas, $hargaSewa, $kapasitasKursi, $imageUrl, $deskripsi, $status);
            }

            if ($stmt->execute()) {
                $message = 'Data armada berhasil disimpan.';
            } else {
                $errorMessage = 'Gagal menyimpan data armada.';
            }

            $stmt->close();
        }
    }

    if (isset($_POST['delete_armada'])) {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

        if ($id) {
            $deleteStmt = $conn->prepare('DELETE FROM armada WHERE id = ?');
            $deleteStmt->bind_param('i', $id);
            $deleteStmt->execute();
            $deleteStmt->close();
            $message = 'Data armada berhasil dihapus.';
        }
    }
}

$query = $conn->query('SELECT * FROM armada ORDER BY kategori, nama_mobil');
$armadaList = $query ? $query->fetch_all(MYSQLI_ASSOC) : [];

$bookingCounts = [];
$bookingResult = $conn->query('SELECT armada_id, COUNT(*) AS total FROM pemesanan GROUP BY armada_id');
if ($bookingResult) {
    while ($row = $bookingResult->fetch_assoc()) {
        $bookingCounts[(int) $row['armada_id']] = (int) $row['total'];
    }
}

foreach ($armadaList as &$armadaItem) {
    $capacity = getKapasitasMobil((string) ($armadaItem['kapasitas'] ?? '0'));
    $booked = $bookingCounts[(int) ($armadaItem['id'] ?? 0)] ?? 0;
    $armadaItem['available_seats'] = max(0, $capacity - $booked);
}
unset($armadaItem);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Armada | CarterMobil Pro</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f8fafc; }
    </style>
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 antialiased">
    <header class="bg-gradient-to-r from-sky-700 to-emerald-700 text-white shadow-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-xl bg-white/10 flex items-center justify-center text-white text-xl backdrop-blur-sm border border-white/20">
                    <i class="fa-solid fa-car-side"></i>
                </div>
                <div>
                    <h1 class="font-bold text-lg leading-tight tracking-wide">CarterMobil Pro</h1>
                    <p class="text-xs text-sky-100 opacity-90">Manajemen Armada</p>
                </div>
            </div>
            <div class="flex items-center space-x-3">
                <a href="dashboard.php" class="px-3 py-2 rounded-lg bg-white/10 hover:bg-white/20 text-white text-sm font-medium transition">Dashboard</a>
                <a href="logout.php" class="px-3 py-2 rounded-lg bg-white/10 hover:bg-white/20 text-white text-sm font-medium transition">Logout</a>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        <?php if ($message !== ''): ?>
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 text-emerald-700 px-4 py-3 text-sm">
                <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <?php if ($errorMessage !== ''): ?>
            <div class="rounded-xl border border-red-200 bg-red-50 text-red-700 px-4 py-3 text-sm">
                <?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-6">
            <h2 class="text-2xl font-bold text-slate-900 mb-6">Tambah / Edit Armada</h2>
            <form method="POST" action="armada.php" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">Nama Mobil</label>
                    <input type="text" name="nama_mobil" class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-sky-500 outline-none" placeholder="Contoh: Avanza" required>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">Kategori</label>
                    <select name="kategori" class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-sky-500 outline-none" required>
                        <option value="">-- Pilih Kategori --</option>
                        <option value="Mobil Pribadi">Mobil Pribadi</option>
                        <option value="Minibus">Minibus</option>
                        <option value="Bus Wisata Besar">Bus Wisata Besar</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">Kapasitas</label>
                    <input type="text" name="kapasitas" class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-sky-500 outline-none" placeholder="Contoh: 4 - 7 Kursi" required>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">Harga Sewa</label>
                    <input type="number" name="harga_sewa" min="0" class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-sky-500 outline-none" placeholder="600000" required>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">Status</label>
                    <select name="status" class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-sky-500 outline-none">
                        <option value="Aktif">Aktif</option>
                        <option value="Nonaktif">Nonaktif</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-slate-700 mb-2">Upload Gambar Mobil</label>
                    <input id="image_file_input" type="file" name="image_file" accept="image/jpeg,image/png,image/webp" class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-sky-500 outline-none bg-white">
                    <div class="mt-3 rounded-xl border border-dashed border-slate-300 bg-slate-50 p-3">
                        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Preview Gambar</p>
                        <img id="image_preview" src="https://images.unsplash.com/photo-1544636331-e26879cd4d9b?auto=format&fit=crop&w=900&q=80" alt="Preview gambar mobil" class="hidden w-40 h-28 object-cover rounded-xl border border-slate-200" onerror="this.classList.add('hidden')">
                    </div>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-slate-700 mb-2">Deskripsi</label>
                    <textarea name="deskripsi" rows="3" class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-sky-500 outline-none" placeholder="Deskripsi armada..."></textarea>
                </div>
                <div class="md:col-span-2 flex justify-end">
                    <button type="submit" name="save_armada" value="1" class="px-5 py-3 rounded-xl bg-gradient-to-r from-sky-600 to-emerald-600 text-white font-semibold shadow-lg shadow-sky-900/20 transition">
                        <i class="fa-solid fa-save mr-2"></i> Simpan Armada
                    </button>
                </div>
            </form>
        </div>

        <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="p-6 border-b border-slate-100">
                <h3 class="text-xl font-bold text-slate-900">Daftar Armada</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-sm">
                    <thead>
                        <tr class="bg-slate-50 text-slate-600 border-b border-slate-200 text-xs font-semibold uppercase tracking-wider">
                            <th class="p-4">Gambar</th>
                            <th class="p-4">Nama</th>
                            <th class="p-4">Kategori</th>
                            <th class="p-4">Kapasitas</th>
                            <th class="p-4">Harga</th>
                            <th class="p-4">Sisa Kursi</th>
                            <th class="p-4">Status</th>
                            <th class="p-4 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if (empty($armadaList)): ?>
                            <tr>
                                <td colspan="8" class="p-8 text-center text-slate-400">Belum ada data armada.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($armadaList as $armada): ?>
                                <tr class="hover:bg-slate-50 transition">
                                    <td class="p-4">
                                        <img src="<?= htmlspecialchars($armada['image_url'] ?? 'https://images.unsplash.com/photo-1544636331-e26879cd4d9b?auto=format&fit=crop&w=900&q=80', ENT_QUOTES, 'UTF-8'); ?>" alt="<?= htmlspecialchars($armada['nama_mobil'], ENT_QUOTES, 'UTF-8'); ?>" class="w-20 h-16 object-cover rounded-lg border border-slate-200" onerror="this.src='https://images.unsplash.com/photo-1544636331-e26879cd4d9b?auto=format&fit=crop&w=900&q=80'">
                                    </td>
                                    <td class="p-4">
                                        <div class="font-semibold text-slate-900"><?= htmlspecialchars($armada['nama_mobil'], ENT_QUOTES, 'UTF-8'); ?></div>
                                    </td>
                                    <td class="p-4"><?= htmlspecialchars($armada['kategori'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td class="p-4"><?= htmlspecialchars($armada['kapasitas'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td class="p-4">Rp <?= number_format((float) $armada['harga_sewa'], 0, ',', '.'); ?></td>
                                    <td class="p-4"><?= (int) ($armada['available_seats'] ?? 0); ?></td>
                                    <td class="p-4">
                                        <span class="px-2.5 py-1 rounded-full text-xs font-semibold <?= $armada['status'] === 'Aktif' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-700'; ?>">
                                            <?= htmlspecialchars($armada['status'], ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    </td>
                                    <td class="p-4 text-center">
                                        <div class="flex justify-center gap-2">
                                            <form method="POST" action="armada.php">
                                                <input type="hidden" name="id" value="<?= (int) $armada['id']; ?>">
                                                <button type="submit" name="delete_armada" value="1" class="px-3 py-2 rounded-lg bg-rose-50 text-rose-600 text-xs font-semibold">Hapus</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
    <script>
        const imageInput = document.getElementById('image_file_input');
        const imagePreview = document.getElementById('image_preview');

        if (imageInput && imagePreview) {
            imageInput.addEventListener('change', function () {
                const file = this.files && this.files[0];
                if (!file) {
                    imagePreview.classList.add('hidden');
                    return;
                }

                const reader = new FileReader();
                reader.onload = function (event) {
                    imagePreview.src = event.target.result;
                    imagePreview.classList.remove('hidden');
                };
                reader.readAsDataURL(file);
            });
        }
    </script>
</body>
</html>

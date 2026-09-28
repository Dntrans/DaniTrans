<?php
session_start();

if (isset($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
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

$errorMessage = '';
$successMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $errorMessage = 'Username dan password wajib diisi.';
    } else {
        $stmt = $conn->prepare('SELECT id, username, password, nama_lengkap FROM admin WHERE username = ? LIMIT 1');

        if (!$stmt) {
            $errorMessage = 'Terjadi kesalahan saat memproses login.';
        } else {
            $stmt->bind_param('s', $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $admin = $result->fetch_assoc();
                $passwordMatches = password_verify($password, $admin['password']) || hash_equals((string) $admin['password'], $password);

                if ($passwordMatches) {
                    $_SESSION['admin_id'] = (int) $admin['id'];
                    $_SESSION['admin_username'] = $admin['username'];
                    $_SESSION['admin_nama_lengkap'] = $admin['nama_lengkap'];

                    header('Location: dashboard.php');
                    exit;
                }
            }

            $errorMessage = 'Username atau password salah.';
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Access | <?= htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #eaf4ff 0%, #edfaf5 100%);
            font-family: Arial, sans-serif;
        }

        .auth-card {
            width: min(100%, 460px);
            background: #fff;
            border-radius: 22px;
            box-shadow: 0 25px 60px rgba(22, 63, 122, 0.18);
            padding: 2rem;
        }

        .brand-box {
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .brand-mark {
            width: 68px;
            height: 68px;
            border-radius: 18px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 1.7rem;
            background: linear-gradient(135deg, #0d6efd 0%, #198754 100%);
            color: #fff;
            margin-bottom: 0.8rem;
        }

        .brand-title {
            font-size: 1.7rem;
            font-weight: 800;
            color: #0d3b85;
            margin: 0;
        }

        .brand-subtitle {
            color: #5c697c;
            margin-top: 0.35rem;
            font-size: 0.9rem;
        }

        .toggle-bar {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            background: #edf4ff;
            padding: 8px;
            border-radius: 12px;
            margin-bottom: 1rem;
        }

        .toggle-btn {
            border: none;
            padding: 0.75rem 1rem;
            border-radius: 10px;
            font-weight: 700;
            background: transparent;
            color: #38557c;
            text-decoration: none;
            display: block;
            text-align: center;
        }

        .toggle-btn.active {
            background: linear-gradient(135deg, #0d6efd 0%, #198754 100%);
            color: #fff;
            box-shadow: 0 8px 18px rgba(13, 110, 253, 0.25);
        }

        .form-control {
            min-height: 48px;
            border-radius: 12px;
            border: 1px solid #dfeaf7;
        }

        .btn-submit {
            width: 100%;
            min-height: 50px;
            border-radius: 12px;
            border: none;
            background: linear-gradient(135deg, #0d6efd 0%, #198754 100%);
            font-weight: 700;
        }

        .alert {
            border-radius: 12px;
        }
    </style>
</head>
<body>
    <div class="auth-card">
        <div class="brand-box">
            <div class="brand-mark" style="overflow: hidden; padding: 0;">
                <img src="<?= htmlspecialchars($companyLogo . '?t=' . time(), ENT_QUOTES, 'UTF-8'); ?>" alt="Logo <?= htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8'); ?>" style="width: 100%; height: 100%; object-fit: cover; display: block;" onerror="this.style.display='none'; this.parentElement.innerHTML='C';">
            </div>
            <h1 class="brand-title"><?= htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8'); ?></h1>
            <div class="brand-subtitle">Panel Administrator</div>
        </div>

        <?php if ($errorMessage !== ''): ?>
            <div class="alert alert-danger" role="alert">
                <?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <div class="mb-3">
                <label for="login_username" class="form-label">Username</label>
                <input type="text" class="form-control" id="login_username" name="username" placeholder="Masukkan username" required>
            </div>
            <div class="mb-3">
                <label for="login_password" class="form-label">Password</label>
                <input type="password" class="form-control" id="login_password" name="password" placeholder="Masukkan password" required>
            </div>
            <button type="submit" class="btn btn-primary btn-submit">Login</button>
        </form>
    </div>
</body>
</html>

<?php
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$pdo = get_db();
$error_msg = null;

// Auto-login with quick role switcher buttons
if (isset($_GET['quick_role'])) {
    $role_param = $_GET['quick_role'];
    $stmt = $pdo->prepare("SELECT * FROM users WHERE role = ? LIMIT 1");
    $stmt->execute([$role_param]);
    $user = $stmt->fetch();

    if ($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['jabatan'] = $user['jabatan'];
        $_SESSION['foto_profil'] = $user['foto_profil'] ?? null;
        header("Location: index.php");
        exit;
    }
}

// Normal Login Submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!empty($username) && !empty($password)) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['jabatan'] = $user['jabatan'];
            $_SESSION['foto_profil'] = $user['foto_profil'] ?? null;
            header("Location: index.php");
            exit;
        } else {
            $error_msg = "Username atau Password yang Anda masukkan salah.";
        }
    } else {
        $error_msg = "Silakan isi Username dan Password.";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ALISA - Aplikasi Logistik Inspeksi & Servis Armada</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Custom Kurohiko Topup Exact Styling (with cache buster) -->
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
</head>
<body class="kh-login-body">

<div class="container-fluid p-0 kh-login-container">
    <div class="row g-0 min-vh-100">
        <!-- Left Side: Form Area (Exact Kurohiko Layout & Ocean Blue Theme) -->
        <div class="col-md-6 d-flex align-items-center bg-dark" style="background-color: #23272b !important;">
            <div class="kh-login-left w-100 p-4 p-md-5">
                
                <!-- Top Logo Image Centered (LOGO JATENG) -->
                <div class="d-flex justify-content-center mb-3 login-anim-1">
                    <img src="assets/images/logo_jateng.png?v=<?= time() ?>" alt="Logo Jawa Tengah" style="height: 68px; width: auto; object-fit: contain;">
                </div>

                <!-- Page Heading ALISA & Full Acronym Subtitle Centered -->
                <div class="text-center mb-4 login-anim-2">
                    <h1 class="text-white fw-extrabold mb-1" style="letter-spacing: 2px; font-size: 38px;">ALISA</h1>
                    <p class="text-white-50 mb-1 fw-semibold" style="font-size: 15px;">Aplikasi Logistik Inspeksi & Servis Armada</p>
                    <small class="text-muted" style="font-size: 0.78rem;">Balai Pengelolaan Jalan Wilayah Tegal</small>
                </div>

                <?php if ($error_msg): ?>
                    <div class="alert alert-danger alert-dismissible fade show rounded-4 small border-0 shadow-sm mb-4 bg-danger text-white text-center login-anim-2" role="alert">
                        <i class="fa-solid fa-circle-exclamation me-2"></i> <?= htmlspecialchars($error_msg) ?>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Login Form -->
                <form method="POST" action="">
                    <div class="mb-4 login-anim-3">
                        <label class="text-white fw-bold mb-2 d-block" style="font-size: 16px;">Username</label>
                        <input type="text" name="username" class="form-control rounded-pill px-4" placeholder="Masukkan username" style="height: 52px; background-color: #eaeff8 !important; border: 2px solid transparent !important; font-size: 16px; color: #0f172a !important; font-weight: 600;" required autofocus>
                    </div>

                    <div class="mb-4 login-anim-4">
                        <label class="text-white fw-bold mb-2 d-block" style="font-size: 16px;">Password</label>
                        <input type="password" name="password" class="form-control rounded-pill px-4" placeholder="Masukkan password" style="height: 52px; background-color: #eaeff8 !important; border: 2px solid transparent !important; font-size: 16px; color: #0f172a !important; font-weight: 600;" required>
                    </div>

                    <!-- SOLID OCEAN BLUE PILL BUTTON -->
                    <button type="submit" class="btn w-100 fw-bold py-3 text-white rounded-pill shadow-lg mb-4 login-anim-5" style="background-color: #0284c7 !important; border: none !important; border-radius: 50px !important; font-size: 16px; letter-spacing: 0.5px; box-shadow: 0 6px 20px rgba(2, 132, 199, 0.5) !important;">
                        Login Sekarang
                    </button>
                </form>

                <!-- 4 Quick Role Switcher Buttons Centered -->
                <div class="border-top pt-4 text-center login-anim-6" style="border-color: rgba(255, 255, 255, 0.15) !important;">
                    <span class="small d-block mb-3 text-uppercase fw-bold text-center" style="letter-spacing: 1px; color: #a0a6b0; font-size: 0.72rem;">Akses Cepat 4 Aktor:</span>
                    
                    <div class="d-flex flex-wrap justify-content-center gap-2">
                        <a href="login.php?quick_role=admin" class="btn btn-sm btn-outline-info rounded-pill px-3 py-1.5 fw-semibold">
                            <i class="fa-solid fa-user-gear me-1"></i> Admin
                        </a>
                        <a href="login.php?quick_role=teknisi" class="btn btn-sm btn-outline-warning rounded-pill px-3 py-1.5 fw-semibold">
                            <i class="fa-solid fa-wrench me-1"></i> Teknisi
                        </a>
                        <a href="login.php?quick_role=pimpinan" class="btn btn-sm btn-outline-success rounded-pill px-3 py-1.5 fw-semibold">
                            <i class="fa-solid fa-user-tie me-1"></i> Pimpinan
                        </a>
                        <a href="login.php?quick_role=bendahara" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1.5 fw-semibold">
                            <i class="fa-solid fa-wallet me-1"></i> Bendahara
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Side: Hero Banner Image Area (login_bpj.jpg Wallpaper) -->
        <div class="col-md-6 d-none d-md-block position-relative overflow-hidden login-hero-anim" style="min-height: 100vh; background-color: #1a1e22;">
            <img src="assets/images/login_bpj.jpg?v=<?= time() ?>" alt="Balai Pengelolaan Jalan Wilayah Tegal" class="w-100 h-100 position-absolute" style="object-fit: cover; object-position: center; top:0; left:0; width:100%; height:100%;">
            <div class="position-absolute w-100 h-100" style="top:0; left:0; width:100%; height:100%; background: linear-gradient(to right, #23272b 0%, rgba(35, 39, 43, 0.15) 25%, transparent 100%);"></div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

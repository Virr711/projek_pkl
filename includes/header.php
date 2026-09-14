<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

// Verifikasi autentikasi sesi
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$pdo = get_db();
$current_role = current_user_role();
$user_id = (int)$_SESSION['user_id'];

// Handler Edit Profil (Nama Lengkap, Upload Foto, & Hapus Foto)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_update_profile'])) {
    $nama_baru = trim($_POST['nama_lengkap'] ?? '');
    $is_delete_photo = isset($_POST['action_delete_photo']) && $_POST['action_delete_photo'] == '1';
    
    // Fetch current foto_profil from DB first
    $stmt_cur = $pdo->prepare("SELECT `foto_profil`, `nama_lengkap` FROM `users` WHERE `id` = ?");
    $stmt_cur->execute([$user_id]);
    $curr_data = $stmt_cur->fetch();
    $foto_path = $curr_data['foto_profil'] ?? null;
    
    if (empty($nama_baru)) {
        $nama_baru = $curr_data['nama_lengkap'] ?? $_SESSION['nama_lengkap'] ?? 'Admin';
    }

    $msg = 'Profil berhasil diperbarui!';

    // Action 1: Delete Photo requested
    if ($is_delete_photo) {
        if (!empty($foto_path) && file_exists(__DIR__ . '/../' . $foto_path)) {
            @unlink(__DIR__ . '/../' . $foto_path);
        }
        $foto_path = null;
        $msg = 'Foto profil berhasil dihapus!';
    }
    // Action 2: New Photo Uploaded
    elseif (isset($_FILES['foto_profil']) && $_FILES['foto_profil']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['foto_profil']['tmp_name'];
        $file_name = $_FILES['foto_profil']['name'];
        $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        if (in_array($ext, $allowed)) {
            $upload_dir = __DIR__ . '/../assets/images/profiles/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            // Remove old physical photo if present
            if (!empty($foto_path) && file_exists(__DIR__ . '/../' . $foto_path)) {
                @unlink(__DIR__ . '/../' . $foto_path);
            }

            $new_filename = 'profile_' . $user_id . '_' . time() . '.' . $ext;
            $target_file = $upload_dir . $new_filename;
            
            if (move_uploaded_file($file_tmp, $target_file)) {
                $foto_path = 'assets/images/profiles/' . $new_filename;
            }
        }
    }
    
    // Update database permanently
    $stmt_up = $pdo->prepare("UPDATE `users` SET `nama_lengkap` = ?, `foto_profil` = ? WHERE `id` = ?");
    $stmt_up->execute([$nama_baru, $foto_path, $user_id]);
    
    // Update active session
    $_SESSION['nama_lengkap'] = $nama_baru;
    $_SESSION['foto_profil'] = $foto_path;
    
    echo "<script>
        alert('" . addslashes($msg) . "');
        window.location.href = '" . htmlspecialchars($_SERVER['REQUEST_URI']) . "';
    </script>";
    exit;
}

// Fetch latest user data from DB
$stmt_curr = $pdo->prepare("SELECT * FROM `users` WHERE `id` = ?");
$stmt_curr->execute([$user_id]);
$curr_user = $stmt_curr->fetch();

if ($curr_user) {
    $user_name = $curr_user['nama_lengkap'];
    $_SESSION['nama_lengkap'] = $curr_user['nama_lengkap'];
    $user_avatar = $curr_user['foto_profil'];
    $_SESSION['foto_profil'] = $curr_user['foto_profil'];
} else {
    $user_name = $_SESSION['nama_lengkap'] ?? 'User ALISA';
    $user_avatar = $_SESSION['foto_profil'] ?? null;
}

// Pengecekan pengingat otomatis
check_auto_whatsapp_daily($pdo);

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ALISA - Aplikasi Logistik Inspeksi & Servis Armada (BPJ Tegal)</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Select2 Searchable Dropdown CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <!-- Custom Dedicated Kurohiko Dark CSS (with cache-buster) -->
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- jQuery (Required for Select2 Searchable Select) -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-dark text-white">

<!-- Early Theme & Sidebar Auto-Restore Script -->
<script>
    (function() {
        const savedTheme = localStorage.getItem('alisa_theme');
        if (savedTheme === 'light') {
            document.body.classList.add('light-mode');
        }
        const savedSidebar = localStorage.getItem('alisa_sidebar_collapsed');
        if (savedSidebar === 'true') {
            document.body.classList.add('sidebar-collapsed');
        }
    })();
</script>

<!-- GLOBAL FULL-WIDTH TOP HEADER (AptaSchool Style Layout) -->
<header class="global-header d-flex align-items-center justify-content-between px-3 px-md-4">
    <!-- Left Section: Hamburger Toggle (Left of Logo), Brand Logo & Title -->
    <div class="d-flex align-items-center gap-3">
        <!-- Hamburger Toggle Button (Positioned to the LEFT of Logo) -->
        <button onclick="toggleSidebarCollapse()" class="btn btn-dark rounded-circle border border-secondary shadow-sm p-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px; background-color: rgba(255,255,255,0.06);" title="Buka / Sembunyikan Sidebar">
            <i class="fa-solid fa-bars text-white fs-6"></i>
        </button>

        <!-- Brand Logo & Title -->
        <a href="index.php" class="d-flex align-items-center text-decoration-none ms-1">
            <img src="assets/images/logo_jateng.png?v=<?= time() ?>" alt="Logo Jawa Tengah" class="me-3" style="height: 36px; width: auto; object-fit: contain;">
            <div class="brand-title-wrap d-flex align-items-center">
                <span class="fw-extrabold text-white fs-5" style="letter-spacing: 0.8px;">ALISA</span>
                <span class="d-none d-lg-inline text-white-50 small ms-3 border-start border-secondary ps-3" style="font-weight: 500;">Aplikasi Logistik Inspeksi & Servis Armada</span>
            </div>
        </a>
    </div>

    <!-- Right Section: Theme Toggle, User Avatar & Dropdown -->
    <div class="d-flex align-items-center gap-3">
        <!-- Theme Toggle Button (Mode Gelap / Terang) -->
        <button id="themeToggleBtn" onclick="toggleAlisaTheme()" class="btn btn-dark rounded-circle border border-secondary shadow-sm p-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px; background-color: rgba(255,255,255,0.06);" title="Ganti Mode Gelap / Terang">
            <i id="themeToggleIcon" class="fa-solid fa-sun text-warning fs-6"></i>
        </button>

        <!-- User Profile Dropdown -->
        <div class="dropdown">
            <button class="btn btn-dark rounded-pill border border-secondary shadow-sm px-3 py-1.5 d-flex align-items-center gap-2" style="background-color: rgba(255,255,255,0.06);" data-bs-toggle="dropdown" aria-expanded="false">
                <div class="avatar-circle-sm rounded-circle d-flex align-items-center justify-content-center overflow-hidden me-1" style="width: 26px; height: 26px; background: rgba(56, 189, 248, 0.2);">
                    <?php if (!empty($user_avatar) && file_exists(__DIR__ . '/../' . $user_avatar)): ?>
                        <img src="<?= htmlspecialchars($user_avatar) ?>?v=<?= time() ?>" alt="Avatar" style="width: 100%; height: 100%; object-fit: cover;">
                    <?php else: ?>
                        <i class="fa-solid fa-user-circle fs-5 text-info"></i>
                    <?php endif; ?>
                </div>
                <span class="fw-bold text-white small d-none d-sm-inline"><?= htmlspecialchars($user_name) ?></span>
                <i class="fa-solid fa-chevron-down text-white-50 small ms-1" style="font-size: 0.75rem;"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-lg border-secondary rounded-3 mt-2" style="min-width: 240px; z-index: 1050;">
                <li class="px-3 py-2.5 border-bottom border-secondary">
                    <div class="fw-bold text-white small mb-0.5"><?= htmlspecialchars($user_name) ?></div>
                    <div class="text-white-50 small" style="font-size: 0.78rem;"><?= htmlspecialchars($_SESSION['jabatan'] ?? 'Role: ' . $current_role) ?></div>
                </li>
                <li class="p-1">
                    <button type="button" class="dropdown-item text-info fw-bold rounded-2 py-2 px-3 d-flex align-items-center" onclick="openModalEditProfil()">
                        <i class="fa-solid fa-user-pen me-2 text-info"></i> Ubah Nama & Foto Profil
                    </button>
                </li>
                <li class="p-1 border-top border-secondary">
                    <a class="dropdown-item text-danger fw-bold rounded-2 py-2 px-3 d-flex align-items-center" href="logout.php">
                        <i class="fa-solid fa-right-from-bracket me-2 text-danger"></i> Keluar (Logout)
                    </a>
                </li>
            </ul>
        </div>
    </div>
</header>

<!-- Sidebar Navigation (Positioned Below Global Header) -->
<div class="sidebar">
    <!-- User Profile Card inside Top of Sidebar -->
    <div class="sidebar-user-card p-3 border-bottom border-secondary">
        <div class="d-flex align-items-center">
            <div class="avatar-box bg-info bg-opacity-25 text-info rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 cursor-pointer me-3" onclick="openModalEditProfil()" style="width: 42px; height: 42px; border: 1px solid rgba(56, 189, 248, 0.4); overflow: hidden;" title="Klik untuk ubah foto profil">
                <?php if (!empty($user_avatar) && file_exists(__DIR__ . '/../' . $user_avatar)): ?>
                    <img src="<?= htmlspecialchars($user_avatar) ?>?v=<?= time() ?>" alt="Avatar" style="width: 100%; height: 100%; object-fit: cover;">
                <?php else: ?>
                    <i class="fa-solid fa-user fs-5"></i>
                <?php endif; ?>
            </div>
            <div class="user-card-info overflow-hidden flex-grow-1">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <span class="fw-bold text-white text-truncate small me-1" title="<?= htmlspecialchars($user_name) ?>"><?= htmlspecialchars($user_name) ?></span>
                    <button type="button" onclick="openModalEditProfil()" class="btn btn-xs p-0 text-info border-0 bg-transparent flex-shrink-0" title="Ubah Nama & Foto Profil">
                        <i class="fa-solid fa-pen-to-square fs-7" style="font-size: 0.85rem;"></i>
                    </button>
                </div>
                <div class="d-flex align-items-center gap-1">
                    <?= get_role_badge($current_role) ?>
                </div>
            </div>
        </div>
    </div>

    <div class="sidebar-menu">
        <div class="menu-header">Menu Utama</div>
        
        <?php 
        $is_unit_active = ($current_page === 'data_unit.php' || $current_page === 'data_unit_kendaraan.php' || $current_page === 'data_unit_peralatan.php' || $current_page === 'data_unit_ruas.php' || $current_page === 'data_ruas.php' || $current_page === 'data_unit_form.php' || $current_page === 'pembayaran_pajak_kir.php');
        $is_servis_active = ($current_page === 'servis_kelola.php' || $current_page === 'servis_penjadwalan.php' || $current_page === 'servis_darurat.php' || $current_page === 'servis_form.php');
        $is_laporan_active = ($current_page === 'laporan.php' || $current_page === 'laporan_ruas.php' || $current_page === 'laporan_unit.php' || $current_page === 'laporan_servis.php' || $current_page === 'laporan_nota_pajak.php');
        
        $pending_nota_count = 0;
        try {
            $stmt_cnt_n = $pdo->query("SELECT COUNT(*) FROM nota_teknisi WHERE status = 'Menunggu Verifikasi'");
            if ($stmt_cnt_n) $pending_nota_count = (int)$stmt_cnt_n->fetchColumn();
        } catch (Exception $e) {}
        ?>

        <!-- Menu Beranda -->
        <a href="index.php" class="nav-link-custom <?= ($current_page === 'index.php') ? 'active' : '' ?>" title="Beranda">
            <i class="fa-solid fa-house"></i> <span class="link-text">Beranda</span>
        </a>

        <!-- Menu Data Unit & Submenus -->
        <div class="sidebar-item-group mb-1">
            <div class="d-flex align-items-center justify-content-between nav-link-custom <?= $is_unit_active ? 'active' : '' ?>" title="Data Unit">
                <a href="data_unit.php" class="text-decoration-none text-reset flex-grow-1 d-flex align-items-center">
                    <i class="fa-solid fa-truck-monster me-2"></i> <span class="link-text">Data Unit</span>
                </a>
                <i class="fa-solid fa-chevron-down fs-7 p-1 cursor-pointer sidebar-chevron" id="sub-data-unit-chevron" onclick="toggleSidebarSubmenu('sub-data-unit')" style="transform: <?= $is_unit_active ? 'rotate(180deg)' : 'rotate(0deg)' ?>;" title="Buka / Tutup Submenu"></i>
            </div>
            <div id="sub-data-unit" class="ps-3 ms-2 border-start border-secondary py-1 sidebar-sub-group <?= $is_unit_active ? '' : 'd-none' ?>">
                <a href="data_unit_kendaraan.php" class="nav-link-custom py-1.5 fs-7 <?= ($current_page === 'data_unit_kendaraan.php') ? 'active-sub-item' : '' ?>" title="Kendaraan">
                    <i class="fa-solid fa-truck-pickup me-2 fs-6"></i> <span class="link-text">Kendaraan</span>
                </a>
                <a href="data_unit_peralatan.php" class="nav-link-custom py-1.5 fs-7 <?= ($current_page === 'data_unit_peralatan.php') ? 'active-sub-item' : '' ?>" title="Peralatan">
                    <i class="fa-solid fa-toolbox me-2 fs-6"></i> <span class="link-text">Peralatan</span>
                </a>
                <a href="data_unit_ruas.php" class="nav-link-custom py-1.5 fs-7 <?= ($current_page === 'data_unit_ruas.php' || $current_page === 'data_ruas.php') ? 'active-sub-item' : '' ?>" title="Data Ruas Jalan">
                    <i class="fa-solid fa-map-location-dot me-2 fs-6"></i> <span class="link-text">Data Ruas</span>
                </a>
                <?php if ($current_role === 'admin' || $current_role === 'bendahara' || $current_role === 'teknisi'): ?>
                    <a href="pembayaran_pajak_kir.php" class="nav-link-custom py-1.5 fs-7 <?= ($current_page === 'pembayaran_pajak_kir.php') ? 'active-sub-item' : '' ?>" title="Pajak">
                        <i class="fa-solid fa-file-invoice-dollar me-2 fs-6"></i> <span class="link-text">Pajak</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Menu Melakukan Servis & Submenus -->
        <?php if ($current_role === 'admin' || $current_role === 'teknisi' || $current_role === 'pimpinan'): ?>
            <div class="menu-header">Layanan & Servis</div>
            <div class="sidebar-item-group mb-1">
                <div class="d-flex align-items-center justify-content-between nav-link-custom <?= $is_servis_active ? 'active' : '' ?>" title="Melakukan Servis">
                    <a href="servis_kelola.php" class="text-decoration-none text-reset flex-grow-1 d-flex align-items-center justify-content-between pe-1">
                        <span><i class="fa-solid fa-wrench me-2"></i> <span class="link-text">Servis</span></span>
                        <?php if ($pending_nota_count > 0): ?>
                            <span class="badge bg-danger rounded-pill px-2 py-0.5 small" title="<?= $pending_nota_count ?> Nota Teknisi Menunggu Verifikasi"><?= $pending_nota_count ?></span>
                        <?php endif; ?>
                    </a>
                    <i class="fa-solid fa-chevron-down fs-7 p-1 cursor-pointer sidebar-chevron" id="sub-servis-chevron" onclick="toggleSidebarSubmenu('sub-servis')" style="transform: <?= $is_servis_active ? 'rotate(180deg)' : 'rotate(0deg)' ?>;" title="Buka / Tutup Submenu"></i>
                </div>
                <div id="sub-servis" class="ps-3 ms-2 border-start border-secondary py-1 sidebar-sub-group <?= $is_servis_active ? '' : 'd-none' ?>">
                    <a href="servis_penjadwalan.php" class="nav-link-custom py-1.5 fs-7 <?= ($current_page === 'servis_penjadwalan.php') ? 'active-sub-item' : '' ?>" title="Penjadwalan">
                        <i class="fa-solid fa-calendar-check me-2 fs-6"></i> <span class="link-text">Penjadwalan</span>
                    </a>
                    <a href="servis_darurat.php" class="nav-link-custom py-1.5 fs-7 <?= ($current_page === 'servis_darurat.php') ? 'active-sub-item' : '' ?>" title="Darurat">
                        <i class="fa-solid fa-triangle-exclamation me-2 fs-6"></i> <span class="link-text">Darurat</span>
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Menu Notifikasi -->
        <?php if ($current_role === 'admin' || $current_role === 'bendahara'): ?>
            <div class="menu-header">Notifikasi & Alert</div>
            <a href="notifikasi.php" class="nav-link-custom <?= ($current_page === 'notifikasi.php' || $current_page === 'notifikasi_wa.php' || $current_page === 'notifikasi_alert.php') ? 'active' : '' ?>" title="Notifikasi">
                <i class="fa-solid fa-bell"></i> <span class="link-text">Notifikasi</span>
            </a>
        <?php endif; ?>

        <!-- Menu Laporan & Submenus -->
        <?php if ($current_role === 'admin' || $current_role === 'pimpinan' || $current_role === 'bendahara'): ?>
            <div class="menu-header">Laporan</div>
            <div class="sidebar-item-group mb-1">
                <div class="d-flex align-items-center justify-content-between nav-link-custom <?= $is_laporan_active ? 'active' : '' ?>" title="Laporan">
                    <a href="laporan.php" class="text-decoration-none text-reset flex-grow-1 d-flex align-items-center">
                        <i class="fa-solid fa-file-contract me-2"></i> <span class="link-text">Laporan</span>
                    </a>
                    <i class="fa-solid fa-chevron-down fs-7 p-1 cursor-pointer sidebar-chevron" id="sub-laporan-chevron" onclick="toggleSidebarSubmenu('sub-laporan')" style="transform: <?= $is_laporan_active ? 'rotate(180deg)' : 'rotate(0deg)' ?>;" title="Buka / Tutup Submenu"></i>
                </div>
                <div id="sub-laporan" class="ps-3 ms-2 border-start border-secondary py-1 sidebar-sub-group <?= $is_laporan_active ? '' : 'd-none' ?>">
                    <a href="laporan_ruas.php" class="nav-link-custom py-1.5 fs-7 <?= ($current_page === 'laporan_ruas.php') ? 'active-sub-item' : '' ?>" title="Data Ruas">
                        <i class="fa-solid fa-map-location-dot me-2 fs-6"></i> <span class="link-text">Data Ruas</span>
                    </a>
                    <a href="laporan_unit.php" class="nav-link-custom py-1.5 fs-7 <?= ($current_page === 'laporan_unit.php') ? 'active-sub-item' : '' ?>" title="Data Unit">
                        <i class="fa-solid fa-truck-monster me-2 fs-6"></i> <span class="link-text">Data Unit</span>
                    </a>
                    <a href="laporan_servis.php" class="nav-link-custom py-1.5 fs-7 <?= ($current_page === 'laporan_servis.php') ? 'active-sub-item' : '' ?>" title="Servis">
                        <i class="fa-solid fa-wrench me-2 fs-6"></i> <span class="link-text">Servis</span>
                    </a>
                    <a href="laporan_nota_pajak.php" class="nav-link-custom py-1.5 fs-7 <?= ($current_page === 'laporan_nota_pajak.php') ? 'active-sub-item' : '' ?>" title="Nota & Pajak">
                        <i class="fa-solid fa-receipt me-2 fs-6"></i> <span class="link-text">Nota & Pajak</span>
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Main Workspace Content (Positioned Below Global Header) -->
<div class="main-content">

<!-- MODAL EDIT PROFIL SAYA -->
<div class="modal fade" id="modalEditProfilSaya" tabindex="-1" aria-labelledby="modalEditProfilSayaLabel" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-white border-secondary rounded-4 shadow-lg">
            <div class="modal-header border-secondary">
                <h5 class="modal-title fw-bold text-white" id="modalEditProfilSayaLabel">
                    <i class="fa-solid fa-user-pen text-info me-2"></i> Ubah Nama & Foto Profil Saya
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="action_update_profile" value="1">
                <div class="modal-body p-4">
                    <div class="text-center mb-4">
                        <div class="avatar-preview-container mx-auto mb-2 rounded-circle bg-info bg-opacity-25 text-info d-flex align-items-center justify-content-center overflow-hidden" style="width: 90px; height: 90px; border: 2px solid #38bdf8;">
                            <?php if (!empty($user_avatar) && file_exists(__DIR__ . '/../' . $user_avatar)): ?>
                                <img id="previewAvatarImg" src="<?= htmlspecialchars($user_avatar) ?>?v=<?= time() ?>" style="width: 100%; height: 100%; object-fit: cover;">
                            <?php else: ?>
                                <i id="previewAvatarIcon" class="fa-solid fa-user fs-1"></i>
                            <?php endif; ?>
                        </div>
                        <div class="small text-white-50 mt-1 mb-2">Role Akun: <?= get_role_badge($current_role) ?></div>
                        
                        <?php if (!empty($user_avatar) && file_exists(__DIR__ . '/../' . $user_avatar)): ?>
                            <button type="submit" name="action_delete_photo" value="1" onclick="return confirm('Apakah Anda yakin ingin menghapus foto profil ini?')" class="btn btn-outline-danger btn-sm rounded-pill px-3 py-1 mt-1">
                                <i class="fa-solid fa-trash-can me-1"></i> Hapus Foto Profil
                            </button>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-white small"><i class="fa-solid fa-signature me-1 text-info"></i> Nama Lengkap / Display Name <span class="text-danger">*</span></label>
                        <input type="text" name="nama_lengkap" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($user_name) ?>" required placeholder="Masukkan Nama Lengkap Anda">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-white small"><i class="fa-solid fa-camera me-1 text-info"></i> Upload Foto Profil Baru (Opsional)</label>
                        <input type="file" name="foto_profil" class="form-control bg-dark text-white border-secondary" accept="image/*" onchange="previewImageFile(this)">
                        <div class="form-text text-white-50 small">Format gambar: JPG, PNG, WEBP, GIF (Maks. 2MB).</div>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 shadow" style="background-color: #0284c7; border: none;">
                        <i class="fa-solid fa-check me-1"></i> Simpan Perubahan Profil
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openModalEditProfil() {
    var modalEl = document.getElementById('modalEditProfilSaya');
    if (modalEl) {
        var modal = new bootstrap.Modal(modalEl);
        modal.show();
    }
}

function previewImageFile(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            var img = document.getElementById('previewAvatarImg');
            var container = document.querySelector('.avatar-preview-container');
            if (!img && container) {
                container.innerHTML = '<img id="previewAvatarImg" style="width: 100%; height: 100%; object-fit: cover;">';
                img = document.getElementById('previewAvatarImg');
            }
            if (img) {
                img.src = e.target.result;
            }
        }
        reader.readAsDataURL(input.files[0]);
    }
}

function syncThemeIcon() {
    const isLight = document.body.classList.contains('light-mode');
    const icon = document.getElementById('themeToggleIcon');
    if (icon) {
        if (isLight) {
            icon.className = 'fa-solid fa-moon text-primary fs-6';
        } else {
            icon.className = 'fa-solid fa-sun text-warning fs-6';
        }
    }
}

function toggleAlisaTheme() {
    document.body.classList.toggle('light-mode');
    const isLight = document.body.classList.contains('light-mode');
    localStorage.setItem('alisa_theme', isLight ? 'light' : 'dark');
    syncThemeIcon();
}

function toggleSidebarCollapse() {
    document.body.classList.toggle('sidebar-collapsed');
    const isCollapsed = document.body.classList.contains('sidebar-collapsed');
    localStorage.setItem('alisa_sidebar_collapsed', isCollapsed ? 'true' : 'false');
}

function toggleSidebarSubmenu(groupId) {
    if (document.body.classList.contains('sidebar-collapsed')) {
        toggleSidebarCollapse();
    }
    const subGroup = document.getElementById(groupId);
    const chevron = document.getElementById(groupId + '-chevron');
    if (subGroup) {
        if (subGroup.classList.contains('d-none')) {
            subGroup.classList.remove('d-none');
            if (chevron) chevron.style.transform = 'rotate(180deg)';
        } else {
            subGroup.classList.add('d-none');
            if (chevron) chevron.style.transform = 'rotate(0deg)';
        }
    }
}

document.addEventListener("DOMContentLoaded", function() {
    syncThemeIcon();
});
</script>

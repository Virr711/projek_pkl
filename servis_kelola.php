<?php
require_once __DIR__ . '/includes/header.php';

// Handle Delete Servis Record Entry (Admin ONLY)
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    if (!can_edit_data()) {
        echo "<script>alert('Akses Ditolak: Hanya Admin yang dapat menghapus catatan servis.'); window.location='servis_kelola.php';</script>";
        exit;
    }

    $del_id = (int)$_GET['id'];
    
    // Fetch info to delete photo if exists
    $stmt_find = $pdo->prepare("SELECT * FROM riwayat_servis WHERE id = ?");
    $stmt_find->execute([$del_id]);
    $servis_entry = $stmt_find->fetch();

    if ($servis_entry) {
        if (!empty($servis_entry['foto_nota'])) {
            $file_path = __DIR__ . '/uploads/nota/' . $servis_entry['foto_nota'];
            if (file_exists($file_path)) {
                @unlink($file_path);
            }
        }
        
        $stmt_del = $pdo->prepare("DELETE FROM riwayat_servis WHERE id = ?");
        $stmt_del->execute([$del_id]);

        echo "<script>alert('Catatan riwayat servis berhasil dihapus!'); window.location='servis_kelola.php';</script>";
        exit;
    }
}

// Handle Delete Uploaded Nota (Admin & Teknisi)
if (isset($_GET['action']) && $_GET['action'] === 'delete_nota' && isset($_GET['id'])) {
    if (!can_edit_data() && !can_upload_nota()) {
        echo "<script>alert('Akses Ditolak: Anda tidak memiliki akses untuk menghapus nota.'); window.location='servis_kelola.php';</script>";
        exit;
    }

    $del_nota_id = (int)$_GET['id'];
    $stmt_find_n = $pdo->prepare("SELECT * FROM nota_teknisi WHERE id = ?");
    $stmt_find_n->execute([$del_nota_id]);
    $nota_item = $stmt_find_n->fetch();

    if ($nota_item) {
        if (!empty($nota_item['foto_nota'])) {
            $file_path = __DIR__ . '/uploads/nota/' . $nota_item['foto_nota'];
            if (file_exists($file_path)) {
                @unlink($file_path);
            }
        }
        $stmt_del_n = $pdo->prepare("DELETE FROM nota_teknisi WHERE id = ?");
        $stmt_del_n->execute([$del_nota_id]);

        echo "<script>alert('Catatan nota upload berhasil dihapus!'); window.location='servis_kelola.php';</script>";
        exit;
    }
}

// Handle POST actions for Teknisi Upload Nota & Admin Approval
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $post_action = $_POST['action'];

    // 1. Upload Nota Ke Admin by Teknisi / Admin
    if ($post_action === 'upload_nota_teknisi') {
        if (!can_upload_nota()) {
            echo "<script>alert('Akses Ditolak: Hanya Teknisi dan Admin yang dapat mengunggah nota.'); window.location='servis_kelola.php';</script>";
            exit;
        }

        $id_kendaraan = (int)($_POST['id_kendaraan'] ?? 0);
        $tgl_nota     = $_POST['tgl_nota'] ?? date('Y-m-d');
        $jenis_servis = $_POST['jenis_servis'] ?? 'Penjadwalan';
        $nama_layanan = trim($_POST['nama_layanan'] ?? 'Servis Berkala & Maintenance');
        $total_biaya  = (float)($_POST['total_biaya'] ?? 0);
        $nama_bengkel = trim($_POST['nama_bengkel'] ?? '');
        $catatan      = trim($_POST['catatan'] ?? '');
        $dikirim_oleh = $_SESSION['nama_lengkap'] ?? 'Teknisi';

        $foto_nota = '';
        if (!empty($_FILES['foto_nota']['name'])) {
            $upload_dir = __DIR__ . '/uploads/nota/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $ext = pathinfo($_FILES['foto_nota']['name'], PATHINFO_EXTENSION);
            $foto_nota = 'nota_teknisi_' . time() . '_' . rand(1000, 9999) . '.' . strtolower($ext);
            move_uploaded_file($_FILES['foto_nota']['tmp_name'], $upload_dir . $foto_nota);
        }

        if (!empty($foto_nota) && $id_kendaraan > 0) {
            $stmt_ins = $pdo->prepare("INSERT INTO nota_teknisi (id_kendaraan, tgl_nota, jenis_servis, nama_layanan, total_biaya, nama_bengkel, foto_nota, catatan, dikirim_oleh, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Menunggu Verifikasi')");
            $stmt_ins->execute([$id_kendaraan, $tgl_nota, $jenis_servis, $nama_layanan, $total_biaya, $nama_bengkel, $foto_nota, $catatan, $dikirim_oleh]);

            echo "<script>alert('Foto nota berhasil diupload dan dikirim ke Admin!'); window.location='servis_kelola.php';</script>";
            exit;
        } else {
            echo "<script>alert('Gagal: Wajib memilih unit armada dan mengunggah foto nota.'); window.location='servis_kelola.php';</script>";
            exit;
        }
    }

    // 2. Admin Accepts Nota & Converts to Servis Record
    if ($post_action === 'terima_nota') {
        if (!can_edit_data()) {
            echo "<script>alert('Akses Ditolak: Hanya Admin yang dapat memverifikasi nota.'); window.location='servis_kelola.php';</script>";
            exit;
        }

        $nota_id = (int)($_POST['nota_id'] ?? 0);
        $stmt_n = $pdo->prepare("SELECT * FROM nota_teknisi WHERE id = ?");
        $stmt_n->execute([$nota_id]);
        $nota_rec = $stmt_n->fetch();

        if ($nota_rec) {
            // Insert into riwayat_servis
            $stmt_srv = $pdo->prepare("INSERT INTO riwayat_servis (id_kendaraan, tgl_servis, jenis_servis, nama_layanan, total_biaya, nama_bengkel, foto_nota, catatan) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt_srv->execute([
                $nota_rec['id_kendaraan'],
                $nota_rec['tgl_nota'],
                $nota_rec['jenis_servis'],
                $nota_rec['nama_layanan'],
                $nota_rec['total_biaya'],
                $nota_rec['nama_bengkel'],
                $nota_rec['foto_nota'],
                "Nota dikirim oleh " . $nota_rec['dikirim_oleh'] . ". " . $nota_rec['catatan']
            ]);

            // Calculate next service date (default +6 months for vehicle, or +1000h)
            $stmt_u = $pdo->prepare("SELECT * FROM kendaraan_alat WHERE id = ?");
            $stmt_u->execute([$nota_rec['id_kendaraan']]);
            $u_info = $stmt_u->fetch();

            if ($u_info) {
                $interval = (int)($u_info['interval_servis_bulan'] ?: 6);
                $next_date = date('Y-m-d', strtotime("+{$interval} months", strtotime($nota_rec['tgl_nota'])));
                $stmt_upd_u = $pdo->prepare("UPDATE kendaraan_alat SET tgl_servis_terakhir = ?, tgl_servis_berikutnya = ?, status = 'Baik (B)', kondisi = 'B' WHERE id = ?");
                $stmt_upd_u->execute([$nota_rec['tgl_nota'], $next_date, $nota_rec['id_kendaraan']]);
            }

            // Mark nota as Approved
            $stmt_upd_n = $pdo->prepare("UPDATE nota_teknisi SET status = 'Disetujui' WHERE id = ?");
            $stmt_upd_n->execute([$nota_id]);

            echo "<script>alert('Nota dari Teknisi disetujui & otomatis dimasukkan ke Riwayat Servis Armada!'); window.location='servis_kelola.php';</script>";
            exit;
        }
    }

    // 3. Admin Rejects Nota
    if ($post_action === 'tolak_nota') {
        if (!can_edit_data()) {
            echo "<script>alert('Akses Ditolak: Hanya Admin yang dapat memverifikasi nota.'); window.location='servis_kelola.php';</script>";
            exit;
        }

        $nota_id = (int)($_POST['nota_id'] ?? 0);
        $stmt_upd_n = $pdo->prepare("UPDATE nota_teknisi SET status = 'Ditolak' WHERE id = ?");
        $stmt_upd_n->execute([$nota_id]);

        echo "<script>alert('Nota dari Teknisi telah ditolak.'); window.location='servis_kelola.php';</script>";
        exit;
    }
}

$id_kendaraan_filter = (int)($_GET['id_kendaraan'] ?? 0);

// Fetch All Units for Filter Dropdown & Form Modals
$stmt_all_units = $pdo->query("SELECT * FROM kendaraan_alat ORDER BY nama ASC");
$all_units_list = $stmt_all_units->fetchAll();

$jenis_filter = trim($_GET['jenis'] ?? '');

// Count totals for Penjadwalan & Darurat
$cnt_penjadwalan = $pdo->query("SELECT COUNT(*) FROM riwayat_servis WHERE jenis_servis = 'Penjadwalan'")->fetchColumn();
$cnt_darurat = $pdo->query("SELECT COUNT(*) FROM riwayat_servis WHERE jenis_servis = 'Darurat'")->fetchColumn();

// Fetch Servis Records with Unit & Jenis Filters
$sql_log = "SELECT r.*, k.nama, k.kode_plat, k.lokasi_ruas, k.merk, k.kategori FROM riwayat_servis r JOIN kendaraan_alat k ON r.id_kendaraan = k.id WHERE 1=1";
$params_log = [];

if ($id_kendaraan_filter > 0) {
    $sql_log .= " AND r.id_kendaraan = ?";
    $params_log[] = $id_kendaraan_filter;
}
if (!empty($jenis_filter)) {
    $sql_log .= " AND r.jenis_servis = ?";
    $params_log[] = $jenis_filter;
}
$sql_log .= " ORDER BY r.tgl_servis DESC";

$stmt = $pdo->prepare($sql_log);
$stmt->execute($params_log);
$servis_logs = $stmt->fetchAll();

// Calculate Grand Total Expenditure
$grand_total_servis = 0;
foreach ($servis_logs as $log) {
    $grand_total_servis += (float)$log['total_biaya'];
}

// Fetch Schedule Overview
$stmt_units = $pdo->query("SELECT * FROM kendaraan_alat ORDER BY tgl_servis_berikutnya ASC");
$all_units = $stmt_units->fetchAll();

// Fetch Uploaded Receipts from Teknisi
$stmt_nota = $pdo->query("SELECT n.*, COALESCE(k.nama, 'Unit Armada') as nama_unit, COALESCE(k.kode_plat, '-') as kode_plat FROM nota_teknisi n LEFT JOIN kendaraan_alat k ON n.id_kendaraan = k.id ORDER BY n.id DESC");
$nota_list = $stmt_nota->fetchAll();
$cnt_nota_pending = count(array_filter($nota_list, function($x) { return $x['status'] === 'Menunggu Verifikasi'; }));
?>

<div class="container-fluid p-0">
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
        <div>
            <h4 class="fw-bold mb-1 text-white"><i class="fa-solid fa-calendar-check text-info me-2"></i> Kelola & Riwayat Servis Armada</h4>
            <p class="text-white-50 small mb-0">Pemantauan Jadwal Servis Rutin Berkala (Penjadwalan) & Perbaikan Mendadak (Darurat).</p>
        </div>
        <div class="d-flex gap-2">
            <?php if ($current_role === 'teknisi'): ?>
                <button type="button" class="btn btn-warning rounded-pill px-3 shadow fw-bold text-dark" style="background-color: #eab308 !important;" data-bs-toggle="modal" data-bs-target="#modalUploadNotaTeknisi">
                    <i class="fa-solid fa-upload me-2"></i> Upload Nota Ke Admin
                </button>
            <?php endif; ?>
            <?php if (can_edit_data()): ?>
                <a href="servis_form.php" class="btn btn-bpj-primary shadow">
                    <i class="fa-solid fa-plus me-2"></i> Input Servis Baru
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Teknisi Uploaded Receipts Card for Admin Review & Teknisi History -->
    <?php if (can_edit_data() || can_upload_nota() || !empty($nota_list)): ?>
        <div class="card-custom p-4 mb-4 border-info">
            <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                <h6 class="fw-bold text-white m-0">
                    <i class="fa-solid fa-file-invoice text-warning me-2" style="color: #eab308 !important;"></i> Daftar Upload Nota Dari Teknisi 
                    <?php if ($cnt_nota_pending > 0): ?>
                        <span class="badge bg-danger rounded-pill px-2.5 py-1 ms-2"><?= $cnt_nota_pending ?> Menunggu Verifikasi Admin</span>
                    <?php endif; ?>
                </h6>
                <span class="text-white-50 small"><i class="fa-solid fa-info-circle me-1"></i> Teknisi mengunggah foto nota, Admin memverifikasi, mengunduh, atau menghapus foto</span>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 small">
                    <thead>
                        <tr class="text-white">
                            <th>Tanggal Nota</th>
                            <th>Pengirim</th>
                            <th>Unit Armada</th>
                            <th>Bengkel & Layanan</th>
                            <th>Nominal Biaya</th>
                            <th>Foto Nota</th>
                            <th>Status Verifikasi</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($nota_list)): ?>
                            <tr><td colspan="8" class="text-center text-muted py-3">Belum ada nota diunggah oleh Teknisi.</td></tr>
                        <?php else: ?>
                            <?php foreach ($nota_list as $nt): ?>
                                <tr>
                                    <td class="fw-semibold text-white"><?= format_tgl_indo($nt['tgl_nota']) ?></td>
                                    <td class="text-info fw-bold"><i class="fa-solid fa-user-gear me-1"></i> <?= htmlspecialchars($nt['dikirim_oleh']) ?></td>
                                    <td>
                                        <div class="fw-bold text-white font-monospace">[<?= htmlspecialchars($nt['kode_plat']) ?>]</div>
                                        <div class="text-white-50"><?= htmlspecialchars($nt['nama_unit']) ?></div>
                                    </td>
                                    <td>
                                        <strong class="text-white"><?= htmlspecialchars($nt['nama_bengkel'] ?: '-') ?></strong><br>
                                        <span class="text-white-50"><?= htmlspecialchars($nt['nama_layanan']) ?></span>
                                    </td>
                                    <td class="fw-bold text-warning" style="color: #eab308 !important;">
                                        <?= format_rupiah_privacy($nt['total_biaya']) ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($nt['foto_nota'])): ?>
                                            <a href="uploads/nota/<?= htmlspecialchars($nt['foto_nota']) ?>" target="_blank" download class="btn btn-xs btn-outline-info rounded-pill px-2.5 py-1">
                                                <i class="fa-solid fa-download me-1"></i> Unduh
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($nt['status'] === 'Disetujui'): ?>
                                            <span class="badge bg-success rounded-pill"><i class="fa-solid fa-check me-1"></i> Disetujui</span>
                                        <?php elseif ($nt['status'] === 'Ditolak'): ?>
                                            <span class="badge bg-danger rounded-pill"><i class="fa-solid fa-xmark me-1"></i> Ditolak</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark rounded-pill fw-bold" style="background-color: #eab308 !important; color: #0f172a !important;"><i class="fa-solid fa-clock me-1"></i> Menunggu Verifikasi</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end text-nowrap">
                                        <?php if ($nt['status'] === 'Menunggu Verifikasi'): ?>
                                            <?php if (can_edit_data()): ?>
                                                <form method="POST" action="" class="d-inline">
                                                    <input type="hidden" name="action" value="terima_nota">
                                                    <input type="hidden" name="nota_id" value="<?= $nt['id'] ?>">
                                                    <button type="submit" onclick="return confirm('Setujui dan masukkan nota ini ke Riwayat Servis?')" class="btn btn-sm btn-success rounded-pill px-2.5 py-1 me-1" title="Setujui Nota">
                                                        <i class="fa-solid fa-check me-1"></i> Setujui
                                                    </button>
                                                </form>
                                                <form method="POST" action="" class="d-inline">
                                                    <input type="hidden" name="action" value="tolak_nota">
                                                    <input type="hidden" name="nota_id" value="<?= $nt['id'] ?>">
                                                    <button type="submit" onclick="return confirm('Tolak nota ini?')" class="btn btn-sm btn-outline-warning text-warning rounded-pill px-2.5 py-1 me-1" title="Tolak Nota">
                                                        <i class="fa-solid fa-xmark me-1"></i> Tolak
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            <a href="servis_kelola.php?action=delete_nota&id=<?= $nt['id'] ?>" onclick="return confirm('Apakah Anda yakin ingin menghapus nota ini?')" class="btn btn-sm btn-outline-danger rounded-pill px-2.5 py-1" title="Hapus Upload Nota Ini">
                                                <i class="fa-solid fa-trash me-1"></i> Hapus
                                            </a>
                                        <?php elseif ($nt['status'] === 'Ditolak'): ?>
                                            <a href="servis_kelola.php?action=delete_nota&id=<?= $nt['id'] ?>" onclick="return confirm('Hapus pengajuan nota yang ditolak ini agar dapat diunggah ulang?')" class="btn btn-sm btn-danger rounded-pill px-2.5 py-1 shadow-sm" title="Hapus Nota Ditolak Ini">
                                                <i class="fa-solid fa-trash me-1"></i> Hapus Nota Ditolak
                                            </a>
                                        <?php else: ?>
                                            <a href="servis_kelola.php?action=delete_nota&id=<?= $nt['id'] ?>" onclick="return confirm('Apakah Anda yakin ingin menghapus arsip nota ini?')" class="btn btn-sm btn-outline-danger rounded-pill px-2.5 py-1" title="Hapus Nota Ini">
                                                <i class="fa-solid fa-trash me-1"></i> Hapus
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <!-- PPDB-Style Interactive Sub-Menu Card Grid for Melakukan Servis -->
    <div class="ppdb-submenu-grid mb-4">
        <!-- Submenu 1: Penjadwalan -->
        <a href="servis_penjadwalan.php" class="ppdb-card" style="background-image: url('assets/images/card_penjadwalan.jpg?v=<?= time() ?>');">
            <div class="ppdb-card-overlay"></div>
            <div class="ppdb-card-content">
                <span class="ppdb-card-badge" style="background-color: #0284c7;"><i class="fa-solid fa-calendar-check me-1"></i> Penjadwalan</span>
                <div class="ppdb-card-body-text">
                    <h5 class="ppdb-card-title">Servis Penjadwalan</h5>
                    <p class="ppdb-card-desc">Servis Rutin Berkala H-30/H-7 & Maintenance Terjadwal Armada BPJ.</p>
                </div>
                <div class="ppdb-card-footer">
                    <span class="ppdb-card-meta">Total: <?= $cnt_penjadwalan ?> Transaksi</span>
                    <span class="ppdb-card-action">Buka Halaman Penjadwalan <i class="fa-solid fa-arrow-right"></i></span>
                </div>
            </div>
        </a>

        <!-- Submenu 2: Darurat -->
        <a href="servis_darurat.php" class="ppdb-card" style="background-image: url('assets/images/card_darurat.jpg?v=<?= time() ?>');">
            <div class="ppdb-card-overlay"></div>
            <div class="ppdb-card-content">
                <span class="ppdb-card-badge" style="background-color: #dc2626;"><i class="fa-solid fa-triangle-exclamation me-1"></i> Darurat</span>
                <div class="ppdb-card-body-text">
                    <h5 class="ppdb-card-title">Perbaikan Darurat</h5>
                    <p class="ppdb-card-desc">Penanganan Mogok, Rusak Mendadak & Perbaikan Langsung Lapangan.</p>
                </div>
                <div class="ppdb-card-footer">
                    <span class="ppdb-card-meta" style="color: #f87171 !important;">Total: <?= $cnt_darurat ?> Perbaikan</span>
                    <span class="ppdb-card-action" style="color: #f87171 !important;">Buka Halaman Darurat <i class="fa-solid fa-arrow-right"></i></span>
                </div>
            </div>
        </a>
    </div>

    <!-- Upcoming Schedule Table -->
    <div class="card-custom p-4 mb-4">
        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
            <h6 class="fw-bold text-white m-0"><i class="fa-solid fa-clock text-warning me-2" style="color: #eab308 !important;"></i> Status Jadwal Servis Berkala Armada</h6>
            <?php if (!empty($jenis_filter)): ?>
                <span class="badge bg-primary rounded-pill px-3 py-1.5 fs-7">Filter Submenu: <?= htmlspecialchars($jenis_filter) ?> <a href="servis_kelola.php" class="text-white ms-1"><i class="fa-solid fa-xmark"></i></a></span>
            <?php endif; ?>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="text-nowrap" style="width: 12%;">Nopol / Kode</th>
                        <th class="text-nowrap" style="width: 22%;">Nama Unit Armada</th>
                        <th class="text-nowrap" style="width: 16%;">Lokasi Ruas Jalan</th>
                        <th class="text-nowrap" style="width: 13%;">Servis Terakhir</th>
                        <th class="text-nowrap" style="width: 13%;">Jadwal Berikutnya</th>
                        <th class="text-nowrap" style="width: 14%;">Status (H-30 / H-7)</th>
                        <th class="text-nowrap text-end" style="width: 10%;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($all_units)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">Belum ada armada terdaftar.</td></tr>
                    <?php else: ?>
                        <?php foreach ($all_units as $u): 
                            $info = get_alisa_schedule_info($u['tgl_servis_berikutnya'], 'Servis', $u['kondisi'], $u['jenis']);
                        ?>
                            <tr>
                                <td class="fw-bold text-white font-monospace text-nowrap"><?= htmlspecialchars($u['kode_plat']) ?></td>
                                <td class="fw-bold text-white"><?= htmlspecialchars($u['nama']) ?></td>
                                <td class="small fw-semibold text-info text-nowrap"><i class="fa-solid fa-location-dot me-1"></i> <?= htmlspecialchars($u['lokasi_ruas']) ?></td>
                                <td class="small text-white text-nowrap"><?= format_tgl_indo($u['tgl_servis_terakhir']) ?></td>
                                <td class="fw-bold text-white text-nowrap"><?= format_tgl_indo($u['tgl_servis_berikutnya']) ?></td>
                                <td class="text-nowrap">
                                    <span class="badge <?= $info['badge_class'] ?> rounded-pill px-3 py-1">
                                        <?= $info['label'] ?>
                                    </span>
                                </td>
                                <td class="text-end text-nowrap">
                                    <?php if (can_edit_data()): ?>
                                        <a href="servis_form.php?id_kendaraan=<?= $u['id'] ?><?= !empty($jenis_filter) ? '&jenis_servis=' . urlencode($jenis_filter) : '' ?>" class="btn btn-sm btn-bpj-primary rounded-pill px-3 shadow-sm" title="Input Data Servis Unit Ini">
                                            <i class="fa-solid fa-pen-to-square me-1"></i> Input Servis
                                        </a>
                                    <?php else: ?>
                                        <a href="servis_kelola.php?id_kendaraan=<?= $u['id'] ?>" class="btn btn-sm btn-outline-info rounded-pill px-2.5">
                                            <i class="fa-solid fa-filter me-1"></i> Riwayat
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- History Log Table with Vehicle Filter Dropdown & Hapus Action -->
    <div class="card-custom p-4">
        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-3">
            <h6 class="fw-bold text-white m-0"><i class="fa-solid fa-file-invoice text-info me-2"></i> Riwayat Melakukan Servis & Rincian Subtotal</h6>
            
            <!-- UNIT FILTER DROPDOWN FORM -->
            <form method="GET" action="" class="d-flex align-items-center gap-2" style="min-width: 320px;">
                <label class="form-label mb-0 fw-bold small text-nowrap text-white-50"><i class="fa-solid fa-filter me-1"></i> Filter Unit:</label>
                <select name="id_kendaraan" id="selectFilterUnit" class="form-select form-select-sm border-secondary text-white bg-dark" onchange="this.form.submit()">
                    <option value="0">-- Semua Unit Kendaraan / Alat --</option>
                    <?php foreach ($all_units_list as $un): ?>
                        <option value="<?= $un['id'] ?>" <?= ($id_kendaraan_filter === (int)$un['id']) ? 'selected' : '' ?>>
                            [<?= htmlspecialchars($un['kode_plat']) ?>] <?= htmlspecialchars($un['nama']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($id_kendaraan_filter > 0): ?>
                    <a href="servis_kelola.php" class="btn btn-sm btn-outline-secondary rounded-circle text-white" title="Reset Filter"><i class="fa-solid fa-xmark"></i></a>
                <?php endif; ?>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="text-nowrap" style="width: 12%;">Tgl Servis</th>
                        <th class="text-nowrap" style="width: 20%;">Nopol / Unit</th>
                        <th class="text-nowrap" style="width: 12%;">Kategori Servis</th>
                        <th class="text-nowrap" style="width: 20%;">Bengkel / Layanan</th>
                        <th class="text-nowrap" style="width: 14%;">Total Biaya</th>
                        <th class="text-nowrap text-end" style="width: 22%;">Aksi Detail</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($servis_logs)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">Belum ada riwayat servis tercatat untuk unit yang dipilih.</td></tr>
                    <?php else: ?>
                        <?php foreach ($servis_logs as $log): ?>
                            <tr>
                                <td class="fw-semibold text-nowrap text-white"><?= format_tgl_indo($log['tgl_servis']) ?></td>
                                <td>
                                    <div class="fw-bold text-white font-monospace"><?= htmlspecialchars($log['kode_plat']) ?></div>
                                    <div class="text-white-50 small"><?= htmlspecialchars($log['nama']) ?></div>
                                </td>
                                <td>
                                    <?php if ($log['jenis_servis'] === 'Darurat'): ?>
                                        <span class="badge bg-danger text-white"><i class="fa-solid fa-triangle-exclamation me-1"></i> Darurat</span>
                                    <?php else: ?>
                                        <span class="badge bg-info text-white" style="background-color: #0284c7 !important;"><i class="fa-solid fa-calendar-check me-1"></i> Penjadwalan</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-white">
                                    <strong class="text-white"><?= htmlspecialchars($log['nama_bengkel'] ?: '-') ?></strong><br>
                                    <span class="text-white-50"><?= htmlspecialchars($log['nama_layanan']) ?></span>
                                </td>
                                <td class="fw-bold text-info fs-6 text-nowrap">
                                    <?= format_rupiah_privacy($log['total_biaya']) ?>
                                </td>
                                <td class="text-end text-nowrap">
                                    <button type="button" class="btn btn-sm btn-primary rounded-pill px-3 shadow-sm me-1" style="background-color: #0284c7 !important; border: none;" onclick='showDetailNotaModal(<?= json_encode($log, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                        <i class="fa-solid fa-receipt me-1"></i> Detail Nota
                                    </button>
                                    <?php if (can_edit_data()): ?>
                                        <a href="servis_form.php?edit_id=<?= $log['id'] ?>" class="btn btn-sm btn-outline-warning rounded-pill px-2.5 me-1" style="color: #eab308 !important; border-color: #eab308 !important;" title="Edit Catatan Servis Ini">
                                            <i class="fa-solid fa-pen-to-square me-1"></i> Edit
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($current_role === 'admin' || $current_role === 'pimpinan'): ?>
                                        <a href="cetak_nota_servis.php?id=<?= $log['id'] ?>&autoprint=1" target="_blank" class="btn btn-sm btn-outline-info rounded-pill px-2.5 me-1" title="Cetak Transaksi Nota Servis Ini (PDF)">
                                            <i class="fa-solid fa-print me-1"></i> Cetak
                                        </a>
                                    <?php endif; ?>
                                    <?php if (can_edit_data()): ?>
                                        <a href="servis_kelola.php?action=delete&id=<?= $log['id'] ?>" onclick="return confirm('Apakah Anda yakin ingin menghapus catatan servis ini?')" class="btn btn-sm btn-outline-danger rounded-pill px-2.5" title="Hapus Catatan Servis Ini">
                                            <i class="fa-solid fa-trash me-1"></i> Hapus
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <!-- TABLE FOOTER SUBTOTAL SUMMARY -->
                <tfoot>
                    <tr>
                        <td colspan="4" class="text-end fw-extrabold text-white py-3">
                            <i class="fa-solid fa-calculator text-info me-2"></i> REKAP TOTAL BIAYA SERVIS (SUBTOTAL UNIT):
                        </td>
                        <td class="fw-extrabold fs-5 text-info py-3" colspan="2">
                            <?= format_rupiah_privacy($grand_total_servis) ?>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<!-- Modal Upload Nota Ke Admin oleh Teknisi -->
<div class="modal fade" id="modalUploadNotaTeknisi" tabindex="-1" aria-labelledby="modalUploadNotaTeknisiLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-white border-secondary">
            <div class="modal-header border-secondary" style="background-color: #0284c7 !important;">
                <h5 class="modal-title fw-bold text-white" id="modalUploadNotaTeknisiLabel">
                    <i class="fa-solid fa-upload me-2"></i> Form Upload Nota Ke Admin
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload_nota_teknisi">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-info">Pilih Unit Armada <span class="text-danger">*</span></label>
                        <select name="id_kendaraan" class="form-select border-secondary text-white bg-dark" required>
                            <option value="">-- Pilih Unit Armada --</option>
                            <?php foreach ($all_units_list as $u): ?>
                                <option value="<?= $u['id'] ?>">[<?= htmlspecialchars($u['kode_plat']) ?>] <?= htmlspecialchars($u['nama']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-white">Tanggal Servis / Nota <span class="text-danger">*</span></label>
                            <input type="date" name="tgl_nota" class="form-control border-secondary text-white bg-dark" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-white">Kategori Servis <span class="text-danger">*</span></label>
                            <select name="jenis_servis" class="form-select border-secondary text-white bg-dark" required>
                                <option value="Penjadwalan">Servis Penjadwalan</option>
                                <option value="Darurat">Perbaikan Darurat</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-white">Nama Layanan / Pekerjaan <span class="text-danger">*</span></label>
                        <input type="text" name="nama_layanan" class="form-control border-secondary text-white bg-dark" value="Servis Rutin Berkala & Maintenance" required>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-white">Nama Bengkel</label>
                            <input type="text" name="nama_bengkel" class="form-control border-secondary text-white bg-dark" placeholder="Contoh: Bengkel Resmi Hino">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-info">Total Biaya (Rp) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" name="total_biaya" class="form-control border-secondary text-white bg-dark font-monospace fw-bold" placeholder="Contoh: 1500000" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-warning" style="color: #eab308 !important;"><i class="fa-solid fa-camera me-1"></i> Unggah Foto Nota / Kwitansi <span class="text-danger">*</span></label>
                        <input type="file" name="foto_nota" class="form-control border-secondary text-white bg-dark" accept="image/*,.pdf" required>
                        <small class="text-white-50">Upload foto bukti fisik nota transaksi bengkel.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-white">Catatan / Keterangan Tambahan</label>
                        <textarea name="catatan" class="form-control border-secondary text-white bg-dark" rows="2" placeholder="Catatan pekerjaan oleh Teknisi..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-bpj-primary rounded-pill px-4 shadow">
                        <i class="fa-solid fa-paper-plane me-2"></i> Kirim Nota Ke Admin
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/modal_detail_nota.php'; ?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>

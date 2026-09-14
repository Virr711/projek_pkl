<?php
require_once __DIR__ . '/includes/header.php';

$id_kendaraan_filter = (int)($_GET['id_kendaraan'] ?? 0);

// Fetch All Units for Filter Dropdown
$stmt_all_units = $pdo->query("SELECT * FROM kendaraan_alat ORDER BY nama ASC");
$all_units_list = $stmt_all_units->fetchAll();

// Fetch Servis Darurat Records Only
$sql_log = "SELECT r.*, k.nama, k.kode_plat, k.lokasi_ruas, k.merk, k.kategori FROM riwayat_servis r JOIN kendaraan_alat k ON r.id_kendaraan = k.id WHERE r.jenis_servis = 'Darurat'";
$params_log = [];

if ($id_kendaraan_filter > 0) {
    $sql_log .= " AND r.id_kendaraan = ?";
    $params_log[] = $id_kendaraan_filter;
}
$sql_log .= " ORDER BY r.tgl_servis DESC";

$stmt = $pdo->prepare($sql_log);
$stmt->execute($params_log);
$servis_logs = $stmt->fetchAll();

$grand_total_servis = 0;
foreach ($servis_logs as $log) {
    $grand_total_servis += (float)$log['total_biaya'];
}

$cnt_darurat = count($servis_logs);
?>

<div class="container-fluid p-0">
    <!-- Distinct Header with Breadcrumb & Back Button -->
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="servis_kelola.php" class="text-info text-decoration-none"><i class="fa-solid fa-wrench me-1"></i> Melakukan Servis</a></li>
                    <li class="breadcrumb-item active text-white" aria-current="page">Submenu Darurat</li>
                </ol>
            </nav>
            <h4 class="fw-bold mb-0 text-white"><i class="fa-solid fa-triangle-exclamation text-danger me-2"></i> Perbaikan Darurat (Mogok & Perbaikan Mendadak Lapangan)</h4>
        </div>
        <div class="d-flex gap-2">
            <a href="servis_kelola.php" class="btn btn-outline-secondary text-white rounded-pill px-3">
                <i class="fa-solid fa-arrow-left me-1"></i> Kembali ke Semua Servis
            </a>
            <?php if (can_edit_data()): ?>
                <a href="servis_form.php?jenis_servis=Darurat" class="btn btn-bpj-primary shadow">
                    <i class="fa-solid fa-plus me-2"></i> Input Perbaikan Darurat
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- History Table Servis Darurat -->
    <div class="card-custom p-4">
        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-3">
            <h6 class="fw-bold text-white m-0"><i class="fa-solid fa-triangle-exclamation text-danger me-2"></i> Riwayat Perbaikan Darurat (Total: <?= $cnt_darurat ?> Perbaikan)</h6>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="text-nowrap" style="width: 12%;">Tgl Servis</th>
                        <th class="text-nowrap" style="width: 20%;">Nopol / Unit</th>
                        <th class="text-nowrap" style="width: 15%;">Kategori Servis</th>
                        <th class="text-nowrap" style="width: 20%;">Bengkel / Layanan</th>
                        <th class="text-nowrap" style="width: 14%;">Total Biaya</th>
                        <th class="text-nowrap text-end" style="width: 19%;">Aksi Update / Nota</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($servis_logs)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">Belum ada riwayat perbaikan darurat / mogok tercatat.</td></tr>
                    <?php else: ?>
                        <?php foreach ($servis_logs as $log): ?>
                            <tr>
                                <td class="fw-semibold text-nowrap text-white"><?= format_tgl_indo($log['tgl_servis']) ?></td>
                                <td>
                                    <div class="fw-bold text-white font-monospace"><?= htmlspecialchars($log['kode_plat']) ?></div>
                                    <div class="text-white-50 small"><?= htmlspecialchars($log['nama']) ?></div>
                                </td>
                                <td>
                                    <span class="badge bg-danger text-white"><i class="fa-solid fa-triangle-exclamation me-1"></i> Darurat</span>
                                </td>
                                <td class="small text-white">
                                    <strong class="text-white"><?= htmlspecialchars($log['nama_bengkel'] ?: '-') ?></strong><br>
                                    <span class="text-white-50"><?= htmlspecialchars($log['nama_layanan']) ?></span>
                                </td>
                                <td class="fw-bold text-info fs-6 text-nowrap">
                                    <?= format_rupiah_privacy($log['total_biaya']) ?>
                                </td>
                                <td class="text-end text-nowrap">
                                    <a href="servis_form.php?id_kendaraan=<?= $log['id_kendaraan'] ?>&jenis_servis=Darurat" class="btn btn-sm btn-bpj-primary rounded-pill px-2.5 me-1" title="Update Data Servis Unit Ini">
                                        <i class="fa-solid fa-pen-to-square me-1"></i> Update
                                    </a>
                                    <button type="button" class="btn btn-sm btn-outline-info rounded-pill px-2.5" onclick='showDetailNotaModal(<?= json_encode($log, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                        <i class="fa-solid fa-receipt me-1"></i> Nota
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php require_once __DIR__ . '/includes/modal_detail_nota.php'; ?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>

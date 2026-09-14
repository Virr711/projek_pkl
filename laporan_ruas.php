<?php
require_once __DIR__ . '/includes/header.php';

// Strict Use Case Role Guard
if ($current_role !== 'admin' && $current_role !== 'pimpinan' && $current_role !== 'bendahara') {
    echo "<script>alert('Akses Ditolak: Fitur Laporan khusus untuk Admin, Pimpinan, & Bendahara.'); window.location='index.php';</script>";
    exit;
}

$jenis_filter = trim($_GET['jenis'] ?? '');
$ruas_filter = trim($_GET['ruas'] ?? '');
$search = trim($_GET['search'] ?? '');

$sql = "SELECT * FROM kendaraan_alat WHERE 1=1";
$params = [];

if (!empty($jenis_filter)) {
    $sql .= " AND jenis = ?";
    $params[] = $jenis_filter;
}
if (!empty($ruas_filter)) {
    if ($ruas_filter === '13. Tempat Lain (Input Custom)') {
        $std_12 = array_slice(get_lokasi_ruas_list(), 0, 12);
        $in_clause = "'" . implode("','", array_map('addslashes', $std_12)) . "'";
        $sql .= " AND (lokasi_ruas NOT IN ($in_clause) OR lokasi_ruas LIKE 'Tempat Lain%' OR lokasi_ruas = '13. Tempat Lain (Input Custom)')";
    } else {
        $sql .= " AND lokasi_ruas = ?";
        $params[] = $ruas_filter;
    }
}
if (!empty($search)) {
    $search_term = trim($search);
    $search_upper = strtoupper($search_term);
    $search_lower = strtolower($search_term);

    $search_fields = ["kode_plat", "nama", "lokasi_ruas", "merk", "penanggung_jawab", "no_chasis", "no_mesin", "kondisi"];
    $search_clauses = [];
    foreach ($search_fields as $f) {
        $search_clauses[] = "$f LIKE ?";
        $params[] = "%$search_term%";
    }
    if ($search_upper === 'RR' || strpos($search_lower, 'rusak ringan') !== false) {
        $search_clauses[] = "kondisi = 'RR'";
    } elseif ($search_upper === 'RB' || strpos($search_lower, 'rusak berat') !== false) {
        $search_clauses[] = "kondisi = 'RB'";
    } elseif ($search_upper === 'B' || $search_lower === 'baik') {
        $search_clauses[] = "kondisi = 'B'";
    }

    $sql .= " AND (" . implode(" OR ", $search_clauses) . ")";
}

$sql .= " ORDER BY lokasi_ruas ASC, nama ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$data_report = $stmt->fetchAll();

$ruas_list = get_lokasi_ruas_list();
?>

<div class="container-fluid p-0">
    <!-- Distinct Header with Breadcrumb & Back Button -->
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="laporan.php" class="text-info text-decoration-none"><i class="fa-solid fa-file-contract me-1"></i> Laporan</a></li>
                    <li class="breadcrumb-item active text-white" aria-current="page">Submenu Data Ruas</li>
                </ol>
            </nav>
            <h4 class="fw-bold mb-0 text-white"><i class="fa-solid fa-map-location-dot text-info me-2"></i> Laporan Distribusi Armada Berdasarkan 13 Lokasi BPJ Tegal</h4>
        </div>
        <div class="d-flex gap-2">
            <a href="laporan.php" class="btn btn-outline-secondary text-white rounded-pill px-3">
                <i class="fa-solid fa-arrow-left me-1"></i> Kembali ke Laporan Utama
            </a>
            <?php if ($current_role === 'admin' || $current_role === 'pimpinan'): ?>
                <a href="export_docx.php?tipe=data_ruas&<?= htmlspecialchars($_SERVER['QUERY_STRING'] ?? '') ?>" class="btn btn-primary rounded-pill px-4 shadow" style="background-color: #0d9488 !important; border: none;">
                    <i class="fa-solid fa-file-word me-1.5"></i> Export Word (.docx)
                </a>
                <button onclick="window.print()" class="btn btn-primary rounded-pill px-4 shadow" style="background-color: #0284c7 !important; border: none;">
                    <i class="fa-solid fa-print me-2"></i> Cetak Laporan (PDF)
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- FILTER BAR INTERAKTIF DATA RUAS & SEARCH -->
    <div class="card-custom p-3 mb-4 no-print">
        <form method="GET" action="" class="row g-2 align-items-center">
            <div class="col-md-3">
                <label class="form-label fw-bold text-white-50 small mb-1"><i class="fa-solid fa-magnifying-glass me-1"></i> Pencarian Kata Kunci / Nopol:</label>
                <div class="input-group">
                    <input type="text" name="search" class="form-control bg-dark text-white border-secondary" placeholder="Cari Nopol, Nama, Ruas..." value="<?= htmlspecialchars($search) ?>">
                    <button type="submit" class="btn btn-outline-secondary text-white"><i class="fa-solid fa-search"></i></button>
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold text-white-50 small mb-1"><i class="fa-solid fa-truck me-1"></i> Filter Jenis Unit:</label>
                <select name="jenis" class="form-select bg-dark text-white border-secondary" onchange="this.form.submit()">
                    <option value="">-- Semua Jenis (Kendaraan & Peralatan) --</option>
                    <option value="kendaraan_roda_6" <?= ($jenis_filter === 'kendaraan_roda_6') ? 'selected' : '' ?>>Roda 6 (Dump Truck)</option>
                    <option value="kendaraan_roda_4" <?= ($jenis_filter === 'kendaraan_roda_4') ? 'selected' : '' ?>>Roda 4 (Pickup/Mobil)</option>
                    <option value="kendaraan_roda_3" <?= ($jenis_filter === 'kendaraan_roda_3') ? 'selected' : '' ?>>Roda 3 (VIAR Work 200)</option>
                    <option value="kendaraan_roda_2" <?= ($jenis_filter === 'kendaraan_roda_2') ? 'selected' : '' ?>>Roda 2</option>
                    <option value="peralatan" <?= ($jenis_filter === 'peralatan') ? 'selected' : '' ?>>Peralatan / Alat Berat</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold text-white-50 small mb-1"><i class="fa-solid fa-location-dot me-1"></i> Filter Lokasi Ruas Jalan:</label>
                <select name="ruas" class="form-select bg-dark text-white border-secondary" onchange="this.form.submit()">
                    <option value="">-- Semua 13 Lokasi Ruas Jalan --</option>
                    <?php foreach ($ruas_list as $r): ?>
                        <option value="<?= htmlspecialchars($r) ?>" <?= ($ruas_filter === $r) ? 'selected' : '' ?>><?= htmlspecialchars($r) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end" style="height: 60px;">
                <?php if (!empty($jenis_filter) || !empty($ruas_filter) || !empty($search)): ?>
                    <a href="laporan_ruas.php" class="btn btn-outline-secondary w-100 rounded-pill mb-1"><i class="fa-solid fa-rotate-left me-1"></i> Reset</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Printable Report Table Area -->
    <div class="card-custom p-4" id="printableArea">
        <div class="text-center mb-4 pb-3 border-bottom border-secondary">
            <img src="assets/images/logo_jateng.png?v=<?= time() ?>" alt="Logo Jawa Tengah" style="height: 64px; width: auto; margin-bottom: 12px;">
            <h4 class="fw-extrabold text-white mb-1" style="letter-spacing: 1px;">PEMERINTAH PROVINSI JAWA TENGAH</h4>
            <h5 class="fw-extrabold text-white mb-1">BALAI PENGELOLAAN JALAN WILAYAH TEGAL</h5>
            <h6 class="fw-bold text-info mb-1">DOKUMEN RESMI LAPORAN SEBARAN ARMADA 13 LOKASI RUAS JALAN</h6>
            <p class="small text-white-50 mb-0">Tahun Anggaran 2026 &bull; Tanggal Cetak: <?= format_tgl_indo(date('Y-m-d')) ?></p>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="fw-bold text-white mb-0">Distribusi Unit Armada & Peralatan Berdasarkan 13 Lokasi BPJ Tegal:</h6>
            <span class="badge bg-info text-white font-monospace">Total: <?= count($data_report) ?> Unit Ditemukan</span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle small">
                <thead>
                    <tr>
                        <th class="text-nowrap">No</th>
                        <th class="text-nowrap">Lokasi Ruas / Posisi</th>
                        <th class="text-nowrap">Nopol / Kode Unit</th>
                        <th class="text-nowrap">Nama Unit Armada / Peralatan</th>
                        <th class="text-nowrap">Jenis & Merk</th>
                        <th class="text-nowrap">Penanggung Jawab / Operator</th>
                        <th class="text-nowrap">Status Operasional</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($data_report)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">Tidak ada unit armada / peralatan sesuai filter lokasi & jenis yang dipilih.</td></tr>
                    <?php else: ?>
                        <?php foreach ($data_report as $idx => $row): ?>
                            <tr>
                                <td class="text-white"><?= $idx + 1 ?></td>
                                <td class="fw-bold text-info text-nowrap"><i class="fa-solid fa-location-dot me-1"></i> <?= htmlspecialchars($row['lokasi_ruas']) ?></td>
                                <td class="fw-bold text-white font-monospace text-nowrap"><?= htmlspecialchars($row['kode_plat']) ?></td>
                                <td class="fw-bold text-white"><?= htmlspecialchars($row['nama']) ?></td>
                                <td class="text-nowrap text-white"><?= htmlspecialchars($row['merk'] ?: '-') ?> (<?= str_replace('_', ' ', strtoupper($row['jenis'])) ?>)</td>
                                <td class="text-nowrap text-white"><?= htmlspecialchars($row['penanggung_jawab'] ?: '-') ?></td>
                                <td><?= get_kondisi_badge($row['kondisi']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Official Balai Signature Block -->
        <div class="row mt-5 pt-4 border-top border-secondary text-white">
            <div class="col-6 text-center">
                <p class="mb-1 text-white-50">Mengetahui,</p>
                <p class="fw-bold text-white mb-5">Pimpinan Balai Pengelolaan Jalan</p>
                <p class="fw-bold text-decoration-underline text-white mb-0">Pak Adi</p>
                <small class="text-white-50">NIP. 19780112 200501 1 002</small>
            </div>
            <div class="col-6 text-center">
                <p class="mb-1 text-white-50">Tegal, <?= format_tgl_indo(date('Y-m-d')) ?></p>
                <p class="fw-bold text-white mb-5">Pengelola Logistik & Pemeliharaan Armada</p>
                <p class="fw-bold text-decoration-underline text-white mb-0">Balai Pengelolaan Jalan Wilayah Tegal</p>
                <small class="text-white-50">Tim Logistik & Operasional</small>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

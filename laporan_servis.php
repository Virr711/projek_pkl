<?php
require_once __DIR__ . '/includes/header.php';

// Strict Use Case Role Guard
if ($current_role !== 'admin' && $current_role !== 'pimpinan' && $current_role !== 'bendahara') {
    echo "<script>alert('Akses Ditolak: Fitur Laporan khusus untuk Admin, Pimpinan, & Bendahara.'); window.location='index.php';</script>";
    exit;
}

$id_kendaraan_filter = (int)($_GET['id_kendaraan'] ?? 0);
$jenis_filter = trim($_GET['jenis'] ?? '');
$servis_cat_filter = trim($_GET['jenis_servis'] ?? '');
$search = trim($_GET['search'] ?? '');

// Fetch All Units for Dropdown
$stmt_units = $pdo->query("SELECT * FROM kendaraan_alat ORDER BY nama ASC");
$all_units_list = $stmt_units->fetchAll();

// Selected unit info if single unit report requested
$selected_unit = null;
if ($id_kendaraan_filter > 0) {
    $stmt_u = $pdo->prepare("SELECT * FROM kendaraan_alat WHERE id = ?");
    $stmt_u->execute([$id_kendaraan_filter]);
    $selected_unit = $stmt_u->fetch();
}

// Fetch Servis Report Query
$sql_report = "SELECT r.*, k.nama, k.kode_plat, k.lokasi_ruas, k.merk, k.tahun, k.jenis, k.kategori, k.no_chasis, k.no_mesin, k.kondisi FROM riwayat_servis r JOIN kendaraan_alat k ON r.id_kendaraan = k.id WHERE 1=1";
$params_report = [];

if ($id_kendaraan_filter > 0) {
    $sql_report .= " AND r.id_kendaraan = ?";
    $params_report[] = $id_kendaraan_filter;
}
if (!empty($jenis_filter)) {
    $sql_report .= " AND k.jenis = ?";
    $params_report[] = $jenis_filter;
}
if (!empty($servis_cat_filter)) {
    $sql_report .= " AND r.jenis_servis = ?";
    $params_report[] = $servis_cat_filter;
}

if (!empty($search)) {
    $search_term = trim($search);
    $search_upper = strtoupper($search_term);
    $search_lower = strtolower($search_term);

    $search_fields = [
        "k.kode_plat", "k.nama", "k.lokasi_ruas", "k.merk", 
        "k.penanggung_jawab", "k.no_chasis", "k.no_mesin", 
        "r.nama_bengkel", "r.nama_layanan", "r.rincian_item", "r.jenis_servis"
    ];
    
    $search_clauses = [];
    foreach ($search_fields as $f) {
        $search_clauses[] = "$f LIKE ?";
        $params_report[] = "%$search_term%";
    }

    if ($search_upper === 'RR' || strpos($search_lower, 'rusak ringan') !== false) {
        $search_clauses[] = "k.kondisi = 'RR'";
    } elseif ($search_upper === 'RB' || strpos($search_lower, 'rusak berat') !== false) {
        $search_clauses[] = "k.kondisi = 'RB'";
    } elseif ($search_upper === 'B' || $search_lower === 'baik') {
        $search_clauses[] = "k.kondisi = 'B'";
    }

    $sql_report .= " AND (" . implode(" OR ", $search_clauses) . ")";
}

$sql_report .= " ORDER BY r.tgl_servis DESC";

$stmt = $pdo->prepare($sql_report);
$stmt->execute($params_report);
$data_report = $stmt->fetchAll();

$total_biaya = 0;
foreach ($data_report as $r) {
    $total_biaya += (float)$r['total_biaya'];
}
?>

<div class="container-fluid p-0">
    <!-- Distinct Header with Breadcrumb & Back Button -->
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="laporan.php" class="text-info text-decoration-none"><i class="fa-solid fa-file-contract me-1"></i> Laporan</a></li>
                    <li class="breadcrumb-item active text-white" aria-current="page">Submenu Servis</li>
                </ol>
            </nav>
            <h4 class="fw-bold mb-0 text-white">
                <i class="fa-solid fa-wrench text-info me-2"></i> 
                <?= $selected_unit ? 'Laporan Riwayat Servis: ' . htmlspecialchars($selected_unit['kode_plat']) . ' - ' . htmlspecialchars($selected_unit['nama']) : 'Laporan Rekapitulasi Pemeliharaan Servis Armada' ?>
            </h4>
        </div>
        <div class="d-flex gap-2">
            <a href="laporan.php" class="btn btn-outline-secondary text-white rounded-pill px-3">
                <i class="fa-solid fa-arrow-left me-1"></i> Kembali ke Laporan Utama
            </a>
            <?php if ($current_role === 'admin' || $current_role === 'pimpinan'): ?>
                <a href="export_docx.php?tipe=servis&<?= htmlspecialchars($_SERVER['QUERY_STRING'] ?? '') ?>" class="btn btn-primary rounded-pill px-4 shadow" style="background-color: #0d9488 !important; border: none;">
                    <i class="fa-solid fa-file-word me-1.5"></i> Export Word (.docx)
                </a>
                <button onclick="window.print()" class="btn btn-primary rounded-pill px-4 shadow" style="background-color: #0284c7 !important; border: none;">
                    <i class="fa-solid fa-print me-2"></i> Cetak Laporan (PDF)
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- FILTER BAR INTERAKTIF PER KENDARAAN, KLASIFIKASI & PENCARIAN -->
    <div class="card-custom p-3 mb-4 no-print">
        <form method="GET" action="" class="row g-2 align-items-center">
            <div class="col-md-3">
                <label class="form-label fw-bold text-white-50 small mb-1"><i class="fa-solid fa-magnifying-glass me-1"></i> Pencarian Kata Kunci / Nopol:</label>
                <div class="input-group">
                    <input type="text" name="search" class="form-control bg-dark text-white border-secondary" placeholder="Cari Nopol, Nama, Bengkel..." value="<?= htmlspecialchars($search) ?>">
                    <button type="submit" class="btn btn-outline-secondary text-white"><i class="fa-solid fa-search"></i></button>
                </div>
            </div>
            <div class="col-md-5">
                <label class="form-label fw-bold text-white-50 small mb-1"><i class="fa-solid fa-filter me-1"></i> Pilih Laporan Khusus Unit Armada:</label>
                <select name="id_kendaraan" class="form-select bg-dark text-white border-secondary select2-searchable" onchange="this.form.submit()">
                    <option value="0">-- Ketik Nopol / Nama Unit Armada --</option>
                    <?php foreach ($all_units_list as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= ($id_kendaraan_filter === (int)$u['id']) ? 'selected' : '' ?>>
                            [<?= htmlspecialchars($u['kode_plat']) ?>] <?= htmlspecialchars($u['nama']) ?> &bull; Ruas: <?= htmlspecialchars($u['lokasi_ruas']) ?> (Kondisi: <?= htmlspecialchars($u['kondisi']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold text-white-50 small mb-1"><i class="fa-solid fa-truck me-1"></i> Klasifikasi Jenis Unit:</label>
                <select name="jenis" class="form-select bg-dark text-white border-secondary" onchange="this.form.submit()">
                    <option value="">-- Semua Jenis Unit --</option>
                    <option value="kendaraan_roda_6" <?= ($jenis_filter === 'kendaraan_roda_6') ? 'selected' : '' ?>>Roda 6 (Dump Truck)</option>
                    <option value="kendaraan_roda_4" <?= ($jenis_filter === 'kendaraan_roda_4') ? 'selected' : '' ?>>Roda 4 (Pickup/Mobil)</option>
                    <option value="kendaraan_roda_3" <?= ($jenis_filter === 'kendaraan_roda_3') ? 'selected' : '' ?>>Roda 3 (VIAR Work 200)</option>
                    <option value="kendaraan_roda_2" <?= ($jenis_filter === 'kendaraan_roda_2') ? 'selected' : '' ?>>Roda 2</option>
                    <option value="peralatan" <?= ($jenis_filter === 'peralatan') ? 'selected' : '' ?>>Peralatan / Alat Berat</option>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end" style="height: 60px;">
                <input type="hidden" name="jenis_servis" value="<?= htmlspecialchars($servis_cat_filter) ?>">
                <?php if ($id_kendaraan_filter > 0 || !empty($jenis_filter) || !empty($servis_cat_filter) || !empty($search)): ?>
                    <a href="laporan_servis.php" class="btn btn-outline-secondary w-100 rounded-pill mb-1"><i class="fa-solid fa-rotate-left me-1"></i> Reset</a>
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
            <h6 class="fw-bold text-info mb-1">
                <?= $selected_unit ? 'DOKUMEN RESMI LAPORAN RIWAYAT PEMELIHARAAN & SERVIS UNIT ARMADA' : 'DOKUMEN RESMI LAPORAN REKAPITULASI BIAYA & PEMELIHARAAN SERVIS ARMADA' ?>
            </h6>
            <p class="small text-white-50 mb-0">Tahun Anggaran 2026 &bull; Tanggal Cetak: <?= format_tgl_indo(date('Y-m-d')) ?></p>
        </div>

        <?php if ($selected_unit): ?>
            <!-- SINGLE UNIT PROFILE INFORMATION CARD FOR REPORT -->
            <div class="card bg-dark border-secondary p-3 mb-4 rounded-3 text-white">
                <div class="row g-3">
                    <div class="col-md-6">
                        <span class="text-white-50 small d-block">Nama Unit Armada / Peralatan:</span>
                        <h5 class="fw-extrabold text-white mb-1"><?= htmlspecialchars($selected_unit['nama']) ?></h5>
                        <div class="fw-bold text-info font-monospace fs-5"><?= htmlspecialchars($selected_unit['kode_plat']) ?></div>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <span class="text-white-50 small d-block">Lokasi Ruas Jalan Posisi Unit:</span>
                        <h6 class="fw-bold text-success mb-1"><i class="fa-solid fa-location-dot me-1"></i> <?= htmlspecialchars($selected_unit['lokasi_ruas']) ?></h6>
                        <span class="badge bg-secondary me-1"><?= str_replace('_', ' ', strtoupper($selected_unit['jenis'])) ?></span>
                        <?= get_kondisi_badge($selected_unit['kondisi']) ?>
                    </div>
                    <div class="col-md-4 border-top border-secondary pt-2">
                        <span class="text-white-50 small d-block">Merk / Tahun Pembuatan:</span>
                        <strong class="text-white"><?= htmlspecialchars($selected_unit['merk'] ?: '-') ?> (Tahun <?= htmlspecialchars($selected_unit['tahun'] ?: '-') ?>)</strong>
                    </div>
                    <div class="col-md-4 border-top border-secondary pt-2">
                        <span class="text-white-50 small d-block">Nomor Rangka & Mesin:</span>
                        <strong class="text-white font-monospace"><?= htmlspecialchars($selected_unit['no_chasis'] ?: '-') ?> / <?= htmlspecialchars($selected_unit['no_mesin'] ?: '-') ?></strong>
                    </div>
                    <div class="col-md-4 border-top border-secondary pt-2 text-md-end">
                        <span class="text-white-50 small d-block">Frekuensi Servis Executed:</span>
                        <strong class="text-info fs-6"><?= count($data_report) ?> Kali Servis Recorded</strong>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="fw-bold text-white mb-0">
                <?= $selected_unit ? 'Rincian Riwayat Pemeliharaan & Transaksi Servis Unit Ini:' : 'Rekapitulasi Transaksi Pemeliharaan Servis Penjadwalan & Darurat:' ?>
            </h6>
            <span class="badge bg-info text-white font-monospace">Total: <?= count($data_report) ?> Transaksi Ditemukan</span>
        </div>

        <div class="table-responsive mb-3">
            <table class="table table-hover align-middle small">
                <thead>
                    <tr>
                        <th class="text-nowrap">No</th>
                        <th class="text-nowrap">Tgl Servis</th>
                        <?php if (!$selected_unit): ?>
                            <th class="text-nowrap">Nopol / Unit</th>
                        <?php endif; ?>
                        <th class="text-nowrap">Kategori</th>
                        <th class="text-nowrap">Bengkel / Rekanan</th>
                        <th class="text-nowrap">Rincian Pekerjaan & Sparepart</th>
                        <th class="text-nowrap text-end">Total Biaya</th>
                        <th class="text-nowrap text-end no-print">Aksi Nota</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($data_report)): ?>
                        <tr><td colspan="<?= $selected_unit ? '7' : '8' ?>" class="text-center text-muted py-4">Belum ada riwayat data servis recorded sesuai kriteria pencarian / filter.</td></tr>
                    <?php else: ?>
                        <?php foreach ($data_report as $idx => $row): ?>
                            <tr>
                                <td class="text-white"><?= $idx + 1 ?></td>
                                <td class="text-nowrap text-white"><?= format_tgl_indo($row['tgl_servis']) ?></td>
                                <?php if (!$selected_unit): ?>
                                    <td class="fw-bold text-white font-monospace text-nowrap"><?= htmlspecialchars($row['kode_plat']) ?><br><small class="fw-normal text-white-50"><?= htmlspecialchars($row['nama']) ?></small></td>
                                <?php endif; ?>
                                <td class="text-nowrap">
                                    <span class="badge <?= ($row['jenis_servis'] === 'Darurat') ? 'bg-danger' : 'bg-info' ?> text-white">
                                        <?= htmlspecialchars($row['jenis_servis']) ?>
                                    </span>
                                </td>
                                <td class="text-nowrap text-white"><?= htmlspecialchars($row['nama_bengkel'] ?: '-') ?></td>
                                <td class="text-white">
                                    <strong class="text-white"><?= htmlspecialchars($row['nama_layanan']) ?></strong>
                                    <?php if (!empty($row['rincian_item'])): ?>
                                        <div class="small text-white-50 font-monospace text-truncate" style="max-width: 250px;"><?= htmlspecialchars($row['rincian_item']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="fw-bold text-info text-end text-nowrap"><?= format_rupiah_privacy($row['total_biaya']) ?></td>
                                <td class="text-end text-nowrap no-print">
                                    <button type="button" class="btn btn-xs btn-primary rounded-pill px-2.5 py-1 me-1" style="background-color: #0284c7; border: none;" onclick='showDetailNotaModal(<?= json_encode($row, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                        <i class="fa-solid fa-receipt me-1"></i> Detail
                                    </button>
                                    <?php if ($current_role === 'admin' || $current_role === 'pimpinan'): ?>
                                        <a href="cetak_nota_servis.php?id=<?= $row['id'] ?>&autoprint=1" target="_blank" class="btn btn-xs btn-outline-info rounded-pill px-2.5 py-1" title="Cetak Individual Nota Servis Ini">
                                            <i class="fa-solid fa-print me-1"></i> Cetak Nota
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr class="table-active fw-bold text-white">
                        <td colspan="<?= $selected_unit ? '5' : '6' ?>" class="text-end text-uppercase">Total Alokasi Biaya Servis <?= $selected_unit ? 'Unit Ini' : 'Keseluruhan' ?>:</td>
                        <td class="text-end text-info fs-6" colspan="2"><?= format_rupiah_privacy($total_biaya) ?></td>
                    </tr>
                </tfoot>
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

<?php require_once __DIR__ . '/includes/modal_detail_nota.php'; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<?php
require_once __DIR__ . '/includes/header.php';

// Strict Use Case Role Guard
if ($current_role !== 'admin' && $current_role !== 'pimpinan' && $current_role !== 'bendahara') {
    echo "<script>alert('Akses Ditolak: Fitur Laporan khusus untuk Admin, Pimpinan, & Bendahara.'); window.location='index.php';</script>";
    exit;
}

$id_kendaraan_filter = (int)($_GET['id_kendaraan'] ?? 0);
$search = trim($_GET['search'] ?? '');

// Fetch All Units for Dropdown
$stmt_units = $pdo->query("SELECT * FROM kendaraan_alat WHERE jenis != 'peralatan' ORDER BY nama ASC");
$all_units_list = $stmt_units->fetchAll();

// Selected unit info if single unit report requested
$selected_unit = null;
if ($id_kendaraan_filter > 0) {
    $stmt_u = $pdo->prepare("SELECT * FROM kendaraan_alat WHERE id = ?");
    $stmt_u->execute([$id_kendaraan_filter]);
    $selected_unit = $stmt_u->fetch();
}

// Fetch Pajak & KIR Report Records
$sql_pajak = "SELECT p.*, k.nama, k.kode_plat, k.lokasi_ruas, k.merk, k.tahun, k.jenis, k.kategori, k.no_chasis, k.no_mesin, k.kondisi FROM pembayaran_pajak_kir p JOIN kendaraan_alat k ON p.id_kendaraan = k.id WHERE 1=1";
$params_pajak = [];
if ($id_kendaraan_filter > 0) {
    $sql_pajak .= " AND p.id_kendaraan = ?";
    $params_pajak[] = $id_kendaraan_filter;
}
if (!empty($search)) {
    $sql_pajak .= " AND (k.kode_plat LIKE ? OR k.nama LIKE ? OR k.lokasi_ruas LIKE ? OR k.merk LIKE ? OR p.jenis_pembayaran LIKE ? OR p.catatan LIKE ?)";
    $st = "%$search%";
    $params_pajak = array_merge($params_pajak, [$st, $st, $st, $st, $st, $st]);
}
$sql_pajak .= " ORDER BY p.tgl_bayar DESC";
$stmt_pajak = $pdo->prepare($sql_pajak);
$stmt_pajak->execute($params_pajak);
$data_pajak = $stmt_pajak->fetchAll();

// Fetch Servis Nota Files
$sql_nota = "SELECT r.*, k.nama, k.kode_plat FROM riwayat_servis r JOIN kendaraan_alat k ON r.id_kendaraan = k.id WHERE r.foto_nota IS NOT NULL AND r.foto_nota != ''";
$params_nota = [];
if ($id_kendaraan_filter > 0) {
    $sql_nota .= " AND r.id_kendaraan = ?";
    $params_nota[] = $id_kendaraan_filter;
}
if (!empty($search)) {
    $sql_nota .= " AND (k.kode_plat LIKE ? OR k.nama LIKE ? OR r.nama_bengkel LIKE ? OR r.nama_layanan LIKE ?)";
    $st = "%$search%";
    $params_nota = array_merge($params_nota, [$st, $st, $st, $st]);
}
$sql_nota .= " ORDER BY r.tgl_servis DESC";
$stmt_nota = $pdo->prepare($sql_nota);
$stmt_nota->execute($params_nota);
$data_nota = $stmt_nota->fetchAll();

$total_biaya_pajak = 0;
foreach ($data_pajak as $p) {
    $total_biaya_pajak += (float)$p['nominal_biaya'];
}
?>

<div class="container-fluid p-0">
    <!-- Distinct Header with Breadcrumb & Back Button -->
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="laporan.php" class="text-info text-decoration-none"><i class="fa-solid fa-file-contract me-1"></i> Laporan</a></li>
                    <li class="breadcrumb-item active text-white" aria-current="page">Submenu Nota & Pajak</li>
                </ol>
            </nav>
            <h4 class="fw-bold mb-0 text-white">
                <i class="fa-solid fa-receipt text-warning me-2"></i> 
                <?= $selected_unit ? 'Laporan Pajak Unit: ' . htmlspecialchars($selected_unit['kode_plat']) . ' - ' . htmlspecialchars($selected_unit['nama']) : 'Laporan Bukti Nota Fisik & Pembayaran Pajak STNK' ?>
            </h4>
        </div>
        <div class="d-flex gap-2">
            <a href="laporan.php" class="btn btn-outline-secondary text-white rounded-pill px-3">
                <i class="fa-solid fa-arrow-left me-1"></i> Kembali ke Laporan Utama
            </a>
            <?php if ($current_role === 'admin' || $current_role === 'pimpinan'): ?>
                <a href="export_docx.php?tipe=nota_pajak&<?= htmlspecialchars($_SERVER['QUERY_STRING'] ?? '') ?>" class="btn btn-primary rounded-pill px-4 shadow" style="background-color: #0d9488 !important; border: none;">
                    <i class="fa-solid fa-file-word me-1.5"></i> Export Word (.docx)
                </a>
                <button onclick="window.print()" class="btn btn-warning rounded-pill px-4 shadow text-dark fw-bold">
                    <i class="fa-solid fa-print me-2"></i> Cetak Laporan (PDF)
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- FILTER BAR INTERAKTIF PAJAK & SEARCH -->
    <div class="card-custom p-3 mb-4 no-print">
        <form method="GET" action="" class="row g-2 align-items-center">
            <div class="col-md-4">
                <label class="form-label fw-bold text-white-50 small mb-1"><i class="fa-solid fa-magnifying-glass me-1"></i> Pencarian Kata Kunci / Nopol:</label>
                <div class="input-group">
                    <input type="text" name="search" class="form-control bg-dark text-white border-secondary" placeholder="Cari Nopol, Nama, Pajak..." value="<?= htmlspecialchars($search) ?>">
                    <button type="submit" class="btn btn-outline-secondary text-white"><i class="fa-solid fa-search"></i></button>
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-bold text-white-50 small mb-1"><i class="fa-solid fa-filter me-1"></i> Filter Unit Kendaraan:</label>
                <select name="id_kendaraan" class="form-select bg-dark text-white border-secondary select2-searchable" onchange="this.form.submit()">
                    <option value="0">-- Ketik Nopol / Nama Unit Armada --</option>
                    <?php foreach ($all_units_list as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= ($id_kendaraan_filter === (int)$u['id']) ? 'selected' : '' ?>>
                            [<?= htmlspecialchars($u['kode_plat']) ?>] <?= htmlspecialchars($u['nama']) ?> &bull; Ruas: <?= htmlspecialchars($u['lokasi_ruas']) ?> (Kondisi: <?= htmlspecialchars($u['kondisi']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end" style="height: 60px;">
                <?php if ($id_kendaraan_filter > 0 || !empty($search)): ?>
                    <a href="laporan_nota_pajak.php" class="btn btn-outline-secondary w-100 rounded-pill mb-1"><i class="fa-solid fa-rotate-left me-1"></i> Reset</a>
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
            <h6 class="fw-bold text-warning mb-1">
                <?= $selected_unit ? 'DOKUMEN RESMI LAPORAN RIWAYAT PEMBAYARAN PAJAK STNK PER UNIT' : 'DOKUMEN RESMI LAPORAN BUKTI DOKUMEN NOTA SERVIS & PAJAK STNK' ?>
            </h6>
            <p class="small text-white-50 mb-0">Tahun Anggaran 2026 &bull; Tanggal Cetak: <?= format_tgl_indo(date('Y-m-d')) ?></p>
        </div>

        <?php if ($selected_unit): ?>
            <!-- SINGLE UNIT PROFILE INFORMATION CARD FOR REPORT -->
            <div class="card bg-dark border-secondary p-3 mb-4 rounded-3 text-white">
                <div class="row g-3">
                    <div class="col-md-6">
                        <span class="text-white-50 small d-block">Nama Unit Armada Kendaraan:</span>
                        <h5 class="fw-extrabold text-white mb-1"><?= htmlspecialchars($selected_unit['nama']) ?></h5>
                        <div class="fw-bold text-warning font-monospace fs-5"><?= htmlspecialchars($selected_unit['kode_plat']) ?></div>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <span class="text-white-50 small d-block">Masa Berlaku Pajak STNK Saat Ini:</span>
                        <h6 class="fw-bold text-warning mb-1"><i class="fa-solid fa-calendar-check me-1"></i> STNK: <?= format_tgl_indo($selected_unit['tgl_jatuh_tempo_pajak']) ?></h6>
                        <span class="badge bg-secondary me-1">KIR: <?= format_tgl_indo($selected_unit['tgl_jatuh_tempo_kir']) ?></span>
                        <?= get_kondisi_badge($selected_unit['kondisi']) ?>
                    </div>
                    <div class="col-md-4 border-top border-secondary pt-2">
                        <span class="text-white-50 small d-block">Merk / Tahun / BPKB:</span>
                        <strong class="text-white"><?= htmlspecialchars($selected_unit['merk'] ?: '-') ?> (<?= htmlspecialchars($selected_unit['tahun'] ?: '-') ?>) | BPKB: <?= htmlspecialchars($selected_unit['no_bpkb'] ?: '-') ?></strong>
                    </div>
                    <div class="col-md-4 border-top border-secondary pt-2">
                        <span class="text-white-50 small d-block">Nomor Rangka & Mesin:</span>
                        <strong class="text-white font-monospace"><?= htmlspecialchars($selected_unit['no_chasis'] ?: '-') ?> / <?= htmlspecialchars($selected_unit['no_mesin'] ?: '-') ?></strong>
                    </div>
                    <div class="col-md-4 border-top border-secondary pt-2 text-md-end">
                        <span class="text-white-50 small d-block">Total Riwayat Pembayaran Pajak:</span>
                        <strong class="text-warning fs-6"><?= count($data_pajak) ?> Kali Pembayaran Registered</strong>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <h6 class="fw-bold mb-3 text-white"><i class="fa-solid fa-file-invoice-dollar me-2 text-warning"></i> 1. Rekapitulasi Pembayaran Pajak STNK:</h6>
        <div class="table-responsive mb-4">
            <table class="table table-hover align-middle small">
                <thead>
                    <tr>
                        <th class="text-nowrap">No</th>
                        <th class="text-nowrap">Tgl Bayar</th>
                        <?php if (!$selected_unit): ?>
                            <th class="text-nowrap">Nopol / Kode Unit</th>
                            <th class="text-nowrap">Nama Armada</th>
                        <?php endif; ?>
                        <th class="text-nowrap">Jenis Pembayaran</th>
                        <th class="text-nowrap">Masa Berlaku Baru</th>
                        <th class="text-nowrap">Catatan / Petugas</th>
                        <th class="text-nowrap text-end">Biaya Pembayaran</th>
                        <th class="text-nowrap text-end">Prediksi Thn Depan (+10%)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($data_pajak)): ?>
                        <tr><td colspan="<?= $selected_unit ? '6' : '8' ?>" class="text-center text-muted py-4">Belum ada data pembayaran pajak STNK tercatat.</td></tr>
                    <?php else: ?>
                        <?php foreach ($data_pajak as $idx => $row): ?>
                            <tr>
                                <td class="text-white"><?= $idx + 1 ?></td>
                                <td class="text-nowrap text-white"><?= format_tgl_indo($row['tgl_bayar']) ?></td>
                                <?php if (!$selected_unit): ?>
                                    <td class="fw-bold text-white font-monospace text-nowrap"><?= htmlspecialchars($row['kode_plat']) ?></td>
                                    <td class="fw-bold text-white"><?= htmlspecialchars($row['nama']) ?></td>
                                <?php endif; ?>
                                <td class="text-nowrap"><span class="badge bg-warning text-dark fw-bold"><?= htmlspecialchars($row['jenis_pembayaran']) ?></span></td>
                                <td class="text-nowrap text-info fw-bold"><?= format_tgl_indo($row['tgl_jatuh_tempo_baru']) ?></td>
                                <td class="text-white-50 small"><?= htmlspecialchars($row['catatan'] ?: '-') ?></td>
                                <td class="fw-bold text-warning text-end text-nowrap"><?= format_rupiah_privacy($row['nominal_biaya']) ?></td>
                                <td class="fw-bold text-info text-end text-nowrap"><?= format_rupiah_privacy($row['nominal_biaya'] * 1.10) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr class="table-active fw-bold text-white">
                        <td colspan="<?= $selected_unit ? '4' : '6' ?>" class="text-end text-uppercase">Total Alokasi Biaya Pajak <?= $selected_unit ? 'Unit Ini' : 'Keseluruhan' ?>:</td>
                        <td class="text-end text-warning fs-6"><?= format_rupiah_privacy($total_biaya_pajak) ?></td>
                        <td class="text-end text-info fs-6"><?= format_rupiah_privacy($total_biaya_pajak * 1.10) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <h6 class="fw-bold mb-3 text-white"><i class="fa-solid fa-receipt me-2 text-info"></i> 2. Rekapitulasi Dokumen Bukti Nota Fisik Servis:</h6>
        <div class="table-responsive mb-4">
            <table class="table table-hover align-middle small">
                <thead>
                    <tr>
                        <th class="text-nowrap">No</th>
                        <th class="text-nowrap">Tgl Servis</th>
                        <?php if (!$selected_unit): ?>
                            <th class="text-nowrap">Nopol / Kode Unit</th>
                        <?php endif; ?>
                        <th class="text-nowrap">Bengkel & Layanan</th>
                        <th class="text-nowrap">Status Upload Bukti Nota</th>
                        <th class="text-nowrap text-end">Total Biaya Servis</th>
                        <th class="text-nowrap text-end no-print">Aksi Nota</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($data_nota)): ?>
                        <tr><td colspan="<?= $selected_unit ? '5' : '6' ?>" class="text-center text-muted py-4">Belum ada dokumen foto nota servis fisik diunggah.</td></tr>
                    <?php else: ?>
                        <?php foreach ($data_nota as $idx => $row): ?>
                            <tr>
                                <td class="text-white"><?= $idx + 1 ?></td>
                                <td class="text-nowrap text-white"><?= format_tgl_indo($row['tgl_servis']) ?></td>
                                <?php if (!$selected_unit): ?>
                                    <td class="fw-bold text-white font-monospace text-nowrap"><?= htmlspecialchars($row['kode_plat']) ?> - <?= htmlspecialchars($row['nama']) ?></td>
                                <?php endif; ?>
                                <td class="text-white"><strong class="text-white"><?= htmlspecialchars($row['nama_bengkel'] ?: '-') ?></strong><br><small class="text-white-50"><?= htmlspecialchars($row['nama_layanan']) ?></small></td>
                                <td class="text-nowrap"><span class="badge bg-success-subtle text-success border border-success"><i class="fa-solid fa-file-image me-1"></i> Foto Tersedia</span></td>
                                <td class="fw-bold text-info text-end text-nowrap"><?= format_rupiah_privacy($row['total_biaya']) ?></td>
                                <td class="text-end text-nowrap no-print">
                                    <a href="cetak_nota_servis.php?id=<?= $row['id'] ?>&autoprint=1" target="_blank" class="btn btn-xs btn-outline-info rounded-pill px-2.5 py-1">
                                        <i class="fa-solid fa-print me-1"></i> Cetak Nota
                                    </a>
                                </td>
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
                <p class="fw-bold text-white mb-5">Bendahara / Pengelola Pajak Armada</p>
                <p class="fw-bold text-decoration-underline text-white mb-0">Balai Pengelolaan Jalan Wilayah Tegal</p>
                <small class="text-white-50">Tim Keuangan & Administrasi STNK/KIR</small>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

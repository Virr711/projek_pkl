<?php
require_once __DIR__ . '/includes/header.php';

// Strict Use Case Role Guard: Only Admin, Pimpinan, & Bendahara can access Laporan
if ($current_role !== 'admin' && $current_role !== 'pimpinan' && $current_role !== 'bendahara') {
    echo "<script>alert('Akses Ditolak: Fitur Laporan khusus untuk Admin, Pimpinan, & Bendahara.'); window.location='index.php';</script>";
    exit;
}

$tipe_laporan = $_GET['tipe'] ?? 'servis';
$id_kendaraan_filter = (int)($_GET['id_kendaraan'] ?? 0);
$jenis_filter = trim($_GET['jenis'] ?? '');
$kondisi_filter = trim($_GET['kondisi'] ?? '');
$ruas_filter = trim($_GET['ruas'] ?? '');
$search = trim($_GET['search'] ?? '');

$all_units_list = $pdo->query("SELECT * FROM kendaraan_alat ORDER BY nama ASC")->fetchAll();
$ruas_list = get_lokasi_ruas_list();

// Selected unit info if single unit filter active
$selected_unit = null;
if ($id_kendaraan_filter > 0) {
    $stmt_u = $pdo->prepare("SELECT * FROM kendaraan_alat WHERE id = ?");
    $stmt_u->execute([$id_kendaraan_filter]);
    $selected_unit = $stmt_u->fetch();
}

// Fetch Data Based on 4 PRD Report Types
if ($tipe_laporan === 'data_ruas') {
    // Report Type 4: Laporan Data Ruas (Distribution across 13 Locations)
    $sql = "SELECT * FROM kendaraan_alat WHERE 1=1";
    $params = [];
    if (!empty($jenis_filter)) { $sql .= " AND jenis = ?"; $params[] = $jenis_filter; }
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
        $st = "%$search%";
        $sql .= " AND (kode_plat LIKE ? OR nama LIKE ? OR lokasi_ruas LIKE ? OR merk LIKE ? OR penanggung_jawab LIKE ?)";
        $params = array_merge($params, [$st, $st, $st, $st, $st]);
    }
    $sql .= " ORDER BY lokasi_ruas ASC, nama ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data_report = $stmt->fetchAll();
} elseif ($tipe_laporan === 'data_unit') {
    // Report Type 3: Laporan Data Unit Peralatan dan Kendaraan
    $sql = "SELECT * FROM kendaraan_alat WHERE 1=1";
    $params = [];
    if (!empty($jenis_filter)) { $sql .= " AND jenis = ?"; $params[] = $jenis_filter; }
    if (!empty($kondisi_filter)) { $sql .= " AND kondisi = ?"; $params[] = $kondisi_filter; }
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
        $st = "%$search%";
        $sql .= " AND (kode_plat LIKE ? OR nama LIKE ? OR lokasi_ruas LIKE ? OR merk LIKE ? OR penanggung_jawab LIKE ? OR no_chasis LIKE ? OR no_mesin LIKE ?)";
        $params = array_merge($params, [$st, $st, $st, $st, $st, $st, $st]);
    }
    $sql .= " ORDER BY jenis ASC, nama ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data_report = $stmt->fetchAll();
} elseif ($tipe_laporan === 'nota_pajak') {
    // Report Type 2: Laporan Bukti Nota Servis dan Pajak/KIR
    $sql_s = "SELECT r.tgl_servis as tgl, k.nama, k.kode_plat, k.lokasi_ruas, 'Servis' as jenis_dokumen, r.total_biaya as nominal, r.foto_nota as foto_bukti, r.nama_bengkel as keterangan, r.id as ref_id FROM riwayat_servis r JOIN kendaraan_alat k ON r.id_kendaraan = k.id WHERE r.foto_nota IS NOT NULL AND r.foto_nota != ''";
    $params_s = [];
    if ($id_kendaraan_filter > 0) { $sql_s .= " AND r.id_kendaraan = ?"; $params_s[] = $id_kendaraan_filter; }
    if (!empty($search)) {
        $st = "%$search%";
        $sql_s .= " AND (k.kode_plat LIKE ? OR k.nama LIKE ? OR r.nama_bengkel LIKE ? OR r.nama_layanan LIKE ?)";
        $params_s = array_merge($params_s, [$st, $st, $st, $st]);
    }
    $stmt_s = $pdo->prepare($sql_s);
    $stmt_s->execute($params_s);
    $servis_notes = $stmt_s->fetchAll();

    $sql_p = "SELECT p.tgl_bayar as tgl, k.nama, k.kode_plat, k.lokasi_ruas, p.jenis_pembayaran as jenis_dokumen, p.nominal_biaya as nominal, p.foto_bukti, p.catatan as keterangan, p.id as ref_id FROM pembayaran_pajak_kir p JOIN kendaraan_alat k ON p.id_kendaraan = k.id WHERE p.foto_bukti IS NOT NULL AND p.foto_bukti != ''";
    $params_p = [];
    if ($id_kendaraan_filter > 0) { $sql_p .= " AND p.id_kendaraan = ?"; $params_p[] = $id_kendaraan_filter; }
    if (!empty($search)) {
        $st = "%$search%";
        $sql_p .= " AND (k.kode_plat LIKE ? OR k.nama LIKE ? OR p.jenis_pembayaran LIKE ? OR p.catatan LIKE ?)";
        $params_p = array_merge($params_p, [$st, $st, $st, $st]);
    }
    $stmt_p = $pdo->prepare($sql_p);
    $stmt_p->execute($params_p);
    $pajak_notes = $stmt_p->fetchAll();

    $data_report = array_merge($servis_notes, $pajak_notes);
    usort($data_report, function($a, $b) {
        return strtotime($b['tgl']) - strtotime($a['tgl']);
    });
} else {
    // Report Type 1: Laporan Servis (Rekapitulasi Pengerjaan & Status Servis)
    $sql = "SELECT r.*, k.nama, k.kode_plat, k.lokasi_ruas, k.merk, k.tahun, k.jenis, k.kategori FROM riwayat_servis r JOIN kendaraan_alat k ON r.id_kendaraan = k.id WHERE 1=1";
    $params = [];
    if ($id_kendaraan_filter > 0) { $sql .= " AND r.id_kendaraan = ?"; $params[] = $id_kendaraan_filter; }
    if (!empty($search)) {
        $st = "%$search%";
        $sql .= " AND (k.kode_plat LIKE ? OR k.nama LIKE ? OR k.lokasi_ruas LIKE ? OR k.merk LIKE ? OR r.nama_bengkel LIKE ? OR r.nama_layanan LIKE ? OR r.rincian_item LIKE ?)";
        $params = array_merge($params, [$st, $st, $st, $st, $st, $st, $st]);
    }
    $sql .= " ORDER BY r.tgl_servis DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data_report = $stmt->fetchAll();
}
?>

<div class="container-fluid p-0">
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
        <div>
            <h4 class="fw-bold mb-1 text-white"><i class="fa-solid fa-file-contract text-success me-2"></i> Laporan Resmi ALISA</h4>
            <p class="text-white-50 small mb-0">Modul cetak Laporan Pemeliharaan Servis, Pajak/KIR, Data Unit & 13 Lokasi BPJ Wilayah Tegal.</p>
        </div>
        <?php if ($current_role === 'admin' || $current_role === 'pimpinan'): ?>
            <div class="d-flex gap-2">
                <a href="export_docx.php?<?= htmlspecialchars($_SERVER['QUERY_STRING'] ?? '') ?>" class="btn btn-primary rounded-pill px-4 shadow" style="background-color: #0d9488 !important; border: none;">
                    <i class="fa-solid fa-file-word me-1.5"></i> Export Word (.docx)
                </a>
                <button onclick="window.print()" class="btn btn-primary rounded-pill px-4 shadow" style="background-color: #0284c7 !important; border: none;">
                    <i class="fa-solid fa-print me-1.5"></i> Cetak Laporan (PDF)
                </button>
            </div>
        <?php endif; ?>
    </div>

    <!-- PPDB-Style Interactive Sub-Menu Card Grid for Laporan (2x2 Balanced Layout) -->
    <div class="ppdb-submenu-grid-2x2 mb-4">
        <!-- Submenu 1: Data Ruas -->
        <a href="laporan_ruas.php" class="ppdb-card" style="background-image: url('assets/images/card_ruas.jpg?v=<?= time() ?>');">
            <div class="ppdb-card-overlay"></div>
            <div class="ppdb-card-content">
                <span class="ppdb-card-badge" style="background-color: #0284c7;"><i class="fa-solid fa-map-location-dot me-1"></i> Data Ruas</span>
                <div class="ppdb-card-body-text">
                    <h5 class="ppdb-card-title">Laporan Data 13 Lokasi</h5>
                    <p class="ppdb-card-desc">Distribusi Armada & Peralatan di 13 Ruas Jalan BPJ Tegal.</p>
                </div>
                <div class="ppdb-card-footer">
                    <span class="ppdb-card-meta">13 Ruas Jalan</span>
                    <span class="ppdb-card-action">Buka Halaman Data Ruas <i class="fa-solid fa-arrow-right"></i></span>
                </div>
            </div>
        </a>

        <!-- Submenu 2: Data Unit -->
        <a href="laporan_unit.php" class="ppdb-card" style="background-image: url('assets/images/card_unit.jpg?v=<?= time() ?>');">
            <div class="ppdb-card-overlay"></div>
            <div class="ppdb-card-content">
                <span class="ppdb-card-badge" style="background-color: #0d9488;"><i class="fa-solid fa-truck-monster me-1"></i> Data Unit</span>
                <div class="ppdb-card-body-text">
                    <h5 class="ppdb-card-title">Laporan Data Unit Aset</h5>
                    <p class="ppdb-card-desc">Inventarisasi Lengkap Kendaraan, Peralatan & Alat Berat BPJ.</p>
                </div>
                <div class="ppdb-card-footer">
                    <span class="ppdb-card-meta" style="color: #2dd4bf !important;">Aset Logistik</span>
                    <span class="ppdb-card-action" style="color: #2dd4bf !important;">Buka Halaman Data Unit <i class="fa-solid fa-arrow-right"></i></span>
                </div>
            </div>
        </a>

        <!-- Submenu 3: Servis -->
        <a href="laporan_servis.php" class="ppdb-card" style="background-image: url('assets/images/card_servis.jpg?v=<?= time() ?>');">
            <div class="ppdb-card-overlay"></div>
            <div class="ppdb-card-content">
                <span class="ppdb-card-badge" style="background-color: #10b981;"><i class="fa-solid fa-wrench me-1"></i> Servis</span>
                <div class="ppdb-card-body-text">
                    <h5 class="ppdb-card-title">Laporan Servis Armada</h5>
                    <p class="ppdb-card-desc">Rekapitulasi Pemeliharaan Servis Penjadwalan & Darurat.</p>
                </div>
                <div class="ppdb-card-footer">
                    <span class="ppdb-card-meta" style="color: #34d399 !important;">Rekap Servis</span>
                    <span class="ppdb-card-action" style="color: #34d399 !important;">Buka Halaman Servis <i class="fa-solid fa-arrow-right"></i></span>
                </div>
            </div>
        </a>

        <!-- Submenu 4: Nota & Pajak -->
        <a href="laporan_nota_pajak.php" class="ppdb-card" style="background-image: url('assets/images/card_nota_pajak.jpg?v=<?= time() ?>');">
            <div class="ppdb-card-overlay"></div>
            <div class="ppdb-card-content">
                <span class="ppdb-card-badge" style="background-color: #d97706;"><i class="fa-solid fa-receipt me-1"></i> Nota & Pajak</span>
                <div class="ppdb-card-body-text">
                    <h5 class="ppdb-card-title">Laporan Nota & Pajak</h5>
                    <p class="ppdb-card-desc">Dokumen Bukti Nota Fisik Servis & Resi Pajak.</p>
                </div>
                <div class="ppdb-card-footer">
                    <span class="ppdb-card-meta" style="color: #fbbf24 !important;">Dokumen Fisik</span>
                    <span class="ppdb-card-action" style="color: #fbbf24 !important;">Buka Halaman Nota & Pajak <i class="fa-solid fa-arrow-right"></i></span>
                </div>
            </div>
        </a>
    </div>

    <!-- 4 PRD Report Type Filter Pills -->
    <div class="card-custom p-3 mb-3">
        <div class="nav nav-pills gap-2 flex-wrap">
            <a href="laporan.php?tipe=servis" class="nav-link rounded-pill px-3 py-2 <?= ($tipe_laporan === 'servis') ? 'active bg-primary' : 'bg-dark text-white border border-secondary' ?>">
                <i class="fa-solid fa-wrench me-1"></i> 1. Laporan Servis
            </a>
            <a href="laporan.php?tipe=nota_pajak" class="nav-link rounded-pill px-3 py-2 <?= ($tipe_laporan === 'nota_pajak') ? 'active bg-primary' : 'bg-dark text-white border border-secondary' ?>">
                <i class="fa-solid fa-receipt me-1"></i> 2. Laporan Bukti Nota & Pajak
            </a>
            <a href="laporan.php?tipe=data_unit" class="nav-link rounded-pill px-3 py-2 <?= ($tipe_laporan === 'data_unit') ? 'active bg-primary' : 'bg-dark text-white border border-secondary' ?>">
                <i class="fa-solid fa-truck-monster me-1"></i> 3. Laporan Data Unit (Kendaraan & Peralatan)
            </a>
            <a href="laporan.php?tipe=data_ruas" class="nav-link rounded-pill px-3 py-2 <?= ($tipe_laporan === 'data_ruas') ? 'active bg-primary' : 'bg-dark text-white border border-secondary' ?>">
                <i class="fa-solid fa-map-location-dot me-1"></i> 4. Laporan Data Ruas (13 Lokasi)
            </a>
        </div>
    </div>

    <!-- DYNAMIC INTERACTIVE FILTER & SEARCH BAR BASED ON SELECTED REPORT TYPE -->
    <div class="card-custom p-3 mb-4 no-print">
        <form method="GET" action="" class="row g-2 align-items-center">
            <input type="hidden" name="tipe" value="<?= htmlspecialchars($tipe_laporan) ?>">

            <?php if ($tipe_laporan === 'servis' || $tipe_laporan === 'nota_pajak'): ?>
                <div class="col-md-4">
                    <label class="form-label fw-bold text-white-50 small mb-1"><i class="fa-solid fa-magnifying-glass me-1"></i> Pencarian Kata Kunci / Nopol:</label>
                    <div class="input-group">
                        <input type="text" name="search" class="form-control bg-dark text-white border-secondary" placeholder="Cari Nopol, Nama, Bengkel..." value="<?= htmlspecialchars($search) ?>">
                        <button type="submit" class="btn btn-outline-secondary text-white"><i class="fa-solid fa-search"></i></button>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold text-white-50 small mb-1"><i class="fa-solid fa-filter me-1"></i> Filter Unit Armada / Peralatan:</label>
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
                        <a href="laporan.php?tipe=<?= $tipe_laporan ?>" class="btn btn-outline-secondary w-100 rounded-pill mb-1"><i class="fa-solid fa-rotate-left me-1"></i> Reset</a>
                    <?php endif; ?>
                </div>

            <?php elseif ($tipe_laporan === 'data_unit'): ?>
                <div class="col-md-3">
                    <label class="form-label fw-bold text-white-50 small mb-1"><i class="fa-solid fa-magnifying-glass me-1"></i> Pencarian Kata Kunci:</label>
                    <div class="input-group">
                        <input type="text" name="search" class="form-control bg-dark text-white border-secondary" placeholder="Cari Nopol, Merk..." value="<?= htmlspecialchars($search) ?>">
                        <button type="submit" class="btn btn-outline-secondary text-white"><i class="fa-solid fa-search"></i></button>
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold text-white-50 small mb-1"><i class="fa-solid fa-truck me-1"></i> Klasifikasi Unit:</label>
                    <select name="jenis" class="form-select bg-dark text-white border-secondary" onchange="this.form.submit()">
                        <option value="">-- Semua Jenis Unit --</option>
                        <option value="kendaraan_roda_6" <?= ($jenis_filter === 'kendaraan_roda_6') ? 'selected' : '' ?>>Roda 6 (Dump Truck)</option>
                        <option value="kendaraan_roda_4" <?= ($jenis_filter === 'kendaraan_roda_4') ? 'selected' : '' ?>>Roda 4 (Pickup/Mobil)</option>
                        <option value="kendaraan_roda_3" <?= ($jenis_filter === 'kendaraan_roda_3') ? 'selected' : '' ?>>Roda 3 (VIAR Work 200)</option>
                        <option value="kendaraan_roda_2" <?= ($jenis_filter === 'kendaraan_roda_2') ? 'selected' : '' ?>>Roda 2</option>
                        <option value="peralatan" <?= ($jenis_filter === 'peralatan') ? 'selected' : '' ?>>Peralatan / Alat Berat</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold text-white-50 small mb-1"><i class="fa-solid fa-heart-pulse me-1"></i> Kondisi Fisik:</label>
                    <select name="kondisi" class="form-select bg-dark text-white border-secondary" onchange="this.form.submit()">
                        <option value="">-- Semua Kondisi --</option>
                        <option value="B" <?= ($kondisi_filter === 'B') ? 'selected' : '' ?>>Baik (B)</option>
                        <option value="RR" <?= ($kondisi_filter === 'RR') ? 'selected' : '' ?>>Rusak Ringan (RR)</option>
                        <option value="RB" <?= ($kondisi_filter === 'RB') ? 'selected' : '' ?>>Rusak Berat (RB)</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold text-white-50 small mb-1"><i class="fa-solid fa-location-dot me-1"></i> Posisi Ruas:</label>
                    <select name="ruas" class="form-select bg-dark text-white border-secondary" onchange="this.form.submit()">
                        <option value="">-- Semua 13 Lokasi Ruas --</option>
                        <?php foreach ($ruas_list as $r): ?>
                            <option value="<?= htmlspecialchars($r) ?>" <?= ($ruas_filter === $r) ? 'selected' : '' ?>><?= htmlspecialchars($r) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-1 d-flex align-items-end" style="height: 60px;">
                    <?php if (!empty($jenis_filter) || !empty($kondisi_filter) || !empty($ruas_filter) || !empty($search)): ?>
                        <a href="laporan.php?tipe=data_unit" class="btn btn-outline-secondary w-100 rounded-pill mb-1 p-1 text-center" title="Reset Filter"><i class="fa-solid fa-rotate-left"></i></a>
                    <?php endif; ?>
                </div>

            <?php elseif ($tipe_laporan === 'data_ruas'): ?>
                <div class="col-md-3">
                    <label class="form-label fw-bold text-white-50 small mb-1"><i class="fa-solid fa-magnifying-glass me-1"></i> Pencarian Kata Kunci:</label>
                    <div class="input-group">
                        <input type="text" name="search" class="form-control bg-dark text-white border-secondary" placeholder="Cari Nopol, Nama..." value="<?= htmlspecialchars($search) ?>">
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
                <div class="col-md-4">
                    <label class="form-label fw-bold text-white-50 small mb-1"><i class="fa-solid fa-location-dot me-1"></i> Filter Lokasi Ruas Jalan:</label>
                    <select name="ruas" class="form-select bg-dark text-white border-secondary" onchange="this.form.submit()">
                        <option value="">-- Semua 13 Lokasi Ruas Jalan --</option>
                        <?php foreach ($ruas_list as $r): ?>
                            <option value="<?= htmlspecialchars($r) ?>" <?= ($ruas_filter === $r) ? 'selected' : '' ?>><?= htmlspecialchars($r) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-1 d-flex align-items-end" style="height: 60px;">
                    <?php if (!empty($jenis_filter) || !empty($ruas_filter) || !empty($search)): ?>
                        <a href="laporan.php?tipe=data_ruas" class="btn btn-outline-secondary w-100 rounded-pill mb-1 p-1 text-center" title="Reset Filter"><i class="fa-solid fa-rotate-left"></i></a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </form>
    </div>

    <!-- Printable Report Table Area (Official Balai Format with Logo Jateng) -->
    <div class="card-custom p-4" id="printableArea">
        <div class="text-center mb-4 pb-3 border-bottom border-secondary">
            <img src="assets/images/logo_jateng.png?v=<?= time() ?>" alt="Logo Jawa Tengah" style="height: 64px; width: auto; margin-bottom: 12px;">
            <h4 class="fw-extrabold text-white mb-1" style="letter-spacing: 1px;">PEMERINTAH PROVINSI JAWA TENGAH</h4>
            <h5 class="fw-extrabold text-white mb-1">BALAI PENGELOLAAN JALAN WILAYAH TEGAL</h5>
            <h6 class="fw-bold text-success mb-1">
                <?= $selected_unit ? 'DOKUMEN REKAP LAPORAN RESMI KHUSUS UNIT: ' . htmlspecialchars($selected_unit['kode_plat']) : 'DOKUMEN REKAP LAPORAN RESMI LOGISTIK & PEMELIHARAAN ARMADA (ALISA)' ?>
            </h6>
            <p class="small text-white-50 mb-0">Tahun Anggaran 2026 &bull; Tanggal Cetak: <?= format_tgl_indo(date('Y-m-d')) ?></p>
        </div>

        <?php if ($selected_unit && ($tipe_laporan === 'servis' || $tipe_laporan === 'nota_pajak')): ?>
            <!-- SINGLE UNIT PROFILE CARD IN LAPORAN -->
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
                </div>
            </div>
        <?php endif; ?>

        <?php if ($tipe_laporan === 'data_ruas'): ?>
            <!-- 4. Laporan Data Ruas -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold text-white mb-0">4. Laporan Distribusi Unit Armada & Peralatan Berdasarkan 13 Lokasi BPJ Tegal:</h6>
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
                            <tr><td colspan="7" class="text-center text-muted py-4">Tidak ditemukan data unit armada sesuai filter ruas/jenis/pencarian yang dipilih.</td></tr>
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

        <?php elseif ($tipe_laporan === 'data_unit'): ?>
            <!-- 3. Laporan Data Unit Peralatan dan Kendaraan -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold text-white mb-0">3. Laporan Data Unit Peralatan dan Kendaraan (Aset Logistik BPJ Tegal):</h6>
                <span class="badge bg-info text-white font-monospace">Total: <?= count($data_report) ?> Unit Ditemukan</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle small">
                    <thead>
                        <tr>
                            <th class="text-nowrap">No</th>
                            <th class="text-nowrap">Jenis Aset</th>
                            <th class="text-nowrap">Nopol / Kode Unit</th>
                            <th class="text-nowrap">Nama Unit</th>
                            <th class="text-nowrap">Merk / Type</th>
                            <th class="text-nowrap">No. Rangka / Mesin</th>
                            <th class="text-nowrap">Kondisi</th>
                            <th class="text-nowrap">Lokasi Ruas Jalan</th>
                            <th class="text-nowrap">Jadwal Servis Next</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($data_report)): ?>
                            <tr><td colspan="9" class="text-center text-muted py-4">Tidak ditemukan data unit aset sesuai filter klasifikasi/kondisi/pencarian yang dipilih.</td></tr>
                        <?php else: ?>
                            <?php foreach ($data_report as $idx => $row): ?>
                                <tr>
                                    <td class="text-white"><?= $idx + 1 ?></td>
                                    <td class="text-nowrap"><span class="badge bg-dark border border-secondary text-white"><?= ($row['jenis'] === 'peralatan') ? 'Peralatan / Alat Berat' : 'Kendaraan' ?></span></td>
                                    <td class="fw-bold text-white font-monospace text-nowrap"><?= htmlspecialchars($row['kode_plat']) ?></td>
                                    <td class="fw-bold text-white"><?= htmlspecialchars($row['nama']) ?></td>
                                    <td class="text-nowrap text-white"><?= htmlspecialchars($row['merk'] ?: '-') ?> (<?= htmlspecialchars($row['tahun'] ?: '-') ?>)</td>
                                    <td class="font-monospace text-nowrap text-white"><?= htmlspecialchars($row['no_chasis'] ?: '-') ?> / <?= htmlspecialchars($row['no_mesin'] ?: '-') ?></td>
                                    <td><?= get_kondisi_badge($row['kondisi']) ?></td>
                                    <td class="text-nowrap text-white"><?= htmlspecialchars($row['lokasi_ruas']) ?></td>
                                    <td class="fw-bold text-info text-nowrap"><?= format_tgl_indo($row['tgl_servis_berikutnya']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($tipe_laporan === 'nota_pajak'): ?>
            <!-- 2. Laporan Bukti Nota Servis dan Pajak -->
            <h6 class="fw-bold mb-3 text-white">2. Laporan Bukti Nota Fisik Servis dan Dokumen Pajak:</h6>
            <div class="table-responsive">
                <table class="table table-hover align-middle small">
                    <thead>
                        <tr>
                            <th class="text-nowrap">No</th>
                            <th class="text-nowrap">Tanggal Transaksi</th>
                            <th class="text-nowrap">Nopol / Unit Armada</th>
                            <th class="text-nowrap">Jenis Dokumen</th>
                            <th class="text-nowrap">Nominal Biaya</th>
                            <th class="text-nowrap">File Bukti Nota / Resi</th>
                            <th class="text-nowrap">Keterangan Bengkel / Pajak</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($data_report)): ?>
                            <tr><td colspan="7" class="text-center text-muted py-3">Belum ada dokumen bukti nota / pajak fisik diunggah sesuai kriteria pencarian.</td></tr>
                        <?php else: ?>
                            <?php foreach ($data_report as $idx => $row): ?>
                                <tr>
                                    <td class="text-white"><?= $idx + 1 ?></td>
                                    <td class="text-nowrap text-white"><?= format_tgl_indo($row['tgl']) ?></td>
                                    <td class="fw-bold text-white"><?= htmlspecialchars($row['kode_plat']) ?> - <?= htmlspecialchars($row['nama']) ?></td>
                                    <td><span class="badge bg-info text-white"><?= htmlspecialchars($row['jenis_dokumen']) ?></span></td>
                                    <td class="fw-bold text-success text-nowrap"><?= format_rupiah_privacy($row['nominal']) ?></td>
                                    <td class="text-nowrap">
                                        <?php if (!empty($row['foto_bukti'])): ?>
                                            <span class="badge bg-success-subtle text-success border border-success"><i class="fa-solid fa-file-image me-1"></i> Ter-Upload</span>
                                        <?php else: ?>
                                            <span class="text-white-50">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-white"><?= htmlspecialchars($row['keterangan'] ?: '-') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        <?php else: ?>
            <!-- 1. Laporan Servis -->
            <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                <h6 class="fw-bold text-white m-0">1. Laporan Servis (Rekapitulasi Pengerjaan & Pemeliharaan Rutin / Darurat):</h6>
                <a href="laporan_servis.php" class="btn btn-sm btn-outline-info rounded-pill px-3 no-print">
                    <i class="fa-solid fa-filter me-1"></i> Buka Submenu Laporan Servis Khusus Per Unit
                </a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle small">
                    <thead>
                        <tr>
                            <th class="text-nowrap">No</th>
                            <th class="text-nowrap">Tgl Servis</th>
                            <th class="text-nowrap">Nopol / Unit</th>
                            <th class="text-nowrap">Kategori Servis</th>
                            <th class="text-nowrap">Bengkel & Layanan</th>
                            <th class="text-nowrap">Rincian Item Subtotal</th>
                            <th class="text-nowrap">Total Biaya</th>
                            <?php if ($current_role === 'admin' || $current_role === 'pimpinan'): ?>
                                <th class="text-nowrap text-end no-print">Cetak Nota</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($data_report)): ?>
                            <tr><td colspan="8" class="text-center text-muted py-4">Belum ada riwayat transaksi servis recorded sesuai kriteria pencarian.</td></tr>
                        <?php else: ?>
                            <?php $no = 1; foreach ($data_report as $row): ?>
                                <tr>
                                    <td class="text-center text-white-50 small"><?= $no++ ?></td>
                                    <td class="text-nowrap text-white-50 font-monospace small"><?= format_tgl_indo($row['tgl_servis']) ?></td>
                                    <td class="fw-bold text-white"><?= htmlspecialchars($row['kode_plat']) ?><br><small class="text-white-50"><?= htmlspecialchars($row['nama']) ?></small></td>
                                    <td>
                                        <?php if (strcasecmp($row['jenis_servis'], 'Darurat') === 0): ?>
                                            <span class="badge bg-danger text-white">Darurat</span>
                                        <?php else: ?>
                                            <span class="badge bg-success text-white">Penjadwalan</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-white"><strong class="text-white"><?= htmlspecialchars($row['nama_bengkel'] ?: '-') ?></strong><br><small class="text-white-50"><?= htmlspecialchars($row['nama_layanan']) ?></small></td>
                                    <td class="text-white"><?= nl2br(htmlspecialchars($row['rincian_item'] ?: '-')) ?></td>
                                    <td class="fw-bold text-success text-nowrap"><?= format_rupiah_privacy($row['total_biaya']) ?></td>
                                    <?php if ($current_role === 'admin' || $current_role === 'pimpinan'): ?>
                                        <td class="text-end text-nowrap no-print">
                                            <a href="cetak_nota_servis.php?id=<?= $row['id'] ?>&autoprint=1" target="_blank" class="btn btn-xs btn-outline-info rounded-pill px-2.5 py-1">
                                                <i class="fa-solid fa-print me-1"></i> Cetak Nota
                                            </a>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

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

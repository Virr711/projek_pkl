<?php
require_once __DIR__ . '/includes/header.php';

$search = trim($_GET['search'] ?? '');

// Filter Query Construction for Kendaraan Only
$sql = "SELECT * FROM kendaraan_alat WHERE jenis IN ('kendaraan_roda_4', 'kendaraan_roda_6') AND kondisi != 'RB'";
$params = [];

if (!empty($search)) {
    $search_term = trim($search);
    $search_upper = strtoupper($search_term);
    $search_lower = strtolower($search_term);

    $search_fields = [
        "kode_plat", "nama", "penanggung_jawab", "lokasi_ruas", 
        "no_chasis", "no_mesin", "merk", "kategori", "catatan", 
        "kup_reg", "kondisi"
    ];
    
    $search_clauses = [];
    foreach ($search_fields as $f) {
        $search_clauses[] = "$f LIKE ?";
        $params[] = "%$search_term%";
    }

    if ($search_upper === 'RR' || strpos($search_lower, 'rusak ringan') !== false || $search_lower === 'ringan') {
        $search_clauses[] = "kondisi = 'RR'";
    } elseif ($search_upper === 'RB' || strpos($search_lower, 'rusak berat') !== false || $search_lower === 'berat') {
        $search_clauses[] = "kondisi = 'RB'";
    } elseif ($search_upper === 'B' || $search_lower === 'baik') {
        $search_clauses[] = "kondisi = 'B'";
    }

    $sql .= " AND (" . implode(" OR ", $search_clauses) . ")";
}

$sql .= " ORDER BY tgl_servis_berikutnya ASC, nama ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$units = $stmt->fetchAll();

$cnt_knd = count($units);
?>

<div class="container-fluid p-0">
    <!-- Distinct Header with Breadcrumb & Back Button -->
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="data_unit.php" class="text-info text-decoration-none"><i class="fa-solid fa-truck-monster me-1"></i> Data Unit</a></li>
                    <li class="breadcrumb-item active text-white" aria-current="page">Submenu Kendaraan</li>
                </ol>
            </nav>
            <h4 class="fw-bold mb-0 text-white"><i class="fa-solid fa-truck-pickup text-info me-2"></i> Data Unit Kendaraan (Dump Truck, Patroli & Dinas)</h4>
        </div>
        <div class="d-flex gap-2">
            <a href="data_unit.php" class="btn btn-outline-secondary text-white rounded-pill px-3">
                <i class="fa-solid fa-arrow-left me-1"></i> Kembali ke Semua Unit
            </a>
            <?php if (can_edit_data()): ?>
                <a href="data_unit_form.php" class="btn btn-bpj-primary shadow">
                    <i class="fa-solid fa-plus me-2"></i> Tambah Unit Baru
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Category Filter Bar -->
    <div class="card-custom p-3 mb-4">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-primary rounded-pill px-3 py-2 fs-6"><i class="fa-solid fa-truck-pickup me-1"></i> Halaman Khusus Kendaraan (Total: <?= $cnt_knd ?> Unit)</span>
            </div>

            <form method="GET" action="" class="d-flex gap-2" style="max-width: 300px;">
                <input type="text" name="search" class="form-control form-control-sm rounded-pill px-3 text-white bg-dark border-secondary" placeholder="Cari Nopol, Nama..." value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="btn btn-sm btn-outline-secondary rounded-pill px-3 text-white"><i class="fa-solid fa-search"></i></button>
            </form>
        </div>
    </div>

    <!-- Data Table Kendaraan -->
    <div class="card-custom p-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="text-nowrap" style="width: 12%;">Nopol / Kode</th>
                        <th class="text-nowrap" style="width: 22%;">Nama Unit & Merk</th>
                        <th class="text-nowrap" style="width: 14%;">Jenis Unit</th>
                        <th class="text-nowrap" style="width: 16%;">No. Rangka / Mesin</th>
                        <th class="text-nowrap" style="width: 9%;">Kondisi</th>
                        <th class="text-nowrap" style="width: 15%;">Lokasi Ruas Jalan</th>
                        <th class="text-nowrap" style="width: 14%;">Jadwal Servis / Pajak</th>
                        <th class="text-nowrap text-end" style="width: 10%;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($units)): ?>
                        <tr><td colspan="8" class="text-center text-muted py-5">Tidak ditemukan data kendaraan operasional.</td></tr>
                    <?php else: ?>
                        <?php foreach ($units as $u): 
                            $info_s = get_alisa_schedule_info($u['tgl_servis_berikutnya'], 'Servis', $u['kondisi'], $u['jenis']);
                            $info_p = get_alisa_schedule_info($u['tgl_jatuh_tempo_pajak'], 'Pajak STNK', $u['kondisi'], $u['jenis']);
                            $riwayat_p = get_riwayat_plat($pdo, $u['id']);
                        ?>
                            <tr>
                                <td class="fw-bold text-white font-monospace text-nowrap">
                                    <?= htmlspecialchars($u['kode_plat']) ?>
                                    <?php if (!empty($riwayat_p)): ?>
                                        <div>
                                            <button type="button" class="btn btn-xs btn-outline-info rounded-pill px-2 py-0 mt-1" style="font-size: 0.7rem;" onclick="viewRiwayatPlat(<?= $u['id'] ?>, '<?= htmlspecialchars(addslashes($u['nama'])) ?>')">
                                                <i class="fa-solid fa-clock-rotate-left"></i> Plat Lama (<?= count($riwayat_p) ?>)
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="fw-bold text-white"><?= htmlspecialchars($u['nama']) ?></div>
                                    <span class="text-white-50 small"><?= htmlspecialchars($u['merk'] ?: '-') ?> (<?= htmlspecialchars($u['tahun'] ?: '-') ?>)</span>
                                </td>
                                <td class="text-nowrap">
                                    <span class="badge bg-primary text-white rounded-pill px-2.5 py-1"><i class="fa-solid fa-truck-pickup me-1"></i> Kendaraan</span>
                                </td>
                                <td class="small font-monospace text-nowrap text-white" style="font-size: 0.8rem;">
                                    <div>Rangka: <?= htmlspecialchars($u['no_chasis'] ?: '-') ?></div>
                                    <div>Mesin: <?= htmlspecialchars($u['no_mesin'] ?: '-') ?></div>
                                </td>
                                <td class="text-nowrap"><?= get_kondisi_badge($u['kondisi']) ?></td>
                                <td class="small fw-semibold text-info text-nowrap"><i class="fa-solid fa-location-dot me-1"></i> <?= htmlspecialchars($u['lokasi_ruas']) ?></td>
                                <td class="text-nowrap">
                                    <span class="badge <?= $info_s['badge_class'] ?> rounded-pill mb-1"><?= $info_s['label'] ?></span><br>
                                    <span class="badge <?= $info_p['badge_class'] ?> rounded-pill">Pajak: <?= $info_p['label'] ?></span>
                                </td>
                                <td class="text-end text-nowrap">
                                    <?php if (can_edit_data()): ?>
                                        <a href="data_unit_form.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-outline-primary rounded-pill px-2.5"><i class="fa-solid fa-pen-to-square me-1"></i> Edit</a>
                                        <a href="data_unit_form.php?id=<?= $u['id'] ?>&action=delete" onclick="return confirm('Hapus unit ini?')" class="btn btn-sm btn-outline-danger rounded-pill px-2"><i class="fa-solid fa-trash"></i></a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

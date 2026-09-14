<?php
require_once __DIR__ . '/includes/header.php';

$tab = $_GET['tab'] ?? 'semua';
$search = trim($_GET['search'] ?? '');

// Filter Query Construction
$sql = "SELECT * FROM kendaraan_alat WHERE 1=1";
$params = [];

if ($tab === 'roda6') {
    $sql .= " AND jenis = 'kendaraan_roda_6' AND kondisi != 'RB'";
} elseif ($tab === 'roda4') {
    $sql .= " AND jenis = 'kendaraan_roda_4' AND kondisi != 'RB'";
} elseif ($tab === 'roda3' || $tab === 'viar') {
    $sql .= " AND jenis = 'kendaraan_roda_3' AND kondisi != 'RB'";
} elseif ($tab === 'roda2') {
    $sql .= " AND jenis = 'kendaraan_roda_2' AND kondisi != 'RB'";
} elseif ($tab === 'peralatan') {
    $sql .= " AND jenis = 'peralatan' AND kondisi != 'RB'";
} elseif ($tab === 'rb') {
    $sql .= " AND kondisi = 'RB'";
}

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

// Order: RB Units ALWAYS placed at the very bottom!
$sql .= " ORDER BY CASE WHEN kondisi = 'RB' THEN 2 ELSE 1 END ASC, tgl_servis_berikutnya ASC, nama ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$units = $stmt->fetchAll();

// Count Badge Totals
$cnt_total = $pdo->query("SELECT COUNT(*) FROM kendaraan_alat")->fetchColumn();
$cnt_r6 = $pdo->query("SELECT COUNT(*) FROM kendaraan_alat WHERE jenis = 'kendaraan_roda_6' AND kondisi != 'RB'")->fetchColumn();
$cnt_r4 = $pdo->query("SELECT COUNT(*) FROM kendaraan_alat WHERE jenis = 'kendaraan_roda_4' AND kondisi != 'RB'")->fetchColumn();
$cnt_r3 = $pdo->query("SELECT COUNT(*) FROM kendaraan_alat WHERE jenis = 'kendaraan_roda_3' AND kondisi != 'RB'")->fetchColumn();
$cnt_r2 = $pdo->query("SELECT COUNT(*) FROM kendaraan_alat WHERE jenis = 'kendaraan_roda_2' AND kondisi != 'RB'")->fetchColumn();
$cnt_alat = $pdo->query("SELECT COUNT(*) FROM kendaraan_alat WHERE jenis = 'peralatan' AND kondisi != 'RB'")->fetchColumn();
$cnt_rb = $pdo->query("SELECT COUNT(*) FROM kendaraan_alat WHERE kondisi = 'RB'")->fetchColumn();
$cnt_knd = $cnt_r6 + $cnt_r4 + $cnt_r3 + $cnt_r2;

// Fetch all equipment for modal dropdown
$peralatan_units = $pdo->query("SELECT * FROM kendaraan_alat WHERE jenis = 'peralatan' AND kondisi != 'RB' ORDER BY nama ASC")->fetchAll();
?>

<div class="container-fluid p-0">
    <!-- Flash Notifications -->
    <?php if (isset($_SESSION['flash_success'])): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-3 mb-4 shadow" role="alert">
            <i class="fa-solid fa-circle-check me-2 fs-5"></i> <?= htmlspecialchars($_SESSION['flash_success']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['flash_error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-3 mb-4 shadow" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-2 fs-5"></i> <?= htmlspecialchars($_SESSION['flash_error']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>

    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
        <div>
            <h4 class="fw-bold mb-1 text-white"><i class="fa-solid fa-truck-monster text-info me-2"></i> Kelola Data Unit</h4>
            <p class="text-white-50 small mb-0">Inventarisasi armada (Servis 6 Bulan) & peralatan (Target 1.000 Jam Operasional) BPJ Wilayah Tegal.</p>
        </div>
        <div class="d-flex gap-2">
            <?php if (can_edit_data()): ?>
                <button type="button" class="btn btn-success rounded-pill px-3 shadow" onclick="openModalInputJam(0)">
                    <i class="fa-solid fa-clock-rotate-left me-1"></i> + Input Pemakaian Jam Kerja Alat
                </button>
                <a href="data_unit_form.php" class="btn btn-bpj-primary shadow">
                    <i class="fa-solid fa-plus me-2"></i> Tambah Unit Baru
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- PPDB-Style Interactive Sub-Menu Card Grid (2x2 Balanced Layout) -->
    <div class="ppdb-submenu-grid-2x2 mb-4">
        <!-- Submenu 1: Kendaraan -->
        <a href="data_unit_kendaraan.php" class="ppdb-card" style="background-image: url('assets/images/card_kendaraan.jpg?v=<?= time() ?>');">
            <div class="ppdb-card-overlay"></div>
            <div class="ppdb-card-content">
                <span class="ppdb-card-badge"><i class="fa-solid fa-truck-pickup me-1"></i> Kendaraan</span>
                <div class="ppdb-card-body-text">
                    <h5 class="ppdb-card-title">Armada Kendaraan</h5>
                    <p class="ppdb-card-desc">Dump Truck, Pick Up Patroli & Mobil Dinas (Interval Servis 6 Bulan).</p>
                </div>
                <div class="ppdb-card-footer">
                    <span class="ppdb-card-meta">Total: <?= $cnt_knd ?> Unit</span>
                    <span class="ppdb-card-action">Buka Halaman Kendaraan <i class="fa-solid fa-arrow-right"></i></span>
                </div>
            </div>
        </a>

        <!-- Submenu 2: Peralatan -->
        <a href="data_unit_peralatan.php" class="ppdb-card" style="background-image: url('assets/images/card_peralatan.jpg?v=<?= time() ?>');">
            <div class="ppdb-card-overlay"></div>
            <div class="ppdb-card-content">
                <span class="ppdb-card-badge" style="background-color: #0d9488;"><i class="fa-solid fa-toolbox me-1"></i> Peralatan</span>
                <div class="ppdb-card-body-text">
                    <h5 class="ppdb-card-title">Peralatan & Alat Berat</h5>
                    <p class="ppdb-card-desc">Excavator, Roller Tandem & Alat Berat (Target 1.000 Jam Operasional).</p>
                </div>
                <div class="ppdb-card-footer">
                    <span class="ppdb-card-meta" style="color: #2dd4bf !important;">Total: <?= $cnt_alat ?> Unit</span>
                    <span class="ppdb-card-action" style="color: #2dd4bf !important;">Buka Halaman Peralatan <i class="fa-solid fa-arrow-right"></i></span>
                </div>
            </div>
        </a>

        <!-- Submenu 3: Data Ruas -->
        <a href="data_unit_ruas.php" class="ppdb-card" style="background-image: url('assets/images/card_ruas.jpg?v=<?= time() ?>');">
            <div class="ppdb-card-overlay"></div>
            <div class="ppdb-card-content">
                <span class="ppdb-card-badge" style="background-color: #0284c7;"><i class="fa-solid fa-map-location-dot me-1"></i> Data Ruas</span>
                <div class="ppdb-card-body-text">
                    <h5 class="ppdb-card-title">Data Ruas Jalan</h5>
                    <p class="ppdb-card-desc">Pengawasan & Sebaran Unit Armada di 13 Pos Ruas BPJ Wilayah Tegal.</p>
                </div>
                <div class="ppdb-card-footer">
                    <span class="ppdb-card-meta" style="color: #38bdf8 !important;">13 Pos Ruas Jalan</span>
                    <span class="ppdb-card-action" style="color: #38bdf8 !important;">Buka Data Ruas <i class="fa-solid fa-arrow-right"></i></span>
                </div>
            </div>
        </a>

        <!-- Submenu 4: Pajak STNK -->
        <a href="pembayaran_pajak_kir.php" class="ppdb-card" style="background-image: url('assets/images/card_pajak.jpg?v=<?= time() ?>');">
            <div class="ppdb-card-overlay"></div>
            <div class="ppdb-card-content">
                <span class="ppdb-card-badge" style="background-color: #d97706;"><i class="fa-solid fa-file-invoice-dollar me-1"></i> Pajak</span>
                <div class="ppdb-card-body-text">
                    <h5 class="ppdb-card-title">Pajak STNK</h5>
                    <p class="ppdb-card-desc">Kelola Pembayaran, Resi & Masa Berlaku Pajak Armada BPJ.</p>
                </div>
                <div class="ppdb-card-footer">
                    <span class="ppdb-card-meta" style="color: #fbbf24 !important;">Kelola Pajak</span>
                    <span class="ppdb-card-action" style="color: #fbbf24 !important;">Buka Data <i class="fa-solid fa-arrow-right"></i></span>
                </div>
            </div>
        </a>
    </div>

    <!-- Category Tabs & Search Bar -->
    <div class="card-custom p-3 mb-4">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="nav nav-pills gap-2 flex-wrap">
                <a href="data_unit.php?tab=semua" class="nav-link rounded-pill px-3 py-1.5 <?= ($tab === 'semua') ? 'active bg-primary' : 'bg-dark text-white border border-secondary' ?>">
                    Semua Unit (<?= $cnt_total ?>)
                </a>
                <a href="data_unit.php?tab=roda6" class="nav-link rounded-pill px-3 py-1.5 <?= ($tab === 'roda6') ? 'active bg-primary' : 'bg-dark text-white border border-secondary' ?>">
                    Roda 6 (<?= $cnt_r6 ?>)
                </a>
                <a href="data_unit.php?tab=roda4" class="nav-link rounded-pill px-3 py-1.5 <?= ($tab === 'roda4') ? 'active bg-primary' : 'bg-dark text-white border border-secondary' ?>">
                    Roda 4 (<?= $cnt_r4 ?>)
                </a>
                <a href="data_unit.php?tab=roda3" class="nav-link rounded-pill px-3 py-1.5 <?= ($tab === 'roda3' || $tab === 'viar') ? 'active bg-primary' : 'bg-dark text-white border border-secondary' ?>">
                    VIAR / Roda 3 (<?= $cnt_r3 ?>)
                </a>
                <a href="data_unit.php?tab=roda2" class="nav-link rounded-pill px-3 py-1.5 <?= ($tab === 'roda2') ? 'active bg-primary' : 'bg-dark text-white border border-secondary' ?>">
                    Roda 2 (<?= $cnt_r2 ?>)
                </a>
                <a href="data_unit.php?tab=peralatan" class="nav-link rounded-pill px-3 py-1.5 <?= ($tab === 'peralatan') ? 'active bg-primary' : 'bg-dark text-white border border-secondary' ?>">
                    Peralatan & Alat Berat (<?= $cnt_alat ?>)
                </a>
                <a href="data_unit.php?tab=rb" class="nav-link rounded-pill px-3 py-1.5 <?= ($tab === 'rb') ? 'active bg-danger text-white' : 'bg-dark text-danger border border-danger' ?>">
                    Rusak Berat (RB) (<?= $cnt_rb ?>)
                </a>
            </div>

            <form method="GET" action="" class="d-flex gap-2" style="max-width: 300px;">
                <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
                <input type="text" name="search" class="form-control form-control-sm rounded-pill px-3 text-white bg-dark border-secondary" placeholder="Cari Nopol, Nama..." value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="btn btn-sm btn-outline-secondary rounded-pill px-3 text-white"><i class="fa-solid fa-search"></i></button>
            </form>
        </div>
    </div>

    <!-- Data Table -->
    <div class="card-custom p-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">

                <?php if ($tab === 'peralatan'): ?>
                    <!-- TAB PERALATAN SPECIFIC HEADERS -->
                    <thead>
                        <tr>
                            <th class="text-nowrap" style="width: 12%;">Nomor Alat</th>
                            <th class="text-nowrap" style="width: 22%;">Jenis & Nama Peralatan</th>
                            <th class="text-nowrap" style="width: 14%;">Merk / Type</th>
                            <th class="text-nowrap" style="width: 18%;">Jam Operasional & Servis</th>
                            <th class="text-nowrap" style="width: 10%;">Kondisi</th>
                            <th class="text-nowrap" style="width: 12%;">Lokasi Ruas Jalan</th>
                            <th class="text-nowrap text-end" style="width: 12%;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($units)): ?>
                            <tr><td colspan="7" class="text-center text-muted py-5">Belum ada data peralatan & alat berat terdaftar.</td></tr>
                        <?php else: ?>
                            <?php foreach ($units as $u): 
                                $jam_info = get_peralatan_jam_info($u);
                            ?>
                                <tr>
                                    <td class="fw-bold text-white font-monospace text-nowrap"><?= htmlspecialchars($u['kode_plat']) ?></td>
                                    <td>
                                        <div class="fw-bold text-white"><?= htmlspecialchars($u['nama']) ?></div>
                                        <span class="text-white-50 small"><?= htmlspecialchars($u['kategori'] ?: 'Peralatan / Alat Berat') ?></span>
                                    </td>
                                    <td class="text-nowrap text-white"><?= htmlspecialchars($u['merk'] ?: '-') ?> <small class="text-white-50">(<?= htmlspecialchars($u['tahun'] ?: '-') ?>)</small></td>
                                    <td>
                                        <div class="fw-bold text-info"><i class="fa-solid fa-stopwatch me-1"></i> Total: <?= number_format((int)($u['jam_operasional'] ?? 0)) ?> Jam</div>
                                        <span class="badge <?= $jam_info['badge_class'] ?> mt-1 d-inline-block">
                                            <i class="fa-solid <?= $jam_info['icon'] ?> me-1"></i> <?= $jam_info['label'] ?>
                                        </span>
                                    </td>
                                    <td class="text-nowrap"><?= get_kondisi_badge($u['kondisi']) ?></td>
                                    <td class="small fw-semibold text-info text-nowrap"><i class="fa-solid fa-location-dot me-1"></i> <?= htmlspecialchars($u['lokasi_ruas']) ?></td>
                                    <td class="text-end text-nowrap">
                                        <?php if (can_edit_data()): ?>
                                            <button type="button" class="btn btn-sm btn-success rounded-pill px-2.5 me-1" onclick="openModalInputJam(<?= $u['id'] ?>)" title="Input Pemakaian Jam">
                                                <i class="fa-solid fa-clock-rotate-left me-1"></i> + Jam
                                            </button>
                                            <a href="proses_reset_jam_alat.php?id=<?= $u['id'] ?>&redirect_url=data_unit.php?tab=peralatan" onclick="return confirm('Reset jam operasional unit <?= htmlspecialchars(addslashes($u['nama'])) ?> (<?= htmlspecialchars(addslashes($u['kode_plat'])) ?>) ke 0 Jam setelah servis?')" class="btn btn-sm btn-warning rounded-pill px-2.5 me-1 text-dark fw-bold" title="Reset Jam Operasional ke 0 (Servis Selesai)">
                                                <i class="fa-solid fa-rotate-left me-1"></i> Reset Jam
                                            </a>
                                            <a href="data_unit_form.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-outline-primary rounded-pill px-2"><i class="fa-solid fa-pen-to-square"></i></a>
                                            <a href="data_unit_form.php?id=<?= $u['id'] ?>&action=delete" onclick="return confirm('Hapus peralatan ini?')" class="btn btn-sm btn-outline-danger rounded-pill px-2"><i class="fa-solid fa-trash"></i></a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>

                <?php else: ?>
                    <!-- DEFAULT HEADERS (Kendaraan & Semua) -->
                    <thead>
                        <tr>
                            <th class="text-nowrap" style="width: 12%;">Nopol / Kode</th>
                            <th class="text-nowrap" style="width: 20%;">Nama Unit & Merk</th>
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
                            <tr><td colspan="8" class="text-center text-muted py-5">Tidak ditemukan data unit armada untuk kategori ini.</td></tr>
                        <?php else: ?>
                            <?php foreach ($units as $u): 
                                $is_rb = ($u['kondisi'] === 'RB');
                                $is_alat = ($u['jenis'] === 'peralatan');
                                $info_s = get_alisa_schedule_info($u['tgl_servis_berikutnya'], 'Servis', $u['kondisi'], $u['jenis']);
                                $info_p = get_alisa_schedule_info($u['tgl_jatuh_tempo_pajak'], 'Pajak STNK', $u['kondisi'], $u['jenis']);
                                $jam_info = $is_alat ? get_peralatan_jam_info($u) : null;
                                $riwayat_p = get_riwayat_plat($pdo, $u['id']);
                            ?>
                                <tr class="<?= $is_rb ? 'opacity-75' : '' ?>">
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
                                        <?php if ($u['jenis'] === 'peralatan'): ?>
                                            <span class="badge bg-primary text-white rounded-pill px-2.5 py-1"><i class="fa-solid fa-toolbox me-1"></i> Peralatan</span>
                                        <?php elseif ($u['jenis'] === 'kendaraan_roda_3'): ?>
                                            <span class="badge bg-primary text-white rounded-pill px-2.5 py-1"><i class="fa-solid fa-motorcycle me-1"></i> VIAR Roda 3</span>
                                        <?php else: ?>
                                            <span class="badge bg-primary text-white rounded-pill px-2.5 py-1"><i class="fa-solid fa-truck-pickup me-1"></i> Kendaraan</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small font-monospace text-nowrap text-white" style="font-size: 0.8rem;">
                                        <div>Rangka: <?= htmlspecialchars($u['no_chasis'] ?: '-') ?></div>
                                        <div>Mesin: <?= htmlspecialchars($u['no_mesin'] ?: '-') ?></div>
                                    </td>
                                    <td class="text-nowrap"><?= get_kondisi_badge($u['kondisi']) ?></td>
                                    <td class="small fw-semibold text-info text-nowrap"><i class="fa-solid fa-location-dot me-1"></i> <?= htmlspecialchars($u['lokasi_ruas']) ?></td>
                                    <td class="text-nowrap">
                                        <?php if ($is_rb): ?>
                                            <span class="badge bg-dark text-white border border-danger">Rusak Berat</span>
                                        <?php elseif ($is_alat): ?>
                                            <div class="fw-bold text-info small">Total: <?= number_format((int)($u['jam_operasional'] ?? 0)) ?> Jam</div>
                                            <span class="badge <?= $jam_info['badge_class'] ?> rounded-pill"><i class="fa-solid <?= $jam_info['icon'] ?> me-1"></i> <?= $jam_info['label'] ?></span>
                                        <?php else: ?>
                                            <span class="badge <?= $info_s['badge_class'] ?> rounded-pill mb-1"><?= $info_s['label'] ?></span><br>
                                            <span class="badge <?= $info_p['badge_class'] ?> rounded-pill">Pajak: <?= $info_p['label'] ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end text-nowrap">
                                        <?php if ($is_alat && can_edit_data()): ?>
                                            <button type="button" class="btn btn-sm btn-success rounded-pill px-2 me-1" onclick="openModalInputJam(<?= $u['id'] ?>)" title="Input Pemakaian Jam">
                                                <i class="fa-solid fa-clock-rotate-left"></i>
                                            </button>
                                        <?php endif; ?>
                                        <?php if (can_edit_data()): ?>
                                            <a href="data_unit_form.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-outline-primary rounded-pill px-2.5"><i class="fa-solid fa-pen-to-square"></i></a>
                                            <a href="data_unit_form.php?id=<?= $u['id'] ?>&action=delete" onclick="return confirm('Hapus unit ini?')" class="btn btn-sm btn-outline-danger rounded-pill px-2"><i class="fa-solid fa-trash"></i></a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                <?php endif; ?>

            </table>
        </div>
    </div>
</div>

<!-- Modal Input Pemakaian Jam Kerja Peralatan -->
<div class="modal fade" id="modalInputJamKerja" tabindex="-1" aria-labelledby="modalInputJamKerjaLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-white border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title fw-bold" id="modalInputJamKerjaLabel"><i class="fa-solid fa-clock-rotate-left text-success me-2"></i> Input Pemakaian Jam Kerja Alat</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="proses_input_jam_alat.php" method="POST">
                <input type="hidden" name="redirect_url" value="data_unit.php?tab=<?= htmlspecialchars($tab) ?>">
                <div class="modal-body">
                    <p class="small text-white-50 mb-3">Teknisi wajib mencatat durasi penggunaan (jam) setiap kali selesai memakai peralatan/alat berat. Sistem akan otomatis mengurangi sisa jam servis menuju target 1.000 jam.</p>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold text-white">Pilih Unit Peralatan & Alat Berat</label>
                        <select name="id_kendaraan" id="select_id_kendaraan_all" class="form-select bg-dark text-white border-secondary" required onchange="onSelectUnitChangeAll(this)">
                            <option value="">-- Pilih Peralatan --</option>
                            <?php foreach ($peralatan_units as $pu): ?>
                                <option value="<?= $pu['id'] ?>" data-jam="<?= (int)($pu['jam_operasional'] ?? 0) ?>" data-sisa="<?= (int)($pu['sisa_jam_servis'] ?? 1000) ?>">
                                    [<?= htmlspecialchars($pu['kode_plat']) ?>] <?= htmlspecialchars($pu['nama']) ?> (Total: <?= (int)($pu['jam_operasional'] ?? 0) ?> Jam | Sisa: <?= (int)($pu['sisa_jam_servis'] ?? 1000) ?> Jam)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="card bg-black bg-opacity-40 border-secondary p-3 mb-3" id="boxUnitInfoAll" style="display: none;">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-white-50 small">Total Jam Operasional Lalu:</span>
                            <span class="fw-bold text-info" id="infoTotalJamAll">0 Jam</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-white-50 small">Sisa Jam Menuju Servis 1.000 Jam:</span>
                            <span class="fw-bold text-warning" id="infoSisaJamAll">1000 Jam</span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-white">Durasi Pemakaian Baru (Dalam Jam)</label>
                        <div class="input-group">
                            <input type="number" name="jam_dipakai" class="form-control bg-dark text-white border-secondary fw-bold text-success fs-5" min="1" max="500" required placeholder="Contoh: 5">
                            <span class="input-group-text bg-secondary text-white fw-bold">Jam</span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-white">Catatan / Lokasi Pekerjaan Operasional</label>
                        <textarea name="catatan" class="form-control bg-dark text-white border-secondary" rows="2" placeholder="Contoh: Pemadatan jalan di Ruas Slawi - Jatibarang"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-3" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success rounded-pill px-4 shadow fw-bold"><i class="fa-solid fa-save me-1"></i> Simpan Pemakaian Jam</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openModalInputJam(unitId) {
    const select = document.getElementById('select_id_kendaraan_all');
    if (unitId > 0) {
        select.value = unitId;
        onSelectUnitChangeAll(select);
    } else {
        select.value = '';
        document.getElementById('boxUnitInfoAll').style.display = 'none';
    }
    const modal = new bootstrap.Modal(document.getElementById('modalInputJamKerja'));
    modal.show();
}

function onSelectUnitChangeAll(selectElem) {
    const selectedOption = selectElem.options[selectElem.selectedIndex];
    const box = document.getElementById('boxUnitInfoAll');
    if (selectedOption && selectedOption.value) {
        const total = selectedOption.getAttribute('data-jam') || '0';
        const sisa = selectedOption.getAttribute('data-sisa') || '1000';
        document.getElementById('infoTotalJamAll').innerText = total + ' Jam';
        document.getElementById('infoSisaJamAll').innerText = sisa + ' Jam';
        box.style.display = 'block';
    } else {
        box.style.display = 'none';
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

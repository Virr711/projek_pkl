<?php
require_once __DIR__ . '/includes/header.php';

$search = trim($_GET['search'] ?? '');

// Filter Query Construction for Peralatan Only
$sql = "SELECT * FROM kendaraan_alat WHERE jenis = 'peralatan' AND kondisi != 'RB'";
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

$sql .= " ORDER BY nama ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$units = $stmt->fetchAll();

$cnt_alat = count($units);
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

    <!-- Distinct Header with Breadcrumb & Back Button -->
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="data_unit.php" class="text-info text-decoration-none"><i class="fa-solid fa-truck-monster me-1"></i> Data Unit</a></li>
                    <li class="breadcrumb-item active text-white" aria-current="page">Submenu Peralatan</li>
                </ol>
            </nav>
            <h4 class="fw-bold mb-0 text-white"><i class="fa-solid fa-toolbox text-teal me-2" style="color: #2dd4bf;"></i> Data Unit Peralatan & Alat Berat (Target 1.000 Jam & Sisa <= 100 Jam Alert)</h4>
        </div>
        <div class="d-flex gap-2">
            <a href="data_unit.php" class="btn btn-outline-secondary text-white rounded-pill px-3">
                <i class="fa-solid fa-arrow-left me-1"></i> Kembali ke Semua Unit
            </a>
            <?php if (can_edit_data()): ?>
                <button type="button" class="btn btn-success rounded-pill px-3 shadow" onclick="openModalInputJam(0, '', '', 0, 0)">
                    <i class="fa-solid fa-clock-rotate-left me-1"></i> + Input Pemakaian Jam Kerja
                </button>
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
                <span class="badge rounded-pill px-3 py-2 fs-6" style="background-color: #0d9488; color: #ffffff;">
                    <i class="fa-solid fa-toolbox me-1"></i> Peralatan & Alat Berat (Total: <?= $cnt_alat ?> Unit) - Interval Servis: 1.000 Jam Operasional
                </span>
            </div>

            <form method="GET" action="" class="d-flex gap-2" style="max-width: 300px;">
                <input type="text" name="search" class="form-control form-control-sm rounded-pill px-3 text-white bg-dark border-secondary" placeholder="Cari Kode, Nama..." value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="btn btn-sm btn-outline-secondary rounded-pill px-3 text-white"><i class="fa-solid fa-search"></i></button>
            </form>
        </div>
    </div>

    <!-- Data Table Peralatan -->
    <div class="card-custom p-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="text-nowrap" style="width: 12%;">Nomor Alat</th>
                        <th class="text-nowrap" style="width: 22%;">Jenis & Nama Peralatan</th>
                        <th class="text-nowrap" style="width: 14%;">Merk / Type</th>
                        <th class="text-nowrap" style="width: 18%;">Jam Operasional & Status Servis</th>
                        <th class="text-nowrap" style="width: 10%;">Kondisi</th>
                        <th class="text-nowrap" style="width: 12%;">Lokasi Ruas Jalan</th>
                        <th class="text-nowrap text-end" style="width: 12%;">Aksi Teknisi</th>
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
                                        <button type="button" class="btn btn-sm btn-success rounded-pill px-2.5 me-1" onclick="openModalInputJam(<?= $u['id'] ?>, '<?= htmlspecialchars(addslashes($u['nama'])) ?>', '<?= htmlspecialchars(addslashes($u['kode_plat'])) ?>', <?= (int)($u['jam_operasional'] ?? 0) ?>, <?= (int)($u['sisa_jam_servis'] ?? 1000) ?>)" title="Input Pemakaian Jam">
                                            <i class="fa-solid fa-clock-rotate-left me-1"></i> + Jam
                                        </button>
                                        <a href="data_unit_form.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-outline-primary rounded-pill px-2" title="Edit Unit"><i class="fa-solid fa-pen-to-square"></i></a>
                                        <a href="data_unit_form.php?id=<?= $u['id'] ?>&action=delete" onclick="return confirm('Hapus peralatan ini?')" class="btn btn-sm btn-outline-danger rounded-pill px-2" title="Hapus Unit"><i class="fa-solid fa-trash"></i></a>
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

<!-- Modal Input Pemakaian Jam Kerja Peralatan (Khusus Teknisi) -->
<div class="modal fade" id="modalInputJamKerja" tabindex="-1" aria-labelledby="modalInputJamKerjaLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-white border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title fw-bold" id="modalInputJamKerjaLabel"><i class="fa-solid fa-clock-rotate-left text-success me-2"></i> Input Pemakaian Jam Kerja Alat</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="proses_input_jam_alat.php" method="POST">
                <input type="hidden" name="redirect_url" value="data_unit_peralatan.php">
                <div class="modal-body">
                    <p class="small text-white-50 mb-3">Teknisi wajib mencatat durasi penggunaan (jam) setiap kali selesai memakai peralatan/alat berat. Sistem akan otomatis mengurangi sisa jam servis menuju target 1.000 jam.</p>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold text-white">Pilih Unit Peralatan & Alat Berat</label>
                        <select name="id_kendaraan" id="select_id_kendaraan" class="form-select bg-dark text-white border-secondary" required onchange="onSelectUnitChange(this)">
                            <option value="">-- Pilih Peralatan --</option>
                            <?php foreach ($units as $u): ?>
                                <option value="<?= $u['id'] ?>" data-nama="<?= htmlspecialchars($u['nama']) ?>" data-plat="<?= htmlspecialchars($u['kode_plat']) ?>" data-jam="<?= (int)($u['jam_operasional'] ?? 0) ?>" data-sisa="<?= (int)($u['sisa_jam_servis'] ?? 1000) ?>">
                                    [<?= htmlspecialchars($u['kode_plat']) ?>] <?= htmlspecialchars($u['nama']) ?> (Total: <?= (int)($u['jam_operasional'] ?? 0) ?> Jam | Sisa: <?= (int)($u['sisa_jam_servis'] ?? 1000) ?> Jam)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="card bg-black bg-opacity-40 border-secondary p-3 mb-3" id="boxUnitInfo" style="display: none;">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-white-50 small">Total Jam Operasional Lalu:</span>
                            <span class="fw-bold text-info" id="infoTotalJam">0 Jam</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-white-50 small">Sisa Jam Menuju Servis 1.000 Jam:</span>
                            <span class="fw-bold text-warning" id="infoSisaJam">1000 Jam</span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-white">Durasi Pemakaian Baru (Dalam Jam)</label>
                        <div class="input-group">
                            <input type="number" name="jam_dipakai" class="form-control bg-dark text-white border-secondary fw-bold text-success fs-5" min="1" max="500" required placeholder="Contoh: 5">
                            <span class="input-group-text bg-secondary text-white fw-bold">Jam</span>
                        </div>
                        <div class="form-text text-white-50">Misal: Dipakai 5 jam operasional, maka sisa jam servis akan otomatis berkurang 5 jam.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-white">Catatan / Lokasi Pekerjaan Operasional</label>
                        <textarea name="catatan" class="form-control bg-dark text-white border-secondary" rows="2" placeholder="Contoh: Pemadatan aspal di Ruas Slawi - Jatibarang oleh Operator"></textarea>
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
function openModalInputJam(unitId, nama, plat, totalJam, sisaJam) {
    const select = document.getElementById('select_id_kendaraan');
    if (unitId > 0) {
        select.value = unitId;
        onSelectUnitChange(select);
    } else {
        select.value = '';
        document.getElementById('boxUnitInfo').style.display = 'none';
    }
    const modal = new bootstrap.Modal(document.getElementById('modalInputJamKerja'));
    modal.show();
}

function onSelectUnitChange(selectElem) {
    const selectedOption = selectElem.options[selectElem.selectedIndex];
    const box = document.getElementById('boxUnitInfo');
    if (selectedOption && selectedOption.value) {
        const total = selectedOption.getAttribute('data-jam') || '0';
        const sisa = selectedOption.getAttribute('data-sisa') || '1000';
        document.getElementById('infoTotalJam').innerText = total + ' Jam';
        document.getElementById('infoSisaJam').innerText = sisa + ' Jam';
        box.style.display = 'block';
    } else {
        box.style.display = 'none';
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

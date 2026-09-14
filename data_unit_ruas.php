<?php
require_once __DIR__ . '/includes/header.php';

$ruas_list = get_lokasi_ruas_list();
$standard_12_ruas = array_slice($ruas_list, 0, 12);

// Fetch all units from DB
$stmt_all = $pdo->query("
    SELECT id, kode_plat, kup_reg, nama, jenis, merk, tahun, kondisi, penanggung_jawab, lokasi_ruas 
    FROM kendaraan_alat 
    ORDER BY lokasi_ruas ASC, jenis ASC, kode_plat ASC
");
$all_units_raw = $stmt_all->fetchAll();

$all_units_by_ruas = [];
$ruas_list_data = [];

// Initialize structure for all 13 official ruas items
foreach ($ruas_list as $r_name) {
    $ruas_list_data[$r_name] = [
        'total_unit' => 0, 'total_r6' => 0, 'total_r4' => 0, 'total_r3' => 0,
        'total_r2' => 0, 'total_peralatan' => 0, 'total_baik' => 0, 'total_rr' => 0, 'total_rb' => 0
    ];
    $all_units_by_ruas[$r_name] = [];
}

$total_all_units = 0;
$total_all_baik = 0;
$total_all_rr = 0;
$total_all_rb = 0;

foreach ($all_units_raw as $row) {
    $lokasi = $row['lokasi_ruas'];
    
    // Target key in $ruas_list: Default to Item 13 (Tempat Lain / Custom Input) if not in standard 1-12
    $target_ruas = '13. Tempat Lain (Input Custom)';
    if (in_array($lokasi, $standard_12_ruas)) {
        $target_ruas = $lokasi;
    }
    
    $all_units_by_ruas[$target_ruas][] = $row;
    
    $ruas_list_data[$target_ruas]['total_unit']++;
    
    if ($row['jenis'] === 'kendaraan_roda_6') $ruas_list_data[$target_ruas]['total_r6']++;
    elseif ($row['jenis'] === 'kendaraan_roda_4') $ruas_list_data[$target_ruas]['total_r4']++;
    elseif ($row['jenis'] === 'kendaraan_roda_3') $ruas_list_data[$target_ruas]['total_r3']++;
    elseif ($row['jenis'] === 'kendaraan_roda_2') $ruas_list_data[$target_ruas]['total_r2']++;
    else $ruas_list_data[$target_ruas]['total_peralatan']++;
    
    $k = strtoupper($row['kondisi']);
    if ($k === 'B') { $ruas_list_data[$target_ruas]['total_baik']++; $total_all_baik++; }
    elseif ($k === 'RR') { $ruas_list_data[$target_ruas]['total_rr']++; $total_all_rr++; }
    elseif ($k === 'RB') { $ruas_list_data[$target_ruas]['total_rb']++; $total_all_rb++; }
    
    $total_all_units++;
}
?>

<div class="container-fluid p-0">
    <!-- Header Page & Breadcrumb -->
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="index.php" class="text-info text-decoration-none"><i class="fa-solid fa-house me-1"></i> Beranda</a></li>
                    <li class="breadcrumb-item"><a href="data_unit.php" class="text-info text-decoration-none">Data Unit</a></li>
                    <li class="breadcrumb-item active text-white" aria-current="page">Data Ruas Jalan</li>
                </ol>
            </nav>
            <h4 class="fw-bold mb-0 text-white"><i class="fa-solid fa-map-location-dot text-info me-2"></i> Data Ruas Jalan & Sebaran Armada BPJ Tegal</h4>
            <div class="text-white-50 small mt-1">Pengawasan sebaran unit kendaraan & peralatan di 13 Pos Ruas Jalan Balai BPJ Wilayah Tegal</div>
        </div>
        <?php if ($current_role === 'admin' || $current_role === 'pimpinan'): ?>
            <div class="d-flex gap-2">
                <a href="export_docx.php?tipe=data_ruas" class="btn btn-primary rounded-pill px-4 shadow-sm" style="background-color: #0d9488 !important; border: none;">
                    <i class="fa-solid fa-file-word me-1.5"></i> Export Word (.docx)
                </a>
                <a href="laporan_ruas.php" class="btn btn-outline-info rounded-pill px-4 shadow-sm">
                    <i class="fa-solid fa-file-pdf me-1.5"></i> Laporan Ruas
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- STATS OVERVIEW CARDS -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card-custom p-3 border-start border-4 border-info">
                <div class="text-white-50 small fw-bold">TOTAL POS RUAS</div>
                <div class="fs-3 fw-extrabold text-info my-1">13 <span class="fs-6 fw-normal text-white-50">Pos</span></div>
                <div class="small text-white-50">13 Lokasi Wilayah BPJ Tegal</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card-custom p-3 border-start border-4 border-primary">
                <div class="text-white-50 small fw-bold">TOTAL ARMADA & ALAT</div>
                <div class="fs-3 fw-extrabold text-primary my-1"><?= $total_all_units ?> <span class="fs-6 fw-normal text-white-50">Unit</span></div>
                <div class="small text-white-50">Tersebar di Seluruh Ruas</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card-custom p-3 border-start border-4 border-success">
                <div class="text-white-50 small fw-bold">KONDISI BAIK (B)</div>
                <div class="fs-3 fw-extrabold text-success my-1"><?= $total_all_baik ?> <span class="fs-6 fw-normal text-white-50">Unit</span></div>
                <div class="small text-white-50">Siap Operasional Lapangan</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card-custom p-3 border-start border-4 border-warning">
                <div class="text-white-50 small fw-bold">RUSAK RINGAN / BERAT</div>
                <div class="fs-3 fw-extrabold text-warning my-1"><?= ($total_all_rr + $total_all_rb) ?> <span class="fs-6 fw-normal text-white-50">Unit</span></div>
                <div class="small text-white-50"><?= $total_all_rr ?> RR &bull; <?= $total_all_rb ?> RB</div>
            </div>
        </div>
    </div>

    <!-- WIDGET SEBARAN 13 LOKASI BPJ TEGAL (LIST VIEW TAMPILAN BERANDA) -->
    <div class="card-custom p-4 shadow-sm mb-4">
        <div class="d-flex align-items-center justify-content-between mb-3 border-bottom border-secondary pb-3">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle p-2.5 d-flex align-items-center justify-content-center" style="width: 44px; height: 44px; background: rgba(56, 189, 248, 0.15); border: 1px solid rgba(56, 189, 248, 0.3);">
                    <i class="fa-solid fa-map-location-dot fs-5 text-info"></i>
                </div>
                <div>
                    <h5 class="fw-bold text-white mb-0">Sebaran 13 Lokasi BPJ Tegal</h5>
                    <div class="text-white-50 small">Klik nama ruas atau tombol unit untuk melihat daftar unit armada di ruas tersebut</div>
                </div>
            </div>
            <a href="laporan_ruas.php" class="btn btn-sm btn-outline-info rounded-pill px-3 fw-bold">
                <i class="fa-solid fa-print me-1"></i> Cetak Laporan Per Ruas
            </a>
        </div>

        <div class="list-group list-group-flush">
            <?php foreach ($ruas_list as $index => $r_name): 
                $data_r = $ruas_list_data[$r_name];
                $unit_cnt = $data_r['total_unit'];
                $is_pool = (strpos(strtolower($r_name), 'workshop') !== false || strpos(strtolower($r_name), 'pool') !== false);
            ?>
                <div class="list-group-item bg-transparent d-flex align-items-center justify-content-between py-3 px-2 border-bottom border-secondary text-white" style="border-opacity: 0.15;">
                    <!-- Left: Icon & Ruas Name -->
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 cursor-pointer" style="width: 36px; height: 36px; background: rgba(56, 189, 248, 0.1); border: 1px solid rgba(56, 189, 248, 0.2);" onclick="showRuasUnitModal('<?= htmlspecialchars(addslashes($r_name)) ?>')">
                            <i class="fa-solid fa-road text-info fs-6"></i>
                        </div>
                        <div>
                            <button type="button" class="btn btn-link text-white text-decoration-none fw-bold p-0 fs-6 hover-text-info text-start" onclick="showRuasUnitModal('<?= htmlspecialchars(addslashes($r_name)) ?>')">
                                <?= htmlspecialchars($r_name) ?>
                            </button>
                            <?php if ($is_pool): ?>
                                <span class="badge bg-primary bg-opacity-25 text-info ms-2 px-2 py-0.5 rounded-pill small">Induk Pool</span>
                            <?php endif; ?>
                            <div class="small text-white-50 mt-0.5">
                                <span class="me-3"><i class="fa-solid fa-truck-moving me-1 text-white-50"></i> R6 & R4: <strong><?= ($data_r['total_r6'] + $data_r['total_r4']) ?></strong></span>
                                <span class="me-3"><i class="fa-solid fa-motorcycle me-1 text-warning"></i> Viar R3: <strong><?= $data_r['total_r3'] ?></strong></span>
                                <span class="me-3"><i class="fa-solid fa-motorcycle me-1 text-info"></i> R2: <strong><?= $data_r['total_r2'] ?></strong></span>
                                <span><i class="fa-solid fa-toolbox me-1 text-success"></i> Alat: <strong><?= $data_r['total_peralatan'] ?></strong></span>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Unit Badge & Action -->
                    <div class="d-flex align-items-center gap-3 flex-shrink-0">
                        <div class="text-end d-none d-sm-block me-1">
                            <span class="text-success small fw-bold me-2"><i class="fa-solid fa-circle-check me-1"></i> <?= $data_r['total_baik'] ?> B</span>
                            <?php if ($data_r['total_rr'] > 0): ?>
                                <span class="text-warning small fw-bold me-2"><i class="fa-solid fa-triangle-exclamation me-1"></i> <?= $data_r['total_rr'] ?> RR</span>
                            <?php endif; ?>
                            <?php if ($data_r['total_rb'] > 0): ?>
                                <span class="text-danger small fw-bold"><i class="fa-solid fa-ban me-1"></i> <?= $data_r['total_rb'] ?> RB</span>
                            <?php endif; ?>
                        </div>
                        <button type="button" onclick="showRuasUnitModal('<?= htmlspecialchars(addslashes($r_name)) ?>')" class="badge <?= $unit_cnt > 0 ? 'bg-primary' : 'bg-secondary' ?> text-white font-monospace fs-6 px-3 py-2 rounded-pill border-0 shadow-sm text-decoration-none cursor-pointer" title="Klik untuk lihat unit di ruas ini">
                            <?= $unit_cnt ?> Unit
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- MODAL DETAIL DAFTAR UNIT PER RUAS JALAN -->
<div class="modal fade" id="modalRuasUnitDetail" tabindex="-1" aria-labelledby="modalRuasUnitDetailLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content bg-dark text-white border-secondary shadow-lg rounded-4">
            <div class="modal-header border-secondary">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle p-2 d-flex align-items-center justify-content-center bg-info bg-opacity-25 text-info" style="width: 38px; height: 38px; border: 1px solid rgba(56, 189, 248, 0.4);">
                        <i class="fa-solid fa-map-pin fs-5"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold text-white mb-0" id="modalRuasTitle">Daftar Unit Pos Ruas</h5>
                        <div class="text-white-50 small" id="modalRuasSubtitle">Unit armada & peralatan di pos ini</div>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="table-responsive">
                    <table class="table table-dark table-hover align-middle border-secondary mb-0">
                        <thead>
                            <tr class="table-dark-header">
                                <th style="width: 50px;">No</th>
                                <th>Kode Plat / Nopol</th>
                                <th>Nama Unit & Merk</th>
                                <th>Jenis Unit</th>
                                <th>Penanggung Jawab</th>
                                <th class="text-center">Kondisi</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="modalRuasTableBody">
                            <!-- Populated via JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer border-secondary justify-content-between">
                <?php if ($current_role === 'admin' || $current_role === 'pimpinan'): ?>
                    <div class="d-flex gap-2">
                        <a id="btnExportWordRuasIni" href="export_docx.php?tipe=data_ruas" target="_blank" class="btn btn-primary rounded-pill px-3 shadow-sm" style="background-color: #0d9488 !important; border: none;">
                            <i class="fa-solid fa-file-word me-1.5"></i> Export Word (.docx)
                        </a>
                        <a id="btnCetakRuasIni" href="laporan_ruas.php" target="_blank" class="btn btn-outline-info rounded-pill px-3">
                            <i class="fa-solid fa-print me-1.5"></i> Cetak Laporan (PDF)
                        </a>
                    </div>
                <?php endif; ?>
                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script>
const unitsByRuas = <?= json_encode($all_units_by_ruas, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

function getJenisBadge(jenis) {
    switch (jenis) {
        case 'kendaraan_roda_6':
            return '<span class="badge bg-danger bg-opacity-25 text-danger border border-danger px-2.5 py-1 rounded-pill"><i class="fa-solid fa-truck-moving me-1"></i> Roda 6</span>';
        case 'kendaraan_roda_4':
            return '<span class="badge bg-primary bg-opacity-25 text-primary border border-primary px-2.5 py-1 rounded-pill"><i class="fa-solid fa-truck-pickup me-1"></i> Roda 4</span>';
        case 'kendaraan_roda_3':
            return '<span class="badge bg-warning bg-opacity-25 text-warning border border-warning px-2.5 py-1 rounded-pill"><i class="fa-solid fa-motorcycle me-1"></i> Viar Roda 3</span>';
        case 'kendaraan_roda_2':
            return '<span class="badge bg-info bg-opacity-25 text-info border border-info px-2.5 py-1 rounded-pill"><i class="fa-solid fa-motorcycle me-1"></i> Roda 2</span>';
        default:
            return '<span class="badge bg-success bg-opacity-25 text-success border border-success px-2.5 py-1 rounded-pill"><i class="fa-solid fa-toolbox me-1"></i> Peralatan</span>';
    }
}

function getKondisiBadgeJS(kondisi) {
    switch ((kondisi || '').toUpperCase()) {
        case 'B':
            return '<span class="badge bg-success text-white px-2.5 py-1 rounded-pill"><i class="fa-solid fa-circle-check me-1"></i> Baik (B)</span>';
        case 'RR':
            return '<span class="badge bg-warning text-white px-2.5 py-1 rounded-pill"><i class="fa-solid fa-triangle-exclamation me-1"></i> Rusak Ringan (RR)</span>';
        case 'RB':
            return '<span class="badge bg-danger text-white px-2.5 py-1 rounded-pill"><i class="fa-solid fa-ban me-1"></i> Rusak Berat (RB)</span>';
        default:
            return '<span class="badge bg-secondary text-white px-2.5 py-1 rounded-pill">' + (kondisi || '-') + '</span>';
    }
}

function showRuasUnitModal(ruasName) {
    const units = unitsByRuas[ruasName] || [];
    
    document.getElementById('modalRuasTitle').innerText = ruasName;
    document.getElementById('modalRuasSubtitle').innerText = 'Daftar ' + units.length + ' unit armada & peralatan terdaftar di pos ini';
    document.getElementById('btnCetakRuasIni').href = 'laporan_ruas.php?ruas=' + encodeURIComponent(ruasName);
    document.getElementById('btnExportWordRuasIni').href = 'export_docx.php?tipe=data_ruas&ruas=' + encodeURIComponent(ruasName);
    
    const tbody = document.getElementById('modalRuasTableBody');
    tbody.innerHTML = '';
    
    if (units.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="text-center py-4 text-white-50">
                    <i class="fa-solid fa-circle-exclamation fs-3 d-block mb-2 text-warning"></i>
                    Belum ada unit armada atau peralatan yang ditempatkan pada pos ruas ini.
                </td>
            </tr>
        `;
    } else {
        units.forEach((u, idx) => {
            const kupInfo = u.kup_reg ? `<div class="small text-white-50 font-monospace" style="font-size: 0.78rem;">KUP: ${escapeHtml(u.kup_reg)}</div>` : '';
            const merkInfo = (u.merk || '-') + (u.tahun ? ' • Th ' + u.tahun : '');
            
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td class="text-white-50">${idx + 1}</td>
                <td>
                    <span class="fw-extrabold text-info font-monospace">${escapeHtml(u.kode_plat)}</span>
                    ${kupInfo}
                </td>
                <td>
                    <div class="fw-bold text-white">${escapeHtml(u.nama)}</div>
                    <div class="small text-white-50">${escapeHtml(merkInfo)}</div>
                </td>
                <td>${getJenisBadge(u.jenis)}</td>
                <td><div class="small text-white"><i class="fa-solid fa-user me-1 text-white-50"></i> ${escapeHtml(u.penanggung_jawab || 'Petugas Lapangan')}</div></td>
                <td class="text-center">${getKondisiBadgeJS(u.kondisi)}</td>
                <td class="text-center">
                    <a href="data_unit_form.php?id=${u.id}" class="btn btn-xs btn-outline-info rounded-pill px-2.5 py-1" title="Lihat Detail & Edit">
                        <i class="fa-solid fa-pen-to-square"></i> Detail
                    </a>
                </td>
            `;
            tbody.appendChild(tr);
        });
    }
    
    const modalEl = document.getElementById('modalRuasUnitDetail');
    if (modalEl) {
        const modal = new bootstrap.Modal(modalEl);
        modal.show();
    }
}

function escapeHtml(text) {
    if (!text) return '';
    return text
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

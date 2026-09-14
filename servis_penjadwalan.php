<?php
require_once __DIR__ . '/includes/header.php';

$tab_cat = $_GET['cat'] ?? 'roda4';

// Fetch 14 Checklist Items definition
$checklist_items = get_standard_checklist_items();

// Fetch Vehicles by selected Category
$sql_units = "SELECT * FROM kendaraan_alat WHERE 1=1";
if ($tab_cat === 'roda6') {
    $sql_units .= " AND jenis = 'kendaraan_roda_6'";
} elseif ($tab_cat === 'roda4') {
    $sql_units .= " AND jenis = 'kendaraan_roda_4'";
} elseif ($tab_cat === 'roda3') {
    $sql_units .= " AND jenis = 'kendaraan_roda_3'";
} elseif ($tab_cat === 'roda2') {
    $sql_units .= " AND jenis = 'kendaraan_roda_2'";
} elseif ($tab_cat === 'peralatan') {
    $sql_units .= " AND jenis = 'peralatan'";
}
$sql_units .= " ORDER BY kode_plat ASC";

$stmt_u = $pdo->query($sql_units);
$units_list = $stmt_u->fetchAll();

// Build matrix lookup [id_kendaraan][item_servis] => tgl_servis_terakhir
$matrix_data = [];
if (!empty($units_list)) {
    $unit_ids = array_column($units_list, 'id');
    $in_clause = implode(',', array_map('intval', $unit_ids));
    $stmt_c = $pdo->query("SELECT * FROM servis_checklist WHERE id_kendaraan IN ($in_clause)");
    while ($r = $stmt_c->fetch()) {
        $matrix_data[$r['id_kendaraan']][$r['item_servis']] = $r['tgl_servis_terakhir'];
    }
}
?>

<div class="container-fluid p-0">
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="servis_kelola.php" class="text-info text-decoration-none"><i class="fa-solid fa-wrench me-1"></i> Melakukan Servis</a></li>
                    <li class="breadcrumb-item active text-white" aria-current="page">Penjadwalan Servis</li>
                </ol>
            </nav>
            <h4 class="fw-bold mb-0 text-white"><i class="fa-solid fa-calendar-check text-info me-2"></i> Matriks Penjadwalan Checklist Servis (Data 2)</h4>
            <p class="text-white-50 small mb-0">
                <?php if ($tab_cat === 'peralatan'): ?>
                    Catatan pemakaian <strong>Jam Operasional (Hour Meter)</strong> & Sisa Jam Servis menuju target 1.000 Jam untuk Peralatan & Alat Berat.
                <?php else: ?>
                    Catatan tanggal pelaksanaan servis rutin per item checklist dan NOPOL armada BPJ.
                <?php endif; ?>
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="servis_kelola.php" class="btn btn-outline-secondary text-white rounded-pill px-3">
                <i class="fa-solid fa-arrow-left me-1"></i> Kembali ke Riwayat
            </a>
            <?php if (can_edit_data()): ?>
                <?php if ($tab_cat === 'peralatan'): ?>
                    <button type="button" class="btn btn-success rounded-pill px-3 shadow" onclick="openModalInputJam(0)">
                        <i class="fa-solid fa-clock-rotate-left me-1"></i> + Input Jam Kerja Alat
                    </button>
                <?php else: ?>
                    <a href="servis_form.php?jenis_servis=Penjadwalan" class="btn btn-bpj-primary shadow">
                        <i class="fa-solid fa-plus me-2"></i> Input Servis Baru
                    </a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Category Tabs Filter -->
    <div class="card-custom p-3 mb-4">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="nav nav-pills gap-2 flex-wrap">
                <a href="servis_penjadwalan.php?cat=roda4" class="nav-link rounded-pill px-3 py-1.5 <?= ($tab_cat === 'roda4') ? 'active bg-primary' : 'bg-dark text-white border border-secondary' ?>">
                    <i class="fa-solid fa-car me-1"></i> Roda 4 (<?= ($tab_cat==='roda4')?count($units_list):'' ?>)
                </a>
                <a href="servis_penjadwalan.php?cat=roda6" class="nav-link rounded-pill px-3 py-1.5 <?= ($tab_cat === 'roda6') ? 'active bg-primary' : 'bg-dark text-white border border-secondary' ?>">
                    <i class="fa-solid fa-truck me-1"></i> Roda 6 (<?= ($tab_cat==='roda6')?count($units_list):'' ?>)
                </a>
                <a href="servis_penjadwalan.php?cat=roda3" class="nav-link rounded-pill px-3 py-1.5 <?= ($tab_cat === 'roda3') ? 'active bg-primary' : 'bg-dark text-white border border-secondary' ?>">
                    <i class="fa-solid fa-motorcycle me-1"></i> VIAR / Roda 3 (<?= ($tab_cat==='roda3')?count($units_list):'' ?>)
                </a>
                <a href="servis_penjadwalan.php?cat=roda2" class="nav-link rounded-pill px-3 py-1.5 <?= ($tab_cat === 'roda2') ? 'active bg-primary' : 'bg-dark text-white border border-secondary' ?>">
                    <i class="fa-solid fa-bicycle me-1"></i> Roda 2 (<?= ($tab_cat==='roda2')?count($units_list):'' ?>)
                </a>
                <a href="servis_penjadwalan.php?cat=peralatan" class="nav-link rounded-pill px-3 py-1.5 <?= ($tab_cat === 'peralatan') ? 'active bg-teal text-white' : 'bg-dark text-teal border border-secondary' ?>" style="<?= ($tab_cat==='peralatan')?'background-color:#0d9488 !important;':'' ?>">
                    <i class="fa-solid fa-toolbox me-1"></i> Peralatan & Alat Berat (1.000 Jam)
                </a>
            </div>
        </div>
    </div>

    <!-- Matrix Table (Data 2 Format) -->
    <div class="card-custom p-4 mb-4">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <h6 class="fw-bold text-white mb-0">
                <i class="fa-solid fa-table-cells text-info me-2"></i> 
                <?php if ($tab_cat === 'peralatan'): ?>
                    Matriks Servis Berbasis Jam Operasional (Alat Berat & Peralatan)
                <?php else: ?>
                    Matriks Jadwal & Tanggal Servis (NOPOL Armada)
                <?php endif; ?>
            </h6>
            <span class="text-white-50 small">
                <?php if ($tab_cat === 'peralatan'): ?>
                    <i class="fa-solid fa-info-circle me-1"></i> Menampilkan total Jam Operasional & sisa jam menuju target 1.000 Jam
                <?php else: ?>
                    <i class="fa-solid fa-info-circle me-1"></i> Menampilkan tanggal servis terakhir untuk setiap item checklist
                <?php endif; ?>
            </span>
        </div>

        <div class="table-responsive" style="max-height: 700px;">
            <table class="table table-bordered table-hover align-middle mb-0 border-secondary">
                <thead class="bg-dark text-center align-middle sticky-top">
                    <tr>
                        <th style="width: 40px;" class="bg-dark text-white">NO</th>
                        <th style="min-width: 220px;" class="bg-dark text-white text-start">NAMA SERVICE</th>
                        <th style="min-width: 180px;" class="bg-dark text-white text-start">INTERVAL SERVICE</th>
                        <?php foreach ($units_list as $u): ?>
                            <th style="min-width: 150px;" class="bg-dark text-info font-monospace">
                                <div><?= htmlspecialchars($u['kode_plat']) ?></div>
                                <span class="badge bg-secondary fw-normal text-wrap" style="font-size: 0.7rem;"><?= htmlspecialchars($u['nama']) ?></span>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($checklist_items as $idx => $item): ?>
                        <tr>
                            <td class="text-center fw-bold text-white-50"><?= $idx + 1 ?></td>
                            <td class="fw-bold text-white text-start"><?= htmlspecialchars($item[0]) ?></td>
                            <td class="small text-info text-start">
                                <?php if ($tab_cat === 'peralatan'): ?>
                                    <span class="badge bg-teal text-white" style="background-color: #0d9488;"><i class="fa-solid fa-clock me-1"></i> 1.000 Jam Operasional</span>
                                <?php else: ?>
                                    <?= htmlspecialchars($item[1]) ?>
                                <?php endif; ?>
                            </td>
                            <?php foreach ($units_list as $u): 
                                $uid = $u['id'];
                                $tgl_val = $matrix_data[$uid][$item[0]] ?? ($u['tgl_servis_terakhir'] ?? '');
                                $jam_info = get_peralatan_jam_info($u);
                            ?>
                                <td class="text-center p-2 text-nowrap">
                                    <?php if ($tab_cat === 'peralatan'): ?>
                                        <!-- Display Operating Hours for Peralatan & Alat Berat -->
                                        <div class="fw-bold text-info small"><i class="fa-solid fa-stopwatch me-1"></i> <?= number_format((int)($u['jam_operasional'] ?? 0)) ?> Jam</div>
                                        <span class="badge <?= $jam_info['badge_class'] ?> mt-1 font-monospace" style="font-size: 0.75rem;">
                                            <i class="fa-solid <?= $jam_info['icon'] ?> me-1"></i> <?= $jam_info['label'] ?>
                                        </span>
                                         <?php if (can_edit_data()): ?>
                                             <button type="button" class="btn btn-link btn-sm text-success p-0 ms-1" onclick="openModalInputJam(<?= $uid ?>)" title="Input Jam Kerja Baru">
                                                 <i class="fa-solid fa-plus-circle"></i>
                                             </button>
                                             <a href="proses_reset_jam_alat.php?id=<?= $uid ?>&redirect_url=servis_penjadwalan.php?cat=peralatan" onclick="return confirm('Reset jam operasional unit <?= htmlspecialchars(addslashes($u['nama'])) ?> (<?= htmlspecialchars(addslashes($u['kode_plat'])) ?>) ke 0 Jam setelah servis?')" class="btn btn-link btn-sm text-warning p-0 ms-1" title="Reset Jam Operasional ke 0 (Servis Selesai)">
                                                 <i class="fa-solid fa-rotate-left"></i>
                                             </a>
                                         <?php endif; ?>
                                    <?php else: ?>
                                        <!-- Display Date for Kendaraan (Auto-Linked via Form Servis) -->
                                        <?php if (!empty($tgl_val) && $tgl_val !== '0000-00-00'): ?>
                                            <span class="badge bg-success text-white px-2.5 py-1 font-monospace" style="font-size: 0.8rem;">
                                                <i class="fa-solid fa-calendar-day me-1"></i> <?= date('d/m/Y', strtotime($tgl_val)) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted fst-italic small">-</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>



<!-- Modal Input Pemakaian Jam Kerja Peralatan (Untuk Peralatan & Alat Berat) -->
<div class="modal fade" id="modalInputJamKerja" tabindex="-1" aria-labelledby="modalInputJamKerjaLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-white border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title fw-bold" id="modalInputJamKerjaLabel"><i class="fa-solid fa-clock-rotate-left text-success me-2"></i> Input Pemakaian Jam Kerja Alat</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="proses_input_jam_alat.php" method="POST">
                <input type="hidden" name="redirect_url" value="servis_penjadwalan.php?cat=peralatan">
                <div class="modal-body">
                    <p class="small text-white-50 mb-3">Teknisi wajib mencatat durasi penggunaan (jam) setiap kali selesai memakai peralatan/alat berat. Sistem akan otomatis mengurangi sisa jam servis menuju target 1.000 jam.</p>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold text-white">Pilih Unit Peralatan & Alat Berat</label>
                        <select name="id_kendaraan" id="select_id_kendaraan_mat" class="form-select bg-dark text-white border-secondary" required onchange="onSelectUnitChangeMat(this)">
                            <option value="">-- Pilih Peralatan --</option>
                            <?php 
                            $stmt_p = $pdo->query("SELECT * FROM kendaraan_alat WHERE jenis = 'peralatan' AND kondisi != 'RB' ORDER BY nama ASC");
                            while ($pu = $stmt_p->fetch()): 
                            ?>
                                <option value="<?= $pu['id'] ?>" data-jam="<?= (int)($pu['jam_operasional'] ?? 0) ?>" data-sisa="<?= (int)($pu['sisa_jam_servis'] ?? 1000) ?>">
                                    [<?= htmlspecialchars($pu['kode_plat']) ?>] <?= htmlspecialchars($pu['nama']) ?> (Total: <?= (int)($pu['jam_operasional'] ?? 0) ?> Jam | Sisa: <?= (int)($pu['sisa_jam_servis'] ?? 1000) ?> Jam)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="card bg-black bg-opacity-40 border-secondary p-3 mb-3" id="boxUnitInfoMat" style="display: none;">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-white-50 small">Total Jam Operasional Lalu:</span>
                            <span class="fw-bold text-info" id="infoTotalJamMat">0 Jam</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-white-50 small">Sisa Jam Menuju Servis 1.000 Jam:</span>
                            <span class="fw-bold text-warning" id="infoSisaJamMat">1000 Jam</span>
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
function openUpdateModal(idKnd, nopol, itemServis, tglVal) {
    document.getElementById('modal_id_kendaraan').value = idKnd;
    document.getElementById('modal_item_servis').value = itemServis;
    document.getElementById('modal_plat_display').value = nopol;
    document.getElementById('modal_item_display').value = itemServis;
    document.getElementById('modal_tgl_input').value = tglVal || '<?= date('Y-m-d') ?>';

    var myModal = new bootstrap.Modal(document.getElementById('updateChecklistModal'));
    myModal.show();
}

function openModalInputJam(unitId) {
    const select = document.getElementById('select_id_kendaraan_mat');
    if (unitId > 0) {
        select.value = unitId;
        onSelectUnitChangeMat(select);
    } else {
        select.value = '';
        document.getElementById('boxUnitInfoMat').style.display = 'none';
    }
    const modal = new bootstrap.Modal(document.getElementById('modalInputJamKerja'));
    modal.show();
}

function onSelectUnitChangeMat(selectElem) {
    const selectedOption = selectElem.options[selectElem.selectedIndex];
    const box = document.getElementById('boxUnitInfoMat');
    if (selectedOption && selectedOption.value) {
        const total = selectedOption.getAttribute('data-jam') || '0';
        const sisa = selectedOption.getAttribute('data-sisa') || '1000';
        document.getElementById('infoTotalJamMat').innerText = total + ' Jam';
        document.getElementById('infoSisaJamMat').innerText = sisa + ' Jam';
        box.style.display = 'block';
    } else {
        box.style.display = 'none';
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

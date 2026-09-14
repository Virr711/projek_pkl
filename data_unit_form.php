<?php
require_once __DIR__ . '/includes/header.php';

// Otorisasi hak akses: khusus Admin
if (!can_edit_data()) {
    echo "<script>alert('Akses Ditolak: Fitur Tambah/Edit Data Unit khusus untuk Admin.'); window.location='data_unit.php';</script>";
    exit;
}

$id = (int)($_GET['id'] ?? 0);
$action = $_GET['action'] ?? '';
$is_edit = ($id > 0);

// Pemrosesan penghapusan data unit armada
if ($is_edit && $action === 'delete') {
    $stmt_del = $pdo->prepare("DELETE FROM kendaraan_alat WHERE id = ?");
    $stmt_del->execute([$id]);
    echo "<script>alert('Data unit armada berhasil dihapus!'); window.location='data_unit.php';</script>";
    exit;
}

// Mengambil data unit yang terdaftar
$unit = [
    'kode_plat' => '',
    'nama' => '',
    'jenis' => 'kendaraan_roda_4',
    'kategori' => 'Kendaraan Operasional',
    'lokasi_ruas' => '1. Jatinegara - slawi',
    'merk' => '',
    'tahun' => date('Y'),
    'kondisi' => 'B',
    'interval_servis_bulan' => 3,
    'tgl_servis_terakhir' => date('Y-m-d'),
    'tgl_jatuh_tempo_pajak' => date('Y-m-d', strtotime('+1 year')),
    'tgl_jatuh_tempo_kir' => date('Y-m-d', strtotime('+6 months')),
    'no_chasis' => '',
    'no_mesin' => '',
    'no_bpkb' => '',
    'penanggung_jawab' => '',
    'catatan' => ''
];

if ($is_edit) {
    $stmt = $pdo->prepare("SELECT * FROM kendaraan_alat WHERE id = ?");
    $stmt->execute([$id]);
    $existing = $stmt->fetch();
    if ($existing) {
        $unit = array_merge($unit, $existing);
    } else {
        echo "<script>alert('Data unit tidak ditemukan!'); window.location='data_unit.php';</script>";
        exit;
    }
}

// Pemrosesan input formulir data
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kode_plat = trim($_POST['kode_plat'] ?? '');
    $nama = trim($_POST['nama'] ?? '');
    $jenis = $_POST['jenis'] ?? 'kendaraan_roda_4';
    $kategori = trim($_POST['kategori'] ?? '');
    
    // Penanganan lokasi ruas jalan kustom (pilihan ke-13)
    $lokasi_select = $_POST['lokasi_ruas'] ?? '';
    $lokasi_custom = trim($_POST['lokasi_ruas_custom'] ?? '');
    if (strpos($lokasi_select, '13. Tempat Lain') !== false || strpos($lokasi_select, 'Input Custom') !== false || strpos($lokasi_select, 'Tempat Lain') !== false) {
        $lokasi_ruas = !empty($lokasi_custom) ? ('Tempat Lain: ' . $lokasi_custom) : '13. Tempat Lain (Input Custom)';
    } else {
        $lokasi_ruas = $lokasi_select;
    }

    $merk = trim($_POST['merk'] ?? '');
    $tahun = (int)($_POST['tahun'] ?? date('Y'));
    $kondisi = $_POST['kondisi'] ?? 'B';
    $interval_servis_bulan = (int)($_POST['interval_servis_bulan'] ?? 3);
    $tgl_servis_terakhir = $_POST['tgl_servis_terakhir'] ?? date('Y-m-d');
    $tgl_jatuh_tempo_pajak = $_POST['tgl_jatuh_tempo_pajak'] ?? date('Y-m-d');
    $tgl_jatuh_tempo_kir = $_POST['tgl_jatuh_tempo_kir'] ?? date('Y-m-d');
    $no_chasis = trim($_POST['no_chasis'] ?? '');
    $no_mesin = trim($_POST['no_mesin'] ?? '');
    $no_bpkb = trim($_POST['no_bpkb'] ?? '');
    $penanggung_jawab = trim($_POST['penanggung_jawab'] ?? '');
    $catatan = trim($_POST['catatan'] ?? '');

    // Kalkulasi tanggal pemeliharaan dan status berikutnya
    if ($jenis === 'peralatan') {
        $tgl_servis_berikutnya = '0000-00-00';
    } else {
        $tgl_servis_berikutnya = hitung_tgl_servis_berikutnya($tgl_servis_terakhir, $interval_servis_bulan);
    }
    
    $info_sch = get_alisa_schedule_info($tgl_servis_berikutnya, 'Servis', $kondisi, $jenis);
    $status = $info_sch['status'];

    if ($is_edit) {
        $plat_saat_ini = trim($unit['kode_plat']);
        $plat_baru = strtoupper(trim($_POST['kode_plat_baru'] ?? ''));
        $alasan_ganti = trim($_POST['alasan_ganti_plat'] ?? '');
        if (empty($alasan_ganti)) {
            $alasan_ganti = 'Pergantian Nopol (Pajak STNK 5-Tahunan)';
        }

        if (!empty($plat_baru) && strcasecmp($plat_saat_ini, $plat_baru) !== 0) {
            catat_perubahan_plat($pdo, $id, $plat_saat_ini, $plat_baru, $alasan_ganti);
            $kode_plat = $plat_baru;
        } else {
            $kode_plat = $plat_saat_ini;
        }

        $sql = "UPDATE kendaraan_alat SET kode_plat = ?, nama = ?, jenis = ?, kategori = ?, lokasi_ruas = ?, merk = ?, tahun = ?, kondisi = ?, interval_servis_bulan = ?, tgl_servis_terakhir = ?, tgl_servis_berikutnya = ?, tgl_jatuh_tempo_pajak = ?, tgl_jatuh_tempo_kir = ?, no_chasis = ?, no_mesin = ?, no_bpkb = ?, penanggung_jawab = ?, status = ?, catatan = ? WHERE id = ?";
        $stmt_upd = $pdo->prepare($sql);
        $stmt_upd->execute([$kode_plat, $nama, $jenis, $kategori, $lokasi_ruas, $merk, $tahun, $kondisi, $interval_servis_bulan, $tgl_servis_terakhir, $tgl_servis_berikutnya, $tgl_jatuh_tempo_pajak, $tgl_jatuh_tempo_kir, $no_chasis, $no_mesin, $no_bpkb, $penanggung_jawab, $status, $catatan, $id]);
        echo "<script>alert('Data unit berhasil diperbarui!'); window.location='data_unit.php';</script>";
    } else {
        $kode_plat = strtoupper(trim($_POST['kode_plat'] ?? ''));
        $sql = "INSERT INTO kendaraan_alat (kode_plat, nama, jenis, kategori, lokasi_ruas, merk, tahun, kondisi, interval_servis_bulan, tgl_servis_terakhir, tgl_servis_berikutnya, tgl_jatuh_tempo_pajak, tgl_jatuh_tempo_kir, no_chasis, no_mesin, no_bpkb, penanggung_jawab, status, catatan) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_ins = $pdo->prepare($sql);
        $stmt_ins->execute([$kode_plat, $nama, $jenis, $kategori, $lokasi_ruas, $merk, $tahun, $kondisi, $interval_servis_bulan, $tgl_servis_terakhir, $tgl_servis_berikutnya, $tgl_jatuh_tempo_pajak, $tgl_jatuh_tempo_kir, $no_chasis, $no_mesin, $no_bpkb, $penanggung_jawab, $status, $catatan]);
        echo "<script>alert('Unit armada baru berhasil ditambahkan!'); window.location='data_unit.php';</script>";
    }
    exit;
}
?>

<div class="container-fluid p-0">
    <form method="POST" action="" id="unitForm">
        <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
            <div>
                <h4 class="fw-bold mb-1 text-white"><i class="fa-solid fa-truck-monster text-success me-2"></i> <?= $is_edit ? 'Edit Data Unit Armada' : 'Tambah Unit Armada Baru' ?></h4>
                <p class="text-white-50 small mb-0">Kelola rincian data kendaraan, peralatan, Nopol, dan lokasi ruas jalan BPJ Tegal.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="data_unit.php" class="btn btn-outline-secondary text-white rounded-pill px-3">
                    <i class="fa-solid fa-arrow-left me-2"></i> Batal / Kembali
                </a>
                <button type="submit" class="btn btn-success fw-bold px-4 rounded-pill shadow-lg" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); border: none;">
                    <i class="fa-solid fa-floppy-disk me-2"></i> Simpan Data Unit
                </button>
            </div>
        </div>

        <div class="card-custom p-4 mb-4">
            <div class="row g-3">
                <div class="col-md-5">
                    <label class="form-label fw-bold small text-white">Nama Unit Armada / Peralatan <span class="text-danger">*</span></label>
                    <input type="text" name="nama" class="form-control text-white bg-dark border-secondary" placeholder="Contoh: ISUZU TRUCK DUMP 6 RODA" value="<?= htmlspecialchars($unit['nama']) ?>" required>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-bold small text-white">Jenis Klasifikasi Unit <span class="text-danger">*</span></label>
                    <select name="jenis" id="jenisUnitSelect" class="form-select text-white bg-dark border-secondary" required onchange="toggleFormFields()">
                        <option value="kendaraan_roda_4" <?= ($unit['jenis'] === 'kendaraan_roda_4') ? 'selected' : '' ?>>Kendaraan Roda 4 (Pickup / Mobil)</option>
                        <option value="kendaraan_roda_6" <?= ($unit['jenis'] === 'kendaraan_roda_6') ? 'selected' : '' ?>>Kendaraan Roda 6 (Dump Truck)</option>
                        <option value="kendaraan_roda_3" <?= ($unit['jenis'] === 'kendaraan_roda_3') ? 'selected' : '' ?>>Kendaraan Roda 3 (VIAR Work 200)</option>
                        <option value="peralatan" <?= ($unit['jenis'] === 'peralatan') ? 'selected' : '' ?>>Peralatan / Alat Berat (Stamper, Roller, Mower)</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-bold small text-white">Kondisi Fisik Unit <span class="text-danger">*</span></label>
                    <select name="kondisi" class="form-select text-white bg-dark border-secondary" required>
                        <option value="B" <?= ($unit['kondisi'] === 'B') ? 'selected' : '' ?>>Baik (B) - Siap Operasional</option>
                        <option value="RR" <?= ($unit['kondisi'] === 'RR') ? 'selected' : '' ?>>Rusak Ringan (RR) - Perlu Maintenance</option>
                        <option value="RB" <?= ($unit['kondisi'] === 'RB') ? 'selected' : '' ?>>Rusak Berat (RB) - Non-Aktif / Gudang</option>
                    </select>
                </div>

                <?php if ($is_edit): ?>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small text-white-50">Nopol Saat Ini <i class="fa-solid fa-lock text-warning ms-1" title="Tidak dapat diubah secara langsung"></i></label>
                        <input type="text" class="form-control font-monospace fw-bold text-white-50 bg-secondary bg-opacity-25 border-secondary" value="<?= htmlspecialchars($unit['kode_plat']) ?>" readonly disabled>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-bold small text-warning"><i class="fa-solid fa-pen-to-square me-1"></i> Nopol Baru / Ganti Plat (Opsional)</label>
                        <input type="text" name="kode_plat_baru" class="form-control font-monospace fw-bold text-white bg-dark border-warning" placeholder="Isi hanya jika Nopol diganti (cth: H 8030 YZ)">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-bold small text-info"><i class="fa-solid fa-clock-rotate-left me-1"></i> Alasan Ganti Nopol (Opsional)</label>
                        <input type="text" name="alasan_ganti_plat" class="form-control text-white bg-dark border-secondary" placeholder="Contoh: Ganti Plat 5-Tahunan STNK">
                    </div>
                <?php else: ?>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small text-white">Nopol / Kode Unit Plat <span class="text-danger">*</span></label>
                        <input type="text" name="kode_plat" class="form-control font-monospace fw-bold text-white bg-dark border-secondary" placeholder="Contoh: G 8134 VW / PR/086/D.86" value="<?= htmlspecialchars($unit['kode_plat']) ?>" required>
                    </div>
                <?php endif; ?>

                <div class="col-md-4">
                    <label class="form-label fw-bold small text-white">Merk / Type / Seri</label>
                    <input type="text" name="merk" class="form-control text-white bg-dark border-secondary" placeholder="Contoh: Isuzu Elf / Viar 200cc" value="<?= htmlspecialchars($unit['merk']) ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-bold small text-white">Tahun Pembuatan</label>
                    <input type="number" name="tahun" class="form-control text-white bg-dark border-secondary" value="<?= htmlspecialchars($unit['tahun']) ?>">
                </div>

                <!-- 13 LOKASI RUAS JALAN BPJ TEGAL -->
                <div class="col-md-6">
                    <label class="form-label fw-bold small text-info"><i class="fa-solid fa-location-dot me-1"></i> Lokasi Ruas Jalan (13 Lokasi BPJ Tegal) <span class="text-danger">*</span></label>
                    <select name="lokasi_ruas" id="lokasiRuasSelect" class="form-select text-white bg-dark border-secondary" required onchange="toggleCustomRuas()">
                        <?php 
                        $ruas_options = get_lokasi_ruas_list();
                        $custom_val = '';
                        $is_custom_selected = false;

                        if (!empty($unit['lokasi_ruas'])) {
                            if (strpos($unit['lokasi_ruas'], 'Tempat Lain:') !== false) {
                                $is_custom_selected = true;
                                $custom_val = trim(str_replace(['Tempat Lain:', '13. Tempat Lain:'], '', $unit['lokasi_ruas']));
                            } elseif (!in_array($unit['lokasi_ruas'], $ruas_options) && $unit['lokasi_ruas'] !== '13. Tempat Lain (Input Custom)') {
                                $is_custom_selected = true;
                                $custom_val = $unit['lokasi_ruas'];
                            }
                        }

                        foreach ($ruas_options as $r_opt) {
                            $selected = '';
                            if ($is_custom_selected && (strpos($r_opt, '13. Tempat Lain') !== false || strpos($r_opt, 'Input Custom') !== false)) {
                                $selected = 'selected';
                            } elseif ($unit['lokasi_ruas'] === $r_opt) {
                                $selected = 'selected';
                            }
                            echo "<option value=\"" . htmlspecialchars($r_opt) . "\" $selected>" . htmlspecialchars($r_opt) . "</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="col-md-6 <?= $is_custom_selected ? '' : 'd-none' ?>" id="customRuasBox">
                    <label class="form-label fw-bold small text-warning">Input Nama Lokasi Custom <span class="text-danger">*</span></label>
                    <input type="text" name="lokasi_ruas_custom" class="form-control text-white bg-dark border-secondary" placeholder="Tuliskan nama lokasi ruas lain..." value="<?= htmlspecialchars($custom_val) ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-bold small text-white">Penanggung Jawab / Operator</label>
                    <input type="text" name="penanggung_jawab" class="form-control text-white bg-dark border-secondary" placeholder="Nama pengemudi / operator..." value="<?= htmlspecialchars($unit['penanggung_jawab']) ?>">
                </div>

                <!-- NOMOR DOKUMEN FAKTUR / BPKB / MESIN -->
                <div class="col-md-4">
                    <label class="form-label fw-bold small text-white">Nomor Rangka / Sertifikat</label>
                    <input type="text" name="no_chasis" class="form-control font-monospace text-white bg-dark border-secondary" value="<?= htmlspecialchars($unit['no_chasis']) ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-bold small text-white">Nomor Mesin</label>
                    <input type="text" name="no_mesin" class="form-control font-monospace text-white bg-dark border-secondary" value="<?= htmlspecialchars($unit['no_mesin']) ?>">
                </div>

                <div class="col-md-4" id="bpkbBox">
                    <label class="form-label fw-bold small text-info">Nomor BPKB (VIAR & Kendaraan)</label>
                    <input type="text" name="no_bpkb" class="form-control font-monospace text-white bg-dark border-secondary" value="<?= htmlspecialchars($unit['no_bpkb']) ?>">
                </div>

                <!-- JADWAL DOKUMEN & INTERVAL -->
                <div class="col-md-4" id="intervalBox">
                    <label class="form-label fw-bold small text-white">Interval Servis Berkala (Bulan) <span class="text-danger">*</span></label>
                    <select name="interval_servis_bulan" class="form-select text-white bg-dark border-secondary">
                        <option value="1" <?= ($unit['interval_servis_bulan'] == 1) ? 'selected' : '' ?>>1 Bulan Sekali</option>
                        <option value="2" <?= ($unit['interval_servis_bulan'] == 2) ? 'selected' : '' ?>>2 Bulan Sekali</option>
                        <option value="3" <?= ($unit['interval_servis_bulan'] == 3) ? 'selected' : '' ?>>3 Bulan Sekali (Default)</option>
                        <option value="4" <?= ($unit['interval_servis_bulan'] == 4) ? 'selected' : '' ?>>4 Bulan Sekali</option>
                        <option value="6" <?= ($unit['interval_servis_bulan'] == 6) ? 'selected' : '' ?>>6 Bulan Sekali</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-bold small text-white">Tgl Servis Terakhir</label>
                    <input type="date" name="tgl_servis_terakhir" class="form-control text-white bg-dark border-secondary" value="<?= htmlspecialchars($unit['tgl_servis_terakhir']) ?>">
                </div>

                <div class="col-md-4 tax-box">
                    <label class="form-label fw-bold small text-white">Jatuh Tempo Pajak STNK Tahunan</label>
                    <input type="date" name="tgl_jatuh_tempo_pajak" class="form-control text-white bg-dark border-secondary" value="<?= htmlspecialchars($unit['tgl_jatuh_tempo_pajak']) ?>">
                </div>

                <div class="col-md-4 tax-box">
                    <label class="form-label fw-bold small text-white">Jatuh Tempo Uji KIR Berkala</label>
                    <input type="date" name="tgl_jatuh_tempo_kir" class="form-control text-white bg-dark border-secondary" value="<?= htmlspecialchars($unit['tgl_jatuh_tempo_kir']) ?>">
                </div>

                <div class="col-12">
                    <label class="form-label fw-bold small text-white">Catatan Tambahan Unit</label>
                    <textarea name="catatan" class="form-control text-white bg-dark border-secondary" rows="2" placeholder="Catatan khusus kelengkapan atau riwayat fisik armada..."><?= htmlspecialchars($unit['catatan']) ?></textarea>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4 border-top border-secondary pt-3">
                <a href="data_unit.php" class="btn btn-secondary border px-4 rounded-pill">Batal</a>
                <button type="submit" class="btn btn-success fw-bold px-4 rounded-pill shadow-lg" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); border: none;">
                    <i class="fa-solid fa-floppy-disk me-2"></i> Simpan Data Unit
                </button>
            </div>
        </div>
    </form>

    <?php 
    if ($is_edit) {
        $riwayat_plat_list = get_riwayat_plat($pdo, $id);
        if (!empty($riwayat_plat_list)):
    ?>
    <div class="card-custom p-4 mt-4 mb-4">
        <h5 class="fw-bold text-white mb-3">
            <i class="fa-solid fa-clock-rotate-left text-warning me-2"></i> Riwayat Perubahan Nopol / Kode Plat Lama Unit Ini
        </h5>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr class="text-info small border-bottom border-secondary">
                        <th style="width: 5%;">No</th>
                        <th style="width: 20%;">Nopol Lama</th>
                        <th style="width: 20%;">Nopol Baru</th>
                        <th style="width: 20%;">Tanggal Perubahan</th>
                        <th style="width: 25%;">Keterangan / Alasan</th>
                        <th style="width: 10%;">Petugas</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($riwayat_plat_list as $idx => $rp): ?>
                        <tr>
                            <td class="text-white-50"><?= $idx + 1 ?></td>
                            <td class="fw-bold text-danger font-monospace">
                                <span class="badge bg-danger bg-opacity-25 text-danger px-2.5 py-1 rounded-pill"><?= htmlspecialchars($rp['plat_lama']) ?></span>
                            </td>
                            <td class="fw-bold text-success font-monospace">
                                <span class="badge bg-success bg-opacity-25 text-success px-2.5 py-1 rounded-pill"><?= htmlspecialchars($rp['plat_baru']) ?></span>
                            </td>
                            <td class="small text-white-50"><?= date('d M Y H:i', strtotime($rp['tgl_perubahan'])) ?></td>
                            <td class="text-white small"><?= htmlspecialchars($rp['keterangan'] ?: '-') ?></td>
                            <td><span class="badge bg-secondary rounded-pill"><?= htmlspecialchars($rp['diubah_oleh'] ?: 'Admin') ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php 
        endif;
    }
    ?>
</div>

<script>
function toggleCustomRuas() {
    let select = document.getElementById('lokasiRuasSelect');
    let box = document.getElementById('customRuasBox');
    if (select && box) {
        if (select.value.indexOf('13. Tempat Lain') !== -1 || select.value.indexOf('Input Custom') !== -1 || select.value.indexOf('Tempat Lain') !== -1) {
            box.classList.remove('d-none');
        } else {
            box.classList.add('d-none');
        }
    }
}

function toggleFormFields() {
    let jenis = document.getElementById('jenisUnitSelect').value;
    let taxBoxes = document.querySelectorAll('.tax-box');
    let intervalBox = document.getElementById('intervalBox');

    if (jenis === 'peralatan') {
        taxBoxes.forEach(el => el.classList.add('d-none'));
        intervalBox.classList.add('d-none');
    } else {
        taxBoxes.forEach(el => el.classList.remove('d-none'));
        intervalBox.classList.remove('d-none');
    }
}

document.addEventListener("DOMContentLoaded", function() {
    toggleCustomRuas();
    toggleFormFields();
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

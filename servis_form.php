<?php
require_once __DIR__ . '/includes/header.php';

// Strict Use Case Role Guard: Only Admin can perform Direct Service Form Action
if (!can_edit_data()) {
    echo "<script>alert('Akses Ditolak: Fitur Input/Edit Servis khusus untuk Admin. Untuk Teknisi silakan gunakan fitur Upload Nota Ke Admin.'); window.location='servis_kelola.php';</script>";
    exit;
}

$id_kendaraan_preset = (int)($_GET['id_kendaraan'] ?? 0);
$jenis_servis_preset = trim($_GET['jenis_servis'] ?? 'Penjadwalan');
$edit_id = (int)($_GET['edit_id'] ?? 0);
$edit_data = null;

if ($edit_id > 0) {
    $stmt_edit = $pdo->prepare("SELECT * FROM riwayat_servis WHERE id = ?");
    $stmt_edit->execute([$edit_id]);
    $edit_data = $stmt_edit->fetch();
    if (!$edit_data) {
        echo "<script>alert('Catatan riwayat servis tidak ditemukan.'); window.location='servis_kelola.php';</script>";
        exit;
    }
    $id_kendaraan_preset = (int)$edit_data['id_kendaraan'];
    $jenis_servis_preset = $edit_data['jenis_servis'];
}

// Fetch all units for dropdown
$stmt_u = $pdo->query("SELECT * FROM kendaraan_alat ORDER BY nama ASC");
$all_units = $stmt_u->fetchAll();

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_kendaraan = (int)($_POST['id_kendaraan'] ?? 0);
    $tgl_servis = $_POST['tgl_servis'] ?? date('Y-m-d');
    $jenis_servis = $_POST['jenis_servis'] ?? 'Penjadwalan';
    $nama_layanan = trim($_POST['nama_layanan'] ?? 'Servis Berkala & Maintenance');
    $total_biaya = (float)($_POST['total_biaya'] ?? 0);
    $nama_bengkel = trim($_POST['nama_bengkel'] ?? '');
    $catatan = trim($_POST['catatan'] ?? '');

    // Process Itemized Subtotals & Matrix Auto-Linking
    $item_selects = $_POST['item_select'] ?? [];
    $item_customs = $_POST['item_custom'] ?? [];
    $item_legacy  = $_POST['item_nama'] ?? [];
    $item_qtys    = $_POST['item_qty'] ?? [];
    $item_prices  = $_POST['item_harga'] ?? [];
    
    $rincian_lines = [];
    $calculated_total = 0;
    $matrix_linked_count = 0;
    $checklist_names = array_column(get_standard_checklist_items(), 0);

    $max_items = max(count($item_selects), count($item_legacy));

    for ($i = 0; $i < $max_items; $i++) {
        $sel = trim($item_selects[$i] ?? '');
        $cust = trim($item_customs[$i] ?? '');
        $leg = trim($item_legacy[$i] ?? '');

        $name = '';
        $matched_checklist_item = null;

        if (!empty($sel)) {
            if ($sel === 'Lain-lain') {
                $name = !empty($cust) ? $cust : 'Lain-lain';
            } else {
                $name = $sel;
                if (!empty($cust)) {
                    $name .= " ({$cust})";
                }
                $matched_checklist_item = $sel;
            }
        } elseif (!empty($leg)) {
            $name = $leg;
            if (in_array($leg, $checklist_names)) {
                $matched_checklist_item = $leg;
            }
        }

        $qty = (int)($item_qtys[$i] ?? 1);
        $price = (float)($item_prices[$i] ?? 0);
        
        if (!empty($name) && $qty > 0) {
            $subtotal = $qty * $price;
            $calculated_total += $subtotal;
            $rincian_lines[] = "• {$name} ({$qty} x Rp " . number_format($price, 0, ',', '.') . ") = Rp " . number_format($subtotal, 0, ',', '.');

            // Auto-Link to Matriks Penjadwalan Checklist Servis (Data 2)
            if ($matched_checklist_item && $id_kendaraan > 0 && !empty($tgl_servis)) {
                $stmt_chk = $pdo->prepare("SELECT id FROM servis_checklist WHERE id_kendaraan = ? AND item_servis = ? LIMIT 1");
                $stmt_chk->execute([$id_kendaraan, $matched_checklist_item]);
                $found_chk = $stmt_chk->fetch();

                if ($found_chk) {
                    $stmt_upd_chk = $pdo->prepare("UPDATE servis_checklist SET tgl_servis_terakhir = ? WHERE id = ?");
                    $stmt_upd_chk->execute([$tgl_servis, $found_chk['id']]);
                } else {
                    $stmt_ins_chk = $pdo->prepare("INSERT INTO servis_checklist (id_kendaraan, item_servis, tgl_servis_terakhir) VALUES (?, ?, ?)");
                    $stmt_ins_chk->execute([$id_kendaraan, $matched_checklist_item, $tgl_servis]);
                }
                $matrix_linked_count++;
            }
        }
    }

    $rincian_item = !empty($rincian_lines) ? implode("\n", $rincian_lines) : trim($_POST['rincian_manual'] ?? '');
    if (empty($rincian_item) && !empty($edit_data)) {
        $rincian_item = $edit_data['rincian_item'];
    }
    if ($calculated_total > 0) {
        $total_biaya = $calculated_total;
    } elseif ($total_biaya <= 0 && !empty($edit_data)) {
        $total_biaya = (float)$edit_data['total_biaya'];
    }

    // Handle File Upload (Foto Nota)
    $foto_nota = $edit_data['foto_nota'] ?? null;
    if (isset($_FILES['foto_nota']) && $_FILES['foto_nota']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/uploads/nota/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        $ext = pathinfo($_FILES['foto_nota']['name'], PATHINFO_EXTENSION);
        $new_foto = 'nota_' . time() . '_' . rand(1000, 9999) . '.' . strtolower($ext);
        if (move_uploaded_file($_FILES['foto_nota']['tmp_name'], $upload_dir . $new_foto)) {
            if (!empty($edit_data['foto_nota'])) {
                $old_file = $upload_dir . $edit_data['foto_nota'];
                if (file_exists($old_file)) {
                    @unlink($old_file);
                }
            }
            $foto_nota = $new_foto;
        }
    }

    if ($edit_id > 0) {
        $sql_upd = "UPDATE riwayat_servis 
                    SET id_kendaraan = ?, tgl_servis = ?, jenis_servis = ?, nama_layanan = ?, rincian_item = ?, total_biaya = ?, nama_bengkel = ?, foto_nota = ?, catatan = ? 
                    WHERE id = ?";
        $stmt_upd = $pdo->prepare($sql_upd);
        $stmt_upd->execute([$id_kendaraan, $tgl_servis, $jenis_servis, $nama_layanan, $rincian_item, $total_biaya, $nama_bengkel, $foto_nota, $catatan, $edit_id]);
        $alert_msg = "Data riwayat servis berhasil diperbarui!";
    } else {
        $sql_ins = "INSERT INTO riwayat_servis (id_kendaraan, tgl_servis, jenis_servis, nama_layanan, rincian_item, total_biaya, nama_bengkel, foto_nota, catatan) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_ins = $pdo->prepare($sql_ins);
        $stmt_ins->execute([$id_kendaraan, $tgl_servis, $jenis_servis, $nama_layanan, $rincian_item, $total_biaya, $nama_bengkel, $foto_nota, $catatan]);
        $alert_msg = "Pencatatan Servis Rincian Subtotal & Upload Nota Berhasil!";
    }

    // Update Last Service Date & Next Service Date in kendaraan_alat
    $stmt_fetch = $pdo->prepare("SELECT * FROM kendaraan_alat WHERE id = ?");
    $stmt_fetch->execute([$id_kendaraan]);
    $k_unit = $stmt_fetch->fetch();

    if ($k_unit) {
        $interval = (int)$k_unit['interval_servis_bulan'];
        $tgl_next = ($k_unit['jenis'] === 'peralatan') ? '0000-00-00' : hitung_tgl_servis_berikutnya($tgl_servis, $interval);
        $info_next = get_alisa_schedule_info($tgl_next, 'Servis', $k_unit['kondisi'], $k_unit['jenis']);

        $stmt_upd = $pdo->prepare("UPDATE kendaraan_alat SET tgl_servis_terakhir = ?, tgl_servis_berikutnya = ?, status = ? WHERE id = ?");
        $stmt_upd->execute([$tgl_servis, $tgl_next, $info_next['status'], $id_kendaraan]);

        if ($k_unit['jenis'] === 'peralatan') {
            $interval_jam = (int)($k_unit['interval_jam_servis'] ?: 1000);
            $stmt_reset_jam = $pdo->prepare("UPDATE kendaraan_alat SET sisa_jam_servis = ? WHERE id = ?");
            $stmt_reset_jam->execute([$interval_jam, $id_kendaraan]);
        }

        if ($edit_id == 0) {
            // Send Automatic WA Confirmation on new entry
            $setting_wa = get_whatsapp_setting($pdo);
            $tgl_next_formatted = format_tgl_indo($tgl_next);
            $wa_conf = "*✅ UPDATE SERVIS BERHASIL - ALISA BPJ TEGAL*\n";
            $wa_conf .= "Armada: *{$k_unit['nama']} ({$k_unit['kode_plat']})*\n";
            $wa_conf .= "Jenis Servis: *{$jenis_servis}*\n";
            $wa_conf .= "Bengkel: {$nama_bengkel}\n";
            $wa_conf .= "Grand Total: " . format_rupiah($total_biaya) . "\n";
            $wa_conf .= "--------------------------------------\n";
            if ($k_unit['jenis'] !== 'peralatan') {
                $wa_conf .= "Jadwal Servis Berkala Berikutnya: *{$tgl_next_formatted}*";
            }

            send_whatsapp_msg($setting_wa['api_token'], $setting_wa['target_phone'], $wa_conf);
        }
    }

    if ($matrix_linked_count > 0 && $edit_id == 0) {
        $alert_msg .= "\\n\\n[Auto-Link] {$matrix_linked_count} item tanggal Matriks Penjadwalan Checklist Servis berhasil diperbarui secara otomatis!";
    }
    echo "<script>alert('{$alert_msg}'); window.location='servis_kelola.php';</script>";
    exit;
}
?>

<div class="container-fluid p-0">
    <form method="POST" action="" enctype="multipart/form-data">
        <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
            <div>
                <h4 class="fw-bold mb-1 text-white">
                    <?php if ($edit_id > 0): ?>
                        <i class="fa-solid fa-pen-to-square text-warning me-2"></i> Edit Catatan Servis & Nota (#<?= $edit_id ?>)
                    <?php else: ?>
                        <i class="fa-solid fa-wrench text-info me-2"></i> Melakukan Servis & Upload Nota
                    <?php endif; ?>
                </h4>
                <p class="text-white-50 small mb-0">
                    <?php if ($edit_id > 0): ?>
                        Perbarui rincian item, biaya, nama bengkel, tanggal, atau foto nota transaksi servis ini.
                    <?php else: ?>
                        Input data servis rutin (Penjadwalan) maupun perbaikan darurat dengan pencarian unit instan.
                    <?php endif; ?>
                </p>
            </div>
            <div class="d-flex gap-2">
                <a href="servis_kelola.php" class="btn btn-outline-secondary text-white rounded-pill px-3">
                    <i class="fa-solid fa-arrow-left me-2"></i> Batal / Kembali
                </a>
                <button type="submit" class="btn <?= ($edit_id > 0) ? 'btn-warning text-dark' : 'btn-success text-white' ?> fw-bold px-4 rounded-pill shadow-lg" style="border: none;">
                    <i class="fa-solid fa-floppy-disk me-2"></i> <?= ($edit_id > 0) ? 'Simpan Perubahan Servis' : 'Simpan Servis & Nota' ?>
                </button>
            </div>
        </div>

        <div class="card-custom p-4">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold small text-info"><i class="fa-solid fa-search me-1"></i> Pilih Unit Armada (Ketik Nopol / Nama / Ruas) <span class="text-danger">*</span></label>
                    <select name="id_kendaraan" id="selectUnitArmada" class="form-select" required>
                        <option value="">-- Ketik Nopol / Nama Unit --</option>
                        <?php foreach ($all_units as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= ($id_kendaraan_preset === (int)$u['id']) ? 'selected' : '' ?>>
                                [<?= htmlspecialchars($u['kode_plat']) ?>] <?= htmlspecialchars($u['nama']) ?> &bull; Ruas: <?= htmlspecialchars($u['lokasi_ruas']) ?> (Kondisi: <?= $u['kondisi'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-bold small text-white">Tanggal Pelaksanaan Servis <span class="text-danger">*</span></label>
                    <input type="date" name="tgl_servis" class="form-control" value="<?= htmlspecialchars($edit_data['tgl_servis'] ?? date('Y-m-d')) ?>" required>
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-bold small text-white">Kategori Servis <span class="text-danger">*</span></label>
                    <select name="jenis_servis" class="form-select" required>
                        <option value="Penjadwalan" <?= ($jenis_servis_preset === 'Penjadwalan') ? 'selected' : '' ?>>Penjadwalan (Servis Rutin Berkala)</option>
                        <option value="Darurat" <?= ($jenis_servis_preset === 'Darurat') ? 'selected' : '' ?>>Darurat (Perbaikan Mendadak/Mogok)</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-bold small text-white">Nama Layanan / Servis <span class="text-danger">*</span></label>
                    <input type="text" name="nama_layanan" class="form-control" placeholder="Contoh: Servis Ganti Oli Engine / Perbaikan Rem Hydraulics" value="<?= htmlspecialchars($edit_data['nama_layanan'] ?? '') ?>" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-bold small text-white">Nama Bengkel / Penyedia</label>
                    <input type="text" name="nama_bengkel" class="form-control" placeholder="Contoh: Bengkel Resmi Isuzu / Workshop BPJ Tegal" value="<?= htmlspecialchars($edit_data['nama_bengkel'] ?? '') ?>">
                </div>

                <!-- DYNAMIC RINCIAN ITEM & SUBTOTAL TABLE - FULLY HARMONIZED THEME -->
                <div class="col-12 mt-4">
                    <div class="p-3.5 rounded-4 border alisa-item-box shadow-sm">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <h6 class="fw-bold m-0 alisa-item-title"><i class="fa-solid fa-list-check text-info me-2"></i> Rincian Sparepart / Item Nota & Hitung Subtotal</h6>
                            <button type="button" onclick="addSubtotalRow()" class="btn btn-sm btn-outline-secondary text-info rounded-pill fw-bold px-3">
                                <i class="fa-solid fa-plus me-1"></i> Tambah Baris Item
                            </button>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-bordered align-middle mb-2" id="tableSubtotal">
                                <thead>
                                    <tr>
                                        <th style="width: 40%;">Nama Sparepart / Komponen / Layanan</th>
                                        <th style="width: 15%;">Jumlah (Qty)</th>
                                        <th style="width: 20%;">Harga Satuan (Rp)</th>
                                        <th style="width: 20%;">Subtotal (Rp)</th>
                                        <th style="width: 5%;" class="text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody id="subtotalBody">
                                    <tr>
                                        <td>
                                            <select name="item_select[]" class="form-select form-control-sm item-select mb-1" onchange="toggleItemCustomInput(this)" required>
                                                <option value="">-- Pilih Sparepart / Komponen / Layanan --</option>
                                                <optgroup label="Matriks Checklist Penjadwalan Servis">
                                                    <?php foreach (get_standard_checklist_items() as $c_item): ?>
                                                        <option value="<?= htmlspecialchars($c_item[0]) ?>"><?= htmlspecialchars($c_item[0]) ?></option>
                                                    <?php endforeach; ?>
                                                </optgroup>
                                                <option value="Lain-lain" <?= ($edit_id > 0) ? 'selected' : '' ?>>Lain-lain (Input Manual Sparepart/Layanan)</option>
                                            </select>
                                            <input type="text" name="item_custom[]" class="form-control form-control-sm item-custom" placeholder="Sebutkan Nama Sparepart / Layanan..." value="<?= htmlspecialchars($edit_data['nama_layanan'] ?? '') ?>" style="<?= ($edit_id > 0) ? 'display:block;' : 'display:none;' ?>">
                                        </td>
                                        <td><input type="number" name="item_qty[]" class="form-control form-control-sm item-qty text-center" value="1" min="1" oninput="calcSubtotal()" required></td>
                                        <td><input type="number" step="0.01" name="item_harga[]" class="form-control form-control-sm item-harga" placeholder="0" value="<?= (float)($edit_data['total_biaya'] ?? 0) ?>" oninput="calcSubtotal()" required></td>
                                        <td><input type="text" class="form-control form-control-sm item-subtotal font-monospace fw-bold" readonly value="Rp 0"></td>
                                        <td class="text-center"><button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="removeRow(this)"><i class="fa-solid fa-trash"></i></button></td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="3" class="text-end fw-bold text-white">GRAND TOTAL BIAYA (JUMLAH SUBTOTAL):</td>
                                        <td colspan="2">
                                            <input type="text" id="displayGrandTotal" class="form-control form-control-sm font-monospace fs-5 fw-extrabold text-info" readonly value="Rp 0">
                                            <input type="hidden" name="total_biaya" id="inputGrandTotal" value="<?= (float)($edit_data['total_biaya'] ?? 0) ?>">
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 mt-3">
                    <label class="form-label fw-bold small text-info"><i class="fa-solid fa-file-arrow-up me-1"></i> Upload Foto Nota Servis (Gambar/JPG/PNG/PDF)</label>
                    <input type="file" name="foto_nota" class="form-control" accept="image/*,.pdf">
                    <?php if (!empty($edit_data['foto_nota'])): ?>
                        <div class="mt-2 text-white-50 small">
                            Foto Nota Saat Ini: <a href="uploads/nota/<?= htmlspecialchars($edit_data['foto_nota']) ?>" target="_blank" class="text-info fw-bold"><i class="fa-solid fa-image me-1"></i> Lihat Foto Nota</a> <span class="text-muted">(Biarkan kosong jika tidak ingin mengubah foto)</span>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="col-md-6 mt-3">
                    <label class="form-label fw-bold small text-white">Catatan Tambahan</label>
                    <input type="text" name="catatan" class="form-control" placeholder="Catatan khusus kondisi armada setelah diservis..." value="<?= htmlspecialchars($edit_data['catatan'] ?? '') ?>">
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4 border-top border-secondary pt-3">
                <a href="servis_kelola.php" class="btn btn-secondary border px-4 rounded-pill">Batal</a>
                <button type="submit" class="btn <?= ($edit_id > 0) ? 'btn-warning text-dark' : 'btn-success text-white' ?> fw-bold px-4 rounded-pill shadow-lg" style="border: none;">
                    <i class="fa-solid fa-paper-plane me-2"></i> <?= ($edit_id > 0) ? 'Simpan Perubahan Servis' : 'Simpan & Kirim Konfirmasi WA' ?>
                </button>
            </div>
        </div>
    </form>
</div>

<script>
$(document).ready(function() {
    $('#selectUnitArmada').select2({
        theme: 'bootstrap-5',
        placeholder: '-- Ketik Nopol / Nama Unit Armada --',
        allowClear: true,
        width: '100%'
    });
    calcSubtotal();
});

function toggleItemCustomInput(selectElem) {
    let row = selectElem.closest('tr');
    let customInput = row.querySelector('.item-custom');
    if (!customInput) return;

    let val = selectElem.value;
    if (val === 'Lain-lain') {
        customInput.style.display = 'block';
        customInput.setAttribute('required', 'required');
        customInput.placeholder = 'Masukkan Nama Sparepart / Komponen (Wajib)...';
        customInput.focus();
    } else if (val !== '') {
        customInput.style.display = 'block';
        customInput.removeAttribute('required');
        customInput.placeholder = 'Detail / Merk Spesifikasi (Opsional, contoh: Shell 15W-40)...';
    } else {
        customInput.style.display = 'none';
        customInput.removeAttribute('required');
        customInput.value = '';
    }
}

function calcSubtotal() {
    let rows = document.querySelectorAll('#subtotalBody tr');
    let grandTotal = 0;

    rows.forEach(row => {
        let qty = parseFloat(row.querySelector('.item-qty').value) || 0;
        let harga = parseFloat(row.querySelector('.item-harga').value) || 0;
        let subtotal = qty * harga;
        grandTotal += subtotal;

        row.querySelector('.item-subtotal').value = 'Rp ' + subtotal.toLocaleString('id-ID');
    });

    document.getElementById('displayGrandTotal').value = 'Rp ' + grandTotal.toLocaleString('id-ID');
    document.getElementById('inputGrandTotal').value = grandTotal;
}

function addSubtotalRow() {
    let tbody = document.getElementById('subtotalBody');
    let newRow = document.createElement('tr');
    
    let optionsHtml = '<option value="">-- Pilih Sparepart / Komponen / Layanan --</option><optgroup label="Matriks Checklist Penjadwalan Servis">';
    <?php foreach (get_standard_checklist_items() as $c_item): ?>
        optionsHtml += '<option value="<?= addslashes(htmlspecialchars($c_item[0])) ?>"><?= addslashes(htmlspecialchars($c_item[0])) ?></option>';
    <?php endforeach; ?>
    optionsHtml += '</optgroup><option value="Lain-lain">Lain-lain (Input Manual Sparepart/Layanan)</option>';

    newRow.innerHTML = `
        <td>
            <select name="item_select[]" class="form-select form-control-sm item-select mb-1" onchange="toggleItemCustomInput(this)" required>
                ${optionsHtml}
            </select>
            <input type="text" name="item_custom[]" class="form-control form-control-sm item-custom" placeholder="Sebutkan Nama Sparepart / Layanan..." style="display:none;">
        </td>
        <td><input type="number" name="item_qty[]" class="form-control form-control-sm item-qty text-center" value="1" min="1" oninput="calcSubtotal()" required></td>
        <td><input type="number" step="0.01" name="item_harga[]" class="form-control form-control-sm item-harga" placeholder="0" oninput="calcSubtotal()" required></td>
        <td><input type="text" class="form-control form-control-sm item-subtotal font-monospace fw-bold" readonly value="Rp 0"></td>
        <td class="text-center"><button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="removeRow(this)"><i class="fa-solid fa-trash"></i></button></td>
    `;
    tbody.appendChild(newRow);
    calcSubtotal();
}

function removeRow(btn) {
    let tbody = document.getElementById('subtotalBody');
    if (tbody.children.length > 1) {
        btn.closest('tr').remove();
        calcSubtotal();
    } else {
        alert('Minimal harus ada 1 baris item.');
    }
}

// Enable Searchable Select2 Dropdown for Servis Form
$(document).ready(function() {
    if (typeof $.fn.select2 !== 'undefined') {
        $('#selectUnitArmada').select2({
            theme: 'bootstrap-5',
            placeholder: '-- Ketik Nopol / Nama Unit --',
            allowClear: true,
            width: '100%'
        });
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

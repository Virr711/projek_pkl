<?php
require_once __DIR__ . '/includes/header.php';

// Strict Use Case Role Guard: Admin & Bendahara
if ($current_role !== 'admin' && $current_role !== 'bendahara') {
    echo "<script>alert('Akses Ditolak: Fitur Notifikasi khusus untuk Admin & Bendahara.'); window.location='index.php';</script>";
    exit;
}

$tab = $_GET['tab'] ?? 'alert';
$action_msg = '';

// Handle Manual WhatsApp Broadcast Trigger
if (isset($_POST['action_broadcast'])) {
    $res = broadcast_notif_h30_whatsapp($pdo, true);
    if ($res['success']) {
        $action_msg = "<div class='alert alert-success rounded-4 border-0 shadow-sm'><i class='fa-brands fa-whatsapp fs-4 me-2'></i> " . htmlspecialchars($res['message']) . "</div>";
    } else {
        $action_msg = "<div class='alert alert-warning rounded-4 border-0 shadow-sm'><i class='fa-solid fa-triangle-exclamation fs-4 me-2'></i> " . htmlspecialchars($res['message']) . " <a href='" . ($res['direct_link'] ?? '#') . "' target='_blank' class='btn btn-sm btn-dark rounded-pill ms-2'>Buka WA Manual <i class='fa-solid fa-arrow-right ms-1'></i></a></div>";
    }
}

// Handle Update WhatsApp Settings
if (isset($_POST['save_wa_setting'])) {
    $api_token = trim($_POST['api_token'] ?? '');
    $target_phone = trim($_POST['target_phone'] ?? '');
    $notif_h7_aktif = isset($_POST['notif_h7_aktif']) ? 1 : 0;

    $stmt_upd = $pdo->prepare("UPDATE pengaturan_whatsapp SET api_token = ?, target_phone = ?, notif_h7_aktif = ? WHERE id = 1");
    $stmt_upd->execute([$api_token, $target_phone, $notif_h7_aktif]);

    $action_msg = "<div class='alert alert-info rounded-4 border-0 shadow-sm'><i class='fa-solid fa-check-circle me-2'></i> Pengaturan WhatsApp Gateway berhasil diperbarui!</div>";
}

$wa_setting = get_whatsapp_setting($pdo);

// Fetch All Non-RB Units for Operational Alert Notifications
$stmt_all = $pdo->query("SELECT * FROM kendaraan_alat WHERE kondisi != 'RB' ORDER BY tgl_servis_berikutnya ASC");
$all_units = $stmt_all->fetchAll();

$alert_notifications = [];

foreach ($all_units as $u) {
    $inf_s = get_alisa_schedule_info($u['tgl_servis_berikutnya'], 'Servis', $u['kondisi'], $u['jenis']);
    $inf_p = get_alisa_schedule_info($u['tgl_jatuh_tempo_pajak'], 'Pajak STNK', $u['kondisi'], $u['jenis']);
    $inf_k = get_alisa_schedule_info($u['tgl_jatuh_tempo_kir'], 'Uji KIR', $u['kondisi'], $u['jenis']);

    if ($inf_s['is_h30']) {
        $alert_notifications[] = [
            'unit' => $u,
            'kategori' => 'Servis Armada',
            'info' => $inf_s,
            'target_date' => $u['tgl_servis_berikutnya'],
            'action_link' => 'servis_form.php?id_kendaraan=' . $u['id'] . '&jenis_servis=Penjadwalan',
            'action_label' => 'Update Servis'
        ];
    }
    if ($inf_p['is_h30']) {
        $alert_notifications[] = [
            'unit' => $u,
            'kategori' => 'Pajak STNK',
            'info' => $inf_p,
            'target_date' => $u['tgl_jatuh_tempo_pajak'],
            'action_link' => 'pembayaran_pajak_kir.php?id_kendaraan=' . $u['id'],
            'action_label' => 'Bayar Pajak'
        ];
    }
    if ($inf_k['is_h30']) {
        $alert_notifications[] = [
            'unit' => $u,
            'kategori' => 'Uji KIR',
            'info' => $inf_k,
            'target_date' => $u['tgl_jatuh_tempo_kir'],
            'action_link' => 'pembayaran_pajak_kir.php?id_kendaraan=' . $u['id'],
            'action_label' => 'Bayar KIR'
        ];
    }
}

// Fetch Pending Nota Uploads from Teknisi
try {
    $stmt_p_nota = $pdo->query("SELECT n.*, COALESCE(k.nama, 'Unit Armada') as nama_unit, COALESCE(k.kode_plat, '-') as kode_plat FROM nota_teknisi n LEFT JOIN kendaraan_alat k ON n.id_kendaraan = k.id WHERE n.status = 'Menunggu Verifikasi' ORDER BY n.id DESC");
    $pending_notas_notif = $stmt_p_nota ? $stmt_p_nota->fetchAll() : [];
    foreach ($pending_notas_notif as $pn) {
        $alert_notifications[] = [
            'unit' => [
                'nama' => $pn['nama_unit'],
                'kode_plat' => $pn['kode_plat'],
                'lokasi_ruas' => $pn['nama_bengkel'] ?: 'Bengkel Teknisi',
                'id' => $pn['id_kendaraan']
            ],
            'kategori' => 'Verifikasi Nota Teknisi',
            'info' => [
                'status' => 'Menunggu Verifikasi',
                'badge_class' => 'bg-warning text-dark fw-bold',
                'icon' => 'fa-file-invoice',
                'label' => 'Perlu Verifikasi Admin'
            ],
            'target_date' => $pn['tgl_nota'],
            'action_link' => 'servis_kelola.php',
            'action_label' => 'Verifikasi Nota'
        ];
    }
} catch (Exception $e) {}
?>

<div class="container-fluid p-0">
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
        <div>
            <h4 class="fw-bold mb-1 text-white"><i class="fa-solid fa-bell text-info me-2"></i> Pusat Notifikasi & Alert Operasional</h4>
            <p class="text-white-50 small mb-0">Pengingat otomatis H-30 (Mingguan) & H-7 (Harian) untuk Servis, Pajak STNK & WhatsApp Gateway.</p>
        </div>
    </div>

    <?= $action_msg ?>

    <!-- PPDB-Style Interactive Sub-Menu Card Grid for Notifikasi -->
    <div class="ppdb-submenu-grid mb-4">
        <!-- Submenu 1: Alert Operasional -->
        <a href="notifikasi_alert.php" class="ppdb-card" style="background-image: url('assets/images/card_alert.jpg?v=<?= time() ?>');">
            <div class="ppdb-card-overlay"></div>
            <div class="ppdb-card-content">
                <span class="ppdb-card-badge" style="background-color: #ef4444;"><i class="fa-solid fa-triangle-exclamation me-1"></i> Alert Jatuh Tempo</span>
                <div class="ppdb-card-body-text">
                    <h5 class="ppdb-card-title">Daftar Alert Operasional</h5>
                    <p class="ppdb-card-desc">Notifikasi Armada mendekati Servis & Pajak STNK (H-30 s/d H-7).</p>
                </div>
                <div class="ppdb-card-footer">
                    <span class="ppdb-card-meta" style="color: #f87171 !important;">Aktif: <?= count($alert_notifications) ?> Alert</span>
                    <span class="ppdb-card-action" style="color: #f87171 !important;">Buka Halaman Alert <i class="fa-solid fa-arrow-right"></i></span>
                </div>
            </div>
        </a>

        <!-- Submenu 2: WhatsApp Gateway Settings -->
        <a href="notifikasi_wa.php" class="ppdb-card" style="background-image: url('assets/images/card_wa.jpg?v=<?= time() ?>');">
            <div class="ppdb-card-overlay"></div>
            <div class="ppdb-card-content">
                <span class="ppdb-card-badge" style="background-color: #10b981;"><i class="fa-brands fa-whatsapp me-1"></i> WA Gateway</span>
                <div class="ppdb-card-body-text">
                    <h5 class="ppdb-card-title">Pengaturan WhatsApp</h5>
                    <p class="ppdb-card-desc">Konfigurasi Server Gateway, Nomor Penerima Alert & Trigger Broadcast WA.</p>
                </div>
                <div class="ppdb-card-footer">
                    <span class="ppdb-card-meta" style="color: #34d399 !important;">Port 3000 / Fonnte</span>
                    <span class="ppdb-card-action" style="color: #34d399 !important;">Buka Halaman WA Gateway <i class="fa-solid fa-arrow-right"></i></span>
                </div>
            </div>
        </a>
    </div>

    <?php if ($tab === 'whatsapp'): ?>
        <!-- SECTION 2: WHATSAPP GATEWAY SETTINGS & MANUAL BROADCAST -->
        <div class="card-custom p-4 mb-4">
            <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3 border-bottom border-secondary pb-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle p-3 d-flex align-items-center justify-content-center" style="width: 52px; height: 52px; background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.3);">
                        <i class="fa-brands fa-whatsapp text-success fs-2"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold text-white mb-0">Konfigurasi Gateway Notifikasi WhatsApp</h5>
                        <p class="text-white-50 small mb-0">Otomatisasi pengiriman pesan pengingat ke HP Pengelola / Pimpinan BPJ Tegal.</p>
                    </div>
                </div>

                <form method="POST" action="">
                    <button type="submit" name="action_broadcast" value="1" class="btn btn-wa-broadcast rounded-pill px-4 py-2 shadow-sm d-flex align-items-center gap-2">
                        <i class="fa-brands fa-whatsapp fs-5"></i> Kirim Notifikasi WA Sekarang
                    </button>
                </form>
            </div>

            <form method="POST" action="">
                <div class="row g-4">
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-info"><i class="fa-solid fa-phone me-1"></i> Nomor HP Tujuan Notifikasi (Nomor WA BPJ Tegal) <span class="text-danger">*</span></label>
                        <input type="text" name="target_phone" class="form-control font-monospace fs-6" value="<?= htmlspecialchars($wa_setting['target_phone'] ?? '082225352170') ?>" required placeholder="Contoh: 082225352170">
                        <span class="text-white-50 small d-block mt-1">Pesan pengingat otomatis H-30 & H-7 akan dikirimkan ke nomor WhatsApp ini.</span>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-white"><i class="fa-solid fa-key me-1"></i> Token API Cloud Fonnte (Backup Gateway)</label>
                        <input type="text" name="api_token" class="form-control font-monospace small" value="<?= htmlspecialchars($wa_setting['api_token'] ?? '') ?>" placeholder="Isi token jika menggunakan Fonnte Cloud Backup...">
                        <span class="text-white-50 small d-block mt-1">Local Server Gateway (Port 3000) digunakan secara gratis & otomatis.</span>
                    </div>

                    <div class="col-12">
                        <div class="form-check form-switch p-3 rounded-3 border border-secondary" style="background-color: #1a1e22;">
                            <input class="form-check-input ms-0 me-3" type="checkbox" name="notif_h7_aktif" id="switchNotif" value="1" <?= ($wa_setting['notif_h7_aktif']) ? 'checked' : '' ?> style="transform: scale(1.3);">
                            <label class="form-check-label fw-bold text-white align-middle" for="switchNotif">
                                Aktifkan Pengiriman Pengingat Otomatis Harian (H-7) & Mingguan (H-30)
                            </label>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end mt-4">
                    <button type="submit" name="save_wa_setting" value="1" class="btn btn-bpj-primary rounded-pill px-4 shadow">
                        <i class="fa-solid fa-check me-2"></i> Simpan Pengaturan WhatsApp
                    </button>
                </div>
            </form>
        </div>

    <?php else: ?>
        <!-- SECTION 1: ACTIVE OPERATIONAL ALERTS LIST -->
        <div class="card-custom p-4">
            <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                <h6 class="fw-bold text-white m-0"><i class="fa-solid fa-triangle-exclamation text-warning me-2"></i> Daftar Notifikasi Alert Mendekati Jatuh Tempo (H-30 / H-7)</h6>
                <span class="badge bg-danger rounded-pill px-3 py-1"><?= count($alert_notifications) ?> Item Perlu Tindakan</span>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="text-nowrap" style="width: 14%;">Nopol / Kode</th>
                            <th class="text-nowrap" style="width: 22%;">Nama Unit Armada</th>
                            <th class="text-nowrap" style="width: 15%;">Jenis Alert</th>
                            <th class="text-nowrap" style="width: 14%;">Jatuh Tempo</th>
                            <th class="text-nowrap" style="width: 15%;">Status / Pengingat</th>
                            <th class="text-nowrap" style="width: 12%;">Penanggung Jawab</th>
                            <th class="text-nowrap text-end" style="width: 8%;">Tindakan Langsung</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($alert_notifications)): ?>
                            <tr><td colspan="7" class="text-center text-muted py-5">Semua armada dalam kondisi baik. Tidak ada jadwal mendekati H-30 atau H-7 pada hari ini.</td></tr>
                        <?php else: ?>
                            <?php foreach ($alert_notifications as $item): 
                                $u = $item['unit'];
                                $inf = $item['info'];
                            ?>
                                <tr>
                                    <td class="fw-bold text-white font-monospace text-nowrap"><?= htmlspecialchars($u['kode_plat']) ?></td>
                                    <td>
                                        <div class="fw-bold text-white"><?= htmlspecialchars($u['nama']) ?></div>
                                        <span class="small text-info"><i class="fa-solid fa-location-dot me-1"></i> <?= htmlspecialchars($u['lokasi_ruas']) ?></span>
                                    </td>
                                    <td>
                                        <?php if ($item['kategori'] === 'Servis Armada'): ?>
                                            <span class="badge bg-warning text-dark rounded-pill px-2.5 py-1"><i class="fa-solid fa-wrench me-1"></i> Servis Armada</span>
                                        <?php elseif ($item['kategori'] === 'Pajak STNK'): ?>
                                            <span class="badge bg-danger text-white rounded-pill px-2.5 py-1"><i class="fa-solid fa-file-invoice-dollar me-1"></i> Pajak STNK</span>
                                        <?php else: ?>
                                            <span class="badge bg-success text-white rounded-pill px-2.5 py-1"><i class="fa-solid fa-clipboard-check me-1"></i> Uji KIR</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="fw-bold text-white text-nowrap"><?= format_tgl_indo($item['target_date']) ?></td>
                                    <td class="text-nowrap">
                                        <span class="badge <?= $inf['badge_class'] ?> rounded-pill px-3 py-1"><?= $inf['label'] ?></span>
                                    </td>
                                    <td class="small text-white text-nowrap"><?= htmlspecialchars($u['penanggung_jawab'] ?: '-') ?></td>
                                    <td class="text-end text-nowrap">
                                        <a href="<?= $item['action_link'] ?>" class="btn btn-sm btn-bpj-primary rounded-pill px-3 shadow-sm">
                                            <i class="fa-solid fa-arrow-right me-1"></i> <?= $item['action_label'] ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

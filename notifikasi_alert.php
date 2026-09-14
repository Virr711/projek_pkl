<?php
require_once __DIR__ . '/includes/header.php';

// Strict Role Guard
if ($current_role !== 'admin' && $current_role !== 'bendahara') {
    echo "<script>alert('Akses Ditolak: Halaman Notifikasi khusus untuk Admin & Bendahara.'); window.location='index.php';</script>";
    exit;
}

// Fetch All Operational Alert Notifications (H-30 & H-7)
$all_units_query = $pdo->query("SELECT * FROM kendaraan_alat WHERE kondisi != 'RB' ORDER BY tgl_servis_berikutnya ASC");
$all_units = $all_units_query->fetchAll();

$alert_notifications = [];
foreach ($all_units as $u) {
    $inf_s = get_alisa_schedule_info($u['tgl_servis_berikutnya'], 'Servis', $u['kondisi'], $u['jenis']);
    if ($inf_s['is_h30']) {
        $alert_notifications[] = [
            'unit' => $u,
            'kategori' => 'Servis Armada',
            'info' => $inf_s,
            'target_date' => $u['tgl_servis_berikutnya'],
            'action_link' => 'servis_form.php?id_kendaraan=' . $u['id'],
            'action_label' => 'Update Servis'
        ];
    }

    $inf_p = get_alisa_schedule_info($u['tgl_jatuh_tempo_pajak'], 'Pajak STNK', $u['kondisi'], $u['jenis']);
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

    $inf_k = get_alisa_schedule_info($u['tgl_jatuh_tempo_kir'], 'Uji KIR', $u['kondisi'], $u['jenis']);
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
?>

<div class="container-fluid p-0">
    <!-- Distinct Header with Breadcrumb & Back Button -->
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="notifikasi.php" class="text-info text-decoration-none"><i class="fa-solid fa-bell me-1"></i> Notifikasi</a></li>
                    <li class="breadcrumb-item active text-white" aria-current="page">Submenu Alert Operasional</li>
                </ol>
            </nav>
            <h4 class="fw-bold mb-0 text-white"><i class="fa-solid fa-triangle-exclamation text-danger me-2"></i> Daftar Alert Operasional Jatuh Tempo (H-30 s/d H-7)</h4>
        </div>
        <div class="d-flex gap-2">
            <a href="notifikasi.php" class="btn btn-outline-secondary text-white rounded-pill px-3">
                <i class="fa-solid fa-arrow-left me-1"></i> Kembali ke Notifikasi Utama
            </a>
        </div>
    </div>

    <!-- Alert Notifications Content Table -->
    <div class="card-custom p-4">
        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
            <h6 class="fw-bold text-white m-0"><i class="fa-solid fa-triangle-exclamation text-danger me-2"></i> Pengingat Operasional Armada (Total: <?= count($alert_notifications) ?> Alert Aktif)</h6>
            <span class="badge bg-danger rounded-pill px-3 py-1.5"><i class="fa-solid fa-clock me-1"></i> Mode H-30 (Mingguan) & H-7 (Harian)</span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width: 15%;">Kategori</th>
                        <th style="width: 25%;">Nopol / Nama Unit</th>
                        <th style="width: 18%;">Ruas Jalan / Pos</th>
                        <th style="width: 18%;">Jatuh Tempo</th>
                        <th style="width: 14%;">Status Pengingat</th>
                        <th class="text-end" style="width: 10%;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($alert_notifications)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-5">Seluruh armada dalam kondisi aman. Tidak ada alert jatuh tempo H-30/H-7 saat ini.</td></tr>
                    <?php else: ?>
                        <?php foreach ($alert_notifications as $notif): 
                            $u = $notif['unit'];
                            $inf = $notif['info'];
                        ?>
                            <tr>
                                <td>
                                    <span class="badge bg-dark border border-secondary text-info fw-bold"><?= htmlspecialchars($notif['kategori']) ?></span>
                                </td>
                                <td>
                                    <div class="fw-bold text-white font-monospace"><?= htmlspecialchars($u['kode_plat']) ?></div>
                                    <div class="text-white-50 small"><?= htmlspecialchars($u['nama']) ?></div>
                                </td>
                                <td class="small text-info fw-semibold">
                                    <i class="fa-solid fa-location-dot me-1"></i> <?= htmlspecialchars($u['lokasi_ruas']) ?>
                                </td>
                                <td class="fw-semibold text-white">
                                    <?= format_tgl_indo($notif['target_date']) ?>
                                </td>
                                <td>
                                    <span class="badge <?= $inf['badge_class'] ?> rounded-pill px-2.5 py-1"><?= $inf['label'] ?></span>
                                </td>
                                <td class="text-end">
                                    <a href="<?= $notif['action_link'] ?>" class="btn btn-sm btn-bpj-primary rounded-pill px-3">
                                        <i class="fa-solid fa-arrow-right me-1"></i> <?= $notif['action_label'] ?>
                                    </a>
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

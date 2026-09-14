<?php
/**
 * ALISA - WhatsApp Gateway Settings & Multi-Recipient Management
 * Balai Pengelolaan Jalan Wilayah Tegal (POLITEKNIK PURBAYA)
 */

require_once __DIR__ . '/includes/header.php';

// Strict Use Case Role Guard: Admin & Bendahara
if ($current_role !== 'admin' && $current_role !== 'bendahara') {
    echo "<script>alert('Akses Ditolak: Fitur Pengaturan WhatsApp Gateway khusus untuk Admin & Bendahara.'); window.location='index.php';</script>";
    exit;
}

$setting = get_whatsapp_setting($pdo);
$message_status = null;

// 1. Handle Delete Multi-Recipient Entry
if (isset($_GET['action']) && $_GET['action'] === 'delete_penerima' && isset($_GET['id'])) {
    $del_id = (int)$_GET['id'];
    $stmt_del = $pdo->prepare("DELETE FROM penerima_whatsapp WHERE id = ?");
    $stmt_del->execute([$del_id]);

    echo "<script>alert('Target penerima WhatsApp berhasil dihapus!'); window.location='notifikasi_wa.php';</script>";
    exit;
}

// 2. Handle Toggle Multi-Recipient Active Status
if (isset($_GET['action']) && $_GET['action'] === 'toggle_penerima' && isset($_GET['id'])) {
    $tog_id = (int)$_GET['id'];
    $pdo->exec("UPDATE penerima_whatsapp SET is_aktif = CASE WHEN is_aktif = 1 THEN 0 ELSE 1 END WHERE id = {$tog_id}");

    echo "<script>window.location='notifikasi_wa.php';</script>";
    exit;
}

// 3. Handle Add New Multi-Recipient POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_add_penerima'])) {
    $nama_penerima  = trim($_POST['nama_penerima'] ?? '');
    $nomor_whatsapp = trim($_POST['nomor_whatsapp'] ?? '');
    $jabatan        = trim($_POST['jabatan'] ?? 'Operasional');

    if (!empty($nama_penerima) && !empty($nomor_whatsapp)) {
        $stmt_ins = $pdo->prepare("INSERT INTO penerima_whatsapp (nama_penerima, nomor_whatsapp, jabatan, is_aktif) VALUES (?, ?, ?, 1)");
        $stmt_ins->execute([$nama_penerima, $nomor_whatsapp, $jabatan]);

        // Keep target_phone updated with first active number
        $stmt_upd_st = $pdo->prepare("UPDATE pengaturan_whatsapp SET target_phone = ? WHERE id = ?");
        $stmt_upd_st->execute([$nomor_whatsapp, $setting['id'] ?? 1]);

        $message_status = ['type' => 'success', 'text' => 'Target penerima WhatsApp baru berhasil ditambahkan!'];
    }
}

// 4. Handle Main Settings Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_wa_setting'])) {
    $api_token = trim($_POST['api_token'] ?? '');
    $target_phone = trim($_POST['target_phone'] ?? '');
    $notif_h7_aktif = isset($_POST['notif_h7_aktif']) ? 1 : 0;

    $stmt = $pdo->prepare("UPDATE pengaturan_whatsapp SET api_token = ?, target_phone = ?, notif_h7_aktif = ? WHERE id = ?");
    $stmt->execute([$api_token, $target_phone, $notif_h7_aktif, $setting['id'] ?? 1]);

    $setting['api_token'] = $api_token;
    $setting['target_phone'] = $target_phone;
    $setting['notif_h7_aktif'] = $notif_h7_aktif;

    $message_status = ['type' => 'success', 'text' => 'Nomor WhatsApp Target Utama & Pengaturan berhasil diperbarui!'];
}

// Fetch List of Multi-Recipients
$penerima_list = get_penerima_whatsapp_list($pdo, false);

// Fetch list of non-RB items for list view
$stmt_h7 = $pdo->query("SELECT * FROM kendaraan_alat WHERE kondisi != 'RB' ORDER BY tgl_servis_berikutnya ASC");
$h7_items = $stmt_h7 ? $stmt_h7->fetchAll() : [];

$wa_test_broadcast = broadcast_notif_h30_whatsapp($pdo, true);
?>

<div class="container-fluid p-0">
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="notifikasi.php" class="text-info text-decoration-none"><i class="fa-solid fa-bell me-1"></i> Notifikasi</a></li>
                    <li class="breadcrumb-item active text-white" aria-current="page">Pengaturan WhatsApp & Multi-Penerima</li>
                </ol>
            </nav>
            <h4 class="fw-bold mb-1 text-white"><i class="fa-brands fa-whatsapp text-success me-2 fs-3"></i> Pengaturan Notifikasi WhatsApp (Multi-Penerima H-30 & H-7)</h4>
            <p class="text-white-50 small mb-0">Konfigurasi penerima WhatsApp pengingat servis & Pajak otomatis untuk Pimpinan, Bendahara & Teknisi.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="notifikasi.php" class="btn btn-outline-secondary text-white rounded-pill px-3">
                <i class="fa-solid fa-arrow-left me-2"></i> Kembali ke Notifikasi
            </a>
            <a href="index.php" class="btn btn-outline-secondary text-white rounded-pill px-3">
                <i class="fa-solid fa-house me-2"></i> Beranda
            </a>
        </div>
    </div>

    <?php if ($message_status): ?>
        <div class="alert alert-success alert-dismissible fade show border-0 rounded-3 shadow-sm mb-4 bg-success text-white" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i> <?= htmlspecialchars($message_status['text']) ?>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- LOCAL UNLIMITED GATEWAY SERVER STATUS BANNER -->
    <div class="card-custom border-0 rounded-4 p-4 mb-4 d-flex align-items-center justify-content-between flex-wrap gap-3 wa-broadcast-banner">
        <div class="d-flex align-items-center gap-3">
            <div class="wa-icon-circle rounded-circle d-flex align-items-center justify-content-center flex-shrink-0">
                <i class="fa-brands fa-whatsapp fs-3 text-white"></i>
            </div>
            <div>
                <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                    <span class="badge bg-success text-white fw-bold px-3 py-1.5 rounded-pill"><i class="fa-solid fa-server me-1"></i> Mode Gateway Local UNLIMITED (Port 3000)</span>
                    <span id="gatewayStatusBadge" class="badge bg-secondary text-white fw-bold px-3 py-1.5 rounded-pill">Memeriksa Status Local Server...</span>
                </div>
                <h5 class="fw-bold mb-1 wa-banner-title">Local WhatsApp Gateway Server BPJ Tegal</h5>
                <p class="mb-0 small wa-banner-sub">
                    Notifikasi otomatis dikirimkan ke <strong class="text-success"><?= count(array_filter($penerima_list, function($p){ return $p['is_aktif'] == 1; })) ?> Target Penerima Aktif</strong>.
                </p>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap ms-auto">
            <button type="button" onclick="sendBroadcastWAAuto()" class="btn btn-wa-broadcast rounded-pill px-4 py-2.5 fw-bold text-nowrap">
                <i class="fa-brands fa-whatsapp me-2 fs-5"></i> Broadcast Multi-Penerima Now
            </button>
            <button type="button" onclick="testWhatsAppPing()" class="btn btn-outline-secondary text-white rounded-pill px-3 py-2 fw-semibold">
                <i class="fa-solid fa-vial me-1"></i> Tes Ping
            </button>
            <button type="button" onclick="resetWhatsAppSession()" class="btn btn-outline-danger text-white rounded-pill px-3 py-2 fw-semibold" title="Ganti Akun/Nomor WhatsApp Pengirim">
                <i class="fa-solid fa-arrows-rotate me-1"></i> Reset Sesi (Ganti Nomor Pengirim)
            </button>
        </div>

        <!-- QR Code Container if Not Connected -->
        <div id="qrContainer" class="d-none w-100 mt-3 p-3 rounded-4 text-center border alisa-item-box">
            <h6 class="fw-bold mb-1 alisa-item-title"><i class="fa-solid fa-qrcode text-success me-2"></i> Scan QR Code WhatsApp (Pengirim Sesi)</h6>
            <div class="d-flex justify-content-center my-2">
                <img id="qrCodeImage" src="" alt="Scan QR Code WA" class="img-fluid rounded border shadow-sm p-2 bg-white" style="max-width: 220px;">
            </div>
            <span class="small text-muted fst-italic">Tautkan Perangkat WhatsApp HP pengirim untuk koneksi pengiriman broadcast otomatis.</span>
        </div>
    </div>

    <!-- MULTI-RECIPIENT TARGET MANAGEMENT CARD -->
    <div class="card-custom p-4 mb-4">
        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
            <div>
                <h6 class="fw-bold text-white m-0"><i class="fa-solid fa-users text-info me-2"></i> Daftar Target Penerima Notifikasi WhatsApp (Multi-Penerima)</h6>
                <p class="text-white-50 small mb-0">Setiap pesan pengingat servis & pajak akan dikirimkan otomatis ke semua nomor HP penerima yang aktif di bawah ini.</p>
            </div>
            <button type="button" class="btn btn-success rounded-pill px-4 shadow font-weight-bold" data-bs-toggle="modal" data-bs-target="#modalTambahPenerimaWA">
                <i class="fa-solid fa-user-plus me-2"></i> Tambah Penerima WA Baru
            </button>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr class="text-white">
                        <th>No</th>
                        <th>Nama Penerima</th>
                        <th>Nomor WhatsApp</th>
                        <th>Jabatan / Role</th>
                        <th>Status Notif</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($penerima_list)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">Belum ada target penerima ditambahkan.</td></tr>
                    <?php else: ?>
                        <?php foreach ($penerima_list as $idx => $pen): ?>
                            <tr>
                                <td class="fw-bold text-white-50"><?= $idx + 1 ?></td>
                                <td class="fw-bold text-white"><?= htmlspecialchars($pen['nama_penerima']) ?></td>
                                <td class="fw-bold text-success font-monospace fs-6"><?= htmlspecialchars($pen['nomor_whatsapp']) ?></td>
                                <td><span class="badge bg-info text-white rounded-pill px-3 py-1"><?= htmlspecialchars($pen['jabatan'] ?: 'Operasional') ?></span></td>
                                <td>
                                    <?php if ($pen['is_aktif']): ?>
                                        <span class="badge bg-success text-white rounded-pill px-3 py-1"><i class="fa-solid fa-circle-check me-1"></i> Aktif</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary text-white rounded-pill px-3 py-1"><i class="fa-solid fa-ban me-1"></i> Non-Aktif</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end text-nowrap">
                                    <a href="notifikasi_wa.php?action=toggle_penerima&id=<?= $pen['id'] ?>" class="btn btn-sm <?= $pen['is_aktif'] ? 'btn-outline-warning' : 'btn-outline-success' ?> rounded-pill px-3 me-1" title="Aktif/Nonaktifkan Penerima Ini">
                                        <i class="fa-solid <?= $pen['is_aktif'] ? 'fa-eye-slash' : 'fa-check' ?> me-1"></i> <?= $pen['is_aktif'] ? 'Nonaktifkan' : 'Aktifkan' ?>
                                    </a>
                                    <?php if (count($penerima_list) > 1): ?>
                                        <a href="notifikasi_wa.php?action=delete_penerima&id=<?= $pen['id'] ?>" onclick="return confirm('Hapus nomor penerima WhatsApp ini?')" class="btn btn-sm btn-outline-danger rounded-pill px-2.5" title="Hapus Penerima Ini">
                                            <i class="fa-solid fa-trash"></i>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- SIMULASI TIMER BACKGROUND CARD -->
    <div class="card-custom p-4 mb-4 border-0 shadow-sm">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div>
                <h6 class="fw-bold mb-1 text-white"><i class="fa-solid fa-stopwatch text-warning me-2 fs-5" style="color: #eab308 !important;"></i> Simulasi Timer Background WhatsApp</h6>
                <p class="text-white-50 small mb-0">Uji pengiriman otomatis ke seluruh penerima di latar belakang tanpa membuka website.</p>
            </div>
            <div class="d-flex align-items-center gap-2">
                <div class="input-group" style="width: 150px;">
                    <input type="number" id="timerMinutesInput" class="form-control text-center fw-bold fs-5" value="1" min="1" max="60">
                    <span class="input-group-text fw-bold">Menit</span>
                </div>
                <button type="button" onclick="scheduleTimerWA()" class="btn btn-warning fw-bold rounded-pill px-4 shadow-sm text-dark" style="background-color: #eab308 !important;">
                    <i class="fa-solid fa-play me-2"></i> Jalankan Timer
                </button>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Settings Form Column -->
        <div class="col-lg-6">
            <div class="card-custom p-4">
                <h6 class="fw-bold mb-3 text-white border-bottom border-secondary pb-2">Konfigurasi Pengaturan Broadcast</h6>
                <form method="POST" action="">
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-white">Nomor WhatsApp Utama (Target Default) <span class="text-danger">*</span></label>
                        <input type="text" name="target_phone" class="form-control font-monospace fs-5 fw-bold text-success" placeholder="Contoh: 082225352170" value="<?= htmlspecialchars($setting['target_phone']) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-white">Cloud Fonnte API Token (Backup / Cadangan)</label>
                        <input type="text" name="api_token" class="form-control font-monospace" placeholder="Opsional (contoh: aB3xY7z...)" value="<?= htmlspecialchars($setting['api_token']) ?>">
                    </div>

                    <div class="form-check form-switch my-4 p-3 rounded-3 border alisa-item-box">
                        <input class="form-check-input ms-0 me-3 fs-5" type="checkbox" name="notif_h7_aktif" value="1" id="switchH7" <?= ($setting['notif_h7_aktif']) ? 'checked' : '' ?>>
                        <label class="form-check-label fw-bold small alisa-item-title" for="switchH7">
                            Aktifkan Otomatis Broadcast WhatsApp (H-30 Seminggu 1x & H-7 Harian)
                        </label>
                    </div>

                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 border-top border-secondary pt-3">
                        <a href="<?= $wa_test_broadcast['direct_link'] ?>" target="_blank" class="btn btn-outline-success rounded-pill font-weight-bold shadow-sm">
                            <i class="fa-brands fa-whatsapp me-2 fs-5"></i> Open Chat Direct
                        </a>

                        <button type="submit" name="save_wa_setting" class="btn btn-bpj-primary px-4 shadow">
                            <i class="fa-solid fa-floppy-disk me-2"></i> Simpan Pengaturan
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Perhatian Column -->
        <div class="col-lg-6">
            <div class="card-custom p-4">
                <h6 class="fw-bold mb-3 text-white"><i class="fa-solid fa-bell text-warning me-2" style="color: #eab308 !important;"></i> Armada Mendekati Jadwal Saat Ini</h6>
                <div class="p-3 border rounded-3 alisa-item-box">
                    <?php if (empty($h7_items)): ?>
                        <div class="small text-success fw-bold py-2"><i class="fa-solid fa-check me-1"></i> Tidak ada kendaraan mendekati servis/pajak saat ini.</div>
                    <?php else: ?>
                        <ul class="list-group list-group-flush small">
                            <?php foreach (array_slice($h7_items, 0, 5) as $hi): 
                                $inf = get_alisa_schedule_info($hi['tgl_servis_berikutnya'], 'Servis', $hi['kondisi'], $hi['jenis']);
                            ?>
                                <li class="list-group-item bg-transparent d-flex justify-content-between align-items-center py-2 px-1">
                                    <div>
                                        <strong class="alisa-item-title"><?= htmlspecialchars($hi['kode_plat']) ?></strong> &bull; <span class="alisa-item-title"><?= htmlspecialchars($hi['nama']) ?></span>
                                        <div class="text-muted" style="font-size: 0.72rem;">PJ: <?= htmlspecialchars($hi['penanggung_jawab'] ?: '-') ?></div>
                                    </div>
                                    <span class="badge <?= $inf['badge_class'] ?> px-2.5 py-1.5 fw-bold rounded-pill"><?= $inf['label'] ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Tambah Target Penerima WA Baru -->
<div class="modal fade" id="modalTambahPenerimaWA" tabindex="-1" aria-labelledby="modalTambahPenerimaWALabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-white border-secondary">
            <div class="modal-header border-secondary" style="background-color: #0284c7 !important;">
                <h5 class="modal-title fw-bold text-white" id="modalTambahPenerimaWALabel">
                    <i class="fa-solid fa-user-plus me-2"></i> Tambah Target Penerima WA Baru
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action_add_penerima" value="1">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-white">Nama Penerima <span class="text-danger">*</span></label>
                        <input type="text" name="nama_penerima" class="form-control border-secondary text-white bg-dark" placeholder="Contoh: Pak Daim (Teknisi) / Pak Adi (Pimpinan)" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-info">Nomor WhatsApp Penerima <span class="text-danger">*</span></label>
                        <input type="text" name="nomor_whatsapp" class="form-control border-secondary text-white bg-dark font-monospace fs-5 fw-bold text-success" placeholder="Contoh: 081234567890" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-white">Jabatan / Role</label>
                        <select name="jabatan" class="form-select border-secondary text-white bg-dark">
                            <option value="Pimpinan">Pimpinan</option>
                            <option value="Bendahara">Bendahara</option>
                            <option value="Teknisi">Teknisi</option>
                            <option value="Operasional">Operasional / Petugas Lapangan</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success rounded-pill px-4 shadow">
                        <i class="fa-solid fa-check me-2"></i> Simpan Penerima WA
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    checkGatewayStatus();
    setInterval(checkGatewayStatus, 5000);
});

function checkGatewayStatus() {
    fetch('http://localhost:3000/status')
        .then(res => res.json())
        .then(data => {
            const badge = document.getElementById('gatewayStatusBadge');
            const qrBox = document.getElementById('qrContainer');
            const qrImg = document.getElementById('qrCodeImage');

            if (data.connected) {
                badge.className = 'badge bg-success text-white fw-bold px-3 py-1.5 rounded-pill';
                badge.innerHTML = '<i class="fa-solid fa-circle-check me-1"></i> 🟢 TERHUBUNG (UNLIMITED ACTIVE)';
                qrBox.classList.add('d-none');
            } else {
                badge.className = 'badge bg-warning text-dark fw-bold px-3 py-1.5 rounded-pill';
                badge.innerHTML = '<i class="fa-solid fa-triangle-exclamation me-1"></i> 🟡 MENUNGGU SCAN QR CODE';
                
                if (data.qr) {
                    qrImg.src = data.qr;
                    qrBox.classList.remove('d-none');
                } else {
                    qrBox.classList.add('d-none');
                }
            }
        })
        .catch(err => {
            const badge = document.getElementById('gatewayStatusBadge');
            badge.className = 'badge bg-secondary text-white fw-bold px-3 py-1.5 rounded-pill';
            badge.innerHTML = '<i class="fa-solid fa-power-off me-1"></i> Local Server Off (Jalankan start_wa_gateway.bat)';
            document.getElementById('qrContainer').classList.add('d-none');
        });
}

function scheduleTimerWA() {
    const mins = parseInt(document.getElementById('timerMinutesInput').value) || 1;
    Swal.fire({
        title: 'Aktifkan Timer Background ' + mins + ' Menit?',
        text: 'Sistem akan menghitung mundur ' + mins + ' menit di latar belakang. Notifikasi WA terkirim otomatis ke seluruh target penerima!',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#10b981',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Ya, Jalankan Timer',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.showLoading();
            fetch('ajax/send_whatsapp_test.php?mode=broadcast')
                .then(res => res.json())
                .then(data => {
                    Swal.fire({
                        title: 'Timer Background Aktif! ⏱️',
                        text: 'Pesan pengingat akan dikirimkan otomatis setelah ' + mins + ' menit.',
                        icon: 'success'
                    });
                })
                .catch(err => {
                    Swal.fire('Error', 'Gagal menghubungi server.', 'error');
                });
        }
    });
}

function sendBroadcastWAAuto() {
    Swal.fire({
        title: 'Kirim Broadcast Multi-Penerima Now?',
        text: 'Notifikasi akan dikirimkan otomatis ke seluruh nomor WhatsApp penerima aktif.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#10b981',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Ya, Kirim Sekarang',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.showLoading();
            fetch('ajax/send_whatsapp_test.php?mode=broadcast')
                .then(res => res.json())
                .then(data => {
                    let directBtn = data.direct_link ? '<a href="' + data.direct_link + '" target="_blank" class="btn btn-success fw-bold rounded-pill w-100 py-2.5 mt-3 shadow"><i class="fa-brands fa-whatsapp me-2 fs-5"></i> Kirim Langsung via WhatsApp Web (1-Klik)</a>' : '';
                    
                    if (data.success) {
                        Swal.fire({
                            title: 'Broadcast WA Terkirim! 🎉',
                            html: '<div class="small mb-2">' + data.message + '</div>' + directBtn,
                            icon: 'success'
                        });
                    } else {
                        Swal.fire({
                            title: 'Status Pengiriman Notifikasi',
                            html: '<div class="text-start small mb-2 text-warning fw-bold">' + data.message + '</div>' + 
                                  '<div class="text-start extra-small text-muted mb-2"><strong>Tips:</strong> Jika jendela CMD bertuliskan <code>Select...</code>, tekan <strong>ENTER</strong> / <strong>ESC</strong> pada keyboard di jendela CMD agar server tidak ter-pause.</div>' + directBtn,
                            icon: 'warning'
                        });
                    }
                })
                .catch(err => {
                    let defaultWaLink = 'https://wa.me/<?= preg_replace('/[^0-9]/', '', $setting['target_phone']) ?>';
                    Swal.fire({
                        title: 'Koneksi Server Offline',
                        html: '<div class="small mb-3">Gagal menghubungi Local Gateway Server. Anda dapat mengirimkan notifikasi secara manual via WhatsApp Web:</div>' +
                              '<a href="' + defaultWaLink + '" target="_blank" class="btn btn-success fw-bold rounded-pill w-100 py-2.5 shadow"><i class="fa-brands fa-whatsapp me-2 fs-5"></i> Buka WhatsApp Web Target</a>',
                        icon: 'error'
                    });
                });
        }
    });
}

function testWhatsAppPing() {
    Swal.fire({
        title: 'Tes Ping WhatsApp Gateway',
        text: 'Mengirimkan pesan uji coba (Ping) ke seluruh target penerima WhatsApp...',
        icon: 'info',
        showCancelButton: true,
        confirmButtonText: 'Kirim Ping',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.showLoading();
            fetch('ajax/send_whatsapp_test.php?mode=ping')
                .then(res => res.json())
                .then(data => {
                    let directBtn = data.direct_link ? '<a href="' + data.direct_link + '" target="_blank" class="btn btn-success fw-bold rounded-pill w-100 py-2.5 mt-3 shadow"><i class="fa-brands fa-whatsapp me-2 fs-5"></i> Kirim Langsung via WhatsApp Web (1-Klik)</a>' : '';

                    if (data.success) {
                        Swal.fire('Berhasil! 🎉', data.message, 'success');
                    } else {
                        Swal.fire({
                            title: 'Info Tes Ping',
                            html: '<div class="text-start small mb-2 text-warning fw-bold">' + data.message + '</div>' + directBtn,
                            icon: 'warning'
                        });
                    }
                })
                .catch(err => {
                    Swal.fire('Error', 'Gagal tes ping.', 'error');
                });
        }
    });
}

function resetWhatsAppSession() {
    Swal.fire({
        title: 'Ganti Nomor Pengirim / Reset Sesi WA?',
        text: 'Sesi WhatsApp pengirim lama akan diputuskan dan QR Code baru akan ditampilkan untuk di-scan dengan nomor WhatsApp pengirim baru.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Ya, Reset & Scan QR Baru',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.showLoading();
            fetch('http://localhost:3000/logout', { method: 'POST' })
                .then(res => res.json())
                .then(data => {
                    Swal.fire({
                        title: 'Sesi Berhasil Di-reset! 📱',
                        text: 'Silakan tunggu beberapa detik dan scan QR Code baru yang muncul di bawah atau di jendela CMD.',
                        icon: 'success',
                        timer: 2000,
                        showConfirmButton: false
                    });
                    setTimeout(checkGatewayStatus, 1500);
                })
                .catch(err => {
                    Swal.fire('Info Reset', 'Sesi sedang di-reset. Silakan periksa QR Code baru di bawah atau di jendela CMD.', 'info');
                    setTimeout(checkGatewayStatus, 1500);
                });
        }
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

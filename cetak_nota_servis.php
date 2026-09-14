<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$id_servis = (int)($_GET['id'] ?? 0);
$pdo = get_db();

$stmt = $pdo->prepare("SELECT r.*, k.nama as nama_unit, k.kode_plat, k.lokasi_ruas, k.merk, k.tahun, k.jenis, k.kategori, k.no_chasis, k.no_mesin FROM riwayat_servis r JOIN kendaraan_alat k ON r.id_kendaraan = k.id WHERE r.id = ?");
$stmt->execute([$id_servis]);
$servis = $stmt->fetch();

if (!$servis) {
    echo "<script>alert('Data nota servis tidak ditemukan!'); window.close();</script>";
    exit;
}

$current_role = $_SESSION['user_role'] ?? 'admin';
if ($current_role !== 'admin' && $current_role !== 'pimpinan') {
    echo "<script>alert('Akses Ditolak: Fitur Cetak Nota khusus untuk Admin & Pimpinan.'); window.close();</script>";
    exit;
}
$total_biaya = (float)$servis['total_biaya'];
$terbilang_str = terbilang($total_biaya) . " Rupiah";
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nota Servis #<?= $servis['id'] ?> - <?= htmlspecialchars($servis['kode_plat']) ?> - ALISA BPJ Tegal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        body {
            background-color: #f8fafc;
            color: #1e293b;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        }
        .nota-container {
            max-width: 800px;
            margin: 20px auto;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            padding: 35px;
            border: 1px solid #e2e8f0;
        }
        .kop-header {
            border-bottom: 3px double #0284c7;
            padding-bottom: 15px;
            margin-bottom: 25px;
        }
        .info-box {
            background: #f1f5f9;
            border-radius: 8px;
            padding: 15px;
            border-left: 4px solid #0284c7;
            margin-bottom: 20px;
        }
        .grand-total-box {
            background: #e0f2fe;
            border: 2px solid #0284c7;
            border-radius: 8px;
            padding: 15px 20px;
        }
        .no-print-action {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 9999;
        }
        @media print {
            .no-print-action { display: none !important; }
            body { background: #fff !important; }
            .nota-container {
                box-shadow: none !important;
                border: none !important;
                padding: 0 !important;
                margin: 0 !important;
                max-width: 100% !important;
            }
        }
    </style>
</head>
<body>

<div class="no-print-action d-flex gap-2">
    <button onclick="window.print()" class="btn btn-primary btn-lg rounded-pill shadow-lg fw-bold" style="background-color: #0284c7; border: none;">
        <i class="fa-solid fa-print me-2"></i> Cetak Nota (Print PDF)
    </button>
    <button onclick="window.close()" class="btn btn-secondary btn-lg rounded-pill shadow-lg">
        <i class="fa-solid fa-xmark me-1"></i> Tutup
    </button>
</div>

<div class="nota-container">
    <!-- Kop Suratan Resmi Balai BPJ Tegal -->
    <div class="kop-header text-center">
        <div class="d-flex align-items-center justify-content-center gap-3 mb-2">
            <img src="assets/images/logo_jateng.png?v=<?= time() ?>" alt="Logo Jateng" style="height: 60px; width: auto;">
            <div class="text-start">
                <h6 class="fw-bold text-uppercase mb-0 text-muted" style="letter-spacing: 1px; font-size: 0.85rem;">Pemerintah Provinsi Jawa Tengah</h6>
                <h5 class="fw-extrabold mb-0 text-dark" style="letter-spacing: 0.5px;">BALAI PENGELOLAAN JALAN WILAYAH TEGAL</h5>
                <small class="text-muted">Jl. Kiai H. Ahmad Dahlan No. 1, Tegal &bull; Sistem ALISA Logistik</small>
            </div>
        </div>
        <div class="mt-3">
            <h5 class="fw-extrabold text-uppercase text-primary mb-0" style="color: #0284c7 !important;">NOTA RINCIAN BIAYA & PEMELIHARAAN SERVIS</h5>
            <small class="fw-bold text-secondary">Nomor Transaksi: NOTA/SERVIS/2026/<?= str_pad($servis['id'], 5, '0', STR_PAD_LEFT) ?></small>
        </div>
    </div>

    <!-- Details Box -->
    <div class="info-box">
        <div class="row g-3 small">
            <div class="col-6">
                <span class="text-muted d-block">Armada Kendaraan / Peralatan:</span>
                <strong class="text-dark fs-6"><?= htmlspecialchars($servis['nama_unit']) ?></strong>
                <div class="fw-bold text-primary font-monospace mt-1" style="font-size: 1.1rem;"><?= htmlspecialchars($servis['kode_plat']) ?></div>
                <div class="text-muted mt-1">Merk/Tahun: <?= htmlspecialchars($servis['merk'] ?: '-') ?> (<?= htmlspecialchars($servis['tahun'] ?: '-') ?>)</div>
            </div>
            <div class="col-6 text-end">
                <span class="text-muted d-block">Tanggal Pelaksanaan Servis:</span>
                <strong class="text-dark fs-6"><?= format_tgl_indo($servis['tgl_servis']) ?></strong>
                <div class="mt-1">
                    <span class="badge <?= ($servis['jenis_servis'] === 'Darurat') ? 'bg-danger' : 'bg-info' ?> px-3 py-1 text-white">
                        Servis <?= htmlspecialchars($servis['jenis_servis']) ?>
                    </span>
                </div>
                <div class="text-muted mt-1"><i class="fa-solid fa-location-dot me-1"></i> Ruas: <?= htmlspecialchars($servis['lokasi_ruas']) ?></div>
            </div>
            <div class="col-12 border-top border-secondary-subtle pt-2">
                <div class="row">
                    <div class="col-6">
                        <span class="text-muted d-block">Bengkel / Tempat Servis:</span>
                        <strong class="text-dark"><?= htmlspecialchars($servis['nama_bengkel'] ?: '-') ?></strong>
                    </div>
                    <div class="col-6 text-end">
                        <span class="text-muted d-block">Nama Layanan / Pekerjaan:</span>
                        <strong class="text-dark"><?= htmlspecialchars($servis['nama_layanan']) ?></strong>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sparepart & Work Breakdown -->
    <h6 class="fw-bold text-dark mb-2"><i class="fa-solid fa-list-check me-2 text-primary"></i> Break-Down Sparepart & Subtotal Per Item:</h6>
    <div class="card mb-4 border-slate-300 shadow-sm" style="background-color: #fafafa;">
        <div class="card-body p-3 font-monospace" style="white-space: pre-line; line-height: 1.8; font-size: 0.95rem;">
<?= htmlspecialchars($servis['rincian_item'] ?: 'Servis Rutin Tanpa Rincian Sparepart Tambahan.') ?>
        </div>
    </div>

    <!-- Grand Total Box -->
    <div class="grand-total-box d-flex align-items-center justify-content-between mb-4">
        <div>
            <span class="fw-bold text-uppercase text-secondary small d-block">Grand Total Biaya Servis:</span>
            <span class="fst-italic text-dark small">"<?= htmlspecialchars($terbilang_str) ?>"</span>
        </div>
        <div class="text-end">
            <?php if ($current_role === 'pimpinan'): ?>
                <span class="badge bg-secondary">Rahasia Operasional</span>
            <?php else: ?>
                <h3 class="fw-extrabold text-primary m-0" style="color: #0284c7 !important;"><?= format_rupiah($total_biaya) ?></h3>
            <?php endif; ?>
        </div>
    </div>

    <!-- Lampiran Bukti Foto Resi / Nota Fisik (Jika ada) -->
    <?php if (!empty($servis['foto_nota'])): ?>
        <div class="mb-4 text-center border p-3 rounded bg-white">
            <h6 class="fw-bold text-dark mb-2"><i class="fa-solid fa-file-image me-2 text-primary"></i> Lampiran Foto Resi / Nota Fisik:</h6>
            <img src="uploads/nota/<?= htmlspecialchars($servis['foto_nota']) ?>" alt="Foto Resi Nota" class="img-fluid rounded border shadow-sm" style="max-height: 400px; object-fit: contain;">
        </div>
    <?php endif; ?>

    <!-- Signature Block -->
    <div class="row mt-5 pt-3 border-top border-2 text-center" style="font-size: 0.88rem;">
        <div class="col-6">
            <p class="mb-1 text-muted">Pelaksana / Teknisi Servis,</p>
            <p class="fw-bold text-dark mb-5">Tim Operasional Workshop</p>
            <p class="fw-bold text-decoration-underline mb-0">Pak Daim / Teknisi ALISA</p>
            <small class="text-muted">Teknisi Balai BPJ Tegal</small>
        </div>
        <div class="col-6">
            <p class="mb-1 text-muted">Mengetahui & Menyetujui,</p>
            <p class="fw-bold text-dark mb-5">Pimpinan / Pengelola Logistik</p>
            <p class="fw-bold text-decoration-underline mb-0">Pak Adi / Tim Pimpinan</p>
            <small class="text-muted">NIP. 19780112 200501 1 002</small>
        </div>
    </div>
</div>

<script>
window.onload = function() {
    if (window.location.search.indexOf('autoprint=1') !== -1) {
        setTimeout(function() { window.print(); }, 500);
    }
};
</script>
</body>
</html>

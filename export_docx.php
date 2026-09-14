<?php
/**
 * ALISA - Export Laporan to Microsoft Word (.docx / .doc)
 * Balai Pengelolaan Jalan Wilayah Tegal
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

// Auth & Role Guard
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$current_role = current_user_role();
if ($current_role !== 'admin' && $current_role !== 'pimpinan') {
    die("Akses ditolak: Fitur Export Laporan khusus untuk Admin & Pimpinan.");
}

$pdo = get_db();
$tipe = $_GET['tipe'] ?? 'servis';
$id_kendaraan_filter = (int)($_GET['id_kendaraan'] ?? 0);
$jenis_filter = trim($_GET['jenis'] ?? '');
$kondisi_filter = trim($_GET['kondisi'] ?? '');
$ruas_filter = trim($_GET['ruas'] ?? '');
$search = trim($_GET['search'] ?? '');

$filename_type = "Servis";
if ($tipe === 'data_unit') $filename_type = "Data_Unit";
elseif ($tipe === 'data_ruas') $filename_type = "Data_Ruas";
elseif ($tipe === 'nota_pajak') $filename_type = "Nota_Pajak";

$filename = "Laporan_ALISA_" . $filename_type . "_" . date('Ymd_His') . ".doc";

// Set MS Word Headers
header("Content-Type: application/vnd.ms-word; charset=utf-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Cache-Control: no-cache, must-revalidate");
header("Expires: 0");

// Fetch Data
$data_report = [];
$report_title = "LAPORAN REKAPITULASI SERVIS ARMADA";

if ($tipe === 'data_ruas') {
    $report_title = "LAPORAN DISTRIBUSI ARMADA PER POS RUAS JALAN";
    $sql = "SELECT * FROM kendaraan_alat WHERE 1=1";
    $params = [];
    if (!empty($jenis_filter)) { $sql .= " AND jenis = ?"; $params[] = $jenis_filter; }
    if (!empty($ruas_filter)) { 
        if ($ruas_filter === '13. Tempat Lain (Input Custom)') {
            $std_12 = array_slice(get_lokasi_ruas_list(), 0, 12);
            $in_clause = "'" . implode("','", array_map('addslashes', $std_12)) . "'";
            $sql .= " AND (lokasi_ruas NOT IN ($in_clause) OR lokasi_ruas LIKE 'Tempat Lain%' OR lokasi_ruas = '13. Tempat Lain (Input Custom)')";
        } else {
            $sql .= " AND lokasi_ruas = ?"; 
            $params[] = $ruas_filter; 
        }
    }
    if (!empty($search)) {
        $st = "%$search%";
        $sql .= " AND (kode_plat LIKE ? OR nama LIKE ? OR lokasi_ruas LIKE ? OR merk LIKE ? OR penanggung_jawab LIKE ?)";
        $params = array_merge($params, [$st, $st, $st, $st, $st]);
    }
    $sql .= " ORDER BY lokasi_ruas ASC, nama ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data_report = $stmt->fetchAll();
} elseif ($tipe === 'data_unit') {
    $report_title = "LAPORAN INVENTARISASI DATA UNIT KENDARAAN & PERALATAN";
    $sql = "SELECT * FROM kendaraan_alat WHERE 1=1";
    $params = [];
    if (!empty($jenis_filter)) { $sql .= " AND jenis = ?"; $params[] = $jenis_filter; }
    if (!empty($kondisi_filter)) { $sql .= " AND kondisi = ?"; $params[] = $kondisi_filter; }
    if (!empty($ruas_filter)) { 
        if ($ruas_filter === '13. Tempat Lain (Input Custom)') {
            $std_12 = array_slice(get_lokasi_ruas_list(), 0, 12);
            $in_clause = "'" . implode("','", array_map('addslashes', $std_12)) . "'";
            $sql .= " AND (lokasi_ruas NOT IN ($in_clause) OR lokasi_ruas LIKE 'Tempat Lain%' OR lokasi_ruas = '13. Tempat Lain (Input Custom)')";
        } else {
            $sql .= " AND lokasi_ruas = ?"; 
            $params[] = $ruas_filter; 
        }
    }
    if (!empty($search)) {
        $st = "%$search%";
        $sql .= " AND (kode_plat LIKE ? OR nama LIKE ? OR lokasi_ruas LIKE ? OR merk LIKE ? OR penanggung_jawab LIKE ? OR no_chasis LIKE ? OR no_mesin LIKE ?)";
        $params = array_merge($params, [$st, $st, $st, $st, $st, $st, $st]);
    }
    $sql .= " ORDER BY jenis ASC, nama ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data_report = $stmt->fetchAll();
} elseif ($tipe === 'nota_pajak') {
    $report_title = "LAPORAN BUKTI NOTA SERVIS DAN PAJAK STNK / UJI KIR";
    $sql_s = "SELECT r.tgl_servis as tgl, k.nama, k.kode_plat, k.lokasi_ruas, 'Servis' as jenis_dokumen, r.total_biaya as nominal, r.foto_nota as foto_bukti, r.nama_bengkel as keterangan, r.id as ref_id FROM riwayat_servis r JOIN kendaraan_alat k ON r.id_kendaraan = k.id WHERE r.foto_nota IS NOT NULL AND r.foto_nota != ''";
    $params_s = [];
    if ($id_kendaraan_filter > 0) { $sql_s .= " AND r.id_kendaraan = ?"; $params_s[] = $id_kendaraan_filter; }
    if (!empty($search)) {
        $st = "%$search%";
        $sql_s .= " AND (k.kode_plat LIKE ? OR k.nama LIKE ? OR r.nama_bengkel LIKE ? OR r.nama_layanan LIKE ?)";
        $params_s = array_merge($params_s, [$st, $st, $st, $st]);
    }
    $stmt_s = $pdo->prepare($sql_s);
    $stmt_s->execute($params_s);
    $servis_notes = $stmt_s->fetchAll();

    $sql_p = "SELECT p.tgl_bayar as tgl, k.nama, k.kode_plat, k.lokasi_ruas, p.jenis_pembayaran as jenis_dokumen, p.nominal_biaya as nominal, p.foto_bukti, p.catatan as keterangan, p.id as ref_id FROM pembayaran_pajak_kir p JOIN kendaraan_alat k ON p.id_kendaraan = k.id WHERE p.foto_bukti IS NOT NULL AND p.foto_bukti != ''";
    $params_p = [];
    if ($id_kendaraan_filter > 0) { $sql_p .= " AND p.id_kendaraan = ?"; $params_p[] = $id_kendaraan_filter; }
    if (!empty($search)) {
        $st = "%$search%";
        $sql_p .= " AND (k.kode_plat LIKE ? OR k.nama LIKE ? OR p.jenis_pembayaran LIKE ? OR p.catatan LIKE ?)";
        $params_p = array_merge($params_p, [$st, $st, $st, $st]);
    }
    $stmt_p = $pdo->prepare($sql_p);
    $stmt_p->execute($params_p);
    $pajak_notes = $stmt_p->fetchAll();

    $data_report = array_merge($servis_notes, $pajak_notes);
    usort($data_report, function($a, $b) {
        return strtotime($b['tgl']) - strtotime($a['tgl']);
    });
} else {
    // Default: Servis
    $sql = "SELECT r.*, k.nama, k.kode_plat, k.lokasi_ruas, k.merk, k.tahun, k.jenis, k.kategori FROM riwayat_servis r JOIN kendaraan_alat k ON r.id_kendaraan = k.id WHERE 1=1";
    $params = [];
    if ($id_kendaraan_filter > 0) { $sql .= " AND r.id_kendaraan = ?"; $params[] = $id_kendaraan_filter; }
    if (!empty($search)) {
        $st = "%$search%";
        $sql .= " AND (k.kode_plat LIKE ? OR k.nama LIKE ? OR k.lokasi_ruas LIKE ? OR k.merk LIKE ? OR r.nama_bengkel LIKE ? OR r.nama_layanan LIKE ? OR r.rincian_item LIKE ?)";
        $params = array_merge($params, [$st, $st, $st, $st, $st, $st, $st]);
    }
    $sql .= " ORDER BY r.tgl_servis DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data_report = $stmt->fetchAll();
}
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40">
<head>
<meta charset="utf-8">
<title><?= htmlspecialchars($report_title) ?></title>
<style>
    body { font-family: Arial, sans-serif; font-size: 11pt; color: #000000; margin: 20px; }
    h2, h3, h4 { text-align: center; margin: 3px 0; }
    .header-kop { text-align: center; border-bottom: 3px double #000000; padding-bottom: 10px; margin-bottom: 20px; }
    .header-kop .instansi { font-size: 12pt; font-weight: bold; text-transform: uppercase; }
    .header-kop .balai { font-size: 14pt; font-weight: bold; text-transform: uppercase; }
    .header-kop .alamat { font-size: 9pt; font-style: italic; color: #333333; }
    table.data-table { border-collapse: collapse; width: 100%; margin-top: 15px; }
    table.data-table th, table.data-table td { border: 1px solid #000000; padding: 6px 8px; font-size: 10pt; text-align: left; }
    table.data-table th { background-color: #e2e8f0; font-weight: bold; text-align: center; }
    .text-center { text-align: center; }
    .text-right { text-align: right; }
    .footer-ttd { margin-top: 40px; width: 100%; }
    .footer-ttd td { border: none; text-align: center; font-size: 10pt; }
</style>
</head>
<body>

<div class="header-kop">
    <div class="instansi">PEMERINTAH PROVINSI JAWA TENGAH<br>DINAS PEKERJAAN UMUM BINA MARGA DAN CIPTA KARYA</div>
    <div class="balai">BALAI PENGELOLAAN JALAN WILAYAH TEGAL</div>
    <div class="alamat">Jl. Agus Salim No. 1, Tegal, Jawa Tengah &bull; Email: bpjtegal@jawatengah.go.id &bull; Website: alisa.bpjtegal.net</div>
</div>

<h2><?= htmlspecialchars($report_title) ?></h2>
<h4>ALISA (Aplikasi Logistik Inspeksi & Servis Armada)</h4>
<div style="text-align: center; font-size: 10pt; font-style: italic; margin-bottom: 15px;">
    Tanggal Diunduh: <?= date('d F Y, H:i') ?> WIB | Oleh: <?= htmlspecialchars($_SESSION['nama_lengkap'] ?? 'Admin ALISA') ?>
</div>

<table class="data-table">
<?php if ($tipe === 'data_ruas'): ?>
    <thead>
        <tr>
            <th width="4%">No</th>
            <th width="15%">Kode Plat / KUP</th>
            <th width="25%">Nama Unit & Merk</th>
            <th width="15%">Jenis Klasifikasi</th>
            <th width="20%">Pos Ruas Jalan</th>
            <th width="8%">Kondisi</th>
            <th width="13%">Penanggung Jawab</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($data_report)): ?>
            <tr><td colspan="7" class="text-center">Tidak ada data unit pada filter ini.</td></tr>
        <?php else: ?>
            <?php foreach ($data_report as $idx => $r): ?>
                <tr>
                    <td class="text-center"><?= $idx + 1 ?></td>
                    <td><b><?= htmlspecialchars($r['kode_plat']) ?></b><?= !empty($r['kup_reg']) ? '<br><small>KUP: ' . htmlspecialchars($r['kup_reg']) . '</small>' : '' ?></td>
                    <td><?= htmlspecialchars($r['nama']) ?><br><small><?= htmlspecialchars($r['merk'] ?? '-') ?> (<?= $r['tahun'] ?>)</small></td>
                    <td><?= htmlspecialchars(str_replace('_', ' ', strtoupper($r['jenis']))) ?></td>
                    <td><b><?= htmlspecialchars($r['lokasi_ruas']) ?></b></td>
                    <td class="text-center"><b><?= htmlspecialchars($r['kondisi']) ?></b></td>
                    <td><?= htmlspecialchars($r['penanggung_jawab'] ?? 'Petugas Lapangan') ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
<?php elseif ($tipe === 'data_unit'): ?>
    <thead>
        <tr>
            <th width="4%">No</th>
            <th width="15%">Kode Plat / KUP</th>
            <th width="25%">Nama Unit & Merk</th>
            <th width="15%">Jenis Klasifikasi</th>
            <th width="18%">Pos Ruas Jalan</th>
            <th width="8%">Kondisi</th>
            <th width="15%">Rangka & Mesin</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($data_report)): ?>
            <tr><td colspan="7" class="text-center">Tidak ada data unit pada filter ini.</td></tr>
        <?php else: ?>
            <?php foreach ($data_report as $idx => $r): ?>
                <tr>
                    <td class="text-center"><?= $idx + 1 ?></td>
                    <td><b><?= htmlspecialchars($r['kode_plat']) ?></b><?= !empty($r['kup_reg']) ? '<br><small>KUP: ' . htmlspecialchars($r['kup_reg']) . '</small>' : '' ?></td>
                    <td><?= htmlspecialchars($r['nama']) ?><br><small><?= htmlspecialchars($r['merk'] ?? '-') ?> (<?= $r['tahun'] ?>)</small></td>
                    <td><?= htmlspecialchars(str_replace('_', ' ', strtoupper($r['jenis']))) ?></td>
                    <td><?= htmlspecialchars($r['lokasi_ruas']) ?></td>
                    <td class="text-center"><b><?= htmlspecialchars($r['kondisi']) ?></b></td>
                    <td><small>Chasis: <?= htmlspecialchars($r['no_chasis'] ?? '-') ?><br>Mesin: <?= htmlspecialchars($r['no_mesin'] ?? '-') ?></small></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
<?php elseif ($tipe === 'nota_pajak'): ?>
    <thead>
        <tr>
            <th width="4%">No</th>
            <th width="12%">Tanggal</th>
            <th width="20%">Nama Unit & Plat</th>
            <th width="15%">Jenis Dokumen</th>
            <th width="18%">Keterangan / Bengkel</th>
            <th width="15%">Nominal Biaya (Rp)</th>
            <th width="16%">Prediksi Thn Depan (+10%)</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($data_report)): ?>
            <tr><td colspan="7" class="text-center">Tidak ada bukti nota / resi pajak.</td></tr>
        <?php else: ?>
            <?php $total_biaya_all = 0; ?>
            <?php foreach ($data_report as $idx => $r): 
                $total_biaya_all += $r['nominal'];
            ?>
                <tr>
                    <td class="text-center"><?= $idx + 1 ?></td>
                    <td class="text-center"><?= date('d/m/Y', strtotime($r['tgl'])) ?></td>
                    <td><b><?= htmlspecialchars($r['nama']) ?></b><br><small><?= htmlspecialchars($r['kode_plat']) ?></small></td>
                    <td><b><?= htmlspecialchars($r['jenis_dokumen']) ?></b></td>
                    <td><?= htmlspecialchars($r['keterangan'] ?? '-') ?></td>
                    <td class="text-right"><?= ($current_role === 'pimpinan') ? '[Rahasia]' : number_format($r['nominal'], 0, ',', '.') ?></td>
                    <td class="text-right"><?= ($current_role === 'pimpinan') ? '[Rahasia]' : number_format($r['nominal'] * 1.10, 0, ',', '.') ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($current_role !== 'pimpinan'): ?>
                <tr style="font-weight: bold; background-color: #f1f5f9;">
                    <td colspan="5" class="text-right">TOTAL PENGELUARAN NOTA / PAJAK:</td>
                    <td class="text-right">Rp <?= number_format($total_biaya_all, 0, ',', '.') ?></td>
                    <td class="text-right">Rp <?= number_format($total_biaya_all * 1.10, 0, ',', '.') ?></td>
                </tr>
            <?php endif; ?>
        <?php endif; ?>
    </tbody>
<?php else: ?>
    <thead>
        <tr>
            <th width="4%">No</th>
            <th width="12%">Tanggal Servis</th>
            <th width="22%">Nama Unit & Plat</th>
            <th width="15%">Jenis Servis</th>
            <th width="18%">Layanan & Bengkel</th>
            <th width="15%">Pos Ruas Jalan</th>
            <th width="14%">Total Biaya (Rp)</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($data_report)): ?>
            <tr><td colspan="7" class="text-center">Tidak ada riwayat servis pada filter ini.</td></tr>
        <?php else: ?>
            <?php $total_servis_biaya = 0; ?>
            <?php foreach ($data_report as $idx => $r): 
                $total_servis_biaya += $r['total_biaya'];
            ?>
                <tr>
                    <td class="text-center"><?= $idx + 1 ?></td>
                    <td class="text-center"><?= date('d/m/Y', strtotime($r['tgl_servis'])) ?></td>
                    <td><b><?= htmlspecialchars($r['nama']) ?></b><br><small><?= htmlspecialchars($r['kode_plat']) ?></small></td>
                    <td><?= htmlspecialchars($r['jenis_servis']) ?></td>
                    <td><?= htmlspecialchars($r['nama_layanan']) ?><br><small><?= htmlspecialchars($r['nama_bengkel'] ?? 'Bengkel Resmi') ?></small></td>
                    <td><?= htmlspecialchars($r['lokasi_ruas']) ?></td>
                    <td class="text-right"><?= ($current_role === 'pimpinan') ? '[Rahasia]' : number_format($r['total_biaya'], 0, ',', '.') ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($current_role !== 'pimpinan'): ?>
                <tr style="font-weight: bold; background-color: #f1f5f9;">
                    <td colspan="6" class="text-right">TOTAL BIAYA SERVIS:</td>
                    <td class="text-right">Rp <?= number_format($total_servis_biaya, 0, ',', '.') ?></td>
                </tr>
            <?php endif; ?>
        <?php endif; ?>
    </tbody>
<?php endif; ?>
</table>

<table class="footer-ttd">
    <tr>
        <td width="50%">
            Mengetahui,<br>
            <b>Kepala Balai BPJ Wilayah Tegal</b>
            <br><br><br><br>
            <u><b>( Ir. Dwi Ariyanto, M.T. )</b></u><br>
            NIP. 19740512 200212 1 003
        </td>
        <td width="50%">
            Tegal, <?= date('d F Y') ?><br>
            <b>Penanggung Jawab Logistik & Armada</b>
            <br><br><br><br>
            <u><b>( <?= htmlspecialchars($_SESSION['nama_lengkap'] ?? 'Admin ALISA') ?> )</b></u><br>
            NIP / ID. ALISA-BPJ-<?= date('Y') ?>
        </td>
    </tr>
</table>

</body>
</html>

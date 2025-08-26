<?php
// export_pdf.php - Export laporan ke PDF menggunakan mPDF
require_once 'vendor/autoload.php';
include 'koneksi.php';

use Mpdf\Mpdf;

// Ambil parameter dari URL
$tanggal_filter = isset($_GET['tanggal']) && !empty($_GET['tanggal']) ? $_GET['tanggal'] : date('Y-m-d');
$kelas_filter = isset($_GET['kelas']) && !empty($_GET['kelas']) ? $_GET['kelas'] : '';
$nama_filter = isset($_GET['nama']) && !empty($_GET['nama']) ? $_GET['nama'] : '';
$laporan_type = isset($_GET['laporan_type']) ? $_GET['laporan_type'] : 'harian';
$tanggal_mulai = isset($_GET['tanggal_mulai']) ? $_GET['tanggal_mulai'] : date('Y-m-d', strtotime('-7 days'));
$tanggal_akhir = isset($_GET['tanggal_akhir']) ? $_GET['tanggal_akhir'] : date('Y-m-d');

// Fungsi untuk mendapatkan statistik absensi
function getAttendanceStats($koneksi, $tanggal_filter, $kelas_filter = '', $nama_filter = '') {
    $sql = "SELECT 
                COUNT(*) as total_absensi,
                COUNT(CASE WHEN a.waktu_masuk IS NOT NULL THEN 1 END) as hadir,
                COUNT(CASE WHEN a.waktu_keluar IS NOT NULL THEN 1 END) as sudah_pulang,
                COUNT(CASE WHEN a.waktu_keluar IS NULL AND a.waktu_masuk IS NOT NULL THEN 1 END) as belum_pulang
            FROM absensi a 
            JOIN siswa s ON a.id_siswa = s.id 
            WHERE DATE(a.waktu_masuk) = ?";

    $params = [$tanggal_filter];
    $types = "s";

    if (!empty($kelas_filter)) {
        $sql .= " AND s.kelas = ?";
        $params[] = $kelas_filter;
        $types .= "s";
    }

    if (!empty($nama_filter)) {
        $sql .= " AND s.nama_lengkap LIKE ?";
        $params[] = '%' . $nama_filter . '%';
        $types .= "s";
    }

    $stmt = mysqli_prepare($koneksi, $sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    return mysqli_fetch_assoc($result);
}

// Fungsi untuk mendapatkan siswa yang tidak hadir
function getAbsentStudents($koneksi, $tanggal_filter, $kelas_filter = '') {
    $sql = "SELECT s.nama_lengkap, s.kelas 
            FROM siswa s 
            WHERE s.id NOT IN (
                SELECT DISTINCT a.id_siswa 
                FROM absensi a 
                WHERE DATE(a.waktu_masuk) = ?
            )";

    $params = [$tanggal_filter];
    $types = "s";

    if (!empty($kelas_filter)) {
        $sql .= " AND s.kelas = ?";
        $params[] = $kelas_filter;
        $types .= "s";
    }

    $sql .= " ORDER BY s.kelas, s.nama_lengkap";

    $stmt = mysqli_prepare($koneksi, $sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    return mysqli_stmt_get_result($stmt);
}

// Query untuk laporan berdasarkan tipe
if ($laporan_type == 'rentang') {
    $sql = "SELECT a.id, s.nama_lengkap, s.kelas, 
                   DATE(a.waktu_masuk) as tanggal,
                   a.waktu_masuk, a.waktu_keluar 
            FROM absensi a 
            JOIN siswa s ON a.id_siswa = s.id 
            WHERE DATE(a.waktu_masuk) BETWEEN ? AND ?";

    $params = [$tanggal_mulai, $tanggal_akhir];
    $types = "ss";
} else {
    $sql = "SELECT a.id, s.nama_lengkap, s.kelas, a.waktu_masuk, a.waktu_keluar 
            FROM absensi a 
            JOIN siswa s ON a.id_siswa = s.id 
            WHERE DATE(a.waktu_masuk) = ?";

    $params = [$tanggal_filter];
    $types = "s";
}

// Tambahkan filter kelas dan nama
if (!empty($kelas_filter)) {
    $sql .= " AND s.kelas = ?";
    $params[] = $kelas_filter;
    $types .= "s";
}

if (!empty($nama_filter)) {
    $sql .= " AND s.nama_lengkap LIKE ?";
    $params[] = '%' . $nama_filter . '%';
    $types .= "s";
}

$sql .= " ORDER BY a.waktu_masuk DESC";

$stmt = mysqli_prepare($koneksi, $sql);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Ambil statistik dan data pendukung
$stats = getAttendanceStats($koneksi, $tanggal_filter, $kelas_filter, $nama_filter);
$absent_students = getAbsentStudents($koneksi, $tanggal_filter, $kelas_filter);

// Ambil pengaturan absensi
$sql_pengaturan = "SELECT * FROM pengaturan_absensi WHERE id = 1";
$result_pengaturan = mysqli_query($koneksi, $sql_pengaturan);
$pengaturan = mysqli_fetch_assoc($result_pengaturan);

// Mulai membuat HTML untuk PDF
$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Laporan Absensi - Pondok Pesantren Yati</title>
    <style>
        body {
            font-family: "DejaVu Sans", Arial, sans-serif;
            margin: 0;
            padding: 0;
            font-size: 10px;
            line-height: 1.4;
        }
        
        .header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #2563eb;
        }
        
        .logo-container {
            margin-bottom: 10px;
        }
        
        .logo {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #2563eb, #1e40af);
            border-radius: 50%;
            margin: 0 auto 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
            font-weight: bold;
        }
        
        .header h1 {
            margin: 0;
            font-size: 18px;
            color: #1e40af;
            font-weight: bold;
        }
        
        .header h2 {
            margin: 5px 0;
            font-size: 14px;
            color: #475569;
            font-weight: normal;
        }
        
        .header p {
            margin: 2px 0;
            font-size: 9px;
            color: #64748b;
        }
        
        .report-title {
            background: #f1f5f9;
            padding: 10px;
            margin: 15px 0;
            border-left: 4px solid #2563eb;
        }
        
        .report-title h3 {
            margin: 0;
            font-size: 14px;
            color: #1e40af;
            font-weight: bold;
        }
        
        .info-section {
            display: table;
            width: 100%;
            margin-bottom: 15px;
        }
        
        .info-item {
            display: table-cell;
            width: 25%;
            padding: 5px;
            font-size: 9px;
            vertical-align: top;
        }
        
        .info-label {
            font-weight: bold;
            color: #374151;
        }
        
        .info-value {
            color: #6b7280;
        }
        
        .statistics {
            display: table;
            width: 100%;
            margin-bottom: 15px;
            border-collapse: separate;
            border-spacing: 5px;
        }
        
        .stat-card {
            display: table-cell;
            width: 25%;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            text-align: center;
            padding: 10px 5px;
            vertical-align: middle;
        }
        
        .stat-number {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 8px;
            color: #6b7280;
            text-transform: uppercase;
            font-weight: bold;
        }
        
        .stat-card.total .stat-number { color: #2563eb; }
        .stat-card.hadir .stat-number { color: #059669; }
        .stat-card.belum-pulang .stat-number { color: #d97706; }
        .stat-card.tidak-hadir .stat-number { color: #dc2626; }
        
        .absent-section {
            background: #fef2f2;
            border: 1px solid #fecaca;
            padding: 10px;
            margin-bottom: 15px;
        }
        
        .absent-section h4 {
            color: #dc2626;
            margin: 0 0 10px 0;
            font-size: 12px;
        }
        
        .absent-list {
            display: table;
            width: 100%;
        }
        
        .absent-item {
            display: table-cell;
            width: 33.33%;
            padding: 5px;
            font-size: 9px;
        }
        
        .absent-name {
            font-weight: bold;
            color: #374151;
        }
        
        .absent-class {
            color: #6b7280;
            font-size: 8px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        
        th, td {
            border: 1px solid #e5e7eb;
            padding: 6px 4px;
            text-align: left;
            font-size: 9px;
        }
        
        th {
            background: #1e40af;
            color: white;
            font-weight: bold;
            text-align: center;
        }
        
        tr:nth-child(even) {
            background: #f9fafb;
        }
        
        .text-center { text-align: center; }
        
        .status {
            padding: 2px 6px;
            border-radius: 8px;
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .status.hadir {
            background: #dcfce7;
            color: #166534;
        }
        
        .status.terlambat {
            background: #fef2f2;
            color: #991b1b;
        }
        
        .status.belum-pulang {
            background: #fef3c7;
            color: #92400e;
        }
        
        .status.pulang-cepat {
            background: #fed7aa;
            color: #9a3412;
        }
        
        .kelas-badge {
            background: #eff6ff;
            color: #1e40af;
            padding: 2px 6px;
            border-radius: 6px;
            font-size: 8px;
            font-weight: bold;
        }
        
        .time-badge {
            background: #f0fdf4;
            color: #166534;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 8px;
        }
        
        .time-badge.keluar {
            background: #fef3c7;
            color: #92400e;
        }
        
        .info-box {
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            padding: 10px;
            margin-top: 15px;
        }
        
        .info-box h5 {
            color: #0c4a6e;
            font-size: 11px;
            font-weight: bold;
            margin: 0 0 8px 0;
        }
        
        .info-grid {
            display: table;
            width: 100%;
        }
        
        .info-col {
            display: table-cell;
            width: 50%;
            padding: 0 10px 0 0;
            font-size: 9px;
        }
        
        .footer {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #e5e7eb;
            display: table;
            width: 100%;
        }
        
        .signature {
            display: table-cell;
            width: 50%;
            text-align: center;
            padding: 0 15px;
        }
        
        .signature-title {
            font-size: 10px;
            font-weight: bold;
            color: #374151;
            margin-bottom: 40px;
        }
        
        .signature-name {
            font-size: 10px;
            font-weight: bold;
            color: #1f2937;
            border-bottom: 1px solid #374151;
            padding-bottom: 2px;
            display: inline-block;
            min-width: 120px;
        }
        
        .signature-position {
            font-size: 9px;
            color: #6b7280;
            margin-top: 5px;
        }
        
        .no-data {
            text-align: center;
            padding: 30px;
            color: #6b7280;
            background: #f9fafb;
        }
        
        .page-break {
            page-break-before: always;
        }
    </style>
</head>
<body>';

// Header Kop Surat
$html .= '
    <div class="header">
        <div class="logo-container">
            <div class="logo">PP</div>
        </div>
        <h1>PONDOK PESANTREN YATI</h1>
        <h2>Sistem Absensi Digital</h2>
        <p>Jl. Contoh Alamat No. 123, Kota, Provinsi</p>
        <p>Telp: (021) 1234-5678 | Email: info@pondokyati.ac.id</p>
        <p>Website: www.pondokyati.ac.id</p>
    </div>';

// Judul Laporan
$report_title = $laporan_type == 'rentang' ? 'LAPORAN ABSENSI RENTANG TANGGAL' : 'LAPORAN ABSENSI HARIAN';
$html .= '
    <div class="report-title">
        <h3>' . $report_title . '</h3>
    </div>';

// Info Laporan
$tanggal_laporan = '';
if ($laporan_type == 'rentang') {
    $tanggal_laporan = date('d/m/Y', strtotime($tanggal_mulai)) . ' - ' . date('d/m/Y', strtotime($tanggal_akhir));
} else {
    $tanggal_laporan = date('d/m/Y', strtotime($tanggal_filter));
}

$html .= '
    <div class="info-section">
        <div class="info-item">
            <div class="info-label">Tanggal Laporan:</div>
            <div class="info-value">' . $tanggal_laporan . '</div>
        </div>
        <div class="info-item">
            <div class="info-label">Dicetak pada:</div>
            <div class="info-value">' . date('d/m/Y H:i:s') . ' WIB</div>
        </div>
        <div class="info-item">
            <div class="info-label">Filter Kelas:</div>
            <div class="info-value">' . (!empty($kelas_filter) ? $kelas_filter : 'Semua Kelas') . '</div>
        </div>
        <div class="info-item">
            <div class="info-label">Total Data:</div>
            <div class="info-value">' . mysqli_num_rows($result) . ' Record</div>
        </div>
    </div>';

// Statistik (hanya untuk laporan harian)
if ($laporan_type == 'harian') {
    $html .= '
        <div class="statistics">
            <div class="stat-card total">
                <div class="stat-number">' . $stats['total_absensi'] . '</div>
                <div class="stat-label">Total Absensi</div>
            </div>
            <div class="stat-card hadir">
                <div class="stat-number">' . $stats['hadir'] . '</div>
                <div class="stat-label">Hadir</div>
            </div>
            <div class="stat-card belum-pulang">
                <div class="stat-number">' . $stats['belum_pulang'] . '</div>
                <div class="stat-label">Belum Pulang</div>
            </div>
            <div class="stat-card tidak-hadir">
                <div class="stat-number">' . mysqli_num_rows($absent_students) . '</div>
                <div class="stat-label">Tidak Hadir</div>
            </div>
        </div>';
}

// Siswa Tidak Hadir
if ($laporan_type == 'harian' && mysqli_num_rows($absent_students) > 0) {
    $html .= '
        <div class="absent-section">
            <h4>⚠️ Daftar Siswa Tidak Hadir (' . mysqli_num_rows($absent_students) . ' Siswa)</h4>';
    
    $absent_html = '';
    $count = 0;
    mysqli_data_seek($absent_students, 0);
    
    while ($absent = mysqli_fetch_assoc($absent_students)) {
        if ($count % 3 == 0 && $count > 0) {
            $absent_html .= '</div><div class="absent-list">';
        } elseif ($count == 0) {
            $absent_html .= '<div class="absent-list">';
        }
        
        $absent_html .= '
            <div class="absent-item">
                <div class="absent-name">' . htmlspecialchars($absent['nama_lengkap']) . '</div>
                <div class="absent-class">Kelas: ' . htmlspecialchars($absent['kelas']) . '</div>
            </div>';
        
        $count++;
    }
    
    $absent_html .= '</div>';
    $html .= $absent_html . '</div>';
}

// Tabel Data Absensi
$html .= '
    <table>
        <thead>
            <tr>
                <th style="width: 5%">No</th>
                <th style="width: 25%">Nama Lengkap</th>
                <th style="width: 10%">Kelas</th>';

if ($laporan_type == 'rentang') {
    $html .= '<th style="width: 12%">Tanggal</th>';
}

$html .= '
                <th style="width: 15%">Waktu Masuk</th>
                <th style="width: 15%">Waktu Keluar</th>
                <th style="width: 10%">Durasi</th>
                <th style="width: 13%">Status</th>
            </tr>
        </thead>
        <tbody>';

if (mysqli_num_rows($result) > 0) {
    $no = 1;
    while ($row = mysqli_fetch_assoc($result)) {
        $html .= '<tr>';
        $html .= '<td class="text-center">' . $no++ . '</td>';
        $html .= '<td><strong>' . htmlspecialchars($row['nama_lengkap']) . '</strong></td>';
        $html .= '<td class="text-center"><span class="kelas-badge">' . htmlspecialchars($row['kelas']) . '</span></td>';
        
        if ($laporan_type == 'rentang') {
            $html .= '<td class="text-center">' . date('d/m/Y', strtotime($row['tanggal'])) . '</td>';
        }
        
        // Waktu Masuk
        if ($row['waktu_masuk']) {
            $html .= '<td class="text-center"><span class="time-badge">' . date('H:i:s', strtotime($row['waktu_masuk'])) . '</span></td>';
        } else {
            $html .= '<td class="text-center">-</td>';
        }
        
        // Waktu Keluar
        if ($row['waktu_keluar']) {
            $html .= '<td class="text-center"><span class="time-badge keluar">' . date('H:i:s', strtotime($row['waktu_keluar'])) . '</span></td>';
        } else {
            $html .= '<td class="text-center"><span style="color: #f59e0b; font-size: 8px;">Belum Pulang</span></td>';
        }
        
        // Durasi
        if ($row['waktu_masuk'] && $row['waktu_keluar']) {
            $masuk = strtotime($row['waktu_masuk']);
            $keluar = strtotime($row['waktu_keluar']);
            $durasi = $keluar - $masuk;
            $jam = floor($durasi / 3600);
            $menit = floor(($durasi % 3600) / 60);
            $html .= '<td class="text-center">' . $jam . 'j ' . $menit . 'm</td>';
        } else {
            $html .= '<td class="text-center">-</td>';
        }
        
        // Status
        $status = 'Hadir';
        $status_class = 'hadir';

        if ($row['waktu_masuk']) {
            $waktu_masuk_only = date('H:i:s', strtotime($row['waktu_masuk']));
            if (strtotime($waktu_masuk_only) > strtotime($pengaturan['waktu_masuk_akhir'])) {
                $status = 'Terlambat';
                $status_class = 'terlambat';
            }
        }

        if ($row['waktu_keluar']) {
            $waktu_keluar_only = date('H:i:s', strtotime($row['waktu_keluar']));
            if (strtotime($waktu_keluar_only) < strtotime($pengaturan['waktu_keluar_mulai'])) {
                if ($status == 'Terlambat') {
                    $status = 'Terlambat & Pulang Cepat';
                    $status_class = 'terlambat';
                } else {
                    $status = 'Pulang Cepat';
                    $status_class = 'pulang-cepat';
                }
            }
        } else {
            if ($status == 'Terlambat') {
                $status = 'Terlambat';
            } else {
                $status = 'Belum Pulang';
                $status_class = 'belum-pulang';
            }
        }
        
        $html .= '<td class="text-center"><span class="status ' . $status_class . '">' . $status . '</span></td>';
        $html .= '</tr>';
    }
} else {
    $colspan = $laporan_type == 'rentang' ? '8' : '7';
    $html .= '
        <tr>
            <td colspan="' . $colspan . '" class="text-center">
                <div class="no-data">
                    <h4>Tidak Ada Data Absensi</h4>
                    <p>Tidak ada data yang sesuai dengan filter yang dipilih</p>
                </div>
            </td>
        </tr>';
}

$html .= '
        </tbody>
    </table>';

// Informasi Sistem
$html .= '
    <div class="info-box">
        <h5>📋 Informasi Sistem Absensi</h5>
        <div class="info-grid">
            <div class="info-col">
                <p><strong>Jam Masuk:</strong> ' . date('H:i', strtotime($pengaturan['waktu_masuk_mulai'])) . ' - ' . date('H:i', strtotime($pengaturan['waktu_masuk_akhir'])) . ' WIB</p>
                <p><strong>Jam Pulang:</strong> ' . date('H:i', strtotime($pengaturan['waktu_keluar_mulai'])) . ' - ' . date('H:i', strtotime($pengaturan['waktu_keluar_akhir'])) . ' WIB</p>
            </div>
            <div class="info-col">
                <p><strong>Status Hadir:</strong> Masuk tepat waktu</p>
                <p><strong>Status Terlambat:</strong> Masuk setelah ' . date('H:i', strtotime($pengaturan['waktu_masuk_akhir'])) . ' WIB</p>
            </div>
        </div>
    </div>';

// Footer dan Tanda Tangan
$html .= '
    <div class="footer">
        <div class="signature">
            <div class="signature-title">Mengetahui,<br>Kepala Pondok Pesantren</div>
            <div class="signature-name">................................</div>
            <div class="signature-position">Kepala Pondok Pesantren</div>
        </div>
        <div class="signature">
            <div class="signature-title">Padang, ' . date('d F Y') . '<br>Penanggung Jawab Absensi</div>
            <div class="signature-name">................................</div>
            <div class="signature-position">Admin Sistem</div>
        </div>
    </div>';

$html .= '</body></html>';

try {
    // Konfigurasi mPDF
    $mpdf = new Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4',
        'orientation' => 'P',
        'margin_left' => 15,
        'margin_right' => 15,
        'margin_top' => 20,
        'margin_bottom' => 20,
        'margin_header' => 10,
        'margin_footer' => 10,
        'default_font_size' => 10,
        'default_font' => 'dejavusans'
    ]);

    // Set properties PDF
    $mpdf->SetTitle('Laporan Absensi - Pondok Pesantren Yati');
    $mpdf->SetAuthor('Pondok Pesantren Yati');
    $mpdf->SetSubject('Laporan Absensi Siswa');
    $mpdf->SetKeywords('absensi, laporan, pondok pesantren, yati');

    // Header dan Footer
    $mpdf->SetHTMLHeader('
        <div style="text-align: right; font-size: 8px; color: #666;">
            Laporan Absensi - ' . $tanggal_laporan . '
        </div>
    ');
    
    $mpdf->SetHTMLFooter('
        <div style="text-align: center; font-size: 8px; color: #666; border-top: 1px solid #ddd; padding-top: 5px;">
            Halaman {PAGENO} dari {nbpg} | Dicetak pada: ' . date('d/m/Y H:i:s') . ' WIB | © Pondok Pesantren Yati
        </div>
    ');

    // Write HTML content
    $mpdf->WriteHTML($html);

    // Generate filename
    $filename = 'Laporan_Absensi_';
    if ($laporan_type == 'rentang') {
        $filename .= date('Ymd', strtotime($tanggal_mulai)) . '_to_' . date('Ymd', strtotime($tanggal_akhir));
    } else {
        $filename .= date('Ymd', strtotime($tanggal_filter));
    }
    if (!empty($kelas_filter)) {
        $filename .= '_Kelas_' . $kelas_filter;
    }
    $filename .= '.pdf';

    // Output PDF
    $mpdf->Output($filename, 'D'); // 'D' = Download, 'I' = Inline view

} catch (Exception $e) {
    echo 'Error generating PDF: ' . $e->getMessage();
}

// Close database connection
mysqli_stmt_close($stmt);
mysqli_close($koneksi);
?>
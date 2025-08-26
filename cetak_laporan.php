<?php
// cetak_laporan.php - File khusus untuk mencetak laporan
include 'koneksi.php';

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
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Laporan Absensi - Pondok Pesantren Yati</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
            background: #fff;
        }

        .container {
            max-width: 210mm;
            margin: 0 auto;
            padding: 20px;
            background: white;
        }

        /* Header Kop Surat */
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 3px solid #2563eb;
            position: relative;
        }

        .header::after {
            content: '';
            position: absolute;
            bottom: -3px;
            left: 0;
            width: 100%;
            height: 1px;
            background: #64748b;
        }

        .logo {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #2563eb, #1e40af);
            border-radius: 50%;
            margin: 0 auto 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 28px;
            font-weight: bold;
        }

        .header h1 {
            font-size: 24px;
            font-weight: 800;
            color: #1e40af;
            margin-bottom: 5px;
            letter-spacing: 0.5px;
        }

        .header h2 {
            font-size: 16px;
            font-weight: 600;
            color: #475569;
            margin-bottom: 3px;
        }

        .header p {
            font-size: 11px;
            color: #64748b;
            margin-bottom: 2px;
        }

        .report-title {
            background: linear-gradient(135deg, #f1f5f9, #e2e8f0);
            padding: 15px;
            margin: 20px 0;
            border-radius: 8px;
            border-left: 5px solid #2563eb;
        }

        .report-title h3 {
            font-size: 18px;
            font-weight: 700;
            color: #1e40af;
            margin-bottom: 5px;
        }

        .report-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
            font-size: 11px;
        }

        .info-item {
            display: flex;
            align-items: center;
        }

        .info-item strong {
            min-width: 100px;
            color: #374151;
        }

        .info-item span {
            color: #6b7280;
        }

        /* Statistik */
        .statistics {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: linear-gradient(135deg, #f8fafc, #f1f5f9);
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
        }

        .stat-card.total::before { background: #2563eb; }
        .stat-card.hadir::before { background: #059669; }
        .stat-card.belum-pulang::before { background: #d97706; }
        .stat-card.tidak-hadir::before { background: #dc2626; }

        .stat-number {
            font-size: 24px;
            font-weight: 800;
            margin-bottom: 5px;
        }

        .stat-card.total .stat-number { color: #2563eb; }
        .stat-card.hadir .stat-number { color: #059669; }
        .stat-card.belum-pulang .stat-number { color: #d97706; }
        .stat-card.tidak-hadir .stat-number { color: #dc2626; }

        .stat-label {
            font-size: 11px;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Tabel */
        .table-container {
            margin-bottom: 25px;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid #e5e7eb;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }

        th {
            background: linear-gradient(135deg, #1e40af, #2563eb);
            color: white;
            font-weight: 600;
            padding: 12px 8px;
            text-align: left;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        th.center { text-align: center; }

        td {
            padding: 10px 8px;
            border-bottom: 1px solid #f3f4f6;
            vertical-align: middle;
        }

        tr:nth-child(even) {
            background: #f9fafb;
        }

        tr:hover {
            background: #f3f4f6;
        }

        .center { text-align: center; }

        .status {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 9px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .status.hadir {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .status.terlambat {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .status.belum-pulang {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fde68a;
        }

        .status.pulang-cepat {
            background: #fed7aa;
            color: #9a3412;
            border: 1px solid #fdba74;
        }

        .kelas-badge {
            background: #eff6ff;
            color: #1e40af;
            padding: 3px 8px;
            border-radius: 8px;
            font-size: 9px;
            font-weight: 600;
            border: 1px solid #dbeafe;
        }

        .time-badge {
            background: #f0fdf4;
            color: #166534;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 9px;
            font-weight: 500;
            border: 1px solid #bbf7d0;
        }

        .time-badge.keluar {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fde68a;
        }

        /* Siswa Tidak Hadir */
        .absent-section {
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 25px;
        }

        .absent-section h4 {
            color: #dc2626;
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
        }

        .absent-section h4::before {
            content: '⚠️';
            margin-right: 8px;
        }

        .absent-list {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
        }

        .absent-item {
            background: #fff;
            padding: 8px 12px;
            border-radius: 6px;
            border: 1px solid #f3f4f6;
            font-size: 10px;
        }

        .absent-name {
            font-weight: 600;
            color: #374151;
        }

        .absent-class {
            color: #6b7280;
            font-size: 9px;
        }

        /* Footer */
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }

        .signature {
            text-align: center;
        }

        .signature-title {
            font-size: 11px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 50px;
        }

        .signature-name {
            font-size: 11px;
            font-weight: 700;
            color: #1f2937;
            border-bottom: 1px solid #374151;
            padding-bottom: 2px;
            display: inline-block;
            min-width: 150px;
        }

        .signature-position {
            font-size: 9px;
            color: #6b7280;
            margin-top: 5px;
        }

        /* Info Box */
        .info-box {
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .info-box h5 {
            color: #0c4a6e;
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            font-size: 10px;
        }

        .info-grid p {
            display: flex;
            align-items: center;
            margin-bottom: 5px;
        }

        .info-grid strong {
            min-width: 80px;
            color: #374151;
        }

        /* No Data */
        .no-data {
            text-align: center;
            padding: 40px 20px;
            color: #6b7280;
            background: #f9fafb;
            border-radius: 8px;
            margin: 20px 0;
        }

        .no-data-icon {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        .no-data h4 {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 8px;
            color: #4b5563;
        }

        .no-data p {
            font-size: 12px;
        }

        /* Print Styles */
        @media print {
            body {
                background: white !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .container {
                max-width: none;
                padding: 0;
            }

            .table-container {
                break-inside: avoid;
            }

            .stat-card {
                break-inside: avoid;
            }

            .absent-section {
                break-inside: avoid;
            }

            thead {
                display: table-header-group;
            }

            tr {
                break-inside: avoid;
            }
        }

        @page {
            size: A4;
            margin: 1cm;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header Kop Surat -->
        <div class="header">
            <div class="logo">PP</div>
            <h1>PONDOK PESANTREN YATI</h1>
            <h2>Sistem Absensi Digital</h2>
            <p>Jl. Contoh Alamat No. 123, Kota, Provinsi</p>
            <p>Telp: (021) 1234-5678 | Email: info@pondokyati.ac.id</p>
            <p>Website: www.pondokyati.ac.id</p>
        </div>

        <!-- Judul Laporan -->
        <div class="report-title">
            <h3>
                <?php echo $laporan_type == 'rentang' ? 'LAPORAN ABSENSI RENTANG TANGGAL' : 'LAPORAN ABSENSI HARIAN'; ?>
            </h3>
        </div>

        <!-- Info Laporan -->
        <div class="report-info">
            <div class="info-item">
                <strong>Tanggal Laporan:</strong>
                <span>
                    <?php 
                    if ($laporan_type == 'rentang') {
                        echo date('d/m/Y', strtotime($tanggal_mulai)) . ' - ' . date('d/m/Y', strtotime($tanggal_akhir));
                    } else {
                        echo date('d/m/Y', strtotime($tanggal_filter));
                    }
                    ?>
                </span>
            </div>
            <div class="info-item">
                <strong>Dicetak pada:</strong>
                <span><?php echo date('d/m/Y H:i:s'); ?> WIB</span>
            </div>
            <div class="info-item">
                <strong>Filter Kelas:</strong>
                <span><?php echo !empty($kelas_filter) ? $kelas_filter : 'Semua Kelas'; ?></span>
            </div>
            <div class="info-item">
                <strong>Total Data:</strong>
                <span><?php echo mysqli_num_rows($result); ?> Record</span>
            </div>
        </div>

        <!-- Statistik (hanya untuk laporan harian) -->
        <?php if ($laporan_type == 'harian'): ?>
        <div class="statistics">
            <div class="stat-card total">
                <div class="stat-number"><?php echo $stats['total_absensi']; ?></div>
                <div class="stat-label">Total Absensi</div>
            </div>
            <div class="stat-card hadir">
                <div class="stat-number"><?php echo $stats['hadir']; ?></div>
                <div class="stat-label">Hadir</div>
            </div>
            <div class="stat-card belum-pulang">
                <div class="stat-number"><?php echo $stats['belum_pulang']; ?></div>
                <div class="stat-label">Belum Pulang</div>
            </div>
            <div class="stat-card tidak-hadir">
                <div class="stat-number"><?php echo mysqli_num_rows($absent_students); ?></div>
                <div class="stat-label">Tidak Hadir</div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Siswa Tidak Hadir -->
        <?php if ($laporan_type == 'harian' && mysqli_num_rows($absent_students) > 0): ?>
        <div class="absent-section">
            <h4>Daftar Siswa Tidak Hadir (<?php echo mysqli_num_rows($absent_students); ?> Siswa)</h4>
            <div class="absent-list">
                <?php mysqli_data_seek($absent_students, 0); ?>
                <?php while ($absent = mysqli_fetch_assoc($absent_students)): ?>
                <div class="absent-item">
                    <div class="absent-name"><?php echo htmlspecialchars($absent['nama_lengkap']); ?></div>
                    <div class="absent-class">Kelas: <?php echo htmlspecialchars($absent['kelas']); ?></div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Tabel Data Absensi -->
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th class="center" style="width: 5%">No</th>
                        <th style="width: 25%">Nama Lengkap</th>
                        <th class="center" style="width: 10%">Kelas</th>
                        <?php if ($laporan_type == 'rentang'): ?>
                        <th class="center" style="width: 12%">Tanggal</th>
                        <?php endif; ?>
                        <th class="center" style="width: 15%">Waktu Masuk</th>
                        <th class="center" style="width: 15%">Waktu Keluar</th>
                        <th class="center" style="width: 10%">Durasi</th>
                        <th class="center" style="width: 13%">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($result) > 0): ?>
                        <?php $no = 1; ?>
                        <?php while ($row = mysqli_fetch_assoc($result)): ?>
                        <tr>
                            <td class="center"><?php echo $no++; ?></td>
                            <td><strong><?php echo htmlspecialchars($row['nama_lengkap']); ?></strong></td>
                            <td class="center">
                                <span class="kelas-badge"><?php echo htmlspecialchars($row['kelas']); ?></span>
                            </td>
                            <?php if ($laporan_type == 'rentang'): ?>
                            <td class="center">
                                <?php echo date('d/m/Y', strtotime($row['tanggal'])); ?>
                            </td>
                            <?php endif; ?>
                            <td class="center">
                                <?php if ($row['waktu_masuk']): ?>
                                    <span class="time-badge">
                                        <?php echo date('H:i:s', strtotime($row['waktu_masuk'])); ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color: #9ca3af;">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="center">
                                <?php if ($row['waktu_keluar']): ?>
                                    <span class="time-badge keluar">
                                        <?php echo date('H:i:s', strtotime($row['waktu_keluar'])); ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color: #f59e0b; font-size: 9px;">Belum Pulang</span>
                                <?php endif; ?>
                            </td>
                            <td class="center">
                                <?php
                                if ($row['waktu_masuk'] && $row['waktu_keluar']) {
                                    $masuk = strtotime($row['waktu_masuk']);
                                    $keluar = strtotime($row['waktu_keluar']);
                                    $durasi = $keluar - $masuk;
                                    $jam = floor($durasi / 3600);
                                    $menit = floor(($durasi % 3600) / 60);
                                    echo '<span style="font-size: 10px; color: #4b5563;">' . $jam . 'j ' . $menit . 'm</span>';
                                } else {
                                    echo '<span style="color: #9ca3af;">-</span>';
                                }
                                ?>
                            </td>
                            <td class="center">
                                <?php
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
                                ?>
                                <span class="status <?php echo $status_class; ?>">
                                    <?php echo $status; ?>
                                </span>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="<?php echo $laporan_type == 'rentang' ? '8' : '7'; ?>" class="center">
                                <div class="no-data">
                                    <div class="no-data-icon">📋</div>
                                    <h4>Tidak Ada Data Absensi</h4>
                                    <p>Tidak ada data yang sesuai dengan filter yang dipilih</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Informasi Sistem -->
        <div class="info-box">
            <h5>📋 Informasi Sistem Absensi</h5>
            <div class="info-grid">
                <div>
                    <p><strong>Jam Masuk:</strong> <?php echo date('H:i', strtotime($pengaturan['waktu_masuk_mulai'])); ?> - <?php echo date('H:i', strtotime($pengaturan['waktu_masuk_akhir'])); ?> WIB</p>
                    <p><strong>Jam Pulang:</strong> <?php echo date('H:i', strtotime($pengaturan['waktu_keluar_mulai'])); ?> - <?php echo date('H:i', strtotime($pengaturan['waktu_keluar_akhir'])); ?> WIB</p>
                </div>
                <div>
                    <p><strong>Status Hadir:</strong> Masuk tepat waktu</p>
                    <p><strong>Status Terlambat:</strong> Masuk setelah <?php echo date('H:i', strtotime($pengaturan['waktu_masuk_akhir'])); ?> WIB</p>
                    <p><strong>Status Pulang Cepat:</strong> Pulang sebelum <?php echo date('H:i', strtotime($pengaturan['waktu_keluar_mulai'])); ?> WIB</p>
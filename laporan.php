<?php
// laporan.php - Enhanced Version
include 'koneksi.php'; // Includes database connection and timezone setting

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

// Ambil parameter filter
$tanggal_filter = isset($_GET['tanggal']) && !empty($_GET['tanggal']) ? $_GET['tanggal'] : date('Y-m-d');
$kelas_filter = isset($_GET['kelas']) && !empty($_GET['kelas']) ? $_GET['kelas'] : '';
$nama_filter = isset($_GET['nama']) && !empty($_GET['nama']) ? $_GET['nama'] : '';
$laporan_type = isset($_GET['laporan_type']) ? $_GET['laporan_type'] : 'harian';
$tanggal_mulai = isset($_GET['tanggal_mulai']) ? $_GET['tanggal_mulai'] : date('Y-m-d', strtotime('-7 days'));
$tanggal_akhir = isset($_GET['tanggal_akhir']) ? $_GET['tanggal_akhir'] : date('Y-m-d');

// Export functionality
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="laporan_absensi_' . $tanggal_filter . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['No', 'Nama Lengkap', 'Kelas', 'Waktu Masuk', 'Waktu Keluar', 'Status']);
    
    // Get data for CSV
    $sql = "SELECT s.nama_lengkap, s.kelas, a.waktu_masuk, a.waktu_keluar 
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
    
    $sql .= " ORDER BY a.waktu_masuk DESC";
    
    $stmt = mysqli_prepare($koneksi, $sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $no = 1;
    while ($row = mysqli_fetch_assoc($result)) {
        $waktu_masuk = $row['waktu_masuk'] ? date('d-m-Y H:i:s', strtotime($row['waktu_masuk'])) : '-';
        $waktu_keluar = $row['waktu_keluar'] ? date('d-m-Y H:i:s', strtotime($row['waktu_keluar'])) : '-';
        $status = 'Hadir'; // Simplified for CSV
        
        fputcsv($output, [$no++, $row['nama_lengkap'], $row['kelas'], $waktu_masuk, $waktu_keluar, $status]);
    }
    
    fclose($output);
    exit();
}

// Query untuk laporan berdasarkan tipe
if ($laporan_type == 'rentang') {
    $sql = "SELECT s.nama_lengkap, s.kelas, 
                   DATE(a.waktu_masuk) as tanggal,
                   a.waktu_masuk, a.waktu_keluar 
            FROM absensi a 
            JOIN siswa s ON a.id_siswa = s.id 
            WHERE DATE(a.waktu_masuk) BETWEEN ? AND ?";
    
    $params = [$tanggal_mulai, $tanggal_akhir];
    $types = "ss";
} else {
    $sql = "SELECT s.nama_lengkap, s.kelas, a.waktu_masuk, a.waktu_keluar 
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

// Ambil daftar kelas untuk filter dropdown
$sql_kelas = "SELECT DISTINCT kelas FROM siswa ORDER BY kelas";
$result_kelas = mysqli_query($koneksi, $sql_kelas);

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
    <title>Laporan Absensi Siswa - Enhanced</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        @keyframes fade-in {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-in {
            animation: fade-in 0.4s ease-out;
        }

        @media print {
            .no-print {
                display: none !important;
            }
            
            .print-only {
                display: block !important;
            }
            
            body {
                background: white !important;
            }
            
            .bg-gradient-to-br {
                background: white !important;
            }
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }
    </style>
</head>

<body class="relative min-h-screen bg-gradient-to-br from-blue-50 to-blue-200 flex flex-col font-sans">
    <!-- Navbar -->
    <nav class="bg-blue-800 bg-opacity-80 text-white shadow-lg sticky top-0 z-20 backdrop-blur-md no-print">
        <div class="max-w-6xl mx-auto flex justify-between items-center px-6 py-4">
            <div class="flex items-center gap-3">
                <img src="foto-pondok.jpg" alt="Logo"
                    class="w-10 h-10 rounded-full border-2 border-white shadow-md object-cover" />
                <span class="text-2xl font-bold drop-shadow">Pondok Pesantren Yati</span>
            </div>
            <ul class="flex gap-6 text-lg">
                <li><a href="index.php" class="hover:text-yellow-300 font-medium transition">Absensi</a></li>
                <li><a href="register.php" class="hover:text-yellow-300 font-medium transition">Registrasi</a></li>
                <li><a href="laporan.php" class="hover:text-yellow-300 font-medium transition underline">Laporan</a></li>
                <li><a href="dashboard.php" class="hover:text-yellow-300 font-medium transition">Dashboard</a></li>
                <li><a href="kelola_siswa.php" class="hover:text-yellow-300 font-medium transition">Kelola Siswa</a></li>
            </ul>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="flex-grow p-6">
        <div class="max-w-7xl mx-auto">
            <!-- Header untuk Print -->
            <div class="hidden print-only mb-8 text-center">
                <h1 class="text-3xl font-bold text-blue-800">Pondok Pesantren Yati</h1>
                <h2 class="text-xl text-gray-600">Laporan Absensi Siswa</h2>
                <p class="text-gray-500">Tanggal: <?php echo date('d-m-Y', strtotime($tanggal_filter)); ?></p>
            </div>

            <!-- Filter dan Kontrol -->
            <div class="bg-white bg-opacity-90 rounded-3xl shadow-2xl p-8 mb-6 fade-in backdrop-blur-md border border-blue-200 no-print">
                <h3 class="text-2xl font-bold text-blue-800 mb-6 drop-shadow">Laporan Absensi</h3>
                
                <!-- Tab untuk tipe laporan -->
                <div class="flex mb-6 border-b border-blue-200">
                    <button onclick="showTab('harian')" id="tab-harian" 
                        class="px-6 py-2 font-medium <?php echo $laporan_type == 'harian' ? 'text-blue-800 border-b-2 border-blue-800' : 'text-gray-600 hover:text-blue-600'; ?>">
                        Laporan Harian
                    </button>
                    <button onclick="showTab('rentang')" id="tab-rentang"
                        class="px-6 py-2 font-medium <?php echo $laporan_type == 'rentang' ? 'text-blue-800 border-b-2 border-blue-800' : 'text-gray-600 hover:text-blue-600'; ?>">
                        Laporan Rentang Tanggal
                    </button>
                </div>

                <!-- Form Filter Harian -->
                <form method="GET" action="laporan.php" id="form-harian" 
                    class="<?php echo $laporan_type == 'rentang' ? 'hidden' : ''; ?>">
                    <input type="hidden" name="laporan_type" value="harian">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end mb-6 p-4 bg-blue-50 rounded-xl shadow-inner">
                        <div>
                            <label for="tanggal" class="block font-medium text-blue-900 mb-1">Tanggal:</label>
                            <input type="date" id="tanggal" name="tanggal"
                                value="<?php echo htmlspecialchars($tanggal_filter); ?>"
                                class="w-full border-2 border-blue-300 rounded-xl py-2 px-4 text-lg focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-blue-500 bg-white shadow-sm" />
                        </div>
                        <div>
                            <label for="kelas" class="block font-medium text-blue-900 mb-1">Kelas:</label>
                            <select id="kelas" name="kelas"
                                class="w-full border-2 border-blue-300 rounded-xl py-2 px-4 text-lg focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-blue-500 bg-white shadow-sm">
                                <option value="">Semua Kelas</option>
                                <?php 
                                mysqli_data_seek($result_kelas, 0);
                                while ($row_kelas = mysqli_fetch_assoc($result_kelas)): ?>
                                    <option value="<?php echo htmlspecialchars($row_kelas['kelas']); ?>" <?php echo ($kelas_filter == $row_kelas['kelas']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($row_kelas['kelas']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div>
                            <label for="nama" class="block font-medium text-blue-900 mb-1">Nama Siswa:</label>
                            <input type="text" id="nama" name="nama" placeholder="Cari nama siswa..."
                                value="<?php echo htmlspecialchars($nama_filter); ?>"
                                class="w-full border-2 border-blue-300 rounded-xl py-2 px-4 text-lg focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-blue-500 bg-white shadow-sm" />
                        </div>
                        <div class="flex gap-2">
                            <button type="submit"
                                class="py-2 px-6 rounded-xl bg-blue-700 text-white font-bold text-lg shadow hover:bg-blue-800 transition transform hover:scale-105">Filter</button>
                        </div>
                    </div>
                </form>

                <!-- Form Filter Rentang -->
                <form method="GET" action="laporan.php" id="form-rentang" 
                    class="<?php echo $laporan_type == 'harian' ? 'hidden' : ''; ?>">
                    <input type="hidden" name="laporan_type" value="rentang">
                    <div class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end mb-6 p-4 bg-blue-50 rounded-xl shadow-inner">
                        <div>
                            <label for="tanggal_mulai" class="block font-medium text-blue-900 mb-1">Tanggal Mulai:</label>
                            <input type="date" id="tanggal_mulai" name="tanggal_mulai"
                                value="<?php echo htmlspecialchars($tanggal_mulai); ?>"
                                class="w-full border-2 border-blue-300 rounded-xl py-2 px-4 text-lg focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-blue-500 bg-white shadow-sm" />
                        </div>
                        <div>
                            <label for="tanggal_akhir" class="block font-medium text-blue-900 mb-1">Tanggal Akhir:</label>
                            <input type="date" id="tanggal_akhir" name="tanggal_akhir"
                                value="<?php echo htmlspecialchars($tanggal_akhir); ?>"
                                class="w-full border-2 border-blue-300 rounded-xl py-2 px-4 text-lg focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-blue-500 bg-white shadow-sm" />
                        </div>
                        <div>
                            <label for="kelas_rentang" class="block font-medium text-blue-900 mb-1">Kelas:</label>
                            <select id="kelas_rentang" name="kelas"
                                class="w-full border-2 border-blue-300 rounded-xl py-2 px-4 text-lg focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-blue-500 bg-white shadow-sm">
                                <option value="">Semua Kelas</option>
                                <?php 
                                mysqli_data_seek($result_kelas, 0);
                                while ($row_kelas = mysqli_fetch_assoc($result_kelas)): ?>
                                    <option value="<?php echo htmlspecialchars($row_kelas['kelas']); ?>">
                                        <?php echo htmlspecialchars($row_kelas['kelas']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div>
                            <label for="nama_rentang" class="block font-medium text-blue-900 mb-1">Nama Siswa:</label>
                            <input type="text" id="nama_rentang" name="nama" placeholder="Cari nama siswa..."
                                class="w-full border-2 border-blue-300 rounded-xl py-2 px-4 text-lg focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-blue-500 bg-white shadow-sm" />
                        </div>
                        <div class="flex gap-2">
                            <button type="submit"
                                class="py-2 px-6 rounded-xl bg-blue-700 text-white font-bold text-lg shadow hover:bg-blue-800 transition transform hover:scale-105">Filter</button>
                        </div>
                    </div>
                </form>

                <!-- Tombol Aksi -->
                <div class="flex flex-wrap gap-4 justify-center">
                    <button type="button" onclick="window.print()"
                        class="py-2 px-6 rounded-xl bg-gray-600 text-white font-bold text-lg shadow hover:bg-gray-700 transition transform hover:scale-105">
                        🖨️ Cetak Laporan
                    </button>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'csv'])); ?>"
                        class="py-2 px-6 rounded-xl bg-green-600 text-white font-bold text-lg shadow hover:bg-green-700 transition transform hover:scale-105">
                        📊 Export CSV
                    </a>
                    <button type="button" onclick="showStatistics()"
                        class="py-2 px-6 rounded-xl bg-purple-600 text-white font-bold text-lg shadow hover:bg-purple-700 transition transform hover:scale-105">
                        📈 Statistik
                    </button>
                </div>
            </div>

            <!-- Statistik Dashboard -->
            <?php if ($laporan_type == 'harian'): ?>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6" id="statistics-panel">
                <div class="bg-white bg-opacity-90 rounded-2xl shadow-lg p-6 fade-in backdrop-blur-md border border-blue-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Total Absensi</p>
                            <p class="text-2xl font-bold text-blue-800"><?php echo $stats['total_absensi']; ?></p>
                        </div>
                        <div class="p-3 bg-blue-100 rounded-full">
                            <span class="text-2xl">👥</span>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white bg-opacity-90 rounded-2xl shadow-lg p-6 fade-in backdrop-blur-md border border-green-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Hadir</p>
                            <p class="text-2xl font-bold text-green-600"><?php echo $stats['hadir']; ?></p>
                        </div>
                        <div class="p-3 bg-green-100 rounded-full">
                            <span class="text-2xl">✅</span>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white bg-opacity-90 rounded-2xl shadow-lg p-6 fade-in backdrop-blur-md border border-yellow-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Belum Pulang</p>
                            <p class="text-2xl font-bold text-yellow-600"><?php echo $stats['belum_pulang']; ?></p>
                        </div>
                        <div class="p-3 bg-yellow-100 rounded-full">
                            <span class="text-2xl">⏰</span>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white bg-opacity-90 rounded-2xl shadow-lg p-6 fade-in backdrop-blur-md border border-red-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Tidak Hadir</p>
                            <p class="text-2xl font-bold text-red-600"><?php echo mysqli_num_rows($absent_students); ?></p>
                        </div>
                        <div class="p-3 bg-red-100 rounded-full">
                            <span class="text-2xl">❌</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Chart -->
            <div class="bg-white bg-opacity-90 rounded-3xl shadow-2xl p-8 mb-6 fade-in backdrop-blur-md border border-blue-200 no-print">
                <h4 class="text-xl font-bold text-blue-800 mb-4">Grafik Kehadiran</h4>
                <div class="chart-container">
                    <canvas id="attendanceChart"></canvas>
                </div>
            </div>
            <?php endif; ?>

            <!-- Tabel Siswa Tidak Hadir -->
            <?php if ($laporan_type == 'harian' && mysqli_num_rows($absent_students) > 0): ?>
            <div class="bg-white bg-opacity-90 rounded-3xl shadow-2xl p-8 mb-6 fade-in backdrop-blur-md border border-red-200">
                <h4 class="text-xl font-bold text-red-800 mb-4">⚠️ Siswa Tidak Hadir (<?php echo date('d-m-Y', strtotime($tanggal_filter)); ?>)</h4>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <?php while ($absent = mysqli_fetch_assoc($absent_students)): ?>
                    <div class="bg-red-50 border border-red-200 rounded-lg p-3">
                        <p class="font-semibold text-red-800"><?php echo htmlspecialchars($absent['nama_lengkap']); ?></p>
                        <p class="text-sm text-red-600">Kelas: <?php echo htmlspecialchars($absent['kelas']); ?></p>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Tabel Laporan Utama -->
            <div class="bg-white bg-opacity-90 rounded-3xl shadow-2xl p-8 fade-in backdrop-blur-md border border-blue-200">
                <h4 class="text-xl font-bold text-blue-800 mb-4">
                    📋 Data Absensi
                    <?php if ($laporan_type == 'rentang'): ?>
                        (<?php echo date('d-m-Y', strtotime($tanggal_mulai)); ?> - <?php echo date('d-m-Y', strtotime($tanggal_akhir)); ?>)
                    <?php else: ?>
                        (<?php echo date('d-m-Y', strtotime($tanggal_filter)); ?>)
                    <?php endif; ?>
                </h4>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full border border-blue-200 rounded-xl overflow-hidden shadow-lg">
                        <thead class="bg-blue-700 text-white">
                            <tr>
                                <th class="py-3 px-4 font-semibold text-center">No.</th>
                                <th class="py-3 px-4 font-semibold text-left">Nama Lengkap</th>
                                <th class="py-3 px-4 font-semibold text-left">Kelas</th>
                                <?php if ($laporan_type == 'rentang'): ?>
                                <th class="py-3 px-4 font-semibold text-center">Tanggal</th>
                                <?php endif; ?>
                                <th class="py-3 px-4 font-semibold text-center">Waktu Masuk</th>
                                <th class="py-3 px-4 font-semibold text-center">Waktu Keluar</th>
                                <th class="py-3 px-4 font-semibold text-center">Durasi</th>
                                <th class="py-3 px-4 font-semibold text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white text-blue-900">
                            <?php if (mysqli_num_rows($result) > 0): ?>
                                <?php $no = 1; ?>
                                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                    <tr class="hover:bg-blue-100 transition">
                                        <td class="py-2 px-4 text-center"><?php echo $no++; ?></td>
                                        <td class="py-2 px-4 font-medium"><?php echo htmlspecialchars($row['nama_lengkap']); ?></td>
                                        <td class="py-2 px-4"><?php echo htmlspecialchars($row['kelas']); ?></td>
                                        <?php if ($laporan_type == 'rentang'): ?>
                                        <td class="py-2 px-4 text-center"><?php echo date('d-m-Y', strtotime($row['tanggal'])); ?></td>
                                        <?php endif; ?>
                                        <td class="py-2 px-4 text-center">
                                            <?php echo $row['waktu_masuk'] ? date('H:i:s', strtotime($row['waktu_masuk'])) : '-'; ?>
                                        </td>
                                        <td class="py-2 px-4 text-center">
                                            <?php echo $row['waktu_keluar'] ? date('H:i:s', strtotime($row['waktu_keluar'])) : '-'; ?>
                                        </td>
                                        <td class="py-2 px-4 text-center">
                                            <?php 
                                            if ($row['waktu_masuk'] && $row['waktu_keluar']) {
                                                $masuk = strtotime($row['waktu_masuk']);
                                                $keluar = strtotime($row['waktu_keluar']);
                                                $durasi = $keluar - $masuk;
                                                $jam = floor($durasi / 3600);
                                                $menit = floor(($durasi % 3600) / 60);
                                                echo $jam . 'jam ' . $menit . 'menit';
                                            } else {
                                                echo '-';
                                            }
                                            ?>
                                        </td>
                                        <td class="py-2 px-4 text-center">
                                            <?php
                                            $status = 'Hadir';
                                            $status_class = 'text-green-600 font-semibold';
                                            
                                            if ($row['waktu_masuk']) {
                                                $waktu_masuk_only = date('H:i:s', strtotime($row['waktu_masuk']));
                                                if (strtotime($waktu_masuk_only) > strtotime($pengaturan['waktu_masuk_akhir'])) {
                                                    $status = 'Terlambat';
                                                    $status_class = 'text-red-600 font-semibold';
                                                }
                                            }
                                            
                                            if ($row['waktu_keluar']) {
                                                $waktu_keluar_only = date('H:i:s', strtotime($row['waktu_keluar']));
                                                if (strtotime($waktu_keluar_only) < strtotime($pengaturan['waktu_keluar_mulai'])) {
                                                    if ($status == 'Terlambat') {
                                                        $status = 'Terlambat & Pulang Cepat';
                                                        $status_class = 'text-red-600 font-semibold';
                                                    } else {
                                                        $status = 'Pulang Cepat';
                                                        $status_class = 'text-orange-600 font-semibold';
                                                    }
                                                }
                                            } else {
                                                if ($status == 'Terlambat') {
                                                    $status = 'Terlambat (Belum Pulang)';
                                                } else {
                                                    $status = 'Hadir (Belum Pulang)';
                                                    $status_class = 'text-blue-600 font-semibold';
                                                }
                                            }
                                            ?>
                                            <span class="<?php echo $status_class; ?> px-2 py-1 rounded-full text-xs">
                                                <?php echo $status; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="<?php echo $laporan_type == 'rentang' ? '8' : '7'; ?>" class="py-8 px-4 text-center text-gray-500">
                                        <div class="flex flex-col items-center">
                                            <span class="text-4xl mb-2">📭</span>
                                            <p class="text-lg">Tidak ada data absensi untuk filter yang dipilih.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Informasi Tambahan -->
                <div class="mt-6 p-4 bg-blue-50 rounded-xl">
                    <h5 class="font-bold text-blue-800 mb-2">ℹ️ Keterangan:</h5>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                        <div>
                            <p><span class="font-semibold">Jam Masuk:</span> <?php echo date('H:i', strtotime($pengaturan['waktu_masuk_mulai'])); ?> - <?php echo date('H:i', strtotime($pengaturan['waktu_masuk_akhir'])); ?></p>
                            <p><span class="font-semibold">Jam Pulang:</span> <?php echo date('H:i', strtotime($pengaturan['waktu_keluar_mulai'])); ?> - <?php echo date('H:i', strtotime($pengaturan['waktu_keluar_akhir'])); ?></p>
                        </div>
                        <div>
                            <p><span class="font-semibold text-green-600">●</span> Hadir: Masuk tepat waktu</p>
                            <p><span class="font-semibold text-red-600">●</span> Terlambat: Masuk setelah <?php echo date('H:i', strtotime($pengaturan['waktu_masuk_akhir'])); ?></p>
                            <p><span class="font-semibold text-orange-600">●</span> Pulang Cepat: Pulang sebelum <?php echo date('H:i', strtotime($pengaturan['waktu_keluar_mulai'])); ?></p>
                            <p><span class="font-semibold text-blue-600">●</span> Belum Pulang: Belum melakukan absen keluar</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer untuk Print -->
    <div class="hidden print-only mt-8 text-center text-sm text-gray-600">
        <p>Dicetak pada: <?php echo date('d-m-Y H:i:s'); ?></p>
        <p>© <?php echo date('Y'); ?> Pondok Pesantren Yati - Sistem Absensi</p>
    </div>

    <script>
        // Tab functionality
        function showTab(tabName) {
            // Hide all forms
            document.getElementById('form-harian').classList.add('hidden');
            document.getElementById('form-rentang').classList.add('hidden');
            
            // Remove active class from all tabs
            document.getElementById('tab-harian').className = 'px-6 py-2 font-medium text-gray-600 hover:text-blue-600';
            document.getElementById('tab-rentang').className = 'px-6 py-2 font-medium text-gray-600 hover:text-blue-600';
            
            // Show selected form and activate tab
            if (tabName === 'harian') {
                document.getElementById('form-harian').classList.remove('hidden');
                document.getElementById('tab-harian').className = 'px-6 py-2 font-medium text-blue-800 border-b-2 border-blue-800';
            } else {
                document.getElementById('form-rentang').classList.remove('hidden');
                document.getElementById('tab-rentang').className = 'px-6 py-2 font-medium text-blue-800 border-b-2 border-blue-800';
            }
        }

        // Statistics panel toggle
        function showStatistics() {
            const panel = document.getElementById('statistics-panel');
            if (panel) {
                panel.style.display = panel.style.display === 'none' ? 'grid' : 'none';
            }
        }

        // Chart for attendance statistics
        <?php if ($laporan_type == 'harian'): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('attendanceChart').getContext('2d');
            
            const attendanceChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Hadir', 'Belum Pulang', 'Tidak Hadir'],
                    datasets: [{
                        data: [
                            <?php echo $stats['sudah_pulang']; ?>,
                            <?php echo $stats['belum_pulang']; ?>,
                            <?php echo mysqli_num_rows($absent_students); ?>
                        ],
                        backgroundColor: [
                            '#10B981', // Green for present
                            '#F59E0B', // Yellow for not yet home
                            '#EF4444'  // Red for absent
                        ],
                        borderWidth: 2,
                        borderColor: '#ffffff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 20,
                                font: {
                                    size: 14
                                }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = Math.round((context.parsed / total) * 100);
                                    return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
                                }
                            }
                        }
                    }
                }
            });
        });
        <?php endif; ?>

        // Auto-refresh functionality (optional)
        function enableAutoRefresh() {
            setInterval(function() {
                if (confirm('Refresh data absensi?')) {
                    location.reload();
                }
            }, 300000); // 5 minutes
        }

        // Print optimization
        window.addEventListener('beforeprint', function() {
            // Hide no-print elements
            document.querySelectorAll('.no-print').forEach(el => {
                el.style.display = 'none';
            });
            
            // Show print-only elements
            document.querySelectorAll('.print-only').forEach(el => {
                el.style.display = 'block';
            });
        });

        window.addEventListener('afterprint', function() {
            // Restore visibility
            document.querySelectorAll('.no-print').forEach(el => {
                el.style.display = '';
            });
            
            document.querySelectorAll('.print-only').forEach(el => {
                el.style.display = 'none';
            });
        });

        // Search functionality for large tables
        function searchTable() {
            const input = document.getElementById('searchInput');
            const filter = input.value.toLowerCase();
            const table = document.querySelector('tbody');
            const rows = table.querySelectorAll('tr');

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(filter) ? '' : 'none';
            });
        }

        // Quick date filters
        function setQuickDate(days) {
            const date = new Date();
            date.setDate(date.getDate() + days);
            const dateString = date.toISOString().split('T')[0];
            
            if (days === 0) {
                document.getElementById('tanggal').value = dateString;
            } else {
                document.getElementById('tanggal_mulai').value = dateString;
                document.getElementById('tanggal_akhir').value = new Date().toISOString().split('T')[0];
            }
        }
    </script>
</body>

</html>
<?php
mysqli_stmt_close($stmt);
mysqli_close($koneksi);
?>
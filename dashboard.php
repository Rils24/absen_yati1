<?php
// dashboard.php
include 'koneksi.php'; // Includes database connection and timezone setting

$today = date('Y-m-d');

// --- Handle Settings Update ---
$message = '';
$message_type = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_settings'])) {
    $waktu_masuk_mulai = mysqli_real_escape_string($koneksi, $_POST['waktu_masuk_mulai']);
    $waktu_masuk_akhir = mysqli_real_escape_string($koneksi, $_POST['waktu_masuk_akhir']);
    $waktu_keluar_mulai = mysqli_real_escape_string($koneksi, $_POST['waktu_keluar_mulai']);
    $waktu_keluar_akhir = mysqli_real_escape_string($koneksi, $_POST['waktu_keluar_akhir']);

    // Query untuk INSERT atau UPDATE (UPSERT) pengaturan absensi
    $sql_update_settings = "INSERT INTO pengaturan_absensi (id, waktu_masuk_mulai, waktu_masuk_akhir, waktu_keluar_mulai, waktu_keluar_akhir) 
                            VALUES (1, ?, ?, ?, ?)
                            ON DUPLICATE KEY UPDATE 
                            waktu_masuk_mulai = ?, waktu_masuk_akhir = ?, waktu_keluar_mulai = ?, waktu_keluar_akhir = ?";
    $stmt_update_settings = mysqli_prepare($koneksi, $sql_update_settings);
    mysqli_stmt_bind_param($stmt_update_settings, "ssssssss", 
                            $waktu_masuk_mulai, $waktu_masuk_akhir, $waktu_keluar_mulai, $waktu_keluar_akhir,
                            $waktu_masuk_mulai, $waktu_masuk_akhir, $waktu_keluar_mulai, $waktu_keluar_akhir);

    if (mysqli_stmt_execute($stmt_update_settings)) {
        $message = "Pengaturan jadwal absensi berhasil diperbarui!";
        $message_type = "success";
    } else {
        $message = "Gagal memperbarui pengaturan jadwal absensi: " . mysqli_error($koneksi);
        $message_type = "error";
    }
    mysqli_stmt_close($stmt_update_settings);
}

// --- Fetch Current Settings ---
$sql_get_settings = "SELECT waktu_masuk_mulai, waktu_masuk_akhir, waktu_keluar_mulai, waktu_keluar_akhir FROM pengaturan_absensi WHERE id = 1";
$result_get_settings = mysqli_query($koneksi, $sql_get_settings);
$current_settings = mysqli_fetch_assoc($result_get_settings);

// Default values if no settings exist
$waktu_masuk_mulai_val = $current_settings['waktu_masuk_mulai'] ?? '07:00:00';
$waktu_masuk_akhir_val = $current_settings['waktu_masuk_akhir'] ?? '09:00:00';
$waktu_keluar_mulai_val = $current_settings['waktu_keluar_mulai'] ?? '16:00:00';
$waktu_keluar_akhir_val = $current_settings['waktu_keluar_akhir'] ?? '18:00:00';

// --- Dashboard Data Fetching ---

// Total Siswa
$sql_total_siswa = "SELECT COUNT(id) AS total_siswa FROM siswa";
$result_total_siswa = mysqli_query($koneksi, $sql_total_siswa);
$data_total_siswa = mysqli_fetch_assoc($result_total_siswa);
$total_siswa = $data_total_siswa['total_siswa'];

// Siswa Hadir (Sudah tap masuk hari ini)
$sql_hadir = "SELECT COUNT(DISTINCT id_siswa) AS total_hadir 
              FROM absensi 
              WHERE DATE(waktu_masuk) = ?";
$stmt_hadir = mysqli_prepare($koneksi, $sql_hadir);
mysqli_stmt_bind_param($stmt_hadir, "s", $today);
mysqli_stmt_execute($stmt_hadir);
$result_hadir = mysqli_stmt_get_result($stmt_hadir);
$data_hadir = mysqli_fetch_assoc($result_hadir);
$total_hadir = $data_hadir['total_hadir'];
mysqli_stmt_close($stmt_hadir);

// Siswa Belum Absen
$total_belum_absen = $total_siswa - $total_hadir;

// Siswa Terlambat (Masuk setelah waktu_masuk_akhir)
$sql_terlambat = "SELECT COUNT(DISTINCT a.id_siswa) AS total_terlambat
                  FROM absensi a
                  JOIN pengaturan_absensi pa ON pa.id = 1
                  WHERE DATE(a.waktu_masuk) = ? 
                  AND TIME(a.waktu_masuk) > pa.waktu_masuk_akhir";
$stmt_terlambat = mysqli_prepare($koneksi, $sql_terlambat);
mysqli_stmt_bind_param($stmt_terlambat, "s", $today);
mysqli_stmt_execute($stmt_terlambat);
$result_terlambat = mysqli_stmt_get_result($stmt_terlambat);
$data_terlambat = mysqli_fetch_assoc($result_terlambat);
$total_terlambat = $data_terlambat['total_terlambat'];
mysqli_stmt_close($stmt_terlambat);

// Siswa Sudah Pulang (Sudah tap keluar hari ini)
$sql_sudah_pulang = "SELECT COUNT(DISTINCT a.id_siswa) AS total_sudah_pulang
                     FROM absensi a
                     WHERE DATE(a.waktu_masuk) = ? 
                     AND a.waktu_keluar IS NOT NULL";
$stmt_sudah_pulang = mysqli_prepare($koneksi, $sql_sudah_pulang);
mysqli_stmt_bind_param($stmt_sudah_pulang, "s", $today);
mysqli_stmt_execute($stmt_sudah_pulang);
$result_sudah_pulang = mysqli_stmt_get_result($stmt_sudah_pulang);
$data_sudah_pulang = mysqli_fetch_assoc($result_sudah_pulang);
$total_sudah_pulang = $data_sudah_pulang['total_sudah_pulang'];
mysqli_stmt_close($stmt_sudah_pulang);

// Recent Absensi (Last 10 entries/exits)
$sql_recent_absensi = "SELECT s.nama_lengkap, s.kelas, a.waktu_masuk, a.waktu_keluar
                       FROM absensi a
                       JOIN siswa s ON a.id_siswa = s.id
                       ORDER BY a.waktu_masuk DESC, a.waktu_keluar DESC
                       LIMIT 10";
$result_recent_absensi = mysqli_query($koneksi, $sql_recent_absensi);

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Absensi</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
        
        * {
            font-family: 'Inter', sans-serif;
        }

        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.05);
            }
        }

        .animate-slideInUp {
            animation: slideInUp 0.6s ease-out;
        }

        .animate-fadeIn {
            animation: fadeIn 0.8s ease-out;
        }

        .animate-pulse-gentle {
            animation: pulse 2s infinite;
        }

        .glass-effect {
            background: rgba(255, 255, 255, 0.25);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.18);
        }

        .card-hover {
            transition: all 0.3s ease;
        }

        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }

        .gradient-blue {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .gradient-green {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }

        .gradient-orange {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
        }

        .gradient-red {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
        }

        .input-focus {
            transition: all 0.3s ease;
        }

        .input-focus:focus {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(59, 130, 246, 0.15);
        }

        .btn-primary {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%);
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(59, 130, 246, 0.3);
        }

        .alert-success {
            background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
            color: #15803d;
            border: 1px solid #86efac;
        }

        .alert-error {
            background: linear-gradient(135deg, #fef2f2 0%, #fecaca 100%);
            color: #dc2626;
            border: 1px solid #fca5a5;
        }

        .table-row-hover:hover {
            background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
            transform: scale(1.01);
        }

        .shadow-custom {
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>

<body class="min-h-screen bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-100">
    <!-- Background Decorations -->
    <div class="fixed inset-0 overflow-hidden pointer-events-none">
        <div class="absolute -top-40 -right-40 w-80 h-80 bg-blue-300 rounded-full mix-blend-multiply filter blur-xl opacity-70 animate-pulse-gentle"></div>
        <div class="absolute -bottom-40 -left-40 w-80 h-80 bg-indigo-300 rounded-full mix-blend-multiply filter blur-xl opacity-70 animate-pulse-gentle" style="animation-delay: 1s;"></div>
        <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-96 h-96 bg-blue-200 rounded-full mix-blend-multiply filter blur-xl opacity-50 animate-pulse-gentle" style="animation-delay: 2s;"></div>
    </div>

    <!-- Navbar -->
    <nav class="relative z-20 glass-effect shadow-lg sticky top-0">
        <div class="max-w-7xl mx-auto px-6 py-4">
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-4">
                    <div class="w-12 h-12 bg-gradient-to-br from-blue-600 to-indigo-700 rounded-xl flex items-center justify-center shadow-lg">
                        <i class="fas fa-graduation-cap text-white text-xl"></i>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800">Pondok Pesantren Yati</h1>
                        <p class="text-sm text-gray-600">Sistem Absensi Digital</p>
                    </div>
                </div>
                <div class="hidden md:flex space-x-8">
                    <a href="index.php" class="text-gray-700 hover:text-blue-600 font-medium transition duration-300 flex items-center space-x-2">
                        <i class="fas fa-qrcode"></i>
                        <span>Absensi</span>
                    </a>
                    <a href="register.php" class="text-gray-700 hover:text-blue-600 font-medium transition duration-300 flex items-center space-x-2">
                        <i class="fas fa-user-plus"></i>
                        <span>Registrasi</span>
                    </a>
                    <a href="laporan.php" class="text-gray-700 hover:text-blue-600 font-medium transition duration-300 flex items-center space-x-2">
                        <i class="fas fa-chart-bar"></i>
                        <span>Laporan</span>
                    </a>
                    <a href="dashboard.php" class="text-blue-600 font-bold transition duration-300 flex items-center space-x-2 border-b-2 border-blue-600">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                    <a href="kelola_siswa.php" class="text-gray-700 hover:text-blue-600 font-medium transition duration-300 flex items-center space-x-2">
                        <i class="fas fa-users"></i>
                        <span>Kelola Siswa</span>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="relative z-10 px-6 py-8">
        <div class="max-w-7xl mx-auto">
            <!-- Header Section -->
            <div class="text-center mb-12 animate-fadeIn">
                <!-- <h2 class="text-4xl font-bold text-gray-800 mb-4">Dashboard Admin</h2> -->
                <p class="text-lg text-gray-600">Kelola dan monitor sistem absensi dengan mudah</p>
                <div class="w-24 h-1 bg-gradient-to-r from-blue-500 to-indigo-600 mx-auto mt-4 rounded-full"></div>
            </div>

            <?php if ($message): ?>
                <div class="mb-8 p-6 rounded-2xl shadow-lg <?php echo 'alert-' . $message_type; ?> animate-slideInUp border-l-4">
                    <div class="flex items-center">
                        <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> text-2xl mr-3"></i>
                        <span class="text-lg font-semibold"><?php echo $message; ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8 mb-12">
                <!-- Total Siswa -->
                <div class="gradient-blue rounded-2xl p-8 text-white shadow-custom card-hover animate-slideInUp">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-blue-100 text-sm font-medium uppercase tracking-wide">Total Siswa</p>
                            <p class="text-4xl font-bold mt-2"><?php echo $total_siswa; ?></p>
                        </div>
                        <div class="bg-white bg-opacity-20 p-4 rounded-xl">
                            <i class="fas fa-users text-3xl"></i>
                        </div>
                    </div>
                    <div class="mt-4 flex items-center">
                        <div class="w-full bg-white bg-opacity-20 rounded-full h-2">
                            <div class="bg-white h-2 rounded-full" style="width: 100%"></div>
                        </div>
                    </div>
                </div>

                <!-- Hadir Hari Ini -->
                <div class="gradient-green rounded-2xl p-8 text-white shadow-custom card-hover animate-slideInUp" style="animation-delay: 0.1s;">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-blue-100 text-sm font-medium uppercase tracking-wide">Hadir Hari Ini</p>
                            <p class="text-4xl font-bold mt-2"><?php echo $total_hadir; ?></p>
                        </div>
                        <div class="bg-white bg-opacity-20 p-4 rounded-xl">
                            <i class="fas fa-user-check text-3xl"></i>
                        </div>
                    </div>
                    <div class="mt-4 flex items-center">
                        <div class="w-full bg-white bg-opacity-20 rounded-full h-2">
                            <div class="bg-white h-2 rounded-full" style="width: <?php echo $total_siswa > 0 ? ($total_hadir / $total_siswa * 100) : 0; ?>%"></div>
                        </div>
                        <span class="ml-2 text-sm"><?php echo $total_siswa > 0 ? round($total_hadir / $total_siswa * 100) : 0; ?>%</span>
                    </div>
                </div>

                <!-- Belum Absen -->
                <div class="gradient-orange rounded-2xl p-8 text-white shadow-custom card-hover animate-slideInUp" style="animation-delay: 0.2s;">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-blue-100 text-sm font-medium uppercase tracking-wide">Belum Absen</p>
                            <p class="text-4xl font-bold mt-2"><?php echo $total_belum_absen; ?></p>
                        </div>
                        <div class="bg-white bg-opacity-20 p-4 rounded-xl">
                            <i class="fas fa-user-clock text-3xl"></i>
                        </div>
                    </div>
                    <div class="mt-4 flex items-center">
                        <div class="w-full bg-white bg-opacity-20 rounded-full h-2">
                            <div class="bg-white h-2 rounded-full" style="width: <?php echo $total_siswa > 0 ? ($total_belum_absen / $total_siswa * 100) : 0; ?>%"></div>
                        </div>
                        <span class="ml-2 text-sm"><?php echo $total_siswa > 0 ? round($total_belum_absen / $total_siswa * 100) : 0; ?>%</span>
                    </div>
                </div>

                <!-- Terlambat -->
                <div class="gradient-red rounded-2xl p-8 text-white shadow-custom card-hover animate-slideInUp" style="animation-delay: 0.3s;">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-blue-100 text-sm font-medium uppercase tracking-wide">Terlambat</p>
                            <p class="text-4xl font-bold mt-2"><?php echo $total_terlambat; ?></p>
                        </div>
                        <div class="bg-white bg-opacity-20 p-4 rounded-xl">
                            <i class="fas fa-exclamation-triangle text-3xl"></i>
                        </div>
                    </div>
                    <div class="mt-4 flex items-center">
                        <div class="w-full bg-white bg-opacity-20 rounded-full h-2">
                            <div class="bg-white h-2 rounded-full" style="width: <?php echo $total_hadir > 0 ? ($total_terlambat / $total_hadir * 100) : 0; ?>%"></div>
                        </div>
                        <span class="ml-2 text-sm"><?php echo $total_hadir > 0 ? round($total_terlambat / $total_hadir * 100) : 0; ?>%</span>
                    </div>
                </div>
            </div>

            <!-- Settings Section -->
            <div class="bg-white rounded-3xl shadow-custom p-10 mb-12 animate-slideInUp border border-gray-100">
                <div class="flex items-center mb-8">
                    <div class="w-12 h-12 bg-gradient-to-br from-blue-600 to-indigo-700 rounded-xl flex items-center justify-center mr-4">
                        <i class="fas fa-cog text-white text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-2xl font-bold text-gray-800">Pengaturan Jadwal Absensi</h3>
                        <p class="text-gray-600">Atur waktu absensi masuk dan keluar siswa</p>
                    </div>
                </div>

                <form action="dashboard.php" method="POST" class="space-y-8">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div class="space-y-6">
                            <div>
                                <label for="waktu_masuk_mulai" class="block text-sm font-semibold text-gray-700 mb-3">
                                    <i class="fas fa-clock text-blue-600 mr-2"></i>Waktu Masuk Mulai
                                </label>
                                <input type="time" id="waktu_masuk_mulai" name="waktu_masuk_mulai"
                                    value="<?php echo htmlspecialchars($waktu_masuk_mulai_val); ?>" required
                                    class="w-full border-2 border-gray-200 rounded-xl py-4 px-6 text-lg focus:outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-500 bg-gray-50 input-focus transition duration-300" />
                                <p class="text-sm text-gray-500 mt-2 ml-1">Siswa bisa mulai melakukan absen masuk</p>
                            </div>

                            <div>
                                <label for="waktu_masuk_akhir" class="block text-sm font-semibold text-gray-700 mb-3">
                                    <i class="fas fa-exclamation-triangle text-amber-600 mr-2"></i>Batas Waktu Masuk
                                </label>
                                <input type="time" id="waktu_masuk_akhir" name="waktu_masuk_akhir"
                                    value="<?php echo htmlspecialchars($waktu_masuk_akhir_val); ?>" required
                                    class="w-full border-2 border-gray-200 rounded-xl py-4 px-6 text-lg focus:outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-500 bg-gray-50 input-focus transition duration-300" />
                                <p class="text-sm text-gray-500 mt-2 ml-1">Setelah waktu ini, siswa dianggap terlambat</p>
                            </div>
                        </div>

                        <div class="space-y-6">
                            <div>
                                <label for="waktu_keluar_mulai" class="block text-sm font-semibold text-gray-700 mb-3">
                                    <i class="fas fa-sign-out-alt text-green-600 mr-2"></i>Waktu Keluar Mulai
                                </label>
                                <input type="time" id="waktu_keluar_mulai" name="waktu_keluar_mulai"
                                    value="<?php echo htmlspecialchars($waktu_keluar_mulai_val); ?>" required
                                    class="w-full border-2 border-gray-200 rounded-xl py-4 px-6 text-lg focus:outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-500 bg-gray-50 input-focus transition duration-300" />
                                <p class="text-sm text-gray-500 mt-2 ml-1">Siswa bisa mulai melakukan absen keluar</p>
                            </div>

                            <div>
                                <label for="waktu_keluar_akhir" class="block text-sm font-semibold text-gray-700 mb-3">
                                    <i class="fas fa-door-open text-red-600 mr-2"></i>Batas Waktu Keluar
                                </label>
                                <input type="time" id="waktu_keluar_akhir" name="waktu_keluar_akhir"
                                    value="<?php echo htmlspecialchars($waktu_keluar_akhir_val); ?>" required
                                    class="w-full border-2 border-gray-200 rounded-xl py-4 px-6 text-lg focus:outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-500 bg-gray-50 input-focus transition duration-300" />
                                <p class="text-sm text-gray-500 mt-2 ml-1">Batas akhir siswa bisa melakukan absen keluar</p>
                            </div>
                        </div>
                    </div>

                    <div class="text-center pt-6 border-t border-gray-200">
                        <button type="submit" name="update_settings"
                            class="btn-primary py-4 px-12 rounded-xl text-white font-bold text-lg shadow-lg flex items-center mx-auto space-x-3">
                            <i class="fas fa-save"></i>
                            <span>Simpan Pengaturan</span>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Recent Absensi Section -->
            <div class="bg-white rounded-3xl shadow-custom p-10 animate-slideInUp border border-gray-100">
                <div class="flex items-center justify-between mb-8">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-gradient-to-br from-green-600 to-emerald-700 rounded-xl flex items-center justify-center mr-4">
                            <i class="fas fa-history text-white text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-2xl font-bold text-gray-800">Aktivitas Absensi Terbaru</h3>
                            <p class="text-gray-600">10 aktivitas absensi terakhir</p>
                        </div>
                    </div>
                    <div class="text-sm text-gray-500 bg-gray-100 px-4 py-2 rounded-full">
                        <i class="fas fa-calendar-alt mr-2"></i><?php echo date('d F Y'); ?>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="bg-gradient-to-r from-blue-600 to-indigo-700 text-white">
                                <th class="py-4 px-6 text-left font-semibold rounded-tl-xl">
                                    <i class="fas fa-user mr-2"></i>Nama Lengkap
                                </th>
                                <th class="py-4 px-6 text-left font-semibold">
                                    <i class="fas fa-school mr-2"></i>Kelas
                                </th>
                                <th class="py-4 px-6 text-center font-semibold">
                                    <i class="fas fa-sign-in-alt mr-2"></i>Waktu Masuk
                                </th>
                                <th class="py-4 px-6 text-center font-semibold rounded-tr-xl">
                                    <i class="fas fa-sign-out-alt mr-2"></i>Waktu Keluar
                                </th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-700">
                            <?php if (mysqli_num_rows($result_recent_absensi) > 0): ?>
                                <?php $index = 0; ?>
                                <?php while ($row_recent = mysqli_fetch_assoc($result_recent_absensi)): ?>
                                    <tr class="border-b border-gray-100 table-row-hover transition duration-300">
                                        <td class="py-5 px-6">
                                            <div class="flex items-center">
                                                <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-full flex items-center justify-center mr-3">
                                                    <span class="text-white font-bold text-sm"><?php echo strtoupper(substr($row_recent['nama_lengkap'], 0, 1)); ?></span>
                                                </div>
                                                <div>
                                                    <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($row_recent['nama_lengkap']); ?></p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-5 px-6">
                                            <span class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-sm font-medium">
                                                <?php echo htmlspecialchars($row_recent['kelas']); ?>
                                            </span>
                                        </td>
                                        <td class="py-5 px-6 text-center">
                                            <?php if ($row_recent['waktu_masuk']): ?>
                                                <div class="bg-green-50 text-green-700 px-3 py-2 rounded-lg inline-block">
                                                    <i class="fas fa-clock mr-1"></i>
                                                    <?php echo date('H:i:s', strtotime($row_recent['waktu_masuk'])); ?>
                                                    <div class="text-xs text-green-600 mt-1">
                                                        <?php echo date('d/m/Y', strtotime($row_recent['waktu_masuk'])); ?>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-gray-400 text-sm">
                                                    <i class="fas fa-minus mr-1"></i>Belum absen
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-5 px-6 text-center">
                                            <?php if ($row_recent['waktu_keluar']): ?>
                                                <div class="bg-blue-50 text-blue-700 px-3 py-2 rounded-lg inline-block">
                                                    <i class="fas fa-clock mr-1"></i>
                                                    <?php echo date('H:i:s', strtotime($row_recent['waktu_keluar'])); ?>
                                                    <div class="text-xs text-blue-600 mt-1">
                                                        <?php echo date('d/m/Y', strtotime($row_recent['waktu_keluar'])); ?>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-orange-500 text-sm bg-orange-50 px-3 py-1 rounded-full">
                                                    <i class="fas fa-hourglass-half mr-1"></i>Masih di sekolah
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php $index++; ?>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="py-12 text-center">
                                        <div class="flex flex-col items-center justify-center">
                                            <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                                                <i class="fas fa-clipboard-list text-gray-400 text-3xl"></i>
                                            </div>
                                            <p class="text-gray-500 text-lg font-medium">Tidak ada data absensi terbaru</p>
                                            <p class="text-gray-400 text-sm mt-1">Data akan muncul setelah ada aktivitas absensi</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if (mysqli_num_rows($result_recent_absensi) > 0): ?>
                    <div class="mt-6 flex justify-center">
                        <a href="laporan.php" class="text-blue-600 hover:text-blue-700 font-medium flex items-center space-x-2 transition duration-300">
                            <span>Lihat semua laporan</span>
                            <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Quick Stats Summary -->
            <div class="mt-12 bg-gradient-to-r from-blue-600 to-indigo-700 rounded-3xl p-10 text-white animate-slideInUp">
                <div class="text-center mb-8">
                    <h3 class="text-2xl font-bold mb-2">Ringkasan Hari Ini</h3>
                    <p class="text-blue-100"><?php echo date('l, d F Y'); ?></p>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    <div class="text-center">
                        <div class="bg-white bg-opacity-20 rounded-2xl p-6">
                            <div class="text-3xl font-bold mb-2"><?php echo $total_hadir; ?>/<?php echo $total_siswa; ?></div>
                            <div class="text-blue-100">Tingkat Kehadiran</div>
                            <div class="mt-3 w-full bg-white bg-opacity-20 rounded-full h-2">
                                <div class="bg-white h-2 rounded-full transition-all duration-1000" style="width: <?php echo $total_siswa > 0 ? ($total_hadir / $total_siswa * 100) : 0; ?>%"></div>
                            </div>
                            <div class="mt-2 font-semibold"><?php echo $total_siswa > 0 ? round($total_hadir / $total_siswa * 100) : 0; ?>%</div>
                        </div>
                    </div>
                    
                    <div class="text-center">
                        <div class="bg-white bg-opacity-20 rounded-2xl p-6">
                            <div class="text-3xl font-bold mb-2"><?php echo $total_sudah_pulang; ?></div>
                            <div class="text-blue-100">Sudah Pulang</div>
                            <div class="mt-3 w-full bg-white bg-opacity-20 rounded-full h-2">
                                <div class="bg-white h-2 rounded-full transition-all duration-1000" style="width: <?php echo $total_hadir > 0 ? ($total_sudah_pulang / $total_hadir * 100) : 0; ?>%"></div>
                            </div>
                            <div class="mt-2 font-semibold"><?php echo $total_hadir > 0 ? round($total_sudah_pulang / $total_hadir * 100) : 0; ?>%</div>
                        </div>
                    </div>
                    
                    <div class="text-center">
                        <div class="bg-white bg-opacity-20 rounded-2xl p-6">
                            <div class="text-3xl font-bold mb-2"><?php echo $total_terlambat; ?></div>
                            <div class="text-blue-100">Siswa Terlambat</div>
                            <div class="mt-3 w-full bg-white bg-opacity-20 rounded-full h-2">
                                <div class="bg-red-400 h-2 rounded-full transition-all duration-1000" style="width: <?php echo $total_hadir > 0 ? ($total_terlambat / $total_hadir * 100) : 0; ?>%"></div>
                            </div>
                            <div class="mt-2 font-semibold"><?php echo $total_hadir > 0 ? round($total_terlambat / $total_hadir * 100) : 0; ?>%</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="relative z-10 bg-gray-800 text-white py-8 mt-16">
        <div class="max-w-7xl mx-auto px-6 text-center">
            <div class="flex items-center justify-center mb-4">
                <div class="w-8 h-8 bg-gradient-to-br from-blue-600 to-indigo-700 rounded-lg flex items-center justify-center mr-3">
                    <i class="fas fa-graduation-cap text-white"></i>
                </div>
                <span class="text-xl font-bold">Pondok Pesantren Yati</span>
            </div>
            <p class="text-gray-400 mb-4">Sistem Absensi Digital - Memudahkan monitoring kehadiran siswa</p>
            <div class="flex items-center justify-center space-x-6 text-sm text-gray-400">
                <span><i class="fas fa-calendar-alt mr-1"></i><?php echo date('Y'); ?></span>
                <span><i class="fas fa-code mr-1"></i>Developed with ❤️</span>
                <span><i class="fas fa-users mr-1"></i><?php echo $total_siswa; ?> Siswa Terdaftar</span>
            </div>
        </div>
    </footer>

    <script>
        // Add smooth animations when page loads
        document.addEventListener('DOMContentLoaded', function() {
            // Animate numbers counting up
            const numbers = document.querySelectorAll('.text-4xl.font-bold');
            numbers.forEach((number, index) => {
                const finalValue = parseInt(number.textContent);
                let current = 0;
                const increment = finalValue / 30;
                const timer = setInterval(() => {
                    current += increment;
                    if (current >= finalValue) {
                        current = finalValue;
                        clearInterval(timer);
                    }
                    number.textContent = Math.floor(current);
                }, 50 + (index * 20));
            });

            // Add hover effects to table rows
            const tableRows = document.querySelectorAll('.table-row-hover');
            tableRows.forEach(row => {
                row.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateX(5px)';
                });
                row.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateX(0)';
                });
            });

            // Auto-refresh data every 30 seconds
            setTimeout(() => {
                location.reload();
            }, 30000);
        });

        // Add form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const inputs = this.querySelectorAll('input[type="time"]');
            let isValid = true;
            
            inputs.forEach(input => {
                if (!input.value) {
                    isValid = false;
                    input.classList.add('border-red-500');
                } else {
                    input.classList.remove('border-red-500');
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                alert('Mohon lengkapi semua field waktu!');
            }
        });
    </script>
</body>

</html>
<?php
mysqli_close($koneksi);
?>
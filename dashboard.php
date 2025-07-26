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

// Siswa Pulang Cepat (Keluar sebelum waktu_keluar_mulai)
$sql_pulang_cepat = "SELECT COUNT(DISTINCT a.id_siswa) AS total_pulang_cepat
                     FROM absensi a
                     JOIN pengaturan_absensi pa ON pa.id = 1
                     WHERE DATE(a.waktu_masuk) = ? 
                     AND a.waktu_keluar IS NOT NULL
                     AND TIME(a.waktu_keluar) < pa.waktu_keluar_mulai";
$stmt_pulang_cepat = mysqli_prepare($koneksi, $sql_pulang_cepat);
mysqli_stmt_bind_param($stmt_pulang_cepat, "s", $today);
mysqli_stmt_execute($stmt_pulang_cepat);
$result_pulang_cepat = mysqli_stmt_get_result($stmt_pulang_cepat);
$data_pulang_cepat = mysqli_fetch_assoc($result_pulang_cepat);
$total_pulang_cepat = $data_pulang_cepat['total_pulang_cepat'];
mysqli_stmt_close($stmt_pulang_cepat);

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

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border-color: #c3e6cb;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
        }
    </style>
</head>

<body class="relative min-h-screen bg-gradient-to-br from-blue-50 to-blue-200 flex flex-col font-sans">
    <!-- Navbar -->
    <nav class="bg-blue-800 bg-opacity-80 text-white shadow-lg sticky top-0 z-20 backdrop-blur-md">
        <div class="max-w-6xl mx-auto flex justify-between items-center px-6 py-4">
            <div class="flex items-center gap-3">
                <img src="foto-pondok.jpg" alt="Logo"
                    class="w-10 h-10 rounded-full border-2 border-white shadow-md object-cover" />
                <span class="text-2xl font-bold drop-shadow">Pondok Pesantren Yati</span>
            </div>
            <ul class="flex gap-6 text-lg">
                <li><a href="index.php" class="hover:text-yellow-300 font-medium transition">Absensi</a></li>
                <li><a href="register.php" class="hover:text-yellow-300 font-medium transition">Registrasi</a></li>
                <li><a href="laporan.php" class="hover:text-yellow-300 font-medium transition">Laporan</a></li>
                <li><a href="dashboard.php" class="hover:text-yellow-300 font-medium transition underline">Dashboard</a></li>
                <li><a href="kelola_siswa.php" class="hover:text-yellow-300 font-medium transition">Kelola Siswa</a></li>
            </ul>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="flex-grow flex items-center justify-center p-6">
        <div
            class="bg-white bg-opacity-90 rounded-3xl shadow-2xl w-full max-w-6xl p-10 fade-in backdrop-blur-md border border-blue-200">
            <h3 class="text-3xl font-bold text-blue-800 mb-8 drop-shadow text-center">Dashboard Admin</h3>

            <?php if ($message): ?>
                <div class="mb-6 p-4 rounded-xl text-lg font-semibold border <?php echo 'alert-' . $message_type; ?> fade-in">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <!-- Dashboard Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-10">
                <div class="bg-blue-600 text-white rounded-xl p-6 shadow-lg flex flex-col items-center justify-center">
                    <div class="text-5xl font-extrabold"><?php echo $total_siswa; ?></div>
                    <div class="text-xl font-semibold mt-2">Total Siswa</div>
                </div>
                <div class="bg-green-600 text-white rounded-xl p-6 shadow-lg flex flex-col items-center justify-center">
                    <div class="text-5xl font-extrabold"><?php echo $total_hadir; ?></div>
                    <div class="text-xl font-semibold mt-2">Hadir Hari Ini</div>
                </div>
                <div class="bg-red-600 text-white rounded-xl p-6 shadow-lg flex flex-col items-center justify-center">
                    <div class="text-5xl font-extrabold"><?php echo $total_terlambat; ?></div>
                    <div class="text-xl font-semibold mt-2">Terlambat Hari Ini</div>
                </div>
                <div class="bg-yellow-600 text-white rounded-xl p-6 shadow-lg flex flex-col items-center justify-center">
                    <div class="text-5xl font-extrabold"><?php echo $total_pulang_cepat; ?></div>
                    <div class="text-xl font-semibold mt-2">Pulang Cepat Hari Ini</div>
                </div>
            </div>

            <!-- Settings Section -->
            <div class="bg-blue-50 p-8 rounded-2xl shadow-inner border border-blue-200 mb-10">
                <h4 class="text-2xl font-bold text-blue-800 mb-6 border-b pb-3 border-blue-300">Pengaturan Jadwal Absensi</h4>
                <form action="dashboard.php" method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="waktu_masuk_mulai" class="block font-medium text-blue-900 mb-1">Waktu Masuk Mulai:</label>
                        <input type="time" id="waktu_masuk_mulai" name="waktu_masuk_mulai"
                            value="<?php echo htmlspecialchars($waktu_masuk_mulai_val); ?>" required
                            class="w-full border-2 border-blue-300 rounded-xl py-2 px-4 text-lg focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-blue-500 bg-white shadow-sm" />
                        <p class="text-sm text-gray-500 mt-1">Siswa bisa mulai absen masuk.</p>
                    </div>
                    <div>
                        <label for="waktu_masuk_akhir" class="block font-medium text-blue-900 mb-1">Waktu Masuk Akhir (Batas Terlambat):</label>
                        <input type="time" id="waktu_masuk_akhir" name="waktu_masuk_akhir"
                            value="<?php echo htmlspecialchars($waktu_masuk_akhir_val); ?>" required
                            class="w-full border-2 border-blue-300 rounded-xl py-2 px-4 text-lg focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-blue-500 bg-white shadow-sm" />
                        <p class="text-sm text-gray-500 mt-1">Setelah waktu ini, siswa dianggap terlambat.</p>
                    </div>
                    <div>
                        <label for="waktu_keluar_mulai" class="block font-medium text-blue-900 mb-1">Waktu Keluar Mulai (Batas Pulang Cepat):</label>
                        <input type="time" id="waktu_keluar_mulai" name="waktu_keluar_mulai"
                            value="<?php echo htmlspecialchars($waktu_keluar_mulai_val); ?>" required
                            class="w-full border-2 border-blue-300 rounded-xl py-2 px-4 text-lg focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-blue-500 bg-white shadow-sm" />
                        <p class="text-sm text-gray-500 mt-1">Sebelum waktu ini, siswa dianggap pulang cepat.</p>
                    </div>
                    <div>
                        <label for="waktu_keluar_akhir" class="block font-medium text-blue-900 mb-1">Waktu Keluar Akhir:</label>
                        <input type="time" id="waktu_keluar_akhir" name="waktu_keluar_akhir"
                            value="<?php echo htmlspecialchars($waktu_keluar_akhir_val); ?>" required
                            class="w-full border-2 border-blue-300 rounded-xl py-2 px-4 text-lg focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-blue-500 bg-white shadow-sm" />
                        <p class="text-sm text-gray-500 mt-1">Batas akhir siswa bisa absen keluar.</p>
                    </div>
                    <div class="md:col-span-2 text-center mt-4">
                        <button type="submit" name="update_settings"
                            class="py-3 px-10 rounded-xl bg-blue-700 text-white font-bold text-lg shadow hover:bg-blue-800 transition transform hover:scale-105">Simpan Pengaturan</button>
                    </div>
                </form>
            </div>

            <!-- Recent Absensi Section -->
            <div class="bg-blue-50 p-8 rounded-2xl shadow-inner border border-blue-200">
                <h4 class="text-2xl font-bold text-blue-800 mb-6 border-b pb-3 border-blue-300">Absensi Terbaru</h4>
                <div class="overflow-x-auto">
                    <table class="min-w-full border border-blue-200 rounded-xl overflow-hidden shadow-lg">
                        <thead class="bg-blue-700 text-white">
                            <tr>
                                <th class="py-3 px-4 font-semibold text-left">Nama Lengkap</th>
                                <th class="py-3 px-4 font-semibold text-left">Kelas</th>
                                <th class="py-3 px-4 font-semibold text-center">Waktu Masuk</th>
                                <th class="py-3 px-4 font-semibold text-center">Waktu Keluar</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white text-blue-900">
                            <?php if (mysqli_num_rows($result_recent_absensi) > 0): ?>
                                <?php while ($row_recent = mysqli_fetch_assoc($result_recent_absensi)): ?>
                                    <tr class="hover:bg-blue-100 transition">
                                        <td class="py-2 px-4"><?php echo htmlspecialchars($row_recent['nama_lengkap']); ?></td>
                                        <td class="py-2 px-4"><?php echo htmlspecialchars($row_recent['kelas']); ?></td>
                                        <td class="py-2 px-4 text-center">
                                            <?php echo $row_recent['waktu_masuk'] ? date('d-m-Y H:i:s', strtotime($row_recent['waktu_masuk'])) : '-'; ?>
                                        </td>
                                        <td class="py-2 px-4 text-center">
                                            <?php echo $row_recent['waktu_keluar'] ? date('d-m-Y H:i:s', strtotime($row_recent['waktu_keluar'])) : '-'; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="py-4 px-4 text-center text-gray-500">Tidak ada data absensi terbaru.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>
</body>

</html>
<?php
mysqli_close($koneksi);
?>
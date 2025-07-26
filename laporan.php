<?php
// laporan.php
include 'koneksi.php'; // Includes database connection and timezone setting

// Ambil tanggal, kelas, dan nama dari filter
$tanggal_filter = isset($_GET['tanggal']) && !empty($_GET['tanggal']) ? $_GET['tanggal'] : date('Y-m-d');
$kelas_filter = isset($_GET['kelas']) && !empty($_GET['kelas']) ? $_GET['kelas'] : '';
$nama_filter = isset($_GET['nama']) && !empty($_GET['nama']) ? $_GET['nama'] : '';

// Query dasar
$sql = "SELECT s.nama_lengkap, s.kelas, a.waktu_masuk, a.waktu_keluar 
        FROM absensi a 
        JOIN siswa s ON a.id_siswa = s.id 
        WHERE DATE(a.waktu_masuk) = ?";

$params = [$tanggal_filter];
$types = "s";

// Tambahkan filter kelas jika ada
if (!empty($kelas_filter)) {
    $sql .= " AND s.kelas = ?";
    $params[] = $kelas_filter;
    $types .= "s";
}

// Tambahkan filter nama jika ada
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

// Ambil daftar kelas untuk filter dropdown
$sql_kelas = "SELECT DISTINCT kelas FROM siswa ORDER BY kelas";
$result_kelas = mysqli_query($koneksi, $sql_kelas);
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Absensi Siswa</title>
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
                <li><a href="laporan.php" class="hover:text-yellow-300 font-medium transition underline">Laporan</a>
                </li>
                <li><a href="dashboard.php" class="hover:text-yellow-300 font-medium transition">Dashboard</a></li>
                <li><a href="kelola_siswa.php" class="hover:text-yellow-300 font-medium transition">Kelola Siswa</a>
                </li>
            </ul>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="flex-grow flex items-center justify-center p-6">
        <div
            class="bg-white bg-opacity-90 rounded-3xl shadow-2xl w-full max-w-5xl p-10 fade-in backdrop-blur-md border border-blue-200">
            <h3 class="text-2xl font-bold text-blue-800 mb-6 drop-shadow">Laporan Absensi Harian</h3>
            <!-- Form untuk filter -->
            <form method="GET" action="laporan.php"
                class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end mb-6 p-4 bg-blue-50 rounded-xl shadow-inner">
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
                        <?php while ($row_kelas = mysqli_fetch_assoc($result_kelas)): ?>
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
                <div class="md:col-span-3 text-center">
                    <button type="submit"
                        class="py-2 px-8 rounded-xl bg-blue-700 text-white font-bold text-lg shadow hover:bg-blue-800 transition transform hover:scale-105">Tampilkan
                        Laporan</button>
                    <button type="button" onclick="window.print()"
                        class="ml-4 py-2 px-8 rounded-xl bg-gray-500 text-white font-bold text-lg shadow hover:bg-gray-600 transition transform hover:scale-105">Cetak
                        Laporan</button>
                </div>
            </form>

            <!-- Tabel untuk menampilkan data laporan -->
            <div class="overflow-x-auto mt-8">
                <table class="min-w-full border border-blue-200 rounded-xl overflow-hidden shadow-lg">
                    <thead class="bg-blue-700 text-white">
                        <tr>
                            <th class="py-3 px-4 font-semibold text-center">No.</th>
                            <th class="py-3 px-4 font-semibold text-left">Nama Lengkap</th>
                            <th class="py-3 px-4 font-semibold text-left">Kelas</th>
                            <th class="py-3 px-4 font-semibold text-center">Waktu Masuk</th>
                            <th class="py-3 px-4 font-semibold text-center">Waktu Keluar</th>
                            <th class="py-3 px-4 font-semibold text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white text-blue-900">
                        <?php if (mysqli_num_rows($result) > 0): ?>
                            <?php $no = 1; ?>
                            <?php
                            // Get attendance settings for status calculation
                            $sql_pengaturan_status = "SELECT waktu_masuk_akhir, waktu_keluar_mulai FROM pengaturan_absensi WHERE id = 1";
                            $result_pengaturan_status = mysqli_query($koneksi, $sql_pengaturan_status);
                            $pengaturan_status = mysqli_fetch_assoc($result_pengaturan_status);
                            ?>
                            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                <tr class="hover:bg-blue-100 transition">
                                    <td class="py-2 px-4 text-center"><?php echo $no++; ?></td>
                                    <td class="py-2 px-4"><?php echo htmlspecialchars($row['nama_lengkap']); ?></td>
                                    <td class="py-2 px-4"><?php echo htmlspecialchars($row['kelas']); ?></td>
                                    <td class="py-2 px-4 text-center">
                                        <?php echo $row['waktu_masuk'] ? date('d-m-Y H:i:s', strtotime($row['waktu_masuk'])) : '-'; ?>
                                    </td>
                                    <td class="py-2 px-4 text-center">
                                        <?php echo $row['waktu_keluar'] ? date('d-m-Y H:i:s', strtotime($row['waktu_keluar'])) : '-'; ?>
                                    </td>
                                    <td class="py-2 px-4 text-center">
                                        <?php
                                        $status = 'Hadir';
                                        if ($row['waktu_masuk'] && strtotime(date('H:i:s', strtotime($row['waktu_masuk']))) > strtotime($pengaturan_status['waktu_masuk_akhir'])) {
                                            $status = 'Terlambat';
                                        }
                                        if ($row['waktu_keluar'] && strtotime(date('H:i:s', strtotime($row['waktu_keluar']))) < strtotime($pengaturan_status['waktu_keluar_mulai'])) {
                                            $status = 'Pulang Cepat';
                                        }
                                        if (!$row['waktu_masuk']) { // Should not happen with current logic, but as a safeguard
                                            $status = 'Tidak Absen Masuk';
                                        }
                                        ?>
                                        <span class="<?php
                                        if ($status == 'Terlambat')
                                            echo 'text-red-600 font-semibold';
                                        else if ($status == 'Pulang Cepat')
                                            echo 'text-orange-600 font-semibold';
                                        else if ($status == 'Hadir')
                                            echo 'text-green-600 font-semibold';
                                        else
                                            echo 'text-gray-600';
                                        ?>">
                                            <?php echo $status; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="py-4 px-4 text-center text-gray-500">Tidak ada data absensi untuk
                                    filter yang dipilih.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</body>

</html>
<?php
mysqli_stmt_close($stmt);
mysqli_close($koneksi);
?>
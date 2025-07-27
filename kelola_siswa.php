<?php
// kelola_siswa.php
include 'koneksi.php';

$pesan = '';
$pesan_type = '';

// Handle Delete
if (isset($_POST['delete_siswa'])) {
    $id = mysqli_real_escape_string($koneksi, $_POST['id']);
    
    // Delete related absensi records first (due to foreign key constraint)
    $sql_delete_absensi = "DELETE FROM absensi WHERE id_siswa = ?";
    $stmt_delete_absensi = mysqli_prepare($koneksi, $sql_delete_absensi);
    mysqli_stmt_bind_param($stmt_delete_absensi, "i", $id);
    mysqli_stmt_execute($stmt_delete_absensi);
    mysqli_stmt_close($stmt_delete_absensi);
    
    // Then delete the student
    $sql_delete = "DELETE FROM siswa WHERE id = ?";
    $stmt_delete = mysqli_prepare($koneksi, $sql_delete);
    mysqli_stmt_bind_param($stmt_delete, "i", $id);
    
    if (mysqli_stmt_execute($stmt_delete)) {
        $pesan = "Data siswa berhasil dihapus!";
        $pesan_type = "success";
    } else {
        $pesan = "Gagal menghapus data siswa: " . mysqli_error($koneksi);
        $pesan_type = "error";
    }
    mysqli_stmt_close($stmt_delete);
}

// Handle Update
if (isset($_POST['update_siswa'])) {
    $id = mysqli_real_escape_string($koneksi, $_POST['id']);
    $nama_lengkap = mysqli_real_escape_string($koneksi, $_POST['nama_lengkap']);
    $kelas = mysqli_real_escape_string($koneksi, $_POST['kelas']);
    $uid_rfid = mysqli_real_escape_string($koneksi, $_POST['uid_rfid']);
    $email_ortu = mysqli_real_escape_string($koneksi, $_POST['email_ortu']);
    
    $sql_update = "UPDATE siswa SET nama_lengkap = ?, kelas = ?, uid_rfid = ?, email_ortu = ? WHERE id = ?";
    $stmt_update = mysqli_prepare($koneksi, $sql_update);
    mysqli_stmt_bind_param($stmt_update, "ssssi", $nama_lengkap, $kelas, $uid_rfid, $email_ortu, $id);
    
    if (mysqli_stmt_execute($stmt_update)) {
        $pesan = "Data siswa berhasil diperbarui!";
        $pesan_type = "success";
    } else {
        if (mysqli_errno($koneksi) == 1062) {
            $pesan = "Error: UID RFID sudah digunakan oleh siswa lain.";
            $pesan_type = "error";
        } else {
            $pesan = "Gagal memperbarui data siswa: " . mysqli_error($koneksi);
            $pesan_type = "error";
        }
    }
    mysqli_stmt_close($stmt_update);
}

// Search functionality
$search = isset($_GET['search']) ? mysqli_real_escape_string($koneksi, $_GET['search']) : '';
$kelas_filter = isset($_GET['kelas_filter']) ? mysqli_real_escape_string($koneksi, $_GET['kelas_filter']) : '';

// Build query with search and filter
$sql = "SELECT * FROM siswa WHERE 1=1";
$params = [];
$types = "";

if (!empty($search)) {
    $sql .= " AND (nama_lengkap LIKE ? OR uid_rfid LIKE ? OR email_ortu LIKE ?)";
    $search_param = '%' . $search . '%';
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

if (!empty($kelas_filter)) {
    $sql .= " AND kelas = ?";
    $params[] = $kelas_filter;
    $types .= "s";
}

$sql .= " ORDER BY nama_lengkap ASC";

$stmt = mysqli_prepare($koneksi, $sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Get all classes for filter dropdown
$sql_kelas = "SELECT DISTINCT kelas FROM siswa ORDER BY kelas";
$result_kelas = mysqli_query($koneksi, $sql_kelas);

// Count total students
$sql_count = "SELECT COUNT(*) as total FROM siswa";
$result_count = mysqli_query($koneksi, $sql_count);
$total_siswa = mysqli_fetch_assoc($result_count)['total'];

// Count students by class
$sql_kelas_count = "SELECT kelas, COUNT(*) as jumlah FROM siswa GROUP BY kelas ORDER BY kelas";
$result_kelas_count = mysqli_query($koneksi, $sql_kelas_count);
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Siswa - Absensi</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
        
        body {
            font-family: 'Inter', sans-serif;
        }
        
        @keyframes fade-in {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes slide-in {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .fade-in {
            animation: fade-in 0.6s ease-out;
        }

        .slide-in {
            animation: slide-in 0.4s ease-out;
        }

        .alert-success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
            border-left: 5px solid #28a745;
        }

        .alert-error {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
            border-left: 5px solid #dc3545;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 50;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(8px);
        }

        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
            animation: fade-in 0.3s ease-out;
        }

        .card-hover {
            transition: all 0.3s ease;
        }

        .card-hover:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }

        .btn-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            transition: all 0.3s ease;
        }

        .btn-gradient:hover {
            background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }

        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 16px;
            color: white;
            padding: 1.5rem;
            position: relative;
            overflow: hidden;
        }

        .stats-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .stats-card:hover::before {
            opacity: 1;
        }

        .glassmorphism {
            background: rgba(255, 255, 255, 0.25);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.18);
        }
    </style>
</head>

<body class="min-h-screen bg-gradient-to-br from-indigo-100 via-white to-purple-100">
    <!-- Navbar -->
    <nav class="bg-white bg-opacity-90 backdrop-blur-md text-gray-800 shadow-lg sticky top-0 z-20 border-b border-gray-200">
        <div class="max-w-7xl mx-auto flex justify-between items-center px-6 py-4">
            <div class="flex items-center gap-4">
                <div class="p-2 bg-gradient-to-r from-indigo-500 to-purple-600 rounded-xl">
                    <i class="fas fa-graduation-cap text-white text-xl"></i>
                </div>
                <div>
                    <h1 class="text-xl font-bold text-gray-800">Pondok Pesantren Yati</h1>
                    <p class="text-sm text-gray-600">Sistem Manajemen Siswa</p>
                </div>
            </div>
            <ul class="flex gap-6 text-sm font-medium">
                <li><a href="index.php" class="flex items-center gap-2 hover:text-indigo-600 transition px-3 py-2 rounded-lg hover:bg-indigo-50">
                    <i class="fas fa-clock"></i> Absensi</a></li>
                <li><a href="register.php" class="flex items-center gap-2 hover:text-indigo-600 transition px-3 py-2 rounded-lg hover:bg-indigo-50">
                    <i class="fas fa-user-plus"></i> Registrasi</a></li>
                <li><a href="laporan.php" class="flex items-center gap-2 hover:text-indigo-600 transition px-3 py-2 rounded-lg hover:bg-indigo-50">
                    <i class="fas fa-chart-bar"></i> Laporan</a></li>
                <li><a href="dashboard.php" class="flex items-center gap-2 hover:text-indigo-600 transition px-3 py-2 rounded-lg hover:bg-indigo-50">
                    <i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="kelola_siswa.php" class="flex items-center gap-2 text-indigo-600 bg-indigo-50 transition px-3 py-2 rounded-lg">
                    <i class="fas fa-users-cog"></i> Kelola Siswa</a></li>
            </ul>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="p-6">
        <div class="max-w-7xl mx-auto">
            <!-- Header Section -->
            <div class="mb-8 fade-in">
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
                    <div>
                        <h2 class="text-3xl font-bold text-gray-800 mb-2">Kelola Data Siswa</h2>
                        <p class="text-gray-600">Manage dan pantau data siswa dengan mudah</p>
                    </div>
                    
                    <!-- Statistics Cards -->
                    <div class="flex flex-wrap gap-4">
                        <div class="stats-card card-hover min-w-[140px]">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm opacity-90">Total Siswa</p>
                                    <p class="text-2xl font-bold"><?php echo $total_siswa; ?></p>
                                </div>
                                <i class="fas fa-users text-2xl opacity-75"></i>
                            </div>
                        </div>
                        
                        <?php if (mysqli_num_rows($result_kelas_count) > 0): ?>
                            <?php $kelas_data = mysqli_fetch_assoc($result_kelas_count); ?>
                            <div class="bg-gradient-to-r from-emerald-500 to-teal-600 rounded-2xl p-4 text-white card-hover min-w-[140px]">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm opacity-90">Kelas <?php echo $kelas_data['kelas']; ?></p>
                                        <p class="text-2xl font-bold"><?php echo $kelas_data['jumlah']; ?> siswa</p>
                                    </div>
                                    <i class="fas fa-chalkboard-teacher text-2xl opacity-75"></i>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if ($pesan): ?>
                <div class="mb-6 p-4 rounded-2xl text-sm font-medium border shadow-lg <?php echo 'alert-' . $pesan_type; ?> fade-in">
                    <div class="flex items-center gap-3">
                        <i class="fas <?php echo $pesan_type == 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?> text-lg"></i>
                        <?php echo $pesan; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Search and Filter Section -->
            <div class="bg-white rounded-2xl shadow-xl p-6 mb-8 border border-gray-100 slide-in">
                <div class="flex items-center gap-3 mb-4">
                    <i class="fas fa-search text-indigo-600 text-lg"></i>
                    <h3 class="text-lg font-semibold text-gray-800">Cari & Filter Data</h3>
                </div>
                
                <form method="GET" action="kelola_siswa.php" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                    <div class="md:col-span-2">
                        <label for="search" class="block font-medium text-gray-700 mb-2 text-sm">Pencarian</label>
                        <div class="relative">
                            <input type="text" id="search" name="search" 
                                placeholder="Cari nama, UID RFID, atau email..." 
                                value="<?php echo htmlspecialchars($search); ?>"
                                class="w-full border-2 border-gray-300 rounded-xl py-3 pl-10 pr-4 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-gray-50 transition" />
                            <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        </div>
                    </div>
                    
                    <div>
                        <label for="kelas_filter" class="block font-medium text-gray-700 mb-2 text-sm">Filter Kelas</label>
                        <select id="kelas_filter" name="kelas_filter"
                            class="w-full border-2 border-gray-300 rounded-xl py-3 px-4 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-gray-50 transition">
                            <option value="">Semua Kelas</option>
                            <?php mysqli_data_seek($result_kelas, 0); ?>
                            <?php while ($row_kelas = mysqli_fetch_assoc($result_kelas)): ?>
                                <option value="<?php echo htmlspecialchars($row_kelas['kelas']); ?>" 
                                    <?php echo ($kelas_filter == $row_kelas['kelas']) ? 'selected' : ''; ?>>
                                    Kelas <?php echo htmlspecialchars($row_kelas['kelas']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="flex gap-2">
                        <button type="submit" class="flex-1 btn-gradient text-white font-semibold py-3 px-4 rounded-xl text-sm shadow-lg">
                            <i class="fas fa-search mr-2"></i>Cari
                        </button>
                        <a href="kelola_siswa.php" class="flex-1 bg-gray-500 hover:bg-gray-600 text-white font-semibold py-3 px-4 rounded-xl text-sm shadow-lg transition text-center">
                            <i class="fas fa-redo mr-2"></i>Reset
                        </a>
                    </div>
                </form>
            </div>

            <!-- Students Table -->
            <div class="bg-white rounded-2xl shadow-xl overflow-hidden border border-gray-100 fade-in">
                <div class="bg-gradient-to-r from-indigo-600 to-purple-600 p-6">
                    <h3 class="text-xl font-bold text-white flex items-center gap-3">
                        <i class="fas fa-table"></i>
                        Data Siswa Terdaftar
                    </h3>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead class="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th class="py-4 px-6 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">No.</th>
                                <th class="py-4 px-6 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Nama Lengkap</th>
                                <th class="py-4 px-6 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Kelas</th>
                                <th class="py-4 px-6 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">UID RFID</th>
                                <th class="py-4 px-6 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Email Orang Tua</th>
                                <th class="py-4 px-6 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Tanggal Daftar</th>
                                <th class="py-4 px-6 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (mysqli_num_rows($result) > 0): ?>
                                <?php $no = 1; ?>
                                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                    <tr class="hover:bg-gray-50 transition-colors duration-200">
                                        <td class="py-4 px-6 text-sm text-gray-900 font-medium"><?php echo $no++; ?></td>
                                        <td class="py-4 px-6">
                                            <div class="flex items-center gap-3">
                                                <div class="w-10 h-10 bg-gradient-to-r from-indigo-500 to-purple-600 rounded-full flex items-center justify-center text-white font-bold text-sm">
                                                    <?php echo strtoupper(substr($row['nama_lengkap'], 0, 2)); ?>
                                                </div>
                                                <div>
                                                    <div class="text-sm font-semibold text-gray-900"><?php echo htmlspecialchars($row['nama_lengkap']); ?></div>
                                                    <div class="text-xs text-gray-500">ID: <?php echo $row['id']; ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-4 px-6 text-center">
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                                                Kelas <?php echo htmlspecialchars($row['kelas']); ?>
                                            </span>
                                        </td>
                                        <td class="py-4 px-6 text-center">
                                            <code class="bg-gray-100 px-3 py-1 rounded-lg text-xs font-mono text-gray-800">
                                                <?php echo htmlspecialchars($row['uid_rfid']); ?>
                                            </code>
                                        </td>
                                        <td class="py-4 px-6 text-center text-sm">
                                            <?php if ($row['email_ortu']): ?>
                                                <div class="flex items-center justify-center gap-2">
                                                    <i class="fas fa-envelope text-green-500 text-xs"></i>
                                                    <span class="text-gray-700"><?php echo htmlspecialchars($row['email_ortu']); ?></span>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-gray-400 italic">Belum diisi</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-4 px-6 text-center text-sm text-gray-600">
                                            <div class="flex items-center justify-center gap-2">
                                                <i class="fas fa-calendar-alt text-gray-400 text-xs"></i>
                                                <?php echo date('d M Y', strtotime($row['created_at'])); ?>
                                            </div>
                                        </td>
                                        <td class="py-4 px-6 text-center">
                                            <div class="flex justify-center gap-2">
                                                <button onclick="editSiswa(<?php echo htmlspecialchars(json_encode($row)); ?>)"
                                                    class="bg-amber-500 hover:bg-amber-600 text-white px-3 py-2 rounded-lg text-xs font-medium transition-all duration-200 hover:shadow-lg flex items-center gap-1">
                                                    <i class="fas fa-edit text-xs"></i>
                                                    Edit
                                                </button>
                                                <button onclick="deleteSiswa(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['nama_lengkap']); ?>')"
                                                    class="bg-red-500 hover:bg-red-600 text-white px-3 py-2 rounded-lg text-xs font-medium transition-all duration-200 hover:shadow-lg flex items-center gap-1">
                                                    <i class="fas fa-trash text-xs"></i>
                                                    Hapus
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="py-12 px-6 text-center">
                                        <div class="flex flex-col items-center gap-4">
                                            <i class="fas fa-users text-gray-300 text-5xl"></i>
                                            <div>
                                                <h4 class="text-lg font-semibold text-gray-500 mb-2">Tidak ada data siswa</h4>
                                                <?php if (!empty($search) || !empty($kelas_filter)): ?>
                                                    <p class="text-gray-400">Tidak ditemukan siswa yang sesuai dengan kriteria pencarian</p>
                                                    <a href="kelola_siswa.php" class="text-indigo-600 hover:text-indigo-800 text-sm font-medium mt-2 inline-block">
                                                        <i class="fas fa-arrow-left mr-1"></i>Lihat semua data
                                                    </a>
                                                <?php else: ?>
                                                    <p class="text-gray-400">Belum ada siswa yang terdaftar dalam sistem</p>
                                                    <a href="register.php" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium mt-4 inline-block transition">
                                                        <i class="fas fa-plus mr-2"></i>Daftarkan Siswa Baru
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg mx-4 overflow-hidden">
            <div class="bg-gradient-to-r from-amber-500 to-orange-600 p-6">
                <h4 class="text-2xl font-bold text-white flex items-center gap-3">
                    <i class="fas fa-edit"></i>
                    Edit Data Siswa
                </h4>
            </div>
            
            <form id="editForm" method="POST" class="p-6">
                <input type="hidden" id="edit_id" name="id">
                <div class="space-y-5">
                    <div>
                        <label for="edit_nama_lengkap" class="block font-semibold text-gray-700 mb-2 text-sm">
                            <i class="fas fa-user mr-2 text-gray-500"></i>Nama Lengkap
                        </label>
                        <input type="text" id="edit_nama_lengkap" name="nama_lengkap" required
                            class="w-full border-2 border-gray-300 rounded-xl py-3 px-4 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-amber-500 bg-gray-50 transition" />
                    </div>
                    
                    <div>
                        <label for="edit_kelas" class="block font-semibold text-gray-700 mb-2 text-sm">
                            <i class="fas fa-chalkboard-teacher mr-2 text-gray-500"></i>Kelas
                        </label>
                        <input type="text" id="edit_kelas" name="kelas" required
                            class="w-full border-2 border-gray-300 rounded-xl py-3 px-4 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-amber-500 bg-gray-50 transition" />
                    </div>
                    
                    <div>
                        <label for="edit_uid_rfid" class="block font-semibold text-gray-700 mb-2 text-sm">
                            <i class="fas fa-id-card mr-2 text-gray-500"></i>UID RFID
                        </label>
                        <input type="text" id="edit_uid_rfid" name="uid_rfid" required
                            class="w-full border-2 border-gray-300 rounded-xl py-3 px-4 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-amber-500 bg-gray-50 font-mono transition" />
                    </div>
                    
                    <div>
                        <label for="edit_email_ortu" class="block font-semibold text-gray-700 mb-2 text-sm">
                            <i class="fas fa-envelope mr-2 text-gray-500"></i>Email Orang Tua
                        </label>
                        <input type="email" id="edit_email_ortu" name="email_ortu"
                            class="w-full border-2 border-gray-300 rounded-xl py-3 px-4 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-amber-500 bg-gray-50 transition" 
                            placeholder="contoh@email.com" />
                    </div>
                </div>
                
                <div class="flex gap-3 mt-8">
                    <button type="submit" name="update_siswa"
                        class="flex-1 bg-gradient-to-r from-amber-500 to-orange-600 text-white font-bold py-3 px-4 rounded-xl text-sm shadow-lg hover:shadow-xl transition-all duration-200 flex items-center justify-center gap-2">
                        <i class="fas fa-save"></i>
                        Simpan Perubahan
                    </button>
                    <button type="button" onclick="closeEditModal()"
                        class="flex-1 bg-gray-500 hover:bg-gray-600 text-white font-bold py-3 px-4 rounded-xl text-sm shadow-lg transition-all duration-200 flex items-center justify-center gap-2">
                        <i class="fas fa-times"></i>
                        Batal
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Modal -->
    <div id="deleteModal" class="modal">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 overflow-hidden">
            <div class="bg-gradient-to-r from-red-500 to-red-600 p-6">
                <h4 class="text-2xl font-bold text-white flex items-center gap-3">
                    <i class="fas fa-exclamation-triangle"></i>
                    Konfirmasi Hapus
                </h4>
            </div>
            
            <div class="p-6">
                <div class="text-center mb-6">
                    <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-trash-alt text-red-500 text-2xl"></i>
                    </div>
                    <p class="text-gray-700 mb-2">Apakah Anda yakin ingin menghapus siswa:</p>
                    <p class="font-bold text-lg text-gray-900" id="delete_nama"></p>
                </div>
                
                <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-6">
                    <div class="flex items-start gap-3">
                        <i class="fas fa-exclamation-triangle text-red-500 mt-1"></i>
                        <div>
                            <p class="text-sm font-semibold text-red-800 mb-1">Peringatan!</p>
                            <p class="text-sm text-red-700">Semua data absensi siswa ini juga akan ikut terhapus secara permanen.</p>
                        </div>
                    </div>
                </div>
                
                <form id="deleteForm" method="POST">
                    <input type="hidden" id="delete_id" name="id">
                    <div class="flex gap-3">
                        <button type="submit" name="delete_siswa"
                            class="flex-1 bg-red-600 hover:bg-red-700 text-white font-bold py-3 px-4 rounded-xl text-sm shadow-lg transition-all duration-200 flex items-center justify-center gap-2">
                            <i class="fas fa-trash-alt"></i>
                            Ya, Hapus
                        </button>
                        <button type="button" onclick="closeDeleteModal()"
                            class="flex-1 bg-gray-500 hover:bg-gray-600 text-white font-bold py-3 px-4 rounded-xl text-sm shadow-lg transition-all duration-200 flex items-center justify-center gap-2">
                            <i class="fas fa-times"></i>
                            Batal
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function editSiswa(data) {
            document.getElementById('edit_id').value = data.id;
            document.getElementById('edit_nama_lengkap').value = data.nama_lengkap;
            document.getElementById('edit_kelas').value = data.kelas;
            document.getElementById('edit_uid_rfid').value = data.uid_rfid;
            document.getElementById('edit_email_ortu').value = data.email_ortu || '';
            document.getElementById('editModal').classList.add('show');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.remove('show');
        }

        function deleteSiswa(id, nama) {
            document.getElementById('delete_id').value = id;
            document.getElementById('delete_nama').textContent = nama;
            document.getElementById('deleteModal').classList.add('show');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.remove('show');
        }

        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            const editModal = document.getElementById('editModal');
            const deleteModal = document.getElementById('deleteModal');
            
            if (event.target === editModal) {
                closeEditModal();
            }
            if (event.target === deleteModal) {
                closeDeleteModal();
            }
        });

        // Close modal with ESC key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeEditModal();
                closeDeleteModal();
            }
        });

        // Auto-hide alert messages
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert-success, .alert-error');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateY(-20px)';
                    setTimeout(() => {
                        alert.remove();
                    }, 300);
                }, 5000);
            });
        });

        // Enhanced search functionality
        document.getElementById('search').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            if (searchTerm.length >= 3) {
                // Add some visual feedback for search
                this.style.borderColor = '#6366f1';
                this.style.boxShadow = '0 0 0 3px rgba(99, 102, 241, 0.1)';
            } else {
                this.style.borderColor = '#d1d5db';
                this.style.boxShadow = 'none';
            }
        });

        // Add loading state to buttons
        document.querySelectorAll('button[type="submit"]').forEach(button => {
            button.addEventListener('click', function() {
                const originalText = this.innerHTML;
                this.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Memproses...';
                this.disabled = true;
                
                // Re-enable after form submission (in case of validation errors)
                setTimeout(() => {
                    this.innerHTML = originalText;
                    this.disabled = false;
                }, 3000);
            });
        });
    </script>
</body>

</html>
<?php
mysqli_stmt_close($stmt);
mysqli_close($koneksi);
?>
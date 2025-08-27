<?php
// kelola_siswa.php - Enhanced Version with Modern Design
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
        $pesan = "Data siswa berhasil dihapus beserta riwayat absensinya!";
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

// Get statistics
$sql_count = "SELECT COUNT(*) as total FROM siswa";
$result_count = mysqli_query($koneksi, $sql_count);
$total_siswa = mysqli_fetch_assoc($result_count)['total'];

// Count by class
$sql_by_class = "SELECT kelas, COUNT(*) as jumlah FROM siswa GROUP BY kelas ORDER BY kelas";
$result_by_class = mysqli_query($koneksi, $sql_by_class);

// Count students with email
$sql_with_email = "SELECT COUNT(*) as total FROM siswa WHERE email_ortu IS NOT NULL AND email_ortu != ''";
$result_with_email = mysqli_query($koneksi, $sql_with_email);
$siswa_with_email = mysqli_fetch_assoc($result_with_email)['total'];

// Recent registrations
$sql_recent = "SELECT nama_lengkap, kelas, created_at FROM siswa ORDER BY created_at DESC LIMIT 5";
$result_recent = mysqli_query($koneksi, $sql_recent);
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Siswa - Sistem Absensi</title>
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

        .btn-success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            transition: all 0.3s ease;
        }

        .btn-success:hover {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(16, 185, 129, 0.3);
        }

        .btn-warning {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            transition: all 0.3s ease;
        }

        .btn-warning:hover {
            background: linear-gradient(135deg, #d97706 0%, #b45309 100%);
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(245, 158, 11, 0.3);
        }

        .btn-danger {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            transition: all 0.3s ease;
        }

        .btn-danger:hover {
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(239, 68, 68, 0.3);
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

        .modal {
            transition: opacity 0.25s ease;
            opacity: 0;
            pointer-events: none;
        }

        .modal.active {
            opacity: 1;
            pointer-events: auto;
        }

        .modal-content {
            transition: transform 0.25s ease;
            transform: scale(0.95);
        }

        .modal.active .modal-content {
            transform: scale(1);
        }

        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.375rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .badge-blue {
            background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
            color: #1e40af;
        }

        .badge-green {
            background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
            color: #166534;
        }

        .badge-yellow {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            color: #92400e;
        }

        .rfid-code {
            font-family: 'Courier New', monospace;
            background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%);
            padding: 0.25rem 0.5rem;
            border-radius: 0.375rem;
            font-size: 0.75rem;
            font-weight: 600;
            color: #374151;
            border: 1px solid #d1d5db;
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
                    <a href="dashboard.php" class="text-gray-700 hover:text-blue-600 font-medium transition duration-300 flex items-center space-x-2">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                    <a href="kelola_siswa.php" class="text-blue-600 font-bold transition duration-300 flex items-center space-x-2 border-b-2 border-blue-600">
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
               
                <p class="text-lg text-gray-600">Manajemen siswa yang terdaftar dalam sistem absensi</p>
                <div class="w-24 h-1 bg-gradient-to-r from-blue-500 to-indigo-600 mx-auto mt-4 rounded-full"></div>
            </div>

            <!-- Alert Messages -->
            <?php if ($pesan): ?>
                <div class="mb-8 p-6 rounded-2xl shadow-lg <?php echo 'alert-' . $pesan_type; ?> animate-slideInUp border-l-4">
                    <div class="flex items-center">
                        <i class="fas fa-<?php echo $pesan_type === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> text-2xl mr-3"></i>
                        <span class="text-lg font-semibold"><?php echo $pesan; ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8 mb-12">
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
                </div>

                <!-- Siswa dengan Email -->
                <div class="gradient-green rounded-2xl p-8 text-white shadow-custom card-hover animate-slideInUp" style="animation-delay: 0.1s;">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-blue-100 text-sm font-medium uppercase tracking-wide">Dengan Email</p>
                            <p class="text-4xl font-bold mt-2"><?php echo $siswa_with_email; ?></p>
                        </div>
                        <div class="bg-white bg-opacity-20 p-4 rounded-xl">
                            <i class="fas fa-envelope text-3xl"></i>
                        </div>
                    </div>
                </div>

                <!-- Total Kelas -->
                <div class="gradient-orange rounded-2xl p-8 text-white shadow-custom card-hover animate-slideInUp" style="animation-delay: 0.2s;">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-blue-100 text-sm font-medium uppercase tracking-wide">Total Kelas</p>
                            <p class="text-4xl font-bold mt-2"><?php echo mysqli_num_rows($result_kelas); ?></p>
                        </div>
                        <div class="bg-white bg-opacity-20 p-4 rounded-xl">
                            <i class="fas fa-school text-3xl"></i>
                        </div>
                    </div>
                </div>

                <!-- Registrasi Hari Ini -->
                <div class="gradient-red rounded-2xl p-8 text-white shadow-custom card-hover animate-slideInUp" style="animation-delay: 0.3s;">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-blue-100 text-sm font-medium uppercase tracking-wide">Reg. Hari Ini</p>
                            <p class="text-4xl font-bold mt-2">
                                <?php 
                                $today_reg = 0;
                                mysqli_data_seek($result_recent, 0);
                                while($recent = mysqli_fetch_assoc($result_recent)) {
                                    if(date('Y-m-d', strtotime($recent['created_at'])) == date('Y-m-d')) {
                                        $today_reg++;
                                    }
                                }
                                echo $today_reg;
                                ?>
                            </p>
                        </div>
                        <div class="bg-white bg-opacity-20 p-4 rounded-xl">
                            <i class="fas fa-user-plus text-3xl"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Search and Actions Section -->
            <div class="bg-white rounded-3xl shadow-custom p-8 mb-8 animate-slideInUp border border-gray-100">
                <div class="flex items-center justify-between mb-8">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-gradient-to-br from-blue-600 to-indigo-700 rounded-xl flex items-center justify-center mr-4">
                            <i class="fas fa-search text-white text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-2xl font-bold text-gray-800">Pencarian & Filter</h3>
                            <p class="text-gray-600">Cari dan filter data siswa</p>
                        </div>
                    </div>
                    <div class="mb-6 flex justify-end">
                        <a href="register.php" class="bg-green-500 hover:bg-green-600 text-white font-bold py-3 px-6 rounded-xl shadow-lg flex items-center space-x-2 transition-colors">
                        <i class="fas fa-plus"></i>
                            <span>Tambah Siswa</span>
                         </a>
                    </div>
                </div>

                <form method="GET" action="kelola_siswa.php" class="grid grid-cols-1 md:grid-cols-4 gap-6 items-end">
                    <div>
                        <label for="search" class="block font-semibold text-gray-700 mb-2">
                            <i class="fas fa-user text-blue-600 mr-2"></i>Cari Siswa:
                        </label>
                        <input type="text" id="search" name="search" placeholder="Nama, UID RFID, atau Email..."
                            value="<?php echo htmlspecialchars($search); ?>"
                            class="w-full border-2 border-gray-200 rounded-xl py-3 px-4 text-lg focus:outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-500 bg-white input-focus" />
                    </div>
                    
                    <div>
                        <label for="kelas_filter" class="block font-semibold text-gray-700 mb-2">
                            <i class="fas fa-school text-blue-600 mr-2"></i>Filter Kelas:
                        </label>
                        <select id="kelas_filter" name="kelas_filter"
                            class="w-full border-2 border-gray-200 rounded-xl py-3 px-4 text-lg focus:outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-500 bg-white input-focus">
                            <option value="">Semua Kelas</option>
                            <?php mysqli_data_seek($result_kelas, 0); ?>
                            <?php while ($row_kelas = mysqli_fetch_assoc($result_kelas)): ?>
                                <option value="<?php echo htmlspecialchars($row_kelas['kelas']); ?>" 
                                    <?php echo ($kelas_filter == $row_kelas['kelas']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($row_kelas['kelas']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="flex gap-3">
                        <button type="submit"
                            class="btn-primary py-3 px-6 rounded-xl text-white font-bold shadow-lg flex items-center space-x-2">
                            <i class="fas fa-search"></i>
                            <span>Cari</span>
                        </button>
                    </div>
                    
                    <div>
                        <a href="kelola_siswa.php"
                            class="w-full block text-center py-3 px-6 rounded-xl bg-gray-200 text-gray-700 font-bold hover:bg-gray-300 transition duration-300 transform hover:scale-105">
                            <i class="fas fa-refresh mr-2"></i>Reset
                        </a>
                    </div>
                </form>
            </div>

            <!-- Students Table -->
            <div class="bg-white rounded-3xl shadow-custom p-8 animate-slideInUp border border-gray-100">
                <div class="flex items-center justify-between mb-6">
                    <h4 class="text-xl font-bold text-gray-800 flex items-center">
                        <i class="fas fa-table text-blue-600 mr-3"></i>
                        Data Siswa Terdaftar
                    </h4>
                    <div class="text-sm text-gray-500 bg-gray-100 px-4 py-2 rounded-full">
                        <i class="fas fa-list mr-2"></i><?php echo mysqli_num_rows($result); ?> Data
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full border border-gray-200 rounded-xl overflow-hidden shadow-lg">
                        <thead class="bg-gradient-to-r from-blue-600 to-indigo-700 text-white">
                            <tr>
                                <th class="py-4 px-4 font-semibold text-center">No.</th>
                                <th class="py-4 px-4 font-semibold text-left">Nama Lengkap</th>
                                <th class="py-4 px-4 font-semibold text-center">Kelas</th>
                                <th class="py-4 px-4 font-semibold text-center">UID RFID</th>
                                <th class="py-4 px-4 font-semibold text-center">Email Orang Tua</th>
                                <th class="py-4 px-4 font-semibold text-center">Tgl Daftar</th>
                                <th class="py-4 px-4 font-semibold text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white text-gray-700">
                            <?php if (mysqli_num_rows($result) > 0): ?>
                                <?php $no = 1; ?>
                                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                    <tr class="table-row-hover transition duration-300 border-b border-gray-100">
                                        <td class="py-4 px-4 text-center font-medium"><?php echo $no++; ?></td>
                                        <td class="py-4 px-4">
                                            <div class="flex items-center">
                                                <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-full flex items-center justify-center mr-3">
                                                    <span class="text-white font-bold text-sm"><?php echo strtoupper(substr($row['nama_lengkap'], 0, 1)); ?></span>
                                                </div>
                                                <div>
                                                    <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($row['nama_lengkap']); ?></p>
                                                    <p class="text-xs text-gray-500">ID: #<?php echo $row['id']; ?></p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-4 px-4 text-center">
                                            <span class="badge badge-blue">
                                                <?php echo htmlspecialchars($row['kelas']); ?>
                                            </span>
                                        </td>
                                        <td class="py-4 px-4 text-center">
                                            <span class="rfid-code">
                                                <?php echo htmlspecialchars($row['uid_rfid']); ?>
                                            </span>
                                        </td>
                                        <td class="py-4 px-4 text-center">
                                            <?php if ($row['email_ortu']): ?>
                                                <div class="flex items-center justify-center">
                                                    <i class="fas fa-envelope text-green-600 mr-2"></i>
                                                    <span class="text-sm"><?php echo htmlspecialchars($row['email_ortu']); ?></span>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-gray-400 text-sm flex items-center justify-center">
                                                    <i class="fas fa-minus mr-2"></i>Tidak ada
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-4 px-4 text-center">
                                            <div class="text-sm">
                                                <div class="font-medium text-gray-800"><?php echo date('d-m-Y', strtotime($row['created_at'])); ?></div>
                                                <div class="text-gray-500"><?php echo date('H:i', strtotime($row['created_at'])); ?></div>
                                            </div>
                                        </td>
                                        <td class="py-4 px-4 text-center">
                                            <div class="flex justify-center space-x-2">
                                                <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($row)); ?>)"
                                                    class="btn-warning px-3 py-2 rounded-lg text-white text-sm font-medium flex items-center space-x-1">
                                                    <i class="fas fa-edit"></i>
                                                    <span>Edit</span>
                                                </button>
                                                <button onclick="openDeleteModal(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['nama_lengkap']); ?>')"
                                                    class="btn-danger px-3 py-2 rounded-lg text-white text-sm font-medium flex items-center space-x-1">
                                                    <i class="fas fa-trash"></i>
                                                    <span>Hapus</span>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="py-12 text-center">
                                        <div class="flex flex-col items-center justify-center">
                                            <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                                                <i class="fas fa-users text-gray-400 text-3xl"></i>
                                            </div>
                                            <p class="text-gray-500 text-lg font-medium">
                                                <?php if (!empty($search) || !empty($kelas_filter)): ?>
                                                    Tidak ada siswa yang sesuai dengan kriteria pencarian
                                                <?php else: ?>
                                                    Belum ada data siswa yang terdaftar
                                                <?php endif; ?>
                                            </p>
                                            <p class="text-gray-400 text-sm mt-1">
                                                <?php if (empty($search) && empty($kelas_filter)): ?>
                                                    Klik tombol "Tambah Siswa" untuk mulai menambahkan data
                                                <?php else: ?>
                                                    Coba ubah kriteria pencarian atau filter
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Statistics by Class -->
            <?php if (mysqli_num_rows($result_by_class) > 0): ?>
            <div class="mt-8 bg-white rounded-3xl shadow-custom p-8 animate-slideInUp border border-gray-100">
                <h4 class="text-xl font-bold text-gray-800 mb-6 flex items-center">
                    <i class="fas fa-chart-pie text-blue-600 mr-3"></i>
                    Distribusi Siswa per Kelas
                </h4>
                <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-4">
                    <?php mysqli_data_seek($result_by_class, 0); ?>
                    <?php while ($class_data = mysqli_fetch_assoc($result_by_class)): ?>
                    <div class="bg-gradient-to-br from-blue-50 to-indigo-100 rounded-xl p-4 border border-blue-200">
                        <div class="text-center">
                            <div class="text-2xl font-bold text-blue-800"><?php echo $class_data['jumlah']; ?></div>
                            <div class="text-blue-600 font-medium">Kelas <?php echo htmlspecialchars($class_data['kelas']); ?></div>
                            <div class="w-full bg-blue-200 rounded-full h-2 mt-2">
                                <div class="bg-blue-600 h-2 rounded-full" style="width: <?php echo ($class_data['jumlah'] / $total_siswa * 100); ?>%"></div>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Edit Modal -->
    <div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 modal">
        <div class="bg-white rounded-3xl p-8 max-w-md w-full mx-4 shadow-2xl modal-content">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-2xl font-bold text-gray-800 flex items-center">
                    <i class="fas fa-edit text-yellow-600 mr-3"></i>
                    Edit Data Siswa
                </h3>
                <button onclick="closeEditModal()" class="text-gray-400 hover:text-gray-600 text-2xl">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form method="POST" action="kelola_siswa.php" class="space-y-6">
                <input type="hidden" id="edit_id" name="id">
                
                <div>
                    <label for="edit_nama_lengkap" class="block font-semibold text-gray-700 mb-2">
                        <i class="fas fa-user text-blue-600 mr-2"></i>Nama Lengkap:
                    </label>
                    <input type="text" id="edit_nama_lengkap" name="nama_lengkap" required
                        class="w-full border-2 border-gray-200 rounded-xl py-3 px-4 focus:outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-500 input-focus" />
                </div>
                
                <div>
                    <label for="edit_kelas" class="block font-semibold text-gray-700 mb-2">
                        <i class="fas fa-school text-blue-600 mr-2"></i>Kelas:
                    </label>
                    <input type="text" id="edit_kelas" name="kelas" required
                        class="w-full border-2 border-gray-200 rounded-xl py-3 px-4 focus:outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-500 input-focus" />
                </div>
                
                <div>
                    <label for="edit_uid_rfid" class="block font-semibold text-gray-700 mb-2">
                        <i class="fas fa-credit-card text-blue-600 mr-2"></i>UID RFID:
                    </label>
                    <input type="text" id="edit_uid_rfid" name="uid_rfid" required
                        class="w-full border-2 border-gray-200 rounded-xl py-3 px-4 focus:outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-500 input-focus" />
                </div>
                
                <div>
                    <label for="edit_email_ortu" class="block font-semibold text-gray-700 mb-2">
                        <i class="fas fa-envelope text-blue-600 mr-2"></i>Email Orang Tua:
                    </label>
                    <input type="email" id="edit_email_ortu" name="email_ortu"
                        class="w-full border-2 border-gray-200 rounded-xl py-3 px-4 focus:outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-500 input-focus" />
                </div>
                
                <div class="flex justify-end space-x-4 pt-6 border-t border-gray-200">
                    <button type="button" onclick="closeEditModal()" 
                        class="py-3 px-6 rounded-xl bg-gray-200 text-gray-700 font-semibold hover:bg-gray-300 transition duration-300">
                        Batal
                    </button>
                    <button type="submit" name="update_siswa"
                        class="btn-warning py-3 px-6 rounded-xl text-white font-semibold shadow-lg">
                        <i class="fas fa-save mr-2"></i>Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Modal -->
    <div id="deleteModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 modal">
        <div class="bg-white rounded-3xl p-8 max-w-md w-full mx-4 shadow-2xl modal-content">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-2xl font-bold text-red-700 flex items-center">
                    <i class="fas fa-exclamation-triangle text-red-600 mr-3"></i>
                    Konfirmasi Hapus
                </h3>
                <button onclick="closeDeleteModal()" class="text-gray-400 hover:text-gray-600 text-2xl">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="mb-6">
                <p class="text-gray-700 mb-4">Apakah Anda yakin ingin menghapus siswa:</p>
                <div class="bg-red-50 border border-red-200 rounded-xl p-4">
                    <p class="font-bold text-red-800" id="delete_nama_display"></p>
                    <p class="text-sm text-red-600 mt-2">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        <strong>Peringatan:</strong> Semua data absensi siswa ini juga akan terhapus permanen!
                    </p>
                </div>
            </div>
            
            <form method="POST" action="kelola_siswa.php">
                <input type="hidden" id="delete_id" name="id">
                <div class="flex justify-end space-x-4">
                    <button type="button" onclick="closeDeleteModal()" 
                        class="py-3 px-6 rounded-xl bg-gray-200 text-gray-700 font-semibold hover:bg-gray-300 transition duration-300">
                        Batal
                    </button>
                    <button type="submit" name="delete_siswa"
                        class="btn-danger py-3 px-6 rounded-xl text-white font-semibold shadow-lg">
                        <i class="fas fa-trash mr-2"></i>Ya, Hapus
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Footer -->
    <footer class="relative z-10 bg-gray-800 text-white py-8 mt-16">
        <div class="max-w-7xl mx-auto px-6 text-center">
            <div class="flex items-center justify-center mb-4">
                <div class="w-8 h-8 bg-gradient-to-br from-blue-600 to-indigo-700 rounded-lg flex items-center justify-center mr-3">
                    <i class="fas fa-graduation-cap text-white"></i>
                </div>
                <span class="text-xl font-bold">Pondok Pesantren Yati</span>
            </div>
            <p class="text-gray-400 mb-4">Sistem Manajemen Siswa - Kelola data siswa dengan mudah dan efisien</p>
            <div class="flex items-center justify-center space-x-6 text-sm text-gray-400">
                <span><i class="fas fa-calendar-alt mr-1"></i><?php echo date('Y'); ?></span>
                <span><i class="fas fa-users mr-1"></i><?php echo $total_siswa; ?> Siswa Terdaftar</span>
                <span><i class="fas fa-school mr-1"></i><?php echo mysqli_num_rows($result_kelas); ?> Kelas</span>
            </div>
        </div>
    </footer>

    <script>
        // Modal functions
        function openAddModal() {
            document.getElementById('addModal').classList.add('active');
        }

        function closeAddModal() {
            document.getElementById('addModal').classList.remove('active');
            document.querySelector('#addModal form').reset();
        }

        function openEditModal(data) {
            document.getElementById('edit_id').value = data.id;
            document.getElementById('edit_nama_lengkap').value = data.nama_lengkap;
            document.getElementById('edit_kelas').value = data.kelas;
            document.getElementById('edit_uid_rfid').value = data.uid_rfid;
            document.getElementById('edit_email_ortu').value = data.email_ortu || '';
            document.getElementById('editModal').classList.add('active');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.remove('active');
        }

        function openDeleteModal(id, nama) {
            document.getElementById('delete_id').value = id;
            document.getElementById('delete_nama_display').textContent = nama;
            document.getElementById('deleteModal').classList.add('active');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.remove('active');
        }

        // Close modal when clicking outside
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('active');
                }
            });
        });

        // Form validation
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const requiredFields = this.querySelectorAll('input[required]');
                let isValid = true;
                
                requiredFields.forEach(field => {
                    if (!field.value.trim()) {
                        isValid = false;
                        field.classList.add('border-red-500');
                        field.focus();
                    } else {
                        field.classList.remove('border-red-500');
                    }
                });
                
                if (!isValid) {
                    e.preventDefault();
                    alert('Mohon lengkapi semua field yang wajib diisi!');
                }
            });
        });

        // Auto-format UID RFID
        document.querySelectorAll('input[name="uid_rfid"]').forEach(input => {
            input.addEventListener('input', function() {
                // Remove non-numeric characters
                this.value = this.value.replace(/\D/g, '');
            });
        });

        // Email validation
        document.querySelectorAll('input[type="email"]').forEach(input => {
            input.addEventListener('blur', function() {
                if (this.value && !this.value.includes('@')) {
                    this.classList.add('border-red-500');
                    alert('Format email tidak valid!');
                } else {
                    this.classList.remove('border-red-500');
                }
            });
        });

        // Add smooth animations when page loads
        document.addEventListener('DOMContentLoaded', function() {
            // Animate statistics numbers
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
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl + N = Add new student
            if (e.ctrlKey && e.key === 'n') {
                e.preventDefault();
                openAddModal();
            }
            
            // Escape = Close modals
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal.active').forEach(modal => {
                    modal.classList.remove('active');
                });
            }
        });

        // Search on Enter key
        document.getElementById('search').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                this.form.submit();
            }
        });
    </script>
</body>

</html>
<?php
mysqli_stmt_close($stmt);
mysqli_close($koneksi);
?>
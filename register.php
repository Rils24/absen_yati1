<?php
// register.php
include 'koneksi.php';

$pesan = ''; // Variabel untuk menyimpan pesan notifikasi
$pesan_type = ''; // Variabel untuk menyimpan tipe pesan (success, danger, warning)

// Cek jika form telah di-submit
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Ambil data dari form dan sanitasi
    $nama_lengkap = mysqli_real_escape_string($koneksi, $_POST['nama_lengkap']);
    $kelas = mysqli_real_escape_string($koneksi, $_POST['kelas']);
    $uid_rfid = mysqli_real_escape_string($koneksi, $_POST['uid_rfid']);
    $email_ortu = mysqli_real_escape_string($koneksi, $_POST['email_ortu']);

    // Validasi input tidak boleh kosong
    if (empty($nama_lengkap) || empty($kelas) || empty($uid_rfid) || empty($email_ortu)) {
        $pesan = "Semua field harus diisi!";
        $pesan_type = "warning";
    }
    // Validasi format email
    elseif (!filter_var($email_ortu, FILTER_VALIDATE_EMAIL)) {
        $pesan = "Format email tidak valid!";
        $pesan_type = "warning";
    } else {
        // Cek apakah UID RFID sudah terdaftar
        $check_rfid = "SELECT uid_rfid FROM siswa WHERE uid_rfid = ?";
        $stmt_check = mysqli_prepare($koneksi, $check_rfid);

        if ($stmt_check) {
            mysqli_stmt_bind_param($stmt_check, "s", $uid_rfid);
            mysqli_stmt_execute($stmt_check);
            mysqli_stmt_store_result($stmt_check);

            if (mysqli_stmt_num_rows($stmt_check) > 0) {
                $pesan = "Kartu RFID ini sudah terdaftar! Silakan gunakan kartu RFID yang lain.";
                $pesan_type = "warning";
            } else {
                // Query untuk memasukkan data siswa baru
                $sql = "INSERT INTO siswa (nama_lengkap, kelas, uid_rfid, email_ortu) VALUES (?, ?, ?, ?)";
                $stmt = mysqli_prepare($koneksi, $sql);

                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, "ssss", $nama_lengkap, $kelas, $uid_rfid, $email_ortu);

                    if (mysqli_stmt_execute($stmt)) {
                        $pesan = "Pendaftaran siswa berhasil!";
                        $pesan_type = "success";
                    } else {
                        $pesan = "Error: Gagal mendaftarkan siswa.";
                        $pesan_type = "danger";
                    }
                    mysqli_stmt_close($stmt);
                } else {
                    $pesan = "Error: Gagal menyiapkan statement.";
                    $pesan_type = "danger";
                }
            }
            mysqli_stmt_close($stmt_check);
        } else {
            $pesan = "Error: Gagal memeriksa RFID.";
            $pesan_type = "danger";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrasi Siswa - Pondok Pesantren Yati</title>
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

            0%,
            100% {
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

        .alert-danger {
            background: linear-gradient(135deg, #fef2f2 0%, #fecaca 100%);
            color: #dc2626;
            border: 1px solid #fca5a5;
        }

        .alert-warning {
            background: linear-gradient(135deg, #fffbeb 0%, #fde68a 100%);
            color: #b45309;
            border: 1px solid #fcd34d;
        }

        .shadow-custom {
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        }

        .form-label .required {
            color: #ef4444;
            /* Red-500 for required asterisk */
        }
    </style>
</head>

<body class="min-h-screen bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-100">
    <!-- Background Decorations -->
    <div class="fixed inset-0 overflow-hidden pointer-events-none">
        <div
            class="absolute -top-40 -right-40 w-80 h-80 bg-blue-300 rounded-full mix-blend-multiply filter blur-xl opacity-70 animate-pulse-gentle">
        </div>
        <div class="absolute -bottom-40 -left-40 w-80 h-80 bg-indigo-300 rounded-full mix-blend-multiply filter blur-xl opacity-70 animate-pulse-gentle"
            style="animation-delay: 1s;"></div>
        <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-96 h-96 bg-blue-200 rounded-full mix-blend-multiply filter blur-xl opacity-50 animate-pulse-gentle"
            style="animation-delay: 2s;"></div>
    </div>

    <!-- Navbar -->
    <nav class="relative z-20 glass-effect shadow-lg sticky top-0">
        <div class="max-w-7xl mx-auto px-6 py-4">
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-4">
                    <div
                        class="w-12 h-12 bg-gradient-to-br from-blue-600 to-indigo-700 rounded-xl flex items-center justify-center shadow-lg">
                        <i class="fas fa-graduation-cap text-white text-xl"></i>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800">Pondok Pesantren Yati</h1>
                        <p class="text-sm text-gray-600">Sistem Absensi Digital</p>
                    </div>
                </div>
                <div class="hidden md:flex space-x-8">
                    <a href="index.php"
                        class="text-gray-700 hover:text-blue-600 font-medium transition duration-300 flex items-center space-x-2">
                        <i class="fas fa-qrcode"></i>
                        <span>Absensi</span>
                    </a>
                    <a href="register.php"
                        class="text-blue-600 font-bold transition duration-300 flex items-center space-x-2 border-b-2 border-blue-600">
                        <i class="fas fa-user-plus"></i>
                        <span>Registrasi</span>
                    </a>
                    <a href="laporan.php"
                        class="text-gray-700 hover:text-blue-600 font-medium transition duration-300 flex items-center space-x-2">
                        <i class="fas fa-chart-bar"></i>
                        <span>Laporan</span>
                    </a>
                    <a href="dashboard.php"
                        class="text-gray-700 hover:text-blue-600 font-medium transition duration-300 flex items-center space-x-2">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                    <a href="kelola_siswa.php"
                        class="text-gray-700 hover:text-blue-600 font-medium transition duration-300 flex items-center space-x-2">
                        <i class="fas fa-users"></i>
                        <span>Kelola Siswa</span>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="relative z-10 px-6 py-8 flex items-center justify-center min-h-[calc(100vh-120px)]">
        <div class="max-w-xl w-full mx-auto">
            <!-- Header Section -->
            <div class="text-center mb-10 animate-fadeIn">
                <h2 class="text-4xl font-bold text-gray-800 mb-4">Registrasi Siswa Baru</h2>
                <p class="text-lg text-gray-600">Daftarkan siswa baru ke sistem absensi</p>
                <div class="w-24 h-1 bg-gradient-to-r from-blue-500 to-indigo-600 mx-auto mt-4 rounded-full"></div>
            </div>

            <?php if ($pesan): ?>
                <div
                    class="mb-8 p-6 rounded-2xl shadow-lg <?php echo 'alert-' . $pesan_type; ?> animate-slideInUp border-l-4">
                    <div class="flex items-center">
                        <i
                            class="fas fa-<?php echo $pesan_type === 'success' ? 'check-circle' : ($pesan_type === 'warning' ? 'exclamation-triangle' : 'times-circle'); ?> text-2xl mr-3"></i>
                        <span class="text-lg font-semibold"><?php echo $pesan; ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Registration Form -->
            <div class="bg-white rounded-3xl shadow-custom p-10 animate-slideInUp border border-gray-100">
                <form action="register.php" method="POST" class="space-y-6">
                    <div>
                        <label for="nama_lengkap" class="block text-sm font-semibold text-gray-700 mb-3">
                            <i class="fas fa-user-alt text-blue-600 mr-2"></i>Nama Lengkap <span
                                class="required">*</span>
                        </label>
                        <input type="text" id="nama_lengkap" name="nama_lengkap"
                            class="w-full border-2 border-gray-200 rounded-xl py-4 px-6 text-lg focus:outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-500 bg-gray-50 input-focus transition duration-300"
                            placeholder="Masukkan nama lengkap siswa" required />
                    </div>

                    <div>
                        <label for="kelas" class="block text-sm font-semibold text-gray-700 mb-3">
                            <i class="fas fa-school text-green-600 mr-2"></i>Kelas <span class="required">*</span>
                        </label>
                        <select id="kelas" name="kelas"
                            class="w-full border-2 border-gray-200 rounded-xl py-4 px-6 text-lg focus:outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-500 bg-gray-50 input-focus transition duration-300 appearance-none pr-10"
                            required>
                            <option value="">Pilih Kelas</option>
                            <option value="7">Kelas 7</option>
                            <option value="8">Kelas 8</option>
                            <option value="9">Kelas 9</option>
                            <option value="10">Kelas 10</option>
                            <option value="11">Kelas 11</option>
                            <option value="12">Kelas 12</option>
                        </select>
                    </div>

                    <div>
                        <label for="uid_rfid" class="block text-sm font-semibold text-gray-700 mb-3">
                            <i class="fas fa-id-card text-purple-600 mr-2"></i>UID Kartu RFID <span
                                class="required">*</span>
                        </label>
                        <input type="text" id="uid_rfid" name="uid_rfid"
                            class="w-full border-2 border-gray-200 rounded-xl py-4 px-6 text-lg focus:outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-500 bg-gray-50 input-focus transition duration-300"
                            placeholder="Tap kartu RFID di sini" required autofocus />
                        <p class="text-sm text-gray-500 mt-2 ml-1">Tempelkan kartu RFID pada reader untuk mengisi
                            otomatis</p>
                    </div>

                    <div>
                        <label for="email_ortu" class="block text-sm font-semibold text-gray-700 mb-3">
                            <i class="fas fa-envelope text-orange-600 mr-2"></i>Email Orang Tua <span
                                class="required">*</span>
                        </label>
                        <input type="email" id="email_ortu" name="email_ortu"
                            class="w-full border-2 border-gray-200 rounded-xl py-4 px-6 text-lg focus:outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-500 bg-gray-50 input-focus transition duration-300"
                            placeholder="contoh@email.com" required />
                        <p class="text-sm text-gray-500 mt-2 ml-1">Email akan digunakan untuk notifikasi absensi siswa
                        </p>
                    </div>

                    <div class="text-center pt-4">
                        <button type="submit"
                            class="btn-primary py-4 px-12 rounded-xl text-white font-bold text-lg shadow-lg flex items-center mx-auto space-x-3 submit-btn">
                            <i class="fas fa-user-plus"></i>
                            <span>Daftarkan Siswa</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="relative z-10 bg-gray-800 text-white py-8 mt-16">
        <div class="max-w-7xl mx-auto px-6 text-center">
            <div class="flex items-center justify-center mb-4">
                <div
                    class="w-8 h-8 bg-gradient-to-br from-blue-600 to-indigo-700 rounded-lg flex items-center justify-center mr-3">
                    <i class="fas fa-graduation-cap text-white"></i>
                </div>
                <span class="text-xl font-bold">Pondok Pesantren Yati</span>
            </div>
            <p class="text-gray-400 mb-4">Sistem Absensi Digital - Memudahkan monitoring kehadiran siswa</p>
            <div class="flex items-center justify-center space-x-6 text-sm text-gray-400">
                <span><i class="fas fa-calendar-alt mr-1"></i><?php echo date('Y'); ?></span>
                <span><i class="fas fa-code mr-1"></i>Developed with KKN 51 UIN IB</span>
                <!-- You might want to fetch total students here if needed -->
                <span><i class="fas fa-users mr-1"></i>Siswa Terdaftar</span>
            </div>
        </div>
    </footer>

    <script>
        // Auto focus pada UID RFID input saat halaman dimuat
        document.addEventListener('DOMContentLoaded', function () {
            const uidInput = document.getElementById('uid_rfid');
            if (uidInput) {
                uidInput.focus();
            }
        });

        // Animasi saat form di-submit
        document.querySelector('form').addEventListener('submit', function (e) {
            const submitBtn = document.querySelector('.submit-btn');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Memproses...';
            submitBtn.classList.remove('btn-primary');
            submitBtn.classList.add('bg-gray-400', 'cursor-not-allowed');
            submitBtn.disabled = true;
        });
    </script>
</body>

</html>
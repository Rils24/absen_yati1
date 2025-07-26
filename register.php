<?php
// register.php
include 'koneksi.php';

$pesan = ''; // Variabel untuk menyimpan pesan notifikasi

// Cek jika form telah di-submit
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Ambil data dari form dan sanitasi
    $nama_lengkap = mysqli_real_escape_string($koneksi, $_POST['nama_lengkap']);
    $kelas = mysqli_real_escape_string($koneksi, $_POST['kelas']);
    $uid_rfid = mysqli_real_escape_string($koneksi, $_POST['uid_rfid']);
    // BARU: Ambil data email_ortu (menggantikan telegram_chat_id)
    $email_ortu = mysqli_real_escape_string($koneksi, $_POST['email_ortu']);

    // Validasi input tidak boleh kosong (email_ortu boleh kosong)
    if (!empty($nama_lengkap) && !empty($kelas) && !empty($uid_rfid)) {
        // Validasi format email jika diisi
        if (!empty($email_ortu) && !filter_var($email_ortu, FILTER_VALIDATE_EMAIL)) {
            $pesan = "<div class='alert alert-warning'>Format email tidak valid!</div>";
        } else {
            // DIUBAH: Query untuk memasukkan data siswa baru termasuk email_ortu
            $sql = "INSERT INTO siswa (nama_lengkap, kelas, uid_rfid, email_ortu) VALUES (?, ?, ?, ?)";

            $stmt = mysqli_prepare($koneksi, $sql);

            if ($stmt) {
                // DIUBAH: Bind parameter baru (s = string)
                mysqli_stmt_bind_param($stmt, "ssss", $nama_lengkap, $kelas, $uid_rfid, $email_ortu);

                if (mysqli_stmt_execute($stmt)) {
                    $pesan = "<div class='alert alert-success'>Pendaftaran siswa berhasil!</div>";
                } else {
                    if (mysqli_errno($koneksi) == 1062) {
                        $pesan = "<div class='alert alert-danger'>Error: UID RFID sudah terdaftar.</div>";
                    } else {
                        $pesan = "<div class='alert alert-danger'>Error: " . mysqli_stmt_error($stmt) . "</div>";
                    }
                }
                mysqli_stmt_close($stmt);
            } else {
                $pesan = "<div class='alert alert-danger'>Error: Gagal menyiapkan statement.</div>";
            }
        }
    } else {
        $pesan = "<div class='alert alert-warning'>Nama, Kelas, dan UID RFID harus diisi!</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrasi Siswa</title>
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

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
        }
        .alert-warning {
            background-color: #fff3cd;
            color: #856404;
            border-color: #ffeeba;
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
                <li><a href="register.php" class="hover:text-yellow-300 font-medium transition underline">Registrasi</a></li>
                <li><a href="laporan.php" class="hover:text-yellow-300 font-medium transition">Laporan</a></li>
                <li><a href="dashboard.php" class="hover:text-yellow-300 font-medium transition">Dashboard</a></li>
                <li><a href="kelola_siswa.php" class="hover:text-yellow-300 font-medium transition">Kelola Siswa</a></li>
            </ul>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="flex-grow flex items-center justify-center p-6">
        <div
            class="bg-white bg-opacity-90 rounded-3xl shadow-2xl w-full max-w-lg p-10 fade-in backdrop-blur-md border border-blue-200">
            <h3 class="text-2xl font-bold text-blue-800 mb-6 drop-shadow">Formulir Registrasi Siswa</h3>
            <?php if ($pesan)
                echo "<div class='mb-4 p-3 rounded-md " . (strpos($pesan, 'success') !== false ? 'alert-success' : (strpos($pesan, 'danger') !== false ? 'alert-danger' : 'alert-warning')) . "'>$pesan</div>"; ?>
            <form action="register.php" method="POST" class="space-y-5">
                <div>
                    <label for="nama_lengkap" class="block text-left font-medium text-blue-900 mb-1">Nama
                        Lengkap</label>
                    <input type="text" id="nama_lengkap" name="nama_lengkap" required
                        class="w-full border-2 border-blue-300 rounded-xl py-3 px-5 text-lg focus:outline-none focus:ring-4 focus:ring-blue-400 focus:border-blue-500 bg-blue-50 bg-opacity-80 shadow-inner transition" />
                </div>
                <div>
                    <label for="kelas" class="block text-left font-medium text-blue-900 mb-1">Kelas</label>
                    <input type="text" id="kelas" name="kelas" required
                        class="w-full border-2 border-blue-300 rounded-xl py-3 px-5 text-lg focus:outline-none focus:ring-4 focus:ring-blue-400 focus:border-blue-500 bg-blue-50 bg-opacity-80 shadow-inner transition" />
                </div>
                <div>
                    <label for="uid_rfid" class="block text-left font-medium text-blue-900 mb-1">UID Kartu RFID</label>
                    <input type="text" id="uid_rfid" name="uid_rfid" placeholder="Tap kartu RFID Anda di sini" required
                        autofocus
                        class="w-full border-2 border-blue-400 rounded-xl py-3 px-5 text-lg focus:outline-none focus:ring-4 focus:ring-blue-500 focus:border-blue-600 bg-blue-50 bg-opacity-80 shadow-inner transition" />
                </div>
                <div>
                    <label for="email_ortu" class="block text-left font-medium text-blue-900 mb-1">Email Orang Tua</label>
                    <input type="email" id="email_ortu" name="email_ortu" placeholder="contoh@email.com"
                        class="w-full border-2 border-blue-200 rounded-xl py-3 px-5 text-lg focus:outline-none focus:ring-2 focus:ring-blue-300 focus:border-blue-400 bg-blue-50 bg-opacity-80 shadow-inner transition" />
                    <span class="text-sm text-gray-500">Boleh dikosongkan jika tidak ada. Email akan digunakan untuk notifikasi absensi.</span>
                </div>
                <button type="submit"
                    class="w-full py-3 rounded-xl bg-blue-700 text-white font-bold text-lg shadow hover:bg-blue-800 transition">Daftarkan
                    Siswa</button>
            </form>
        </div>
    </main>
</body>

</html>
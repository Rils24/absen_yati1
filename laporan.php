<?php
// laporan.php - Enhanced Version with CRUD
include 'koneksi.php'; // Includes database connection and timezone setting
require 'vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// --- KONFIGURASI EMAIL ---
$smtp_host = 'smtp.gmail.com';
$smtp_port = 587;
$smtp_username = 'fairyrat4@gmail.com';
$smtp_password = 'pocvgkrdsjjjmgvy';
$from_email = 'gearfour020@gmail.com';
$from_name = 'Pondok Pesantren Yati';
// --------------------------

// Copy the email sending function from proses_absen.php
function kirimEmailNotifikasi($to_email, $subject, $message)
{
    global $smtp_host, $smtp_port, $smtp_username, $smtp_password, $from_email, $from_name;

    if (empty($to_email)) {
        return false;
    }

    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = $smtp_host;
        $mail->SMTPAuth = true;
        $mail->Username = $smtp_username;
        $mail->Password = $smtp_password;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $smtp_port;
        $mail->CharSet = 'UTF-8';

        // Recipients
        $mail->setFrom($from_email, $from_name);
        $mail->addAddress($to_email);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;

        // Template HTML untuk email
        $html_message = "
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #1e40af; color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background-color: #f8fafc; padding: 20px; border-radius: 0 0 10px 10px; }
                .footer { text-align: center; margin-top: 20px; font-size: 12px; color: #666; }
                .status { padding: 15px; border-radius: 8px; margin: 15px 0; font-size: 16px; }
                .masuk { background-color: #d1fae5; border-left: 5px solid #10b981; color: #065f46; }
                .keluar { background-color: #fef3c7; border-left: 5px solid #f59e0b; color: #92400e; }
                .info-box { background-color: #e0f2fe; padding: 10px; border-radius: 5px; margin: 10px 0; }
                .time-info { font-weight: bold; color: #1e40af; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>🏫 $from_name</h2>
                    <p>Sistem Notifikasi Absensi Santri</p>
                </div>
                <div class='content'>
                    $message
                    <div class='info-box'>
                        <p class='time-info'>📅 Tanggal: " . date('d/m/Y') . "</p>
                        <p class='time-info'>🕐 Waktu: " . date('H:i:s') . " WIB</p>
                    </div>
                </div>
                <div class='footer'>
                    <p>📧 Email ini dikirim secara otomatis oleh Sistem Absensi Pondok Pesantren Yati</p>
                    <p>🚫 Mohon jangan membalas email ini</p>
                </div>
            </div>
        </body>
        </html>";

        $mail->Body = $html_message;
        return $mail->send();
    } catch (Exception $e) {
        return false;
    }
}

// --- Handle CRUD Operations ---
$message = '';
$message_type = '';

// Handle Delete Absensi
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $sql_delete = "DELETE FROM absensi WHERE id = ?";
    $stmt_delete = mysqli_prepare($koneksi, $sql_delete);
    mysqli_stmt_bind_param($stmt_delete, "i", $id);

    if (mysqli_stmt_execute($stmt_delete)) {
        $message = "Data absensi berhasil dihapus!";
        $message_type = "success";
    } else {
        $message = "Gagal menghapus data absensi!";
        $message_type = "error";
    }
    mysqli_stmt_close($stmt_delete);
}

// Handle Add/Update Absensi
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        $id_siswa = intval($_POST['id_siswa']);
        $tanggal = mysqli_real_escape_string($koneksi, $_POST['tanggal']);
        $waktu_masuk = !empty($_POST['waktu_masuk']) ? $tanggal . ' ' . $_POST['waktu_masuk'] : null;
        $waktu_keluar = !empty($_POST['waktu_keluar']) ? $tanggal . ' ' . $_POST['waktu_keluar'] : null;

        if ($action == 'add') {
            // Add new absensi
            $sql_add = "INSERT INTO absensi (id_siswa, waktu_masuk, waktu_keluar) VALUES (?, ?, ?)";
            $stmt_add = mysqli_prepare($koneksi, $sql_add);
            mysqli_stmt_bind_param($stmt_add, "iss", $id_siswa, $waktu_masuk, $waktu_keluar);

            if (mysqli_stmt_execute($stmt_add)) {
                $message = "Data absensi berhasil ditambahkan!";
                $message_type = "success";
            } else {
                $message = "Gagal menambahkan data absensi!";
                $message_type = "error";
            }
            mysqli_stmt_close($stmt_add);

        } elseif ($action == 'update') {
            // Update existing absensi
            $id = intval($_POST['id']);
            $sql_update = "UPDATE absensi SET id_siswa = ?, waktu_masuk = ?, waktu_keluar = ? WHERE id = ?";
            // Get student details before update
            $sql_student = "SELECT s.nama_lengkap, s.email_ortu FROM siswa s WHERE s.id = ?";
            $stmt_student = mysqli_prepare($koneksi, $sql_student);
            mysqli_stmt_bind_param($stmt_student, "i", $id_siswa);
            mysqli_stmt_execute($stmt_student);
            $result_student = mysqli_stmt_get_result($stmt_student);
            $student_data = mysqli_fetch_assoc($result_student);
            mysqli_stmt_close($stmt_student);

            $stmt_update = mysqli_prepare($koneksi, $sql_update);
            mysqli_stmt_bind_param($stmt_update, "issi", $id_siswa, $waktu_masuk, $waktu_keluar, $id);

            if (mysqli_stmt_execute($stmt_update)) {
                $message = "Data absensi berhasil diperbarui!";
                $message_type = "success";

                // Send email notification
                if ($student_data && $student_data['email_ortu']) {
                    $nama_siswa = $student_data['nama_lengkap'];
                    $to_email = $student_data['email_ortu'];

                    // Get the previous record to check what changed
                    $sql_prev = "SELECT waktu_masuk, waktu_keluar FROM absensi WHERE id = ?";
                    $stmt_prev = mysqli_prepare($koneksi, $sql_prev);
                    mysqli_stmt_bind_param($stmt_prev, "i", $id);
                    mysqli_stmt_execute($stmt_prev);
                    $result_prev = mysqli_stmt_get_result($stmt_prev);
                    $prev_data = mysqli_fetch_assoc($result_prev);
                    mysqli_stmt_close($stmt_prev);

                    // Hanya kirim email jika waktu keluar diubah dan bukan null
                    if ($waktu_keluar !== $prev_data['waktu_keluar'] && $waktu_keluar !== null) {
                        $jam_keluar = date('H:i', strtotime($waktu_keluar));

                        // Ambil pengaturan absensi untuk cek pulang awal
                        $sql_pengaturan = "SELECT waktu_keluar_mulai FROM pengaturan_absensi WHERE id = 1";
                        $result_pengaturan = mysqli_query($koneksi, $sql_pengaturan);
                        $pengaturan = mysqli_fetch_assoc($result_pengaturan);
                        $waktu_keluar_normal = date('H:i', strtotime($pengaturan['waktu_keluar_mulai']));

                        if ($jam_keluar < $waktu_keluar_normal) {
                            // Notifikasi pulang awal
                            $subject = "⚠️ Notifikasi Kepulangan - $nama_siswa (Lebih Awal)";
                            $message_content = "<div class='terlambat'>
                                <h3>⚠️ Pemberitahuan Kepulangan (Lebih Awal)</h3>
                                <p>Assalamualaikum Warahmatullahi Wabarakatuh,</p>
                                <p>Bapak/Ibu yang terhormat,</p>
                                <p>Kami dengan hormat memberitahukan bahwa putra/putri Bapak/Ibu:</p>
                                <p><strong>📝 Nama: $nama_siswa</strong></p>
                                <p>Telah <strong>PULANG LEBIH AWAL</strong> dari Pondok Pesantren Yati pada:</p>
                                <p><strong>🕐 Pukul: $jam_keluar WIB</strong></p>
                                <p><em>⚠️ Catatan: Waktu pulang normal dimulai pukul $waktu_keluar_normal WIB</em></p>
                                <p><em>Perubahan ini dilakukan secara manual oleh admin.</em></p>
                                <p>Terima kasih atas perhatian Bapak/Ibu.</p>
                                <p>Wassalamualaikum Warahmatullahi Wabarakatuh</p>
                            </div>";
                        } else {
                            // Notifikasi pulang normal
                            $subject = "🏠 Notifikasi Kepulangan - $nama_siswa";
                            $message_content = "<div class='keluar'>
                                <h3>🏠 Pemberitahuan Kepulangan</h3>
                                <p>Assalamualaikum Warahmatullahi Wabarakatuh,</p>
                                <p>Bapak/Ibu yang terhormat,</p>
                                <p>Kami dengan hormat memberitahukan bahwa putra/putri Bapak/Ibu:</p>
                                <p><strong>📝 Nama: $nama_siswa</strong></p>
                                <p>Telah <strong>PULANG</strong> dari Pondok Pesantren Yati pada:</p>
                                <p><strong>🕐 Pukul: $jam_keluar WIB</strong></p>
                                <p><em>Perubahan ini dilakukan secara manual oleh admin.</em></p>
                                <p>Terima kasih atas perhatian Bapak/Ibu.</p>
                                <p>Wassalamualaikum Warahmatullahi Wabarakatuh</p>
                            </div>";
                        }
                        kirimEmailNotifikasi($to_email, $subject, $message_content);
                    }
                }
            } else {
                $message = "Gagal memperbarui data absensi!";
                $message_type = "error";
            }
            mysqli_stmt_close($stmt_update);
        }
    }
}

// Fungsi untuk mendapatkan statistik absensi
function getAttendanceStats($koneksi, $tanggal_filter, $kelas_filter = '', $nama_filter = '')
{
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
function getAbsentStudents($koneksi, $tanggal_filter, $kelas_filter = '')
{
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

// Export functionality untuk Excel
if (isset($_GET['export']) && $_GET['export'] == 'excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="laporan_absensi_' . $tanggal_filter . '.xls"');

    echo '<table border="1">';
    echo '<tr>';
    echo '<th>No</th>';
    echo '<th>Nama Lengkap</th>';
    echo '<th>Kelas</th>';
    if ($laporan_type == 'rentang')
        echo '<th>Tanggal</th>';
    echo '<th>Waktu Masuk</th>';
    echo '<th>Waktu Keluar</th>';
    echo '<th>Durasi</th>';
    echo '<th>Status</th>';
    echo '</tr>';

    // Get data for Excel
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
        echo '<tr>';
        echo '<td>' . $no++ . '</td>';
        echo '<td>' . htmlspecialchars($row['nama_lengkap']) . '</td>';
        echo '<td>' . htmlspecialchars($row['kelas']) . '</td>';
        if ($laporan_type == 'rentang') {
            echo '<td>' . date('d-m-Y', strtotime($row['tanggal'])) . '</td>';
        }
        echo '<td>' . ($row['waktu_masuk'] ? date('d-m-Y H:i:s', strtotime($row['waktu_masuk'])) : '-') . '</td>';
        echo '<td>' . ($row['waktu_keluar'] ? date('d-m-Y H:i:s', strtotime($row['waktu_keluar'])) : '-') . '</td>';

        // Calculate duration
        if ($row['waktu_masuk'] && $row['waktu_keluar']) {
            $masuk = strtotime($row['waktu_masuk']);
            $keluar = strtotime($row['waktu_keluar']);
            $durasi = $keluar - $masuk;
            $jam = floor($durasi / 3600);
            $menit = floor(($durasi % 3600) / 60);
            echo '<td>' . $jam . ' jam ' . $menit . ' menit</td>';
        } else {
            echo '<td>-</td>';
        }

        echo '<td>Hadir</td>'; // Simplified status for Excel
        echo '</tr>';
    }

    echo '</table>';
    exit();
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

// Ambil daftar kelas untuk filter dropdown
$sql_kelas = "SELECT DISTINCT kelas FROM siswa ORDER BY kelas";
$result_kelas = mysqli_query($koneksi, $sql_kelas);

// Ambil daftar siswa untuk dropdown
$sql_siswa = "SELECT id, nama_lengkap, kelas FROM siswa ORDER BY nama_lengkap";
$result_siswa = mysqli_query($koneksi, $sql_siswa);

// Ambil pengaturan absensi
$sql_pengaturan = "SELECT * FROM pengaturan_absensi WHERE id = 1";
$result_pengaturan = mysqli_query($koneksi, $sql_pengaturan);
$pengaturan = mysqli_fetch_assoc($result_pengaturan);

// Get data for edit modal
$edit_data = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $sql_edit = "SELECT a.*, s.nama_lengkap, s.kelas FROM absensi a JOIN siswa s ON a.id_siswa = s.id WHERE a.id = ?";
    $stmt_edit = mysqli_prepare($koneksi, $sql_edit);
    mysqli_stmt_bind_param($stmt_edit, "i", $edit_id);
    mysqli_stmt_execute($stmt_edit);
    $result_edit = mysqli_stmt_get_result($stmt_edit);
    $edit_data = mysqli_fetch_assoc($result_edit);
    mysqli_stmt_close($stmt_edit);
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Absensi Siswa - Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
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
                        class="text-gray-700 hover:text-blue-600 font-medium transition duration-300 flex items-center space-x-2">
                        <i class="fas fa-user-plus"></i>
                        <span>Registrasi</span>
                    </a>
                    <a href="laporan.php"
                        class="text-blue-600 font-bold transition duration-300 flex items-center space-x-2 border-b-2 border-blue-600">
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
    <main class="relative z-10 px-6 py-8">
        <div class="max-w-7xl mx-auto">
            <!-- Header Section -->
            <div class="text-center mb-12 animate-fadeIn">
                <h2 class="text-4xl font-bold text-gray-800 mb-4">Laporan & Management Absensi</h2>
                <p class="text-lg text-gray-600">Kelola data absensi siswa dengan mudah dan efisien</p>
                <div class="w-24 h-1 bg-gradient-to-r from-blue-500 to-indigo-600 mx-auto mt-4 rounded-full"></div>
            </div>

            <!-- Alert Messages -->
            <?php if ($message): ?>
                <div
                    class="mb-8 p-6 rounded-2xl shadow-lg <?php echo 'alert-' . $message_type; ?> animate-slideInUp border-l-4">
                    <div class="flex items-center">
                        <i
                            class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> text-2xl mr-3"></i>
                        <span class="text-lg font-semibold"><?php echo $message; ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Filter dan Kontrol -->
            <div class="bg-white rounded-3xl shadow-custom p-8 mb-8 animate-slideInUp border border-gray-100">
                <div class="flex items-center justify-between mb-8">
                    <div class="flex items-center">
                        <div
                            class="w-12 h-12 bg-gradient-to-br from-blue-600 to-indigo-700 rounded-xl flex items-center justify-center mr-4">
                            <i class="fas fa-filter text-white text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-2xl font-bold text-gray-800">Filter & Management</h3>
                            <p class="text-gray-600">Filter data dan kelola absensi siswa</p>
                        </div>
                    </div>
                    <button onclick="openAddModal()"
                        class="btn-primary py-3 px-6 rounded-xl text-white font-bold flex items-center space-x-2 shadow-lg">
                        <i class="fas fa-plus"></i>
                        <span>Tambah Data</span>
                    </button>
                </div>

                <!-- Tab untuk tipe laporan -->
                <div class="flex mb-6 border-b border-gray-200">
                    <button onclick="showTab('harian')" id="tab-harian"
                        class="px-6 py-3 font-medium <?php echo $laporan_type == 'harian' ? 'text-blue-600 border-b-2 border-blue-600 bg-blue-50' : 'text-gray-600 hover:text-blue-600 hover:bg-gray-50'; ?> rounded-t-lg transition duration-300">
                        <i class="fas fa-calendar-day mr-2"></i>Laporan Harian
                    </button>
                    <button onclick="showTab('rentang')" id="tab-rentang"
                        class="px-6 py-3 font-medium <?php echo $laporan_type == 'rentang' ? 'text-blue-600 border-b-2 border-blue-600 bg-blue-50' : 'text-gray-600 hover:text-blue-600 hover:bg-gray-50'; ?> rounded-t-lg transition duration-300">
                        <i class="fas fa-calendar-alt mr-2"></i>Laporan Rentang Tanggal
                    </button>
                </div>

                <!-- Form Filter Harian -->
                <form method="GET" action="laporan.php" id="form-harian"
                    class="<?php echo $laporan_type == 'rentang' ? 'hidden' : ''; ?>">
                    <input type="hidden" name="laporan_type" value="harian">
                    <div
                        class="grid grid-cols-1 md:grid-cols-4 gap-6 items-end mb-6 p-6 bg-gradient-to-r from-blue-50 to-indigo-50 rounded-2xl">
                        <div>
                            <label for="tanggal" class="block font-semibold text-gray-700 mb-2">
                                <i class="fas fa-calendar text-blue-600 mr-2"></i>Tanggal:
                            </label>
                            <input type="date" id="tanggal" name="tanggal"
                                value="<?php echo htmlspecialchars($tanggal_filter); ?>"
                                class="w-full border-2 border-gray-200 rounded-xl py-3 px-4 text-lg focus:outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-500 bg-white input-focus" />
                        </div>
                        <div>
                            <label for="kelas" class="block font-semibold text-gray-700 mb-2">
                                <i class="fas fa-school text-blue-600 mr-2"></i>Kelas:
                            </label>
                            <select id="kelas" name="kelas"
                                class="w-full border-2 border-gray-200 rounded-xl py-3 px-4 text-lg focus:outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-500 bg-white input-focus">
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
                            <label for="nama" class="block font-semibold text-gray-700 mb-2">
                                <i class="fas fa-user text-blue-600 mr-2"></i>Nama Siswa:
                            </label>
                            <input type="text" id="nama" name="nama" placeholder="Cari nama siswa..."
                                value="<?php echo htmlspecialchars($nama_filter); ?>"
                                class="w-full border-2 border-gray-200 rounded-xl py-3 px-4 text-lg focus:outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-500 bg-white input-focus" />
                        </div>
                        <div class="flex gap-3">
                            <button type="submit"
                                class="btn-primary py-3 px-6 rounded-xl text-white font-bold text-lg shadow-lg flex items-center space-x-2">
                                <i class="fas fa-search"></i>
                                <span>Filter</span>
                            </button>
                        </div>
                    </div>
                </form>

                <!-- Form Filter Rentang -->
                <form method="GET" action="laporan.php" id="form-rentang"
                    class="<?php echo $laporan_type == 'harian' ? 'hidden' : ''; ?>">
                    <input type="hidden" name="laporan_type" value="rentang">
                    <div
                        class="grid grid-cols-1 md:grid-cols-5 gap-6 items-end mb-6 p-6 bg-gradient-to-r from-blue-50 to-indigo-50 rounded-2xl">
                        <div>
                            <label for="tanggal_mulai" class="block font-semibold text-gray-700 mb-2">
                                <i class="fas fa-calendar-alt text-blue-600 mr-2"></i>Tanggal Mulai:
                            </label>
                            <input type="date" id="tanggal_mulai" name="tanggal_mulai"
                                value="<?php echo htmlspecialchars($tanggal_mulai); ?>"
                                class="w-full border-2 border-gray-200 rounded-xl py-3 px-4 text-lg focus:outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-500 bg-white input-focus" />
                        </div>
                        <div>
                            <label for="tanggal_akhir" class="block font-semibold text-gray-700 mb-2">
                                <i class="fas fa-calendar-check text-blue-600 mr-2"></i>Tanggal Akhir:
                            </label>
                            <input type="date" id="tanggal_akhir" name="tanggal_akhir"
                                value="<?php echo htmlspecialchars($tanggal_akhir); ?>"
                                class="w-full border-2 border-gray-200 rounded-xl py-3 px-4 text-lg focus:outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-500 bg-white input-focus" />
                        </div>
                        <div>
                            <label for="kelas_rentang" class="block font-semibold text-gray-700 mb-2">
                                <i class="fas fa-school text-blue-600 mr-2"></i>Kelas:
                            </label>
                            <select id="kelas_rentang" name="kelas"
                                class="w-full border-2 border-gray-200 rounded-xl py-3 px-4 text-lg focus:outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-500 bg-white input-focus">
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
                            <label for="nama_rentang" class="block font-semibold text-gray-700 mb-2">
                                <i class="fas fa-user text-blue-600 mr-2"></i>Nama Siswa:
                            </label>
                            <input type="text" id="nama_rentang" name="nama" placeholder="Cari nama siswa..."
                                class="w-full border-2 border-gray-200 rounded-xl py-3 px-4 text-lg focus:outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-500 bg-white input-focus" />
                        </div>
                        <div class="flex gap-3">
                            <button type="submit"
                                class="btn-primary py-3 px-6 rounded-xl text-white font-bold text-lg shadow-lg flex items-center space-x-2">
                                <i class="fas fa-search"></i>
                                <span>Filter</span>
                            </button>
                        </div>
                    </div>
                </form>

                <!-- Tombol Aksi -->
                <div class="flex flex-wrap gap-4 justify-center border-t border-gray-200 pt-6">
                    <button type="button" onclick="cetakLaporan()"
                        class="py-3 px-6 rounded-xl bg-gray-600 text-white font-bold shadow-lg hover:bg-gray-700 transition duration-300 transform hover:scale-105 flex items-center space-x-2">
                        <i class="fas fa-print"></i>
                        <span>Cetak Laporan</span>
                    </button>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'excel'])); ?>"
                        class="py-3 px-6 rounded-xl bg-green-600 text-white font-bold shadow-lg hover:bg-green-700 transition duration-300 transform hover:scale-105 flex items-center space-x-2">
                        <i class="fas fa-file-excel"></i>
                        <span>Export Excel</span>
                    </a>
                    <button type="button" onclick="exportPdf()"
                        class="py-3 px-6 rounded-xl bg-red-600 text-white font-bold shadow-lg hover:bg-red-700 transition duration-300 transform hover:scale-105 flex items-center space-x-2">
                        <i class="fas fa-file-pdf"></i>
                        <span>Export PDF</span>
                    </button>
                    <button type="button" onclick="showStatistics()"
                        class="py-3 px-6 rounded-xl bg-purple-600 text-white font-bold shadow-lg hover:bg-purple-700 transition duration-300 transform hover:scale-105 flex items-center space-x-2">
                        <i class="fas fa-chart-pie"></i>
                        <span>Statistik</span>
                    </button>
                </div>
            </div>

            <!-- Statistik Dashboard -->
            <?php if ($laporan_type == 'harian'): ?>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8" id="statistics-panel">
                    <div class="gradient-blue rounded-2xl p-6 text-white shadow-custom card-hover animate-slideInUp">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-blue-100 text-sm font-medium uppercase tracking-wide">Total Absensi</p>
                                <p class="text-3xl font-bold mt-2"><?php echo $stats['total_absensi']; ?></p>
                            </div>
                            <div class="bg-white bg-opacity-20 p-4 rounded-xl">
                                <i class="fas fa-users text-2xl"></i>
                            </div>
                        </div>
                    </div>

                    <div class="gradient-green rounded-2xl p-6 text-white shadow-custom card-hover animate-slideInUp"
                        style="animation-delay: 0.1s;">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-blue-100 text-sm font-medium uppercase tracking-wide">Hadir</p>
                                <p class="text-3xl font-bold mt-2"><?php echo $stats['hadir']; ?></p>
                            </div>
                            <div class="bg-white bg-opacity-20 p-4 rounded-xl">
                                <i class="fas fa-user-check text-2xl"></i>
                            </div>
                        </div>
                    </div>

                    <div class="gradient-orange rounded-2xl p-6 text-white shadow-custom card-hover animate-slideInUp"
                        style="animation-delay: 0.2s;">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-blue-100 text-sm font-medium uppercase tracking-wide">Belum Pulang</p>
                                <p class="text-3xl font-bold mt-2"><?php echo $stats['belum_pulang']; ?></p>
                            </div>
                            <div class="bg-white bg-opacity-20 p-4 rounded-xl">
                                <i class="fas fa-hourglass-half text-2xl"></i>
                            </div>
                        </div>
                    </div>

                    <div class="gradient-red rounded-2xl p-6 text-white shadow-custom card-hover animate-slideInUp"
                        style="animation-delay: 0.3s;">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-blue-100 text-sm font-medium uppercase tracking-wide">Tidak Hadir</p>
                                <p class="text-3xl font-bold mt-2"><?php echo mysqli_num_rows($absent_students); ?></p>
                            </div>
                            <div class="bg-white bg-opacity-20 p-4 rounded-xl">
                                <i class="fas fa-user-times text-2xl"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Chart -->
                <div class="bg-white rounded-3xl shadow-custom p-8 mb-8 animate-slideInUp border border-gray-100">
                    <h4 class="text-xl font-bold text-gray-800 mb-6 flex items-center">
                        <i class="fas fa-chart-pie text-blue-600 mr-3"></i>Grafik Kehadiran
                    </h4>
                    <div class="chart-container">
                        <canvas id="attendanceChart"></canvas>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Tabel Siswa Tidak Hadir -->
            <?php if ($laporan_type == 'harian' && mysqli_num_rows($absent_students) > 0): ?>
                <div class="bg-white rounded-3xl shadow-custom p-8 mb-8 animate-slideInUp border border-red-200">
                    <h4 class="text-xl font-bold text-red-700 mb-6 flex items-center">
                        <i class="fas fa-exclamation-triangle text-red-600 mr-3"></i>
                        Siswa Tidak Hadir (<?php echo date('d-m-Y', strtotime($tanggal_filter)); ?>)
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <?php mysqli_data_seek($absent_students, 0); ?>
                        <?php while ($absent = mysqli_fetch_assoc($absent_students)): ?>
                            <div
                                class="bg-red-50 border border-red-200 rounded-xl p-4 hover:bg-red-100 transition duration-300">
                                <p class="font-semibold text-red-800 flex items-center">
                                    <i class="fas fa-user-times text-red-600 mr-2"></i>
                                    <?php echo htmlspecialchars($absent['nama_lengkap']); ?>
                                </p>
                                <p class="text-sm text-red-600 ml-6">Kelas: <?php echo htmlspecialchars($absent['kelas']); ?>
                                </p>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Tabel Laporan Utama -->
            <div class="bg-white rounded-3xl shadow-custom p-8 animate-slideInUp border border-gray-100">
                <div class="flex items-center justify-between mb-6">
                    <h4 class="text-xl font-bold text-gray-800 flex items-center">
                        <i class="fas fa-table text-blue-600 mr-3"></i>
                        Data Absensi
                        <?php if ($laporan_type == 'rentang'): ?>
                            (<?php echo date('d-m-Y', strtotime($tanggal_mulai)); ?> -
                            <?php echo date('d-m-Y', strtotime($tanggal_akhir)); ?>)
                        <?php else: ?>
                            (<?php echo date('d-m-Y', strtotime($tanggal_filter)); ?>)
                        <?php endif; ?>
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
                                <th class="py-4 px-4 font-semibold text-left">Kelas</th>
                                <?php if ($laporan_type == 'rentang'): ?>
                                    <th class="py-4 px-4 font-semibold text-center">Tanggal</th>
                                <?php endif; ?>
                                <th class="py-4 px-4 font-semibold text-center">Waktu Masuk</th>
                                <th class="py-4 px-4 font-semibold text-center">Waktu Keluar</th>
                                <th class="py-4 px-4 font-semibold text-center">Durasi</th>
                                <th class="py-4 px-4 font-semibold text-center">Status</th>
                                <th class="py-4 px-4 font-semibold text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white text-gray-700">
                            <?php if (mysqli_num_rows($result) > 0): ?>
                                <?php $no = 1; ?>
                                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                    <tr
                                        class="hover:bg-blue-50 transition duration-300 table-row-hover border-b border-gray-100">
                                        <td class="py-4 px-4 text-center font-medium"><?php echo $no++; ?></td>
                                        <td class="py-4 px-4">
                                            <div class="flex items-center">
                                                <div
                                                    class="w-8 h-8 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-full flex items-center justify-center mr-3">
                                                    <span
                                                        class="text-white font-semibold text-xs"><?php echo strtoupper(substr($row['nama_lengkap'], 0, 1)); ?></span>
                                                </div>
                                                <span
                                                    class="font-medium"><?php echo htmlspecialchars($row['nama_lengkap']); ?></span>
                                            </div>
                                        </td>
                                        <td class="py-4 px-4">
                                            <span class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-sm font-medium">
                                                <?php echo htmlspecialchars($row['kelas']); ?>
                                            </span>
                                        </td>
                                        <?php if ($laporan_type == 'rentang'): ?>
                                            <td class="py-4 px-4 text-center">
                                                <span class="bg-gray-100 text-gray-700 px-3 py-1 rounded-full text-sm">
                                                    <?php echo date('d-m-Y', strtotime($row['tanggal'])); ?>
                                                </span>
                                            </td>
                                        <?php endif; ?>
                                        <td class="py-4 px-4 text-center">
                                            <?php if ($row['waktu_masuk']): ?>
                                                <div class="bg-green-50 text-green-700 px-3 py-2 rounded-lg inline-block">
                                                    <i class="fas fa-clock mr-1"></i>
                                                    <?php echo date('H:i:s', strtotime($row['waktu_masuk'])); ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-gray-400 text-sm">
                                                    <i class="fas fa-minus mr-1"></i>-
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-4 px-4 text-center">
                                            <?php if ($row['waktu_keluar']): ?>
                                                <div class="bg-blue-50 text-blue-700 px-3 py-2 rounded-lg inline-block">
                                                    <i class="fas fa-clock mr-1"></i>
                                                    <?php echo date('H:i:s', strtotime($row['waktu_keluar'])); ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-orange-500 text-sm bg-orange-50 px-3 py-1 rounded-full">
                                                    <i class="fas fa-hourglass-half mr-1"></i>Belum pulang
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-4 px-4 text-center">
                                            <?php
                                            if ($row['waktu_masuk'] && $row['waktu_keluar']) {
                                                $masuk = strtotime($row['waktu_masuk']);
                                                $keluar = strtotime($row['waktu_keluar']);
                                                $durasi = $keluar - $masuk;
                                                $jam = floor($durasi / 3600);
                                                $menit = floor(($durasi % 3600) / 60);
                                                echo '<span class="bg-gray-100 text-gray-700 px-3 py-1 rounded-full text-sm">' . $jam . 'j ' . $menit . 'm</span>';
                                            } else {
                                                echo '<span class="text-gray-400 text-sm">-</span>';
                                            }
                                            ?>
                                        </td>
                                        <td class="py-4 px-4 text-center">
                                            <?php
                                            $status = 'Hadir';
                                            $status_class = 'bg-green-100 text-green-700';

                                            if ($row['waktu_masuk']) {
                                                $waktu_masuk_only = date('H:i:s', strtotime($row['waktu_masuk']));
                                                if (strtotime($waktu_masuk_only) > strtotime($pengaturan['waktu_masuk_akhir'])) {
                                                    $status = 'Terlambat';
                                                    $status_class = 'bg-red-100 text-red-700';
                                                }
                                            }

                                            if ($row['waktu_keluar']) {
                                                $waktu_keluar_only = date('H:i:s', strtotime($row['waktu_keluar']));
                                                if (strtotime($waktu_keluar_only) < strtotime($pengaturan['waktu_keluar_mulai'])) {
                                                    if ($status == 'Terlambat') {
                                                        $status = 'Terlambat & Pulang Cepat';
                                                        $status_class = 'bg-red-100 text-red-700';
                                                    } else {
                                                        $status = 'Pulang Cepat';
                                                        $status_class = 'bg-orange-100 text-orange-700';
                                                    }
                                                }
                                            } else {
                                                if ($status == 'Terlambat') {
                                                    $status = 'Terlambat';
                                                } else {
                                                    $status = 'Belum Pulang';
                                                    $status_class = 'bg-blue-100 text-blue-700';
                                                }
                                            }
                                            ?>
                                            <span
                                                class="<?php echo $status_class; ?> px-3 py-1 rounded-full text-xs font-semibold">
                                                <?php echo $status; ?>
                                            </span>
                                        </td>
                                        <td class="py-4 px-4 text-center">
                                            <div class="flex justify-center space-x-2">
                                                <button onclick="openEditModal(<?php echo $row['id']; ?>)"
                                                    class="bg-blue-100 hover:bg-blue-200 text-blue-700 px-3 py-2 rounded-lg transition duration-300 text-sm">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button onclick="confirmDelete(<?php echo $row['id']; ?>)"
                                                    class="bg-red-100 hover:bg-red-200 text-red-700 px-3 py-2 rounded-lg transition duration-300 text-sm">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="<?php echo $laporan_type == 'rentang' ? '9' : '8'; ?>"
                                        class="py-12 text-center">
                                        <div class="flex flex-col items-center justify-center">
                                            <div
                                                class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                                                <i class="fas fa-clipboard-list text-gray-400 text-3xl"></i>
                                            </div>
                                            <p class="text-gray-500 text-lg font-medium">Tidak ada data absensi</p>
                                            <p class="text-gray-400 text-sm mt-1">Silakan ubah filter atau tambah data baru
                                            </p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Informasi Tambahan -->
                <div class="mt-8 p-6 bg-gradient-to-r from-blue-50 to-indigo-50 rounded-2xl border border-blue-200">
                    <h5 class="font-bold text-gray-800 mb-4 flex items-center">
                        <i class="fas fa-info-circle text-blue-600 mr-2"></i>Keterangan Sistem
                    </h5>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm">
                        <div class="space-y-2">
                            <p class="flex items-center"><i class="fas fa-clock text-blue-600 mr-2"></i><span
                                    class="font-semibold">Jam Masuk:</span>
                                <?php echo date('H:i', strtotime($pengaturan['waktu_masuk_mulai'])); ?> -
                                <?php echo date('H:i', strtotime($pengaturan['waktu_masuk_akhir'])); ?></p>
                            <p class="flex items-center"><i class="fas fa-clock text-blue-600 mr-2"></i><span
                                    class="font-semibold">Jam Pulang:</span>
                                <?php echo date('H:i', strtotime($pengaturan['waktu_keluar_mulai'])); ?> -
                                <?php echo date('H:i', strtotime($pengaturan['waktu_keluar_akhir'])); ?></p>
                        </div>
                        <div class="space-y-2">
                            <p class="flex items-center"><span
                                    class="w-3 h-3 bg-green-500 rounded-full mr-2"></span><span
                                    class="font-semibold">Hadir:</span> Masuk tepat waktu</p>
                            <p class="flex items-center"><span class="w-3 h-3 bg-red-500 rounded-full mr-2"></span><span
                                    class="font-semibold">Terlambat:</span> Masuk setelah
                                <?php echo date('H:i', strtotime($pengaturan['waktu_masuk_akhir'])); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Add/Edit Modal -->
    <div id="crudModal"
        class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 opacity-0 pointer-events-none modal">
        <div class="bg-white rounded-3xl p-8 max-w-md w-full mx-4 shadow-2xl modal-content">
            <div class="flex items-center justify-between mb-6">
                <h3 id="modalTitle" class="text-2xl font-bold text-gray-800">Tambah Data Absensi</h3>
                <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600 text-2xl">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <form id="crudForm" method="POST" action="laporan.php" class="space-y-6">
                <input type="hidden" id="actionInput" name="action" value="add">
                <input type="hidden" id="idInput" name="id" value="">

                <div>
                    <label for="modalSiswa" class="block font-semibold text-gray-700 mb-2">
                        <i class="fas fa-user text-blue-600 mr-2"></i>Pilih Siswa:
                    </label>
                    <select id="modalSiswa" name="id_siswa" required
                        class="w-full border-2 border-gray-200 rounded-xl py-3 px-4 focus:outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-500 input-focus">
                        <option value="">Pilih Siswa</option>
                        <?php
                        mysqli_data_seek($result_siswa, 0);
                        while ($siswa = mysqli_fetch_assoc($result_siswa)): ?>
                            <option value="<?php echo $siswa['id']; ?>">
                                <?php echo htmlspecialchars($siswa['nama_lengkap']); ?> -
                                <?php echo htmlspecialchars($siswa['kelas']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div>
                    <label for="modalTanggal" class="block font-semibold text-gray-700 mb-2">
                        <i class="fas fa-calendar text-blue-600 mr-2"></i>Tanggal:
                    </label>
                    <input type="date" id="modalTanggal" name="tanggal" required
                        class="w-full border-2 border-gray-200 rounded-xl py-3 px-4 focus:outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-500 input-focus">
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="modalWaktuMasuk" class="block font-semibold text-gray-700 mb-2">
                            <i class="fas fa-sign-in-alt text-green-600 mr-2"></i>Waktu Masuk:
                        </label>
                        <input type="time" id="modalWaktuMasuk" name="waktu_masuk"
                            class="w-full border-2 border-gray-200 rounded-xl py-3 px-4 focus:outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-500 input-focus">
                    </div>

                    <div>
                        <label for="modalWaktuKeluar" class="block font-semibold text-gray-700 mb-2">
                            <i class="fas fa-sign-out-alt text-red-600 mr-2"></i>Waktu Keluar:
                        </label>
                        <input type="time" id="modalWaktuKeluar" name="waktu_keluar"
                            class="w-full border-2 border-gray-200 rounded-xl py-3 px-4 focus:outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-500 input-focus">
                    </div>
                </div>

                <div class="flex justify-end space-x-4 pt-6 border-t border-gray-200">
                    <button type="button" onclick="closeModal()"
                        class="py-3 px-6 rounded-xl bg-gray-200 text-gray-700 font-semibold hover:bg-gray-300 transition duration-300">
                        Batal
                    </button>
                    <button type="submit" id="submitButton"
                        class="btn-primary py-3 px-6 rounded-xl text-white font-semibold shadow-lg">
                        <i class="fas fa-save mr-2"></i>Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Fungsi untuk export PDF
        function exportPdf() {
            // Ambil parameter dari URL saat ini
            const urlParams = new URLSearchParams(window.location.search);
            
            // Buat URL untuk export_pdf.php dengan parameter yang sama
            let pdfUrl = 'export_pdf.php?';
            
            // Tambahkan semua parameter yang ada
            for (const [key, value] of urlParams.entries()) {
                pdfUrl += key + '=' + encodeURIComponent(value) + '&';
            }
            
            // Hapus & terakhir
            pdfUrl = pdfUrl.slice(0, -1);
            
            // Redirect ke export PDF
            window.location.href = pdfUrl;
        }

        // Fungsi untuk mencetak laporan dengan membuka window baru
        function cetakLaporan() {
            // Ambil parameter dari URL saat ini
            const urlParams = new URLSearchParams(window.location.search);
            
            // Buat URL untuk cetak_laporan.php dengan parameter yang sama
            let printUrl = 'cetak_laporan.php?';
            
            // Tambahkan semua parameter yang ada
            for (const [key, value] of urlParams.entries()) {
                printUrl += key + '=' + encodeURIComponent(value) + '&';
            }
            
            // Hapus & terakhir
            printUrl = printUrl.slice(0, -1);
            
            // Buka window baru untuk mencetak
            const printWindow = window.open(printUrl, '_blank', 'width=1024,height=768,scrollbars=yes,resizable=yes');
            
            // Focus ke window baru
            if (printWindow) {
                printWindow.focus();
            }
        }

        // Tab functionality
        function showTab(tabName) {
            document.getElementById('form-harian').classList.add('hidden');
            document.getElementById('form-rentang').classList.add('hidden');

            document.getElementById('tab-harian').className = 'px-6 py-3 font-medium text-gray-600 hover:text-blue-600 hover:bg-gray-50 rounded-t-lg transition duration-300';
            document.getElementById('tab-rentang').className = 'px-6 py-3 font-medium text-gray-600 hover:text-blue-600 hover:bg-gray-50 rounded-t-lg transition duration-300';

            if (tabName === 'harian') {
                document.getElementById('form-harian').classList.remove('hidden');
                document.getElementById('tab-harian').className = 'px-6 py-3 font-medium text-blue-600 border-b-2 border-blue-600 bg-blue-50 rounded-t-lg transition duration-300';
            } else {
                document.getElementById('form-rentang').classList.remove('hidden');
                document.getElementById('tab-rentang').className = 'px-6 py-3 font-medium text-blue-600 border-b-2 border-blue-600 bg-blue-50 rounded-t-lg transition duration-300';
            }
        }

        // Statistics panel toggle
        function showStatistics() {
            const panel = document.getElementById('statistics-panel');
            if (panel) {
                panel.style.display = panel.style.display === 'none' ? 'grid' : 'none';
            }
        }

        // Modal functions
        function openAddModal() {
            document.getElementById('modalTitle').textContent = 'Tambah Data Absensi';
            document.getElementById('actionInput').value = 'add';
            document.getElementById('idInput').value = '';
            document.getElementById('submitButton').innerHTML = '<i class="fas fa-plus mr-2"></i>Tambah Data';
            document.getElementById('crudForm').reset();
            document.getElementById('modalTanggal').value = new Date().toISOString().split('T')[0];
            document.getElementById('crudModal').classList.add('active');
        }

        function openEditModal(id) {
            // Fetch data for editing via AJAX or pass data from PHP
            document.getElementById('modalTitle').textContent = 'Edit Data Absensi';
            document.getElementById('actionInput').value = 'update';
            document.getElementById('idInput').value = id;
            document.getElementById('submitButton').innerHTML = '<i class="fas fa-save mr-2"></i>Update Data';

            // You can implement AJAX here to fetch the data
            // For now, redirect to the same page with edit parameter
            window.location.href = '?edit=' + id;
        }

        function closeModal() {
            document.getElementById('crudModal').classList.remove('active');
        }

        // Populate edit modal if edit data exists
        <?php if ($edit_data): ?>
            document.addEventListener('DOMContentLoaded', function () {
                document.getElementById('modalTitle').textContent = 'Edit Data Absensi';
                document.getElementById('actionInput').value = 'update';
                document.getElementById('idInput').value = '<?php echo $edit_data['id']; ?>';
                document.getElementById('submitButton').innerHTML = '<i class="fas fa-save mr-2"></i>Update Data';
                document.getElementById('modalSiswa').value = '<?php echo $edit_data['id_siswa']; ?>';
                document.getElementById('modalTanggal').value = '<?php echo date('Y-m-d', strtotime($edit_data['waktu_masuk'])); ?>';
                document.getElementById('modalWaktuMasuk').value = '<?php echo $edit_data['waktu_masuk'] ? date('H:i', strtotime($edit_data['waktu_masuk'])) : ''; ?>';
                document.getElementById('modalWaktuKeluar').value = '<?php echo $edit_data['waktu_keluar'] ? date('H:i', strtotime($edit_data['waktu_keluar'])) : ''; ?>';
                document.getElementById('crudModal').classList.add('active');
            });
        <?php endif; ?>

        // Delete confirmation
        function confirmDelete(id) {
            if (confirm('Apakah Anda yakin ingin menghapus data absensi ini?')) {
                window.location.href = '?action=delete&id=' + id;
            }
        }

        // Chart for attendance statistics
        <?php if ($laporan_type == 'harian'): ?>
            document.addEventListener('DOMContentLoaded', function () {
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
                            borderWidth: 3,
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
                                        size: 14,
                                        family: 'Inter'
                                    }
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function (context) {
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

        // Close modal when clicking outside
        document.getElementById('crudModal').addEventListener('click', function (e) {
            if (e.target === this) {
                closeModal();
            }
        });

        // Form validation
        document.getElementById('crudForm').addEventListener('submit', function (e) {
            const siswa = document.getElementById('modalSiswa').value;
            const tanggal = document.getElementById('modalTanggal').value;

            if (!siswa || !tanggal) {
                e.preventDefault();
                alert('Mohon lengkapi field Siswa dan Tanggal!');
                return false;
            }
        });

        // Auto-refresh untuk data real-time (optional)
        // setInterval(() => location.reload(), 60000); // Refresh setiap 1 menit
    </script>
</body>

</html>
<?php
mysqli_stmt_close($stmt);
mysqli_close($koneksi);
?>
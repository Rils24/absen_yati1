<?php
// proses_absen.php (PHPMailer Version)
include 'koneksi.php'; // Includes database connection and timezone setting

// Import PHPMailer classes (install via composer: composer require phpmailer/phpmailer)
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php'; // Path ke autoloader composer

// --- KONFIGURASI EMAIL ---
$smtp_host = 'smtp.gmail.com'; // Ganti dengan SMTP server Anda
$smtp_port = 587;
$smtp_username = 'fairyrat4@gmail.com'; // Ganti dengan email pengirim
$smtp_password = 'pocvgkrdsjjjmgvy'; // Ganti dengan app password Gmail
$from_email = 'gearfour020@gmail.com'; // Email pengirim
$from_name = 'Pondok Pesantren Yati'; // Nama pengirim
// --------------------------

/**
 * Fungsi untuk mengirim email notifikasi menggunakan PHPMailer.
 * @param string $to_email Email tujuan
 * @param string $subject Subjek email
 * @param string $message Isi pesan email
 */
function kirimEmailNotifikasi($to_email, $subject, $message)
{
    global $smtp_host, $smtp_port, $smtp_username, $smtp_password, $from_email, $from_name;

    // Pastikan email tujuan tidak kosong
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

        // Set charset
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
                .terlambat { background-color: #fee2e2; border-left: 5px solid #ef4444; color: #991b1b; }
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

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Gagal mengirim email ke $to_email: {$mail->ErrorInfo}");
        return false;
    }
}

// Ambil pengaturan absensi dari database
$sql_pengaturan = "SELECT * FROM pengaturan_absensi WHERE id = 1";
$result_pengaturan = mysqli_query($koneksi, $sql_pengaturan);
$pengaturan = mysqli_fetch_assoc($result_pengaturan);

$current_time = date('H:i:s');
$today = date('Y-m-d');

// Siapkan array untuk respons JSON
$response = array('status' => 'error', 'message' => 'UID tidak diterima.');

if (isset($_POST['uid'])) {
    $uid = mysqli_real_escape_string($koneksi, $_POST['uid']);

    $sql_siswa = "SELECT id, nama_lengkap, email_ortu FROM siswa WHERE uid_rfid = ?";
    $stmt_siswa = mysqli_prepare($koneksi, $sql_siswa);
    mysqli_stmt_bind_param($stmt_siswa, "s", $uid);
    mysqli_stmt_execute($stmt_siswa);
    $result_siswa = mysqli_stmt_get_result($stmt_siswa);

    if (mysqli_num_rows($result_siswa) > 0) {
        $data_siswa = mysqli_fetch_assoc($result_siswa);
        $id_siswa = $data_siswa['id'];
        $nama_siswa = $data_siswa['nama_lengkap'];
        $email_ortu = $data_siswa['email_ortu'];

        // Cek apakah siswa sudah absensi masuk hari ini
        $sql_absensi = "SELECT id, waktu_masuk, waktu_keluar FROM absensi WHERE id_siswa = ? AND DATE(waktu_masuk) = ?";
        $stmt_absensi = mysqli_prepare($koneksi, $sql_absensi);
        mysqli_stmt_bind_param($stmt_absensi, "is", $id_siswa, $today);
        mysqli_stmt_execute($stmt_absensi);
        $result_absensi = mysqli_stmt_get_result($stmt_absensi);

        if (mysqli_num_rows($result_absensi) > 0) {
            $data_absensi = mysqli_fetch_assoc($result_absensi);

            // Jika sudah ada waktu masuk, cek untuk waktu keluar
            if ($data_absensi['waktu_masuk'] && !$data_absensi['waktu_keluar']) {
                // LOGIKA BARU: Cek jadwal untuk tap keluar
                if ($current_time < $pengaturan['waktu_keluar_mulai']) {
                    $response['status'] = 'info';
                    $response['message'] = 'Halo ' . $nama_siswa . ', belum waktunya untuk absen keluar. Waktu keluar dimulai pukul ' . date('H:i', strtotime($pengaturan['waktu_keluar_mulai'])) . '.';
                } elseif ($current_time > $pengaturan['waktu_keluar_akhir']) {
                    $response['status'] = 'info';
                    $response['message'] = 'Halo ' . $nama_siswa . ', waktu absen keluar sudah berakhir. Absensi Anda tidak dapat dicatat.';
                } else {
                    // Update waktu keluar
                    $sql_update = "UPDATE absensi SET waktu_keluar = NOW() WHERE id = ?";
                    $stmt_update = mysqli_prepare($koneksi, $sql_update);
                    mysqli_stmt_bind_param($stmt_update, "i", $data_absensi['id']);

                    if (mysqli_stmt_execute($stmt_update)) {
                        $response['status'] = 'success';
                        $response['message'] = 'Absensi keluar berhasil! Sampai jumpa, ' . $nama_siswa . '.';

                        // Notifikasi Email untuk keluar
                        $subject = "🏠 Notifikasi Kepulangan - $nama_siswa";
                        $pesan_email = "<div class='keluar'>
                            <h3>🏠 Pemberitahuan Kepulangan</h3>
                            <p>Assalamualaikum Warahmatullahi Wabarakatuh,</p>
                            <p>Bapak/Ibu yang terhormat,</p>
                            <p>Kami dengan hormat memberitahukan bahwa putra/putri Bapak/Ibu:</p>
                            <p><strong>📝 Nama: $nama_siswa</strong></p>
                            <p>Telah <strong>PULANG</strong> dari Pondok Pesantren Yati pada:</p>
                            <p><strong>🕐 Pukul: " . date('H:i') . " WIB</strong></p>";

                        // Status Pulang Cepat
                        if ($current_time < $pengaturan['waktu_keluar_mulai']) {
                            $pesan_email = "<div class='terlambat'>
                                <h3>⚠️ Pemberitahuan Kepulangan (Lebih Awal)</h3>
                                <p>Assalamualaikum Warahmatullahi Wabarakatuh,</p>
                                <p>Bapak/Ibu yang terhormat,</p>
                                <p>Kami dengan hormat memberitahukan bahwa putra/putri Bapak/Ibu:</p>
                                <p><strong>📝 Nama: $nama_siswa</strong></p>
                                <p>Telah <strong>PULANG LEBIH AWAL</strong> dari Pondok Pesantren Yati pada:</p>
                                <p><strong>🕐 Pukul: " . date('H:i') . " WIB</strong></p>
                                <p><em>⚠️ Catatan: Waktu pulang normal dimulai pukul " . date('H:i', strtotime($pengaturan['waktu_keluar_mulai'])) . " WIB</em></p>";
                            $response['status'] = 'warning';
                        }

                        $pesan_email .= "<p>Terima kasih atas perhatian Bapak/Ibu.</p>
                            <p>Wassalamualaikum Warahmatullahi Wabarakatuh</p>
                        </div>";

                        kirimEmailNotifikasi($email_ortu, $subject, $pesan_email);

                    } else {
                        $response['message'] = 'Gagal menyimpan data absensi keluar.';
                    }
                    mysqli_stmt_close($stmt_update);
                }
            } else if ($data_absensi['waktu_masuk'] && $data_absensi['waktu_keluar']) {
                // Sudah absensi masuk dan keluar
                $response['status'] = 'info';
                $response['message'] = 'Halo ' . $nama_siswa . ', Anda sudah melakukan absensi masuk dan keluar hari ini.';
            } else {
                // Kasus seharusnya tidak terjadi jika logika di atas benar, tapi sebagai fallback
                $response['status'] = 'info';
                $response['message'] = 'Halo ' . $nama_siswa . ', Anda sudah melakukan absensi masuk hari ini.';
            }

        } else {
            // LOGIKA BARU: Cek jadwal untuk tap masuk
            if ($current_time < $pengaturan['waktu_masuk_mulai']) {
                $response['status'] = 'info';
                $response['message'] = 'Halo ' . $nama_siswa . ', belum waktunya untuk absen masuk. Waktu masuk dimulai pukul ' . date('H:i', strtotime($pengaturan['waktu_masuk_mulai'])) . '.';
            } elseif ($current_time > $pengaturan['waktu_masuk_akhir']) {
                // Jika sudah lewat waktu akhir masuk, tetap boleh absen tapi statusnya terlambat
                $sql_insert = "INSERT INTO absensi (id_siswa, waktu_masuk) VALUES (?, NOW())";
                $stmt_insert = mysqli_prepare($koneksi, $sql_insert);
                mysqli_stmt_bind_param($stmt_insert, "i", $id_siswa);

                if (mysqli_stmt_execute($stmt_insert)) {
                    $response['status'] = 'warning';
                    $response['message'] = 'Absensi masuk berhasil! Selamat datang, ' . $nama_siswa . '. (Terlambat)';

                    // Notifikasi Email untuk masuk terlambat
                    $subject = "⚠️ Notifikasi Kedatangan - $nama_siswa (Terlambat)";
                    $pesan_email = "<div class='terlambat'>
                        <h3>⚠️ Pemberitahuan Kedatangan (Terlambat)</h3>
                        <p>Assalamualaikum Warahmatullahi Wabarakatuh,</p>
                        <p>Bapak/Ibu yang terhormat,</p>
                        <p>Kami dengan hormat memberitahukan bahwa putra/putri Bapak/Ibu:</p>
                        <p><strong>📝 Nama: $nama_siswa</strong></p>
                        <p>Telah <strong>TIBA TERLAMBAT</strong> di Pondok Pesantren Yati pada:</p>
                        <p><strong>🕐 Pukul: " . date('H:i') . " WIB</strong></p>
                        <p><em>⚠️ Catatan: Batas waktu masuk adalah pukul " . date('H:i', strtotime($pengaturan['waktu_masuk_akhir'])) . " WIB</em></p>
                        <p>Mohon untuk mengingatkan putra/putri agar lebih disiplin waktu.</p>
                        <p>Terima kasih atas perhatian Bapak/Ibu.</p>
                        <p>Wassalamualaikum Warahmatullahi Wabarakatuh</p>
                    </div>";
                    kirimEmailNotifikasi($email_ortu, $subject, $pesan_email);
                } else {
                    $response['message'] = 'Gagal menyimpan data absensi masuk.';
                }
                mysqli_stmt_close($stmt_insert);

            } else {
                // Belum absensi masuk hari ini, lakukan absensi masuk (tepat waktu)
                $sql_insert = "INSERT INTO absensi (id_siswa, waktu_masuk) VALUES (?, NOW())";
                $stmt_insert = mysqli_prepare($koneksi, $sql_insert);
                mysqli_stmt_bind_param($stmt_insert, "i", $id_siswa);

                if (mysqli_stmt_execute($stmt_insert)) {
                    $response['status'] = 'success';
                    $response['message'] = 'Absensi masuk berhasil! Selamat datang, ' . $nama_siswa . '.';

                    // Notifikasi Email untuk masuk tepat waktu
                    $subject = "✅ Notifikasi Kedatangan - $nama_siswa";
                    $pesan_email = "<div class='masuk'>
                        <h3>✅ Pemberitahuan Kedatangan</h3>
                        <p>Assalamualaikum Warahmatullahi Wabarakatuh,</p>
                        <p>Bapak/Ibu yang terhormat,</p>
                        <p>Kami dengan hormat memberitahukan bahwa putra/putri Bapak/Ibu:</p>
                        <p><strong>📝 Nama: $nama_siswa</strong></p>
                        <p>Telah <strong>TIBA</strong> di Pondok Pesantren Yati pada:</p>
                        <p><strong>🕐 Pukul: " . date('H:i') . " WIB</strong></p>
                        <p>✅ <em>Alhamdulillah, putra/putri Bapak/Ibu tiba tepat waktu.</em></p>
                        <p>Terima kasih atas perhatian Bapak/Ibu.</p>
                        <p>Wassalamualaikum Warahmatullahi Wabarakatuh</p>
                    </div>";
                    kirimEmailNotifikasi($email_ortu, $subject, $pesan_email);

                } else {
                    $response['message'] = 'Gagal menyimpan data absensi masuk.';
                }
                mysqli_stmt_close($stmt_insert);
            }
        }
        mysqli_stmt_close($stmt_absensi);
    } else {
        $response['message'] = 'Kartu RFID tidak terdaftar.';
    }
    mysqli_stmt_close($stmt_siswa);
}

header('Content-Type: application/json');
echo json_encode($response);

mysqli_close($koneksi);
?>
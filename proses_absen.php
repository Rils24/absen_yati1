<?php
// proses_absen.php
include 'koneksi.php'; // Includes database connection and timezone setting

// --- KONFIGURASI TELEGRAM ---
// Ganti dengan Token Bot Anda yang didapat dari BotFather
$bot_token = 'GANTI_DENGAN_TOKEN_BOT_ANDA'; 
// --------------------------

/**
 * Fungsi untuk mengirim pesan ke API Telegram.
 * @param string $chat_id ID chat tujuan
 * @param string $message Pesan yang akan dikirim
 * @param string $token Token API bot
 */
function kirimPesanTelegram($chat_id, $message, $token) {
    // Pastikan chat_id tidak kosong
    if (empty($chat_id)) {
        return; // Keluar dari fungsi jika tidak ada chat id
    }
    
    // URL encode pesan agar aman dikirim via URL
    $encoded_message = urlencode($message);
    $url = "https://api.telegram.org/bot{$token}/sendMessage?chat_id={$chat_id}&text={$encoded_message}";

    // Gunakan cURL untuk mengirim request
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    // Tambahkan opsi timeout untuk mencegah skrip hang jika API lambat
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5); 
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $result = curl_exec($ch);
    // Anda bisa menambahkan logging di sini jika perlu untuk debug
    // error_log("Telegram Response: " . $result);
    curl_close($ch);
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

    $sql_siswa = "SELECT id, nama_lengkap, telegram_chat_id FROM siswa WHERE uid_rfid = ?";
    $stmt_siswa = mysqli_prepare($koneksi, $sql_siswa);
    mysqli_stmt_bind_param($stmt_siswa, "s", $uid);
    mysqli_stmt_execute($stmt_siswa);
    $result_siswa = mysqli_stmt_get_result($stmt_siswa);

    if (mysqli_num_rows($result_siswa) > 0) {
        $data_siswa = mysqli_fetch_assoc($result_siswa);
        $id_siswa = $data_siswa['id'];
        $nama_siswa = $data_siswa['nama_lengkap'];
        $chat_id_ortu = $data_siswa['telegram_chat_id'];

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

                        // Notifikasi Telegram untuk keluar
                        $pesan_telegram = "Assalamualaikum bapak/ibu dari santri {$nama_siswa}, izin memberitahukan bahwa {$nama_siswa} telah pulang dari pondok pesantren.";
                        
                        // Status Pulang Cepat
                        if ($current_time < $pengaturan['waktu_keluar_mulai']) {
                            $pesan_telegram .= " (Keluar Lebih Awal)";
                            $response['status'] = 'warning'; // Set status warning for early exit
                        }
                        kirimPesanTelegram($chat_id_ortu, $pesan_telegram, $bot_token);

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
                    $response['status'] = 'warning'; // Status warning for late entry
                    $response['message'] = 'Absensi masuk berhasil! Selamat datang, ' . $nama_siswa . '. (Terlambat)';

                    // Notifikasi Telegram untuk masuk
                    $pesan_telegram = "Assalamualaikum bapak/ibu dari santri {$nama_siswa}, izin memberitahukan bahwa {$nama_siswa} telah tiba di pondok pesantren. (Terlambat)";
                    kirimPesanTelegram($chat_id_ortu, $pesan_telegram, $bot_token);
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

                    // Notifikasi Telegram untuk masuk
                    $pesan_telegram = "Assalamualaikum bapak/ibu dari santri {$nama_siswa}, izin memberitahukan bahwa {$nama_siswa} telah tiba di pondok pesantren.";
                    kirimPesanTelegram($chat_id_ortu, $pesan_telegram, $bot_token);

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

<?php
// koneksi.php

// Konfigurasi Database
$db_host = 'localhost';    // Host database, biasanya 'localhost'
$db_user = 'root';         // User database, default 'root' di XAMPP
$db_pass = '';             // Password database, default kosong di XAMPP
$db_name = 'absen_yati1'; // Nama database yang sudah dibuat

// Membuat koneksi ke database
$koneksi = mysqli_connect($db_host, $db_user, $db_pass, $db_name);

// Cek koneksi
if (!$koneksi) {
    // Jika koneksi gagal, hentikan skrip dan tampilkan pesan error
    die("Koneksi ke database gagal: " . mysqli_connect_error());
}

// Set timezone ke Asia/Jakarta
date_default_timezone_set('Asia/Jakarta');

?>
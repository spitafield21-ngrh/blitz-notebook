<?php
// ==========================================
// FILE: config.php
// FUNGSI: Jembatan Koneksi Database (SMART CONFIG AUTO-DETECT)
// ==========================================

// Memulai session untuk keperluan Login nanti
session_start();

// ------------------------------------------
// 🧠 SMART CONFIGURATION (AUTO-DETECT ENV)
// ------------------------------------------
$is_localhost = ($_SERVER['HTTP_HOST'] == 'localhost' || $_SERVER['HTTP_HOST'] == '127.0.0.1');

if ($is_localhost) {
    // 💻 KONFIGURASI LAPTOP (XAMPP LOKAL)
    $host     = "localhost";
    $username = "root"; // Default XAMPP username
    $password = "";     // Default XAMPP password (kosong)
    $database = "db_toko_komputer";
} else {
    // 🌍 KONFIGURASI SERVER INTERNET (HOSTING / CPANEL)
    // Nanti Coach tinggal ubah 3 data ini sesuai yang diberikan oleh provider Hosting
    $host     = "sql306.infinityfree.com";          // Di hosting biasanya tetap 'localhost'
    $username = "if0_42653647";                     // Ganti dengan Username Database dari Hosting
    $password = "DOaOt7OdDY";                       // Ganti dengan Password Database dari Hosting
    $database = "if0_42653647_db_toko_komputer";   // Ganti dengan Nama Database dari Hosting
}

// Membuat Koneksi Menggunakan MySQLi (Object Oriented)
$koneksi = new mysqli($host, $username, $password, $database);

// Cek apakah koneksi berhasil
if ($koneksi->connect_error) {
    // Jika gagal, hentikan program dan tampilkan pesan error
    die("Koneksi Database Gagal: " . $koneksi->connect_error);
}

// Set zona waktu ke Waktu Indonesia Barat (WIB) 
// Agar pencatatan tanggal transaksi kasir sesuai dengan jam lokal
date_default_timezone_set('Asia/Jakarta');

// (Opsional) Fungsi dasar untuk mencegah karakter aneh masuk ke database (Anti SQL Injection sederhana)
function bersihkan_input($koneksi, $data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $koneksi->real_escape_string($data);
}
?>
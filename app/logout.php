<?php
// ==========================================
// FILE: logout.php
// FUNGSI: Menghapus session dan keluar aplikasi
// ==========================================
session_start();
session_unset();    // Hapus semua variabel session
session_destroy();  // Hancurkan session

// Arahkan kembali ke halaman login
header("Location: login.php");
exit;
?>
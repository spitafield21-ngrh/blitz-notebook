<?php
// Mengizinkan Android atau browser mengakses file ini (CORS Header)
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// Hubungkan ke database MySQL XAMPP Anda
$host = "localhost";
$user = "root";
$pass = "";
$db   = "db_toko_komputer"; // Sesuai nama database di phpMyAdmin Anda

$conn = new mysqli($host, $user, $pass, $db);

// Cek koneksi
if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "Gagal koneksi database"]);
    exit();
}

// Ambil data produk dari tabel 'barang'
$sql = "SELECT * FROM barang"; // Menggunakan nama tabel 'barang' sesuai phpMyAdmin Anda
$result = $conn->query($sql);

$data_produk = array();

if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $data_produk[] = $row;
    }
}

// Ubah array PHP menjadi format data JSON
echo json_encode([
    "status" => "success",
    "data" => $data_produk
], JSON_PRETTY_PRINT);

$conn->close();
?>
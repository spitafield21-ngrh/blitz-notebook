<?php
// ==========================================
// FILE: export_laporan_excel.php
// FUNGSI: Mengubah Laporan Laba/Rugi menjadi file .xls
// ==========================================
require_once 'config.php';

// Pastikan hanya Owner
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'owner') {
    die("Akses Ditolak! Khusus Owner.");
}

// Tangkap rentang tanggal dari URL
$tgl_mulai = isset($_GET['tgl_mulai']) ? $_GET['tgl_mulai'] : date('Y-m-01');
$tgl_selesai = isset($_GET['tgl_selesai']) ? $_GET['tgl_selesai'] : date('Y-m-t');

$tgl_mulai_aman = $koneksi->real_escape_string($tgl_mulai);
$tgl_selesai_aman = $koneksi->real_escape_string($tgl_selesai);

// ==========================================
// LOGIKA PERHITUNGAN KEUANGAN (Sama persis dengan laporan.php)
// ==========================================

// 1. Total Penjualan
$q_penjualan = $koneksi->query("SELECT SUM(total_harga) as total_jual FROM penjualan WHERE DATE(tanggal_transaksi) BETWEEN '$tgl_mulai_aman' AND '$tgl_selesai_aman'");
$total_penjualan = $q_penjualan->fetch_assoc()['total_jual'] ?? 0;

// 2. Total HPP
$q_hpp = $koneksi->query("SELECT SUM(d.qty * b.harga_beli) as total_hpp FROM penjualan_detail d JOIN penjualan p ON d.id_penjualan = p.id JOIN barang b ON d.id_barang = b.id WHERE DATE(p.tanggal_transaksi) BETWEEN '$tgl_mulai_aman' AND '$tgl_selesai_aman'");
$total_hpp = $q_hpp->fetch_assoc()['total_hpp'] ?? 0;

// 3. Total Pengeluaran
$q_pengeluaran = $koneksi->query("SELECT SUM(nominal) as total_keluar FROM pengeluaran WHERE tanggal BETWEEN '$tgl_mulai_aman' AND '$tgl_selesai_aman'");
$total_pengeluaran = $q_pengeluaran->fetch_assoc()['total_keluar'] ?? 0;

// 4. Kalkulasi Laba
$laba_kotor = $total_penjualan - $total_hpp;
$laba_bersih = $laba_kotor - $total_pengeluaran;
$status_laba = $laba_bersih >= 0 ? 'PROFIT / UNTUNG' : 'RUGI';

// ==========================================
// HEADER UNTUK FORCE DOWNLOAD KE EXCEL
// ==========================================
$filename = "Laporan_Keuangan_Blitz_" . $tgl_mulai_aman . "_sd_" . $tgl_selesai_aman . ".xls";
header("Content-type: application/vnd-ms-excel");
header("Content-Disposition: attachment; filename=$filename");
?>

<center>
    <h2>LAPORAN LABA / RUGI TOKO KOMPUTER</h2>
    <p>Periode: <?= date('d M Y', strtotime($tgl_mulai_aman)) ?> s/d <?= date('d M Y', strtotime($tgl_selesai_aman)) ?></p>
</center>

<table border="1" cellpadding="5" cellspacing="0" width="100%">
    <thead>
        <tr style="background-color: #3b82f6; color: white;">
            <th colspan="2" style="text-align: center; font-size: 16px; padding: 10px;">RINGKASAN KEUANGAN</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td width="60%"><b>Total Pendapatan (Penjualan Kotor)</b></td>
            <td width="40%" align="right"><?= $total_penjualan ?></td>
        </tr>
        <tr>
            <td>Total Modal Barang Terjual (HPP)</td>
            <td align="right">- <?= $total_hpp ?></td>
        </tr>
        <tr style="background-color: #f3f4f6;">
            <td><b>LABA KOTOR</b></td>
            <td align="right"><b><?= $laba_kotor ?></b></td>
        </tr>
        <tr>
            <td>Total Pengeluaran Operasional</td>
            <td align="right">- <?= $total_pengeluaran ?></td>
        </tr>
        <tr style="background-color: <?= $laba_bersih >= 0 ? '#d1fae5' : '#fee2e2' ?>;">
            <td style="font-size: 14px;"><b>LABA BERSIH (<?= $status_laba ?>)</b></td>
            <td align="right" style="font-size: 14px;"><b><?= $laba_bersih ?></b></td>
        </tr>
    </tbody>
</table>
<br>
<p><i>Laporan ini di-generate secara otomatis dari sistem ERP Blitz Notebook.</i></p>
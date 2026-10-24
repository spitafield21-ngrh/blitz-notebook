<?php
// ==========================================
// FILE: cetak_struk.php
// FUNGSI: Mencetak Struk Kasir & Auto-Kirim E-Struk WA (+ INFO POIN LOYALITAS)
// ==========================================
require_once 'config.php';

// Pastikan nomor transaksi tersedia di URL
if (!isset($_GET['no'])) {
    die("Akses Ditolak! Nomor Transaksi tidak ditemukan.");
}

$no_trx = bersihkan_input($koneksi, $_GET['no']);
$uang_bayar = isset($_GET['bayar']) ? (int)$_GET['bayar'] : 0;

// 1. Ambil Data Pengaturan Toko
$query_toko = $koneksi->query("SELECT * FROM pengaturan LIMIT 1");
$toko = $query_toko->fetch_assoc();

// 2. Ambil Data Header Transaksi
$query_jual = $koneksi->query("SELECT p.*, u.nama_lengkap 
                               FROM penjualan p 
                               JOIN users u ON p.id_user = u.id 
                               WHERE p.no_transaksi = '$no_trx'");

if ($query_jual->num_rows == 0) {
    die("Data Transaksi tidak ditemukan di database!");
}
$penjualan = $query_jual->fetch_assoc();

// Hitung Subtotal Asli (Sebelum Diskon)
$subtotal_awal = $penjualan['total_harga'] + ($penjualan['diskon'] ?? 0);

// LOGIKA METODE PEMBAYARAN & KEMBALIAN
$metode_bayar = $penjualan['metode_pembayaran'] ?? 'Cash';
$is_cash      = (strtolower($metode_bayar) == 'cash');

// Jika non-cash dan uang_bayar tidak dikirim dari URL, samakan dengan total harga
if (!$is_cash || $uang_bayar <= 0) {
    $uang_bayar = $penjualan['total_harga'];
}
$kembalian = $is_cash ? ($uang_bayar - $penjualan['total_harga']) : 0;

// 3. Ambil Data Detail Barang & Spesifikasi
$detail_items = [];
$query_detail = $koneksi->query("SELECT d.*, b.nama_barang, b.spesifikasi 
                                 FROM penjualan_detail d 
                                 JOIN barang b ON d.id_barang = b.id 
                                 WHERE d.id_penjualan = '{$penjualan['id']}'");
while ($row = $query_detail->fetch_assoc()) {
    $detail_items[] = $row;
}

// ==========================================
// 🚀 LOGIKA KIRIM E-STRUK VIA WHATSAPP (FONNTE)
// ==========================================
$pesan_notif = "";
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['kirim_wa_struk'])) {
    $nomor_wa = bersihkan_input($koneksi, $_POST['nomor_wa']);
    
    // ⚠️ TOKEN FONNTE COACH
    $token_fonnte = "CKn8puLkshXnsUYwB4tF"; 
    
    // Bersihkan tag HTML agar tidak error di WhatsApp
    $alamat_bersih_wa   = strip_tags($toko['alamat'] ?? '');
    $pesan_struk_bersih = strip_tags($toko['pesan_struk'] ?? 'Terima Kasih Telah Berbelanja!');

    // Rajut Teks E-Struk WA
    $teks_wa = "✨ *{$toko['nama_toko']}*\n";
    if (!empty($alamat_bersih_wa)) {
        $teks_wa .= "{$alamat_bersih_wa}\n";
    }
    $teks_wa .= "========================\n";
    $teks_wa .= "🧾 *E-STRUK PEMBELIAN*\n";
    $teks_wa .= "No : {$no_trx}\n";
    $teks_wa .= "Tgl: " . date('d M Y, H:i', strtotime($penjualan['tanggal_transaksi'])) . "\n";
    if (!empty($penjualan['nama_customer'])) {
        $teks_wa .= "Plg: {$penjualan['nama_customer']}\n";
    }
    $teks_wa .= "------------------------\n";
    
    foreach ($detail_items as $d) {
        $teks_wa .= "*{$d['nama_barang']}*\n";
        
        if (!empty($d['spesifikasi'])) {
            $spek_bersih = strip_tags($d['spesifikasi']);
            $spek_bersih = str_replace(array("\r", "\n"), ", ", $spek_bersih);
            $teks_wa .= "_Spek: {$spek_bersih}_\n";
        }
        
        $teks_wa .= "{$d['qty']} x " . number_format($d['harga_jual'], 0, ',', '.') . " = " . number_format($d['subtotal'], 0, ',', '.') . "\n";
    }
    
    $teks_wa .= "------------------------\n";
    
    // TAMPILKAN DISKON JIKA ADA
    if ($penjualan['diskon'] > 0) {
        $teks_wa .= "Subtotal : Rp " . number_format($subtotal_awal, 0, ',', '.') . "\n";
        $teks_wa .= "Potongan : *-Rp " . number_format($penjualan['diskon'], 0, ',', '.') . "* (Pakai {$penjualan['poin_digunakan']} Poin)\n";
    }

    $teks_wa .= "Total   : *Rp " . number_format($penjualan['total_harga'], 0, ',', '.') . "*\n";
    $teks_wa .= "Metode  : *{$metode_bayar}*\n";
    $teks_wa .= "Bayar   : Rp " . number_format($uang_bayar, 0, ',', '.') . "\n";
    
    // HANYA TAMPILKAN KEMBALIAN JIKA PEMBAYARAN CASH
    if ($is_cash) {
        $teks_wa .= "Kembali : Rp " . number_format($kembalian, 0, ',', '.') . "\n";
    }
    
    // BERI KEJUTAN JIKA DAPAT POIN BARU
    if (isset($penjualan['poin_didapat']) && $penjualan['poin_didapat'] > 0) {
        $teks_wa .= "\n🎁 _Selamat! Anda mendapatkan +{$penjualan['poin_didapat']} Poin Loyalty dari transaksi ini!_\n";
    }

    $teks_wa .= "========================\n";
    $teks_wa .= "_{$pesan_struk_bersih}_";

    // Eksekusi cURL Fonnte
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://api.fonnte.com/send',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => array(
            'target' => $nomor_wa,
            'message' => $teks_wa
        ),
        CURLOPT_HTTPHEADER => array("Authorization: $token_fonnte"),
    ));
    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);

    if ($err) {
        $pesan_notif = "<div style='color:red; font-size:12px; margin-top:10px; font-weight:bold;'>Gagal kirim WA: $err</div>";
    } else {
        $pesan_notif = "<div style='color:green; font-size:12px; margin-top:10px; font-weight:bold;'>✅ E-Struk berhasil terkirim ke Pelanggan!</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Struk - <?= $no_trx ?></title>
    <style>
        /* Desain Khusus Printer Thermal 80mm */
        @page { margin: 0; }
        body { font-family: 'Courier New', Courier, monospace; font-size: 13px; margin: 0; padding: 10px; color: #000; background: #fff; }
        .struk-container { width: 78mm; margin: 0 auto; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .divider { border-top: 1px dashed #000; margin: 8px 0; }
        table { width: 100%; border-collapse: collapse; }
        td { vertical-align: top; font-size: 13px; padding: 2px 0; }
        
        .btn-print { display: block; width: 100%; padding: 12px; text-align: center; background: #f59e0b; color: #fff; border: none; cursor: pointer; font-weight: bold; margin-top: 20px; font-family: sans-serif; text-decoration: none; border-radius: 5px; }
        .btn-wa { display: block; width: 100%; padding: 12px; text-align: center; background: #22c55e; color: #fff; border: none; cursor: pointer; font-weight: bold; margin-top: 8px; font-family: sans-serif; border-radius: 5px; }
        .btn-back { display: block; width: 100%; padding: 12px; text-align: center; background: #3f3f46; color: #fff; border: none; cursor: pointer; font-weight: bold; margin-top: 8px; font-family: sans-serif; text-decoration: none; border-radius: 5px; }
        .input-wa { width: 100%; padding: 10px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px; margin-top: 8px; font-family: sans-serif; text-align: center; }
        
        @media print { .no-print { display: none !important; } }
    </style>
</head>
<body onload="window.print()">
    <div class="struk-container">
        <div class="text-center">
            <h3 style="margin: 0; font-size: 18px;"><?= $toko['nama_toko'] ?? 'Toko Komputer On Fire' ?></h3>
            <p style="margin: 5px 0;">
                <?= $toko['alamat'] ?? 'Alamat Belum Diatur' ?><br>
                Telp: <?= $toko['telepon'] ?? '-' ?>
            </p>
        </div>
        
        <div class="divider"></div>
        
        <table>
            <tr><td style="width: 40%;">No. Nota</td><td>: <?= $no_trx ?></td></tr>
            <tr><td>Tanggal</td><td>: <?= date('d-m-Y H:i', strtotime($penjualan['tanggal_transaksi'])) ?></td></tr>
            <tr><td>Kasir</td><td>: <?= $penjualan['nama_lengkap'] ?></td></tr>
            <?php if(!empty($penjualan['nama_customer'])): ?>
            <tr><td>Pelanggan</td><td>: <?= $penjualan['nama_customer'] ?></td></tr>
            <?php endif; ?>
            <tr><td>Metode</td><td>: <b><?= strtoupper($metode_bayar) ?></b></td></tr>
        </table>
        
        <div class="divider"></div>
        
        <table>
            <?php foreach($detail_items as $d): ?>
            <tr><td colspan="2"><b><?= $d['nama_barang'] ?></b></td></tr>
            <tr>
                <td style="padding-bottom: 5px;"><?= $d['qty'] ?> x <?= number_format($d['harga_jual'],0,',','.') ?></td>
                <td class="text-right" style="padding-bottom: 5px;">Rp <?= number_format($d['subtotal'],0,',','.') ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        
        <div class="divider"></div>
        
        <table>
            <?php if(isset($penjualan['diskon']) && $penjualan['diskon'] > 0): ?>
            <tr>
                <td><b>SUBTOTAL</b></td>
                <td class="text-right">Rp <?= number_format($subtotal_awal,0,',','.') ?></td>
            </tr>
            <tr>
                <td style="font-size: 11px;">DISKON POIN (<?= $penjualan['poin_digunakan'] ?> Poin)</td>
                <td class="text-right"><b>-Rp <?= number_format($penjualan['diskon'],0,',','.') ?></b></td>
            </tr>
            <?php endif; ?>

            <tr>
                <td><b>TOTAL AKHIR</b></td>
                <td class="text-right"><b>Rp <?= number_format($penjualan['total_harga'],0,',','.') ?></b></td>
            </tr>
            <tr>
                <td>BAYAR (<?= strtoupper($metode_bayar) ?>)</td>
                <td class="text-right">Rp <?= number_format($uang_bayar,0,',','.') ?></td> 
            </tr>
            <?php if($is_cash): ?>
            <tr>
                <td>KEMBALIAN</td>
                <td class="text-right">Rp <?= number_format($kembalian,0,',','.') ?></td> 
            </tr>
            <?php endif; ?>
        </table>
        
        <div class="divider"></div>
        
        <?php if(isset($penjualan['poin_didapat']) && $penjualan['poin_didapat'] > 0): ?>
        <div class="text-center" style="margin-top: 10px; border: 1px dashed #000; padding: 5px; border-radius: 4px;">
            <b>🎁 +<?= $penjualan['poin_didapat'] ?> Poin Loyalty!</b>
        </div>
        <?php endif; ?>

        <div class="text-center" style="margin-top: 15px;">
            <p style="margin: 0; font-size: 12px;">
                <?= $toko['pesan_struk'] ?? 'Terima Kasih Telah Berbelanja!' ?>
            </p>
        </div>

        <div class="no-print text-center">
            <?= $pesan_notif ?>
            <button onclick="window.print()" class="btn-print">🖨️ Cetak Struk Fisik</button>
            
            <form method="POST">
                <input type="text" name="nomor_wa" class="input-wa" placeholder="Input WA Pelanggan (08...)" required value="<?= $penjualan['no_telp'] ?? '' ?>">
                <button type="submit" name="kirim_wa_struk" class="btn-wa">📲 Kirim E-Struk WA</button>
            </form>

            <a href="kasir.php" class="btn-back">⬅ Kembali ke Kasir</a>
        </div>
    </div>
</body>
</html>
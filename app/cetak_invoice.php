<?php
// ==========================================
// FILE: cetak_invoice.php
// FUNGSI: Mencetak Invoice Formal A4 & Auto-Kirim E-Invoice via WA (+ INFO POIN)
// ==========================================
require_once 'config.php';

if (!isset($_GET['no'])) { die("Akses Ditolak! Nomor Transaksi tidak ditemukan."); }
$no_trx = bersihkan_input($koneksi, $_GET['no']);

$query_toko = $koneksi->query("SELECT * FROM pengaturan LIMIT 1");
$toko = $query_toko->fetch_assoc();

$query_jual = $koneksi->query("SELECT p.*, u.nama_lengkap FROM penjualan p JOIN users u ON p.id_user = u.id WHERE p.no_transaksi = '$no_trx'");
if ($query_jual->num_rows == 0) { die("Data Transaksi tidak ditemukan di database!"); }
$penjualan = $query_jual->fetch_assoc();

// Hitung Subtotal Asli (Sebelum Diskon)
$subtotal_awal = $penjualan['total_harga'] + ($penjualan['diskon'] ?? 0);

// Ekstrak Data Detail ke Array
$detail_items = [];
$query_detail = $koneksi->query("SELECT d.*, b.nama_barang, b.kode_barang, b.spesifikasi FROM penjualan_detail d JOIN barang b ON d.id_barang = b.id WHERE d.id_penjualan = '{$penjualan['id']}'");
while ($row = $query_detail->fetch_assoc()) {
    $detail_items[] = $row;
}

// Cek Piutang
$piutang = null;
if ($penjualan['metode_pembayaran'] == 'Tempo/Kasbon') {
    $q_piutang = $koneksi->query("SELECT * FROM piutang WHERE id_penjualan = '{$penjualan['id']}'");
    if ($q_piutang && $q_piutang->num_rows > 0) { $piutang = $q_piutang->fetch_assoc(); }
}
$alamat_bersih = str_replace('<', '', $toko['alamat'] ?? 'Alamat Belum Diatur');

// ==========================================
// 🚀 LOGIKA KIRIM E-INVOICE VIA WHATSAPP (FONNTE)
// ==========================================
$pesan_notif = "";
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['kirim_wa_invoice'])) {
    $nomor_wa = bersihkan_input($koneksi, $_POST['nomor_wa']);
    
    // ⚠️ TOKEN FONNTE COACH
    $token_fonnte = "CKn8puLkshXnsUYwB4tF"; 
    
    // Rajut Teks E-Invoice WA
    $teks_wa = "✨ *E-INVOICE PEMBELIAN* ✨\n";
    $teks_wa .= "--------------------------------\n";
    $teks_wa .= "🏢 *{$toko['nama_toko']}*\n";
    $teks_wa .= "🏷️ No Inv : {$no_trx}\n";
    $teks_wa .= "📅 Tgl    : " . date('d M Y, H:i', strtotime($penjualan['tanggal_transaksi'])) . "\n";
    $teks_wa .= "👤 Yth.   : *" . ($penjualan['nama_customer'] != '' ? $penjualan['nama_customer'] : 'Pelanggan Umum') . "*\n";
    $teks_wa .= "--------------------------------\n";
    $teks_wa .= "*RINCIAN PESANAN:*\n\n";
    
    foreach ($detail_items as $d) {
        $sn_teks = ($d['sn_fisik'] != '') ? " (SN: {$d['sn_fisik']})" : "";
        $teks_wa .= "🔹 *{$d['nama_barang']}*$sn_teks\n";
        
        if (!empty($d['spesifikasi'])) {
            $spek_bersih = strip_tags($d['spesifikasi']);
            $spek_bersih = str_replace(array("\r", "\n"), ", ", $spek_bersih); 
            $teks_wa .= "   _Spek: {$spek_bersih}_\n";
        }
        
        $teks_wa .= "   {$d['qty']} Unit x Rp " . number_format($d['harga_jual'], 0, ',', '.') . " = Rp " . number_format($d['subtotal'], 0, ',', '.') . "\n\n";
    }
    
    $teks_wa .= "--------------------------------\n";
    
    // TAMPILKAN DISKON JIKA ADA
    if ($penjualan['diskon'] > 0) {
        $teks_wa .= "Subtotal : Rp " . number_format($subtotal_awal, 0, ',', '.') . "\n";
        $teks_wa .= "Potongan : *-Rp " . number_format($penjualan['diskon'], 0, ',', '.') . "* (Pakai {$penjualan['poin_digunakan']} Poin)\n";
    }

    $teks_wa .= "💳 *GRAND TOTAL : Rp " . number_format($penjualan['total_harga'], 0, ',', '.') . "*\n";
    
    // Tambahan Teks Jika Bon/Piutang
    if ($piutang) {
        $teks_wa .= "\n📌 *STATUS: BELUM LUNAS*\n";
        $teks_wa .= "Telah Dibayar : Rp " . number_format($piutang['jumlah_terbayar'], 0, ',', '.') . "\n";
        $teks_wa .= "Sisa Tagihan  : *Rp " . number_format($piutang['sisa_hutang'], 0, ',', '.') . "*\n";
        $teks_wa .= "Jatuh Tempo   : " . date('d M Y', strtotime($piutang['jatuh_tempo'])) . "\n";
    } else {
        $teks_wa .= "\n📌 *STATUS: LUNAS*\n";
    }

    // BERI KEJUTAN JIKA DAPAT POIN BARU
    if (isset($penjualan['poin_didapat']) && $penjualan['poin_didapat'] > 0) {
        $teks_wa .= "\n🎁 _Selamat! Anda mendapatkan +{$penjualan['poin_didapat']} Poin Loyalty dari transaksi ini!_\n";
    }
    
    $teks_wa .= "================================\n";
    $teks_wa .= "Syarat & ketentuan garansi berlaku. Harap simpan E-Invoice ini sebagai bukti klaim.\n\n";
    $teks_wa .= "_Terima kasih atas kepercayaannya!_";

    // Eksekusi cURL Fonnte
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://api.fonnte.com/send',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => array('target' => $nomor_wa, 'message' => $teks_wa),
        CURLOPT_HTTPHEADER => array("Authorization: $token_fonnte"),
    ));
    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);

    if ($err) {
        $pesan_notif = "<div style='color:#ef4444; font-weight:bold; margin-top:10px;'>❌ Gagal kirim WA: $err</div>";
    } else {
        $pesan_notif = "<div style='color:#10b981; font-weight:bold; margin-top:10px;'>✅ E-Invoice berhasil terkirim via WhatsApp!</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>INVOICE - <?= $no_trx ?></title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #e5e7eb; margin: 0; padding: 20px; color: #111; }
        .a4-container { background: #fff; width: 210mm; min-height: 297mm; margin: 0 auto; padding: 40px; box-sizing: border-box; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 3px solid #111; padding-bottom: 20px; margin-bottom: 30px; }
        .toko-info { display: flex; gap: 20px; align-items: center; max-width: 60%; }
        .toko-logo { max-width: 90px; max-height: 90px; object-fit: contain; }
        .toko-teks h1 { margin: 0; font-size: 26px; color: #111; text-transform: uppercase; letter-spacing: 1px; }
        .toko-teks p.alamat { margin: 5px 0; color: #444; font-size: 13px; max-width: 350px; line-height: 1.5; }
        .toko-teks p.kontak { margin: 0; color: #111; font-size: 13px; font-weight: bold; }
        .invoice-title { text-align: right; min-width: 250px; }
        .invoice-title h2 { margin: 0; font-size: 34px; color: #f59e0b; letter-spacing: 2px; }
        .invoice-title p { margin: 5px 0; font-size: 14px; color: #666; font-weight: bold; white-space: nowrap; }
        .badge-status { display: inline-block; margin-top: 8px; color: #fff; padding: 6px 12px; font-size: 13px; font-weight: bold; border-radius: 4px; white-space: nowrap; }
        .meta-info { display: flex; justify-content: space-between; margin-bottom: 30px; font-size: 14px; }
        .meta-box { width: 48%; }
        .meta-box table { width: 100%; }
        .meta-box td { padding: 4px 0; }
        .table-items { width: 100%; border-collapse: collapse; margin-bottom: 30px; font-size: 14px; }
        .table-items th, .table-items td { border: 1px solid #ddd; padding: 12px; }
        .table-items th { background: #111; color: #fff; text-transform: uppercase; font-size: 13px; }
        .table-items td.text-right { text-align: right; }
        .table-items td.text-center { text-align: center; }
        .summary-box { width: 350px; float: right; }
        .summary-box table { width: 100%; border-collapse: collapse; }
        .summary-box td { padding: 8px; font-size: 15px; }
        .summary-box tr.total-row { background: #f59e0b; color: #fff; font-weight: bold; font-size: 18px; }
        .clear { clear: both; }
        .signature-area { margin-top: 60px; display: flex; justify-content: space-between; text-align: center; font-size: 14px; }
        .signature-box { width: 250px; }
        .signature-line { margin-top: 80px; border-top: 1px solid #111; padding-top: 5px; font-weight: bold; }
        
        .no-print-area { background: #fff; padding: 20px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); margin-bottom: 25px; text-align: center; max-width: 800px; margin-left: auto; margin-right: auto; }
        .flex-actions { display: flex; justify-content: center; align-items: center; gap: 15px; flex-wrap: wrap; }
        .btn-print { background: #3b82f6; color: #fff; border: none; padding: 12px 25px; font-size: 14px; border-radius: 6px; cursor: pointer; font-weight: bold; font-family: inherit; }
        .btn-wa { background: #10b981; color: #fff; border: none; padding: 12px 25px; font-size: 14px; border-radius: 6px; cursor: pointer; font-weight: bold; font-family: inherit; }
        .input-wa { padding: 12px; border: 1px solid #ccc; border-radius: 6px; outline: none; width: 220px; font-size: 14px; }
        
        @media print { body { background: #fff; padding: 0; } .a4-container { box-shadow: none; width: 100%; min-height: auto; padding: 0; } .no-print-area { display: none !important; } }
    </style>
</head>
<body>
    
    <div class="no-print-area">
        <h3 style="margin-top:0; color:#444;">⚙️ Panel Aksi Invoice</h3>
        <div class="flex-actions">
            <button onclick="window.print()" class="btn-print">🖨️ Cetak Fisik / Simpan PDF</button>
            <form method="POST" style="display: flex; gap: 10px; margin: 0;">
                <input type="text" name="nomor_wa" class="input-wa" placeholder="Nomor WA Pelanggan" required value="<?= $penjualan['no_telp'] ?? '' ?>">
                <button type="submit" name="kirim_wa_invoice" class="btn-wa">📲 Kirim E-Invoice ke WA</button>
            </form>
        </div>
        <?= $pesan_notif ?>
    </div>

    <div class="a4-container">
        <div class="header">
            <div class="toko-info">
                <?php if(!empty($toko['logo']) && file_exists('uploads/' . $toko['logo'])): ?>
                    <img src="uploads/<?= $toko['logo'] ?>" class="toko-logo" alt="Logo">
                <?php endif; ?>
                <div class="toko-teks">
                    <h1><?= $toko['nama_toko'] ?? 'Toko Komputer On Fire' ?></h1>
                    <p class="alamat"><?= nl2br($alamat_bersih) ?></p>
                    <p class="kontak">Telp/WA: <?= $toko['telepon'] ?? '-' ?></p>
                </div>
            </div>
            <div class="invoice-title">
                <h2>INVOICE</h2>
                <p>NO TRX: <?= $no_trx ?></p>
                <?php if($penjualan['metode_pembayaran'] == 'Tempo/Kasbon'): ?>
                    <span class="badge-status" style="background:#ef4444;">TAGIHAN (BELUM LUNAS)</span>
                <?php else: ?>
                    <span class="badge-status" style="background:#10b981;">LUNAS</span>
                <?php endif; ?>
            </div>
        </div>

        <div class="meta-info">
            <div class="meta-box">
                <b>Tagihan Kepada:</b><br><br>
                <table>
                    <tr><td style="width: 100px;">Nama</td><td>: <b><?= $penjualan['nama_customer'] != '' ? $penjualan['nama_customer'] : 'Pelanggan Umum' ?></b></td></tr>
                    <tr><td>No. Telp</td><td>: <?= $penjualan['no_telp'] != '' ? $penjualan['no_telp'] : '-' ?></td></tr>
                </table>
            </div>
            <div class="meta-box">
                <b>Detail Transaksi:</b><br><br>
                <table>
                    <tr><td style="width: 120px;">Tanggal Pembelian</td><td>: <?= date('d F Y, H:i', strtotime($penjualan['tanggal_transaksi'])) ?></td></tr>
                    <tr><td>Metode Bayar</td><td>: <b><?= $penjualan['metode_pembayaran'] ?></b></td></tr>
                    <tr><td>Petugas Kasir</td><td>: <?= $penjualan['nama_lengkap'] ?></td></tr>
                </table>
            </div>
        </div>

        <table class="table-items">
            <thead>
                <tr>
                    <th style="width: 5%;">No</th>
                    <th style="width: 35%; text-align: left;">Deskripsi Barang</th>
                    <th style="width: 15%;">Masa Garansi</th>
                    <th style="width: 15%;">Harga Satuan</th>
                    <th style="width: 10%;">Qty</th>
                    <th style="width: 20%; text-align: right;">Total Harga</th>
                </tr>
            </thead>
            <tbody>
                <?php $no = 1; foreach($detail_items as $d): ?>
                <?php $sn_fisik_teks = isset($d['sn_fisik']) && $d['sn_fisik'] != '' ? $d['sn_fisik'] : '-'; ?>
                <tr>
                    <td class="text-center"><?= $no++ ?></td>
                    <td>
                        <b style="font-size: 14px;"><?= $d['nama_barang'] ?></b>
                        
                        <?php if(!empty($d['spesifikasi'])): ?>
                            <div style="color: #4b5563; font-size: 11px; margin-top: 4px; line-height: 1.4; background: #f8fafc; padding: 4px 8px; border-radius: 4px; border-left: 2px solid #cbd5e1;">
                                <b>Spek:</b> <?= nl2br(htmlspecialchars($d['spesifikasi'])) ?>
                            </div>
                        <?php endif; ?>
                        
                        <span style="color: #666; font-size: 12px; display: block; margin-top: 4px;">SKU: <?= $d['kode_barang'] ?></span>
                        <span style="color: #0284c7; font-size: 12px; font-weight: bold; display: block; margin-top: 1px;">SN Fisik: <?= $sn_fisik_teks ?></span>
                    </td>
                    <td class="text-center"><?= $d['masa_garansi'] ?></td>
                    <td class="text-center">Rp <?= number_format($d['harga_jual'],0,',','.') ?></td>
                    <td class="text-center"><?= $d['qty'] ?></td>
                    <td class="text-right"><b>Rp <?= number_format($d['subtotal'],0,',','.') ?></b></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            
            <div style="width: 45%;">
                <?php if(isset($penjualan['poin_didapat']) && $penjualan['poin_didapat'] > 0): ?>
                <div style="border-left: 4px solid #10b981; background: #d1fae5; padding: 12px; border-radius: 4px;">
                    <b style="color: #065f46; font-size: 14px;">🎁 Keuntungan Loyalitas Member</b>
                    <p style="margin: 5px 0 0 0; font-size: 12px; color: #047857;">Selamat! Anda mendapatkan <b>+<?= $penjualan['poin_didapat'] ?> Poin</b> dari transaksi ini. Poin dapat ditukarkan sebagai diskon pada pembelian berikutnya.</p>
                </div>
                <?php endif; ?>
            </div>

            <div class="summary-box">
                <table>
                    <?php if(isset($penjualan['diskon']) && $penjualan['diskon'] > 0): ?>
                    <tr>
                        <td style="font-size: 14px; color: #666;">SUBTOTAL</td>
                        <td style="text-align: right; font-size: 14px;">Rp <?= number_format($subtotal_awal,0,',','.') ?></td>
                    </tr>
                    <tr>
                        <td style="color: #ef4444; font-size: 14px;">DISKON POIN (<?= $penjualan['poin_digunakan'] ?> Poin)</td>
                        <td style="text-align: right; color: #ef4444; font-size: 14px;"><b>- Rp <?= number_format($penjualan['diskon'],0,',','.') ?></b></td>
                    </tr>
                    <?php endif; ?>

                    <tr class="total-row"><td>GRAND TOTAL</td><td style="text-align: right;">Rp <?= number_format($penjualan['total_harga'],0,',','.') ?></td></tr>
                    
                    <?php if($piutang): ?>
                    <tr><td style="padding-top: 10px;">DP / TELAH DIBAYAR</td><td style="text-align: right; padding-top: 10px;">Rp <?= number_format($piutang['jumlah_terbayar'],0,',','.') ?></td></tr>
                    <tr><td style="color: #ef4444; font-weight: bold;">SISA TAGIHAN</td><td style="text-align: right; color: #ef4444; font-weight: bold;">Rp <?= number_format($piutang['sisa_hutang'],0,',','.') ?></td></tr>
                    <tr><td colspan="2" style="font-size: 12px; color: #ef4444; text-align: right;">Jatuh Tempo: <b><?= date('d M Y', strtotime($piutang['jatuh_tempo'])) ?></b></td></tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
        <div class="clear"></div>

        <div style="margin-top: 40px; border-left: 4px solid #f59e0b; padding-left: 15px; background: #fef3c7; padding: 15px;">
            <b style="color: #b45309;">Syarat & Ketentuan Garansi:</b>
            <ol style="margin: 5px 0 0 0; padding-left: 20px; color: #92400e; font-size: 13px;">
                <li>Simpan invoice ini dengan baik sebagai syarat utama klaim garansi.</li>
                <li>Garansi batal apabila segel rusak, cacat fisik akibat kelalaian (jatuh, terkena air), atau terbakar.</li>
                <li>Barang yang sudah dibeli tidak dapat ditukar atau dikembalikan (refund) tanpa persetujuan pihak toko.</li>
            </ol>
        </div>

        <div class="signature-area">
            <div class="signature-box">Penerima / Pembeli,<div class="signature-line"><?= $penjualan['nama_customer'] != '' ? $penjualan['nama_customer'] : '( ................................... )' ?></div></div>
            <div class="signature-box">Hormat Kami,<div class=\"signature-line\"><?= $toko['nama_toko'] ?? 'Toko Komputer On Fire' ?></div></div>
        </div>
    </div>
</body>
</html>
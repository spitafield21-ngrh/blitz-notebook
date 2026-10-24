<?php
// ==========================================
// FILE: cek_garansi.php
// FUNGSI: Melacak Status Garansi via SN Fisik Unit
// ==========================================
require_once 'config.php';

// Pastikan user sudah login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$search_sn = isset($_GET['sn']) ? bersihkan_input($koneksi, $_GET['sn']) : '';
$data_garansi = null;
$pesan_error = "";

// Jika ada pencarian SN
if ($search_sn != '') {
    // Cari SN di database (Abaikan yang SN-nya kosong atau '-')
    $query = "SELECT pd.sn_fisik, pd.masa_garansi, pd.harga_jual, 
                     b.nama_barang, b.kode_barang, 
                     p.tanggal_transaksi, p.nama_customer, p.no_transaksi 
              FROM penjualan_detail pd 
              JOIN penjualan p ON pd.id_penjualan = p.id 
              JOIN barang b ON pd.id_barang = b.id 
              WHERE pd.sn_fisik = '$search_sn' AND pd.sn_fisik != '-' AND pd.sn_fisik != '' 
              LIMIT 1";
              
    $result = $koneksi->query($query);
    
    if ($result && $result->num_rows > 0) {
        $data_garansi = $result->fetch_assoc();
        
        // --- LOGIKA PERHITUNGAN MASA GARANSI ---
        $tgl_beli = date('Y-m-d', strtotime($data_garansi['tanggal_transaksi']));
        $masa = $data_garansi['masa_garansi']; // Contoh: "1 Bulan"
        
        // Terjemahkan format teks ke bahasa mesin waktu (English)
        $masa_inggris = str_replace(
            ['Bulan', 'bulan', 'Tahun', 'tahun', 'Hari', 'hari'], 
            ['month', 'month', 'year', 'year', 'day', 'day'], 
            $masa
        );
        
        // Hitung Tanggal Habis
        $tgl_habis = date('Y-m-d', strtotime($tgl_beli . ' + ' . $masa_inggris));
        $hari_ini = date('Y-m-d');
        
        // Tentukan Status
        if ($hari_ini <= $tgl_habis) {
            $status_garansi = "AKTIF";
            $warna_status = "#10b981"; // Hijau
            $icon_status = "🟢";
        } else {
            $status_garansi = "HANGUS (EXPIRED)";
            $warna_status = "#ef4444"; // Merah
            $icon_status = "🔴";
        }
        
    } else {
        $pesan_error = "SN Fisik <b>'$search_sn'</b> tidak ditemukan dalam riwayat penjualan kami.";
    }
}

require_once 'header.php';
require_once 'sidebar.php';
?>

<style>
    .tracker-container { max-width: 800px; margin: 0 auto; text-align: center; padding: 20px; }
    
    .search-box-jumbo {
        width: 100%; max-width: 500px; padding: 15px 25px; font-size: 18px; border-radius: 30px;
        background: rgba(0,0,0,0.4); border: 2px solid var(--btn-primary); color: #38bdf8;
        outline: none; transition: 0.3s; text-align: center; margin-bottom: 20px; font-weight: bold;
    }
    .search-box-jumbo:focus { box-shadow: 0 0 20px rgba(56, 189, 248, 0.6); background: rgba(0,0,0,0.7); }
    .search-box-jumbo::placeholder { color: #64748b; font-weight: normal; }

    .result-card {
        background: rgba(30, 30, 40, 0.8); border: 1px solid rgba(255,255,255,0.1);
        border-radius: 15px; padding: 30px; margin-top: 20px; text-align: left;
        box-shadow: 0 10px 30px rgba(0,0,0,0.5); backdrop-filter: blur(10px);
    }

    .badge-status-jumbo {
        display: inline-block; padding: 10px 20px; font-size: 20px; font-weight: 900;
        border-radius: 8px; color: #fff; letter-spacing: 1px; margin-bottom: 20px; border: 2px solid #fff;
    }

    .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 15px; }
    .info-box { background: rgba(0,0,0,0.3); padding: 15px; border-radius: 10px; border-left: 4px solid var(--btn-primary); }
    .info-box label { display: block; font-size: 12px; color: #94a3b8; text-transform: uppercase; font-weight: bold; margin-bottom: 5px; }
    .info-box .val { font-size: 16px; color: #fff; font-weight: bold; }
</style>

<div class="main-content module-container">
    <div class="tracker-container">
        <h1 style="color: #38bdf8; margin-bottom: 5px;">🛡️ Pelacak Garansi Pintar</h1>
        <p style="color: #cbd5e1; margin-bottom: 30px;">Scan barcode atau ketik Serial Number (SN) fisik unit untuk mengecek status garansi pelanggan.</p>
        
        <form method="GET" action="">
            <input type="text" name="sn" class="search-box-jumbo" placeholder="Scan/Ketik SN Fisik di sini..." value="<?= htmlspecialchars($search_sn) ?>" autofocus autocomplete="off">
            <br>
            <button type="submit" class="btn btn-primary" style="padding: 12px 30px; font-size:16px; border-radius:30px;">🔍 Lacak Sekarang</button>
        </form>

        <?php if($pesan_error != ''): ?>
            <div style="margin-top: 30px; background: rgba(239, 68, 68, 0.2); border: 1px dashed #ef4444; padding: 15px; border-radius: 10px; color: #f87171;">
                ⚠️ <?= $pesan_error ?>
            </div>
        <?php endif; ?>

        <?php if($data_garansi): ?>
            <div class="result-card">
                <div style="text-align: center;">
                    <div class="badge-status-jumbo" style="background: <?= $warna_status ?>; border-color: <?= $warna_status ?>;">
                        <?= $icon_status ?> STATUS GARANSI: <?= $status_garansi ?>
                    </div>
                </div>

                <h3 style="border-bottom: 1px solid #444; padding-bottom: 10px; color: var(--btn-primary);">Informasi Produk & Pembelian</h3>
                
                <div class="info-grid">
                    <div class="info-box">
                        <label>Nama Produk</label>
                        <div class="val"><?= $data_garansi['nama_barang'] ?></div>
                        <div style="font-size:12px; color:#aaa; margin-top:3px;">SKU: <?= $data_garansi['kode_barang'] ?></div>
                    </div>
                    
                    <div class="info-box">
                        <label>SN Fisik Unit</label>
                        <div class="val" style="color: #38bdf8; font-size: 18px;"><?= $data_garansi['sn_fisik'] ?></div>
                    </div>

                    <div class="info-box">
                        <label>Nama Pelanggan</label>
                        <div class="val"><?= $data_garansi['nama_customer'] != '' ? $data_garansi['nama_customer'] : 'Pelanggan Umum' ?></div>
                        <div style="font-size:12px; color:#aaa; margin-top:3px;">No Nota: <?= $data_garansi['no_transaksi'] ?></div>
                    </div>

                    <div class="info-box">
                        <label>Riwayat Waktu</label>
                        <div style="font-size: 13px; color: #ccc;">
                            Tgl Beli: <b><?= date('d M Y', strtotime($tgl_beli)) ?></b><br>
                            Masa Garansi: <b><?= $data_garansi['masa_garansi'] ?></b><br>
                            Habis Pada: <b style="color: <?= $warna_status ?>;"><?= date('d M Y', strtotime($tgl_habis)) ?></b>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'footer.php'; ?>
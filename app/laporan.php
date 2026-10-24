<?php
// ==========================================
// FILE: laporan_laba_rugi.php
// FUNGSI: Dashboard Laba/Rugi Toko (Auto-Kalkulasi + Tanggal M-Banking)
// ==========================================
require_once 'config.php';

// Pastikan user sudah login & hanya Owner yang bisa akses (Opsional, tapi Laba Rugi rahasia dapur)
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
if ($_SESSION['role'] != 'owner') { 
    echo "<script>alert('Akses Ditolak! Hanya Owner yang dapat melihat Laporan Laba/Rugi.'); window.history.back();</script>"; 
    exit; 
}

// Filter Periode Tanggal
$tgl_mulai = isset($_GET['tgl_mulai']) ? bersihkan_input($koneksi, $_GET['tgl_mulai']) : date('Y-m-01');
$tgl_selesai = isset($_GET['tgl_selesai']) ? bersihkan_input($koneksi, $_GET['tgl_selesai']) : date('Y-m-d');

// ==========================================
// ALGORITMA AKUNTANSI (LABA RUGI)
// ==========================================

// 1. TOTAL PENDAPATAN (OMSET KOTOR)
$q_omset = $koneksi->query("SELECT SUM(total_harga) as omset FROM penjualan WHERE DATE(tanggal_transaksi) BETWEEN '$tgl_mulai' AND '$tgl_selesai'");
$omset = $q_omset->fetch_assoc()['omset'] ?? 0;

// 2. TOTAL HPP (Harga Pokok Penjualan / Modal Barang yang Laku)
$q_hpp = $koneksi->query("SELECT SUM(d.qty * b.harga_beli) as hpp 
                          FROM penjualan_detail d 
                          JOIN penjualan p ON d.id_penjualan = p.id 
                          JOIN barang b ON d.id_barang = b.id 
                          WHERE DATE(p.tanggal_transaksi) BETWEEN '$tgl_mulai' AND '$tgl_selesai'");
$hpp = $q_hpp->fetch_assoc()['hpp'] ?? 0;

// 3. PENDAPATAN JASA SERVIS (Bagi yang sudah Lunas/Diambil)
$q_servis = $koneksi->query("SELECT SUM(biaya_jasa) as total_jasa FROM servis WHERE status IN ('Selesai', 'Diambil') AND DATE(tanggal_selesai) BETWEEN '$tgl_mulai' AND '$tgl_selesai'");
$jasa_servis = $q_servis->fetch_assoc()['total_jasa'] ?? 0;

// 4. LABA KOTOR = (Omset - HPP) + Jasa Servis
$laba_kotor = ($omset - $hpp) + $jasa_servis;

// 5. TOTAL PENGELUARAN OPERASIONAL
$q_pengeluaran = $koneksi->query("SELECT SUM(nominal) as total_pengeluaran FROM pengeluaran WHERE tanggal BETWEEN '$tgl_mulai' AND '$tgl_selesai'");
$pengeluaran = $q_pengeluaran->fetch_assoc()['total_pengeluaran'] ?? 0;

// 6. LABA BERSIH (Keuntungan Real)
$laba_bersih = $laba_kotor - $pengeluaran;

require_once 'header.php';
require_once 'sidebar.php';
?>

<style>
    .module-container { padding: 30px; color: #fff; }
    
    /* STYLE SAKTI INPUT TANGGAL M-BANKING DARK MODE */
    .date-picker-mbanking {
        padding: 10px 15px; border-radius: 20px; background: rgba(0,0,0,0.4);
        border: 1px solid rgba(56, 189, 248, 0.4); color: #38bdf8; outline: none;
        cursor: pointer; font-family: inherit; font-weight: bold; font-size: 14px;
        transition: 0.3s; color-scheme: dark;
    }
    .date-picker-mbanking:focus, .date-picker-mbanking:hover {
        border-color: #38bdf8; box-shadow: 0 0 12px rgba(56, 189, 248, 0.5); background: rgba(0,0,0,0.7);
    }

    .toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; background: rgba(0,0,0,0.2); padding: 15px 20px; border-radius: 12px; border: 1px solid rgba(255,255,255,0.1); }
    
    .report-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
    
    .panel-box {
        background: rgba(30, 30, 40, 0.8); border: 1px solid rgba(255,255,255,0.1);
        border-radius: 15px; padding: 25px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); backdrop-filter: blur(10px);
    }
    .panel-title { color: #94a3b8; font-size: 14px; text-transform: uppercase; font-weight: bold; margin-bottom: 15px; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 10px; }
    
    .row-data { display: flex; justify-content: space-between; margin-bottom: 12px; font-size: 15px; }
    .row-data .label { color: #cbd5e1; }
    .row-data .val { font-weight: bold; color: #fff; }
    
    .row-total { display: flex; justify-content: space-between; margin-top: 15px; padding-top: 15px; border-top: 1px dashed rgba(255,255,255,0.2); font-size: 18px; font-weight: bold; }
    
    .box-laba-bersih {
        background: <?= $laba_bersih >= 0 ? 'rgba(16, 185, 129, 0.15)' : 'rgba(239, 68, 68, 0.15)' ?>;
        border: 2px solid <?= $laba_bersih >= 0 ? '#10b981' : '#ef4444' ?>;
        border-radius: 15px; padding: 30px; text-align: center; margin-top: 20px;
    }
    .box-laba-bersih h3 { margin: 0 0 10px 0; color: <?= $laba_bersih >= 0 ? '#34d399' : '#f87171' ?>; font-size: 20px; text-transform: uppercase; }
    .box-laba-bersih .nilai-jumbo { font-size: 45px; font-weight: 900; color: #fff; letter-spacing: 1px; }

    .btn-print { background: #3b82f6; color: #fff; border: none; padding: 10px 20px; font-size: 14px; border-radius: 8px; cursor: pointer; font-weight: bold; transition: 0.3s; }
    .btn-print:hover { background: #2563eb; box-shadow: 0 0 15px rgba(59, 130, 246, 0.5); }
    
    @media print {
        body { background: #fff; color: #000; padding: 0; }
        .sidebar, .header, .toolbar, .btn-print { display: none !important; }
        .main-content { margin: 0; padding: 20px; width: 100%; }
        .panel-box, .box-laba-bersih { background: none; border: 1px solid #000; box-shadow: none; color: #000; }
        .row-data .label, .row-data .val, .box-laba-bersih h3, .box-laba-bersih .nilai-jumbo { color: #000 !important; }
    }
</style>

<div class="main-content module-container">
    <h2>📈 Laporan Laba / Rugi</h2>
    <p style="color: #94a3b8; margin-top: -15px;">Pantau performa keuangan, omset, dan keuntungan bersih toko.</p>

    <div class="toolbar">
        <form method="GET" action="" id="formFilter" style="display:flex; gap:15px; align-items:center;">
            <label style="font-weight:bold; color:#38bdf8;">Pilih Periode:</label>
            <input type="date" name="tgl_mulai" class="date-picker-mbanking" value="<?= $tgl_mulai ?>" onclick="this.showPicker()" onchange="document.getElementById('formFilter').submit();">
            <span style="color:#aaa;">s/d</span>
            <input type="date" name="tgl_selesai" class="date-picker-mbanking" value="<?= $tgl_selesai ?>" onclick="this.showPicker()" onchange="document.getElementById('formFilter').submit();">
        </form>
        
        <button class="btn-print" onclick="window.print()">🖨️ Cetak Laporan</button>
    </div>

    <div class="report-grid">
        <div class="panel-box">
            <div class="panel-title">🟢 Pemasukan / Pendapatan</div>
            
            <div class="row-data">
                <span class="label">Penjualan Barang (Omset)</span>
                <span class="val">Rp <?= number_format($omset, 0, ',', '.') ?></span>
            </div>
            <div class="row-data">
                <span class="label">Pendapatan Jasa Servis</span>
                <span class="val">Rp <?= number_format($jasa_servis, 0, ',', '.') ?></span>
            </div>
            
            <div class="row-total" style="color: #38bdf8;">
                <span>Total Omset Kotor</span>
                <span>Rp <?= number_format($omset + $jasa_servis, 0, ',', '.') ?></span>
            </div>
        </div>

        <div class="panel-box">
            <div class="panel-title">🔴 Harga Pokok & Pengeluaran</div>
            
            <div class="row-data">
                <span class="label">HPP (Modal Barang Terjual)</span>
                <span class="val" style="color: #f87171;">Rp <?= number_format($hpp, 0, ',', '.') ?></span>
            </div>
            <div class="row-data">
                <span class="label">Pengeluaran Operasional Toko</span>
                <span class="val" style="color: #f87171;">Rp <?= number_format($pengeluaran, 0, ',', '.') ?></span>
            </div>
            
            <div class="row-total" style="color: #f87171;">
                <span>Total Beban Biaya</span>
                <span>Rp <?= number_format($hpp + $pengeluaran, 0, ',', '.') ?></span>
            </div>
        </div>
    </div>

    <div class="box-laba-bersih">
        <?php if($laba_bersih >= 0): ?>
            <h3>KEUNTUNGAN BERSIH (NET PROFIT)</h3>
            <div class="nilai-jumbo">Rp <?= number_format($laba_bersih, 0, ',', '.') ?></div>
            <p style="color:#34d399; margin-top:10px;">Alhamdulillah, Toko mencetak keuntungan pada periode ini.</p>
        <?php else: ?>
            <h3>⚠️ KERUGIAN BERSIH (NET LOSS)</h3>
            <div class="nilai-jumbo">Rp <?= number_format(abs($laba_bersih), 0, ',', '.') ?></div>
            <p style="color:#f87171; margin-top:10px;">Perhatian: Total pengeluaran lebih besar dari keuntungan kotor.</p>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'footer.php'; ?>
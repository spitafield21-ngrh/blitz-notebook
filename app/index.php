<?php
// ==========================================
// FILE: index.php / dashboard.php
// FUNGSI: Dashboard Utama (Command Center) + SINKRONISASI SERVIS.PHP
// ==========================================
require_once 'config.php';

// Pastikan user sudah login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$id_user = $_SESSION['user_id'];
$role_user = $_SESSION['role'] ?? 'kasir';
$nama_user = $_SESSION['nama_lengkap'] ?? 'User';
$tgl_hari_ini = date('Y-m-d');

// ==========================================
// 🛠️ MANTRA AUTO-HEALING DATABASE
// ==========================================
try {
    // Cek apakah tabel servis ada
    $cek_tabel = $koneksi->query("SHOW TABLES LIKE 'servis'");
    if ($cek_tabel && $cek_tabel->num_rows > 0) {
        // Cek & Tambah kolom 'biaya_jasa' (SESUAI DENGAN SERVIS.PHP)
        $cek_biaya = $koneksi->query("SHOW COLUMNS FROM servis LIKE 'biaya_jasa'");
        if ($cek_biaya && $cek_biaya->num_rows == 0) {
            $koneksi->query("ALTER TABLE servis ADD biaya_jasa INT NOT NULL DEFAULT 0");
        }
        // Cek & Tambah kolom 'tanggal_selesai'
        $cek_tgl = $koneksi->query("SHOW COLUMNS FROM servis LIKE 'tanggal_selesai'");
        if ($cek_tgl && $cek_tgl->num_rows == 0) {
            $koneksi->query("ALTER TABLE servis ADD tanggal_selesai DATETIME NULL");
        }
    }
} catch (Exception $e) {
    // Abaikan
}

// ==========================================
// 🛡️ ANTI-CRASH QUERY SHIELD (TRY-CATCH)
// ==========================================

// 1. Total Omzet Penjualan Hari Ini
try {
    $q_omzet = $koneksi->query("SELECT SUM(total_harga) as total FROM penjualan WHERE DATE(tanggal_transaksi) = '$tgl_hari_ini'");
    $omzet_hari_ini = $q_omzet ? ($q_omzet->fetch_assoc()['total'] ?? 0) : 0;
} catch (Exception $e) { $omzet_hari_ini = 0; }

// 2. Total Transaksi Cash Hari Ini
try {
    $q_cash = $koneksi->query("SELECT SUM(total_harga) as total FROM penjualan WHERE DATE(tanggal_transaksi) = '$tgl_hari_ini' AND metode_pembayaran = 'Cash'");
    $cash_hari_ini = $q_cash ? ($q_cash->fetch_assoc()['total'] ?? 0) : 0;
} catch (Exception $e) { $cash_hari_ini = 0; }

// 3. Total Transaksi Non-Cash Hari Ini
try {
    $q_noncash = $koneksi->query("SELECT SUM(total_harga) as total FROM penjualan WHERE DATE(tanggal_transaksi) = '$tgl_hari_ini' AND metode_pembayaran IN ('Transfer Bank', 'QRIS')");
    $noncash_hari_ini = $q_noncash ? ($q_noncash->fetch_assoc()['total'] ?? 0) : 0;
} catch (Exception $e) { $noncash_hari_ini = 0; }

// 4. Total Servis Selesai Hari Ini (SINKRON DENGAN BIAYA_JASA & STATUS DIAMBIL)
try {
    $q_servis = $koneksi->query("SELECT SUM(biaya_jasa) as total FROM servis WHERE status IN ('Selesai', 'Diambil') AND DATE(tanggal_selesai) = '$tgl_hari_ini'");
    $servis_hari_ini = $q_servis ? ($q_servis->fetch_assoc()['total'] ?? 0) : 0;
} catch (Exception $e) { $servis_hari_ini = 0; }

// 5. Query Stok Kritis (Restock)
try {
    $stok_kritis_limit = 3;
    $q_stok_kritis = $koneksi->query("SELECT * FROM barang WHERE stok <= '$stok_kritis_limit' ORDER BY stok ASC LIMIT 5");
} catch (Exception $e) { $q_stok_kritis = null; }

// 6. Query Rekap Tutup Shift Terakhir
try {
    $q_tutup_shift = $koneksi->query("SELECT ts.*, u.nama_lengkap FROM tutup_shift ts JOIN users u ON ts.id_user = u.id ORDER BY ts.id DESC LIMIT 1");
    $last_shift = $q_tutup_shift ? $q_tutup_shift->fetch_assoc() : null;
} catch (Exception $e) { $last_shift = null; }

// 7. Query Servis Pending
try {
    $q_servis_pending = $koneksi->query("SELECT COUNT(*) as total FROM servis WHERE status IN ('Antrean', 'Dikerjakan', 'Menunggu Sparepart')");
    $total_servis_pending = $q_servis_pending ? ($q_servis_pending->fetch_assoc()['total'] ?? 0) : 0;
} catch (Exception $e) { $total_servis_pending = 0; }

require_once 'header.php';
require_once 'sidebar.php';
?>

<style>
    .dashboard-container { padding: 30px; color: #fff; }
    
    .welcome-card {
        background: linear-gradient(135deg, rgba(56, 189, 248, 0.2), rgba(59, 130, 246, 0.1));
        border: 1px solid rgba(56, 189, 248, 0.3);
        border-radius: 20px;
        padding: 25px;
        margin-bottom: 30px;
        backdrop-filter: blur(10px);
        display: flex;
        justify-content: space-between;
        align-items: center;
        box-shadow: 0 10px 30px rgba(0,0,0,0.3);
    }
    .welcome-title { font-size: 24px; font-weight: 800; color: #38bdf8; margin: 0; }
    .welcome-subtitle { color: #94a3b8; margin-top: 5px; font-size: 14px; }
    
    .stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 35px; }
    .stat-card {
        background: rgba(30, 30, 40, 0.8); border: 1px solid rgba(255,255,255,0.08); border-radius: 16px;
        padding: 20px; backdrop-filter: blur(10px); box-shadow: 0 8px 25px rgba(0,0,0,0.4); transition: 0.3s;
    }
    .stat-card:hover { transform: translateY(-5px); border-color: rgba(56, 189, 248, 0.5); }
    .stat-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
    .stat-icon { font-size: 28px; padding: 10px; border-radius: 12px; background: rgba(0,0,0,0.3); }
    .stat-label { color: #94a3b8; font-size: 13px; font-weight: 600; text-transform: uppercase; }
    .stat-value { font-size: 24px; font-weight: 900; color: #fff; }
    .stat-subtext { font-size: 12px; color: #64748b; margin-top: 8px; }

    .section-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 25px; align-items: start; }
    @media screen and (max-width: 992px) { .section-grid { grid-template-columns: 1fr; } }

    .panel-box {
        background: rgba(30, 30, 40, 0.8); border: 1px solid rgba(255,255,255,0.08); border-radius: 18px;
        padding: 22px; backdrop-filter: blur(10px); box-shadow: 0 10px 30px rgba(0,0,0,0.4); margin-bottom: 25px;
    }
    .panel-title {
        font-size: 16px; font-weight: 700; color: #38bdf8; border-bottom: 1px solid rgba(255,255,255,0.1);
        padding-bottom: 12px; margin-top: 0; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;
    }

    .quick-actions { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 12px; margin-bottom: 25px; }
    .btn-action {
        display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 18px 10px;
        background: rgba(0,0,0,0.4); border: 1px solid rgba(255,255,255,0.1); border-radius: 14px; color: #fff;
        text-decoration: none; transition: 0.3s; text-align: center;
    }
    .btn-action:hover { background: rgba(56, 189, 248, 0.2); border-color: #38bdf8; transform: translateY(-3px); }
    .btn-action span { font-size: 24px; margin-bottom: 8px; }
    .btn-action small { font-size: 12px; font-weight: 600; color: #e2e8f0; }

    .glass-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .glass-table th, .glass-table td { padding: 12px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.08); }
    .glass-table th { background: rgba(0,0,0,0.4); color: #38bdf8; text-transform: uppercase; font-size: 11px; }

    .badge { padding: 5px 10px; border-radius: 6px; font-weight: bold; font-size: 11px; display: inline-block; }
    .badge-danger { background: rgba(239, 68, 68, 0.2); color: #f87171; border: 1px solid #ef4444; }
    .badge-success { background: rgba(16, 185, 129, 0.2); color: #34d399; border: 1px solid #10b981; }
    .badge-warning { background: rgba(245, 158, 11, 0.2); color: #fbbf24; border: 1px solid #f59e0b; }
</style>

<div class="main-content dashboard-container">

    <div class="welcome-card">
        <div>
            <h1 class="welcome-title">Selamat Datang, <?= htmlspecialchars($nama_user) ?>! 🔥</h1>
            <p class="welcome-subtitle">Sistem ERP Toko Komputer On Fire aktif & siap digunakan. Role Anda: <b><?= strtoupper($role_user) ?></b></p>
        </div>
        <div style="text-align: right; display: class-hide-mobile;">
            <b style="font-size: 18px; color: #fff;"><?= date('H:i') ?> WIB</b><br>
            <small style="color: #94a3b8;"><?= date('l, d F Y') ?></small>
        </div>
    </div>

    <div class="quick-actions">
        <a href="kasir.php" class="btn-action"><span>🛒</span><small>Point of Sale</small></a>
        <a href="tutup_shift.php" class="btn-action"><span>💰</span><small>Tutup Shift</small></a>
        <a href="servis.php" class="btn-action"><span>🛠️</span><small>Service Center</small></a>
        <a href="barang.php" class="btn-action"><span>📦</span><small>Master Barang</small></a>
    </div>

    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">Omzet Penjualan (Hari Ini)</span>
                <span class="stat-icon" style="color: #10b981;">💰</span>
            </div>
            <div class="stat-value">Rp <?= number_format($omzet_hari_ini, 0, ',', '.') ?></div>
            <div class="stat-subtext">Cash: Rp <?= number_format($cash_hari_ini, 0, ',', '.') ?> | Non-Cash: Rp <?= number_format($noncash_hari_ini, 0, ',', '.') ?></div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">Servis Dalam Proses</span>
                <span class="stat-icon" style="color: #38bdf8;">🛠️</span>
            </div>
            <div class="stat-value"><?= $total_servis_pending ?> Unit</div>
            <div class="stat-subtext">Hasil Jasa Hari Ini: Rp <?= number_format($servis_hari_ini, 0, ',', '.') ?></div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">Shift Terakhir (Status Laci)</span>
                <span class="stat-icon" style="color: #f59e0b;">📋</span>
            </div>
            <?php if ($last_shift): ?>
                <div class="stat-value" style="font-size: 18px; margin-top: 5px;">
                    <?php if ($last_shift['selisih'] == 0): ?>
                        <span style="color: #34d399;">✅ BALANCE (PAS)</span>
                    <?php elseif ($last_shift['selisih'] < 0): ?>
                        <span style="color: #f87171;">⚠️ MINUS Rp <?= number_format(abs($last_shift['selisih']), 0, ',', '.') ?></span>
                    <?php else: ?>
                        <span style="color: #fbbf24;">💡 SURPLUS Rp <?= number_format($last_shift['selisih'], 0, ',', '.') ?></span>
                    <?php endif; ?>
                </div>
                <div class="stat-subtext">Kasir: <?= htmlspecialchars($last_shift['nama_lengkap']) ?> (<?= date('d M, H:i', strtotime($last_shift['waktu_tutup'])) ?>)</div>
            <?php else: ?>
                <div class="stat-value" style="font-size: 16px; color: #64748b;">Belum Ada Rekap</div>
                <div class="stat-subtext">Lakukan Tutup Shift saat jam kerja berakhir.</div>
            <?php endif; ?>
        </div>
    </div>

    <div class="section-grid">
        <div class="panel-box">
            <h3 class="panel-title"><span>⚠️ Peringatan Stok Kritis (Restock Segera)</span></h3>
            <div style="overflow-x: auto;">
                <table class="glass-table">
                    <thead>
                        <tr>
                            <th>Kode</th>
                            <th>Nama Barang</th>
                            <th>Harga Jual</th>
                            <th style="text-align: center;">Sisa Stok</th>
                            <th style="text-align: center;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($q_stok_kritis && $q_stok_kritis->num_rows > 0): ?>
                            <?php while ($b = $q_stok_kritis->fetch_assoc()): ?>
                                <tr>
                                    <td><b style="color: #38bdf8;"><?= htmlspecialchars($b['kode_barang']) ?></b></td>
                                    <td><?= htmlspecialchars($b['nama_barang']) ?></td>
                                    <td>Rp <?= number_format($b['harga_jual'], 0, ',', '.') ?></td>
                                    <td style="text-align: center;"><span class="badge badge-danger"><?= $b['stok'] ?> Pcs</span></td>
                                    <td style="text-align: center;"><a href="barang_masuk.php?kode=<?= htmlspecialchars($b['kode_barang']) ?>" class="badge badge-warning" style="text-decoration: none;">+ Restock</a></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" style="text-align: center; color: #34d399; padding: 20px;">✅ Semua stok barang dalam kondisi aman.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="panel-box">
            <h3 class="panel-title"><span>📌 Status Operasional</span></h3>
            <div style="display: flex; flex-direction: column; gap: 15px;">
                <div style="background: rgba(0,0,0,0.3); padding: 15px; border-radius: 12px; border-left: 4px solid #38bdf8;">
                    <small style="color: #94a3b8; font-weight: bold;">SERVIS BERJALAN</small>
                    <div style="font-size: 14px; color: #fff; margin-top: 5px;">Ada <b><?= $total_servis_pending ?> laptop</b> sedang dikerjakan teknisi.</div>
                    <a href="servis.php" style="color: #38bdf8; font-size: 12px; text-decoration: none; font-weight: bold; margin-top: 5px; display: inline-block;">Cek Service Center →</a>
                </div>
                <div style="background: rgba(0,0,0,0.3); padding: 15px; border-radius: 12px; border-left: 4px solid #10b981;">
                    <small style="color: #94a3b8; font-weight: bold;">SISTEM WHATSAPP (FONNTE)</small>
                    <div style="font-size: 14px; color: #34d399; margin-top: 5px;">✅ WA Gateway Terhubung</div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>
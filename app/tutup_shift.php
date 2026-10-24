<?php
// ==========================================
// FILE: tutup_shift.php
// FUNGSI: Modul End of Day (EOD) / Rekap Kasir + WA NOTIFIKASI TEKS (STRUK DIGITAL)
// ==========================================
require_once 'config.php';

// AUTO-CREATE TABEL
$koneksi->query("CREATE TABLE IF NOT EXISTS tutup_shift (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_user INT NOT NULL,
    waktu_tutup DATETIME NOT NULL,
    total_cash_sistem INT NOT NULL DEFAULT 0,
    total_noncash_sistem INT NOT NULL DEFAULT 0,
    uang_fisik_laci INT NOT NULL DEFAULT 0,
    selisih INT NOT NULL DEFAULT 0,
    catatan TEXT,
    FOREIGN KEY (id_user) REFERENCES users(id)
)");

// Pastikan user sudah login
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$id_user = $_SESSION['user_id'];
$tgl_hari_ini = date('Y-m-d');
$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['simpan_tutup_shift'])) {
    
    // --- KONFIGURASI WHATSAPP FONNTE ---
    $token_fonnte = "CKn8puLkshXnsUYwB4tF"; // MASUKKAN TOKEN FONNTE DI SINI
    $nomor_wa_owner = "081222223752";            // MASUKKAN NOMOR WA PENERIMA
    // -----------------------------------

    $total_cash    = (int)$_POST['total_cash_sistem'];
    $total_noncash = (int)$_POST['total_noncash_sistem'];
    $uang_fisik    = (int)$_POST['uang_fisik_laci'];
    $catatan       = bersihkan_input($koneksi, $_POST['catatan'] ?? '');
    
    $selisih = $uang_fisik - $total_cash; 
    $waktu_tutup = date('Y-m-d H:i:s');

    // Simpan Data Ke Database
    $q_insert = "INSERT INTO tutup_shift (id_user, waktu_tutup, total_cash_sistem, total_noncash_sistem, uang_fisik_laci, selisih, catatan) 
                 VALUES ('$id_user', '$waktu_tutup', '$total_cash', '$total_noncash', '$uang_fisik', '$selisih', '$catatan')";
                 
    if ($koneksi->query($q_insert)) {
        $message = "<div class='alert alert-success'>✅ Laporan Tutup Shift berhasil disimpan ke Database!</div>";

        if ($token_fonnte != "TOKEN_FONNTE_COACH_DISINI") {
            
            $q_user = $koneksi->query("SELECT nama_lengkap FROM users WHERE id = '$id_user'");
            $nama_kasir = $q_user->fetch_assoc()['nama_lengkap'] ?? 'Kasir';

            // Logika Selisih Teks
            if ($selisih == 0) {
                $status_selisih = "✅ *BALANCE (PAS)*";
            } elseif ($selisih < 0) {
                $status_selisih = "⚠️ *MINUS:* Rp " . number_format(abs($selisih), 0, ',', '.');
            } else {
                $status_selisih = "💡 *SURPLUS:* Rp " . number_format($selisih, 0, ',', '.');
            }

            $catatan_teks = ($catatan != '') ? $catatan : "Tidak ada catatan.";

            // ===============================================
            // 📝 FORMAT PESAN WA (STRUK DIGITAL RAPI)
            // ===============================================
            $teks_wa = "🏪 *TOKO KOMPUTER ON FIRE*\n";
            $teks_wa .= "========================\n";
            $teks_wa .= "💰 *LAPORAN TUTUP SHIFT*\n\n";
            $teks_wa .= "👤 *Kasir:* $nama_kasir\n";
            $teks_wa .= "🕒 *Waktu:* " . date('d M Y, H:i') . "\n\n";
            
            $teks_wa .= "*RINCIAN PENDAPATAN:*\n";
            $teks_wa .= "💵 Cash (Sistem): Rp " . number_format($total_cash, 0, ',', '.') . "\n";
            $teks_wa .= "💳 Non-Cash: Rp " . number_format($total_noncash, 0, ',', '.') . "\n";
            $teks_wa .= "------------------------\n";
            $teks_wa .= "💸 *Fisik di Laci: Rp " . number_format($uang_fisik, 0, ',', '.') . "*\n\n";
            
            $teks_wa .= "📊 *STATUS SELISIH:*\n";
            $teks_wa .= "$status_selisih\n\n";
            
            $teks_wa .= "📝 *CATATAN KASIR:*\n";
            $teks_wa .= "_{$catatan_teks}_\n\n"; // <--- OBAT BUGNYA DI SINI (Kurung Kurawal)
            $teks_wa .= "========================\n";
            $teks_wa .= "_Sistem ERP Blitz Notebook_";

            // Tembak WA (Satu Kali, Teks Saja)
            $curl_text = curl_init();
            curl_setopt_array($curl_text, array(
                CURLOPT_URL => 'https://api.fonnte.com/send',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => false, 
                CURLOPT_POST => true, 
                CURLOPT_POSTFIELDS => array(
                    'target' => $nomor_wa_owner, 
                    'message' => $teks_wa
                ),
                CURLOPT_HTTPHEADER => array("Authorization: $token_fonnte"),
            ));
            
            $response = curl_exec($curl_text);
            $err = curl_error($curl_text);
            curl_close($curl_text);

            if ($err) {
                $message .= "<div class='alert alert-danger' style='margin-top:10px;'>❌ <b>Gagal kirim WA:</b> $err</div>";
            } else {
                $message .= "<div class='alert alert-success' style='margin-top:10px;'>📲 Laporan Tutup Shift berhasil terkirim ke WhatsApp Owner!</div>";
            }
        }
    } else {
        $message = "<div class='alert alert-danger'>Error DB: " . $koneksi->error . "</div>";
    }
}

// 2. KALKULASI REAL-TIME
$q_cash = $koneksi->query("SELECT SUM(total_harga) as cash FROM penjualan WHERE id_user = '$id_user' AND DATE(tanggal_transaksi) = '$tgl_hari_ini' AND metode_pembayaran = 'Cash'");
$sys_cash = $q_cash->fetch_assoc()['cash'] ?? 0;

$q_dp = $koneksi->query("SELECT SUM(p.jumlah_terbayar) as dp FROM piutang p JOIN penjualan j ON p.id_penjualan = j.id WHERE j.id_user = '$id_user' AND DATE(j.tanggal_transaksi) = '$tgl_hari_ini' AND j.metode_pembayaran = 'Tempo/Kasbon'");
$sys_dp = $q_dp->fetch_assoc()['dp'] ?? 0;
$total_cash_sistem = $sys_cash + $sys_dp;

$q_noncash = $koneksi->query("SELECT SUM(total_harga) as noncash FROM penjualan WHERE id_user = '$id_user' AND DATE(tanggal_transaksi) = '$tgl_hari_ini' AND metode_pembayaran IN ('Transfer Bank', 'QRIS')");
$total_noncash_sistem = $q_noncash->fetch_assoc()['noncash'] ?? 0;

$where_riwayat = ($_SESSION['role'] == 'owner') ? "" : "WHERE ts.id_user = '$id_user'";
$q_riwayat = $koneksi->query("SELECT ts.*, u.nama_lengkap FROM tutup_shift ts JOIN users u ON ts.id_user = u.id $where_riwayat ORDER BY ts.id DESC LIMIT 10");

require_once 'header.php';
require_once 'sidebar.php';
?>

<style>
    .module-container { padding: 30px; color: #fff; }
    .grid-container { display: grid; grid-template-columns: 1.5fr 1fr; gap: 25px; align-items: start; }
    
    @media screen and (max-width: 768px) { 
        .grid-container { grid-template-columns: 1fr; } 
    }
    
    .panel-box { 
        background: rgba(30, 30, 40, 0.8); 
        border: 1px solid rgba(255,255,255,0.1); 
        border-radius: 15px; 
        padding: 25px; 
        box-shadow: 0 10px 30px rgba(0,0,0,0.5); 
        backdrop-filter: blur(10px); 
    }
    
    .uang-sistem { 
        background: #000; color: #38bdf8; font-size: 32px; font-weight: 900; 
        text-align: center; padding: 20px; border-radius: 12px; margin-bottom: 20px; 
        border: 2px dashed #38bdf8; 
    }
    
    .form-group { margin-bottom: 20px; }
    .form-group label { display: block; margin-bottom: 8px; color: #ccc; font-weight: bold; font-size: 14px; }
    
    .form-control-jumbo { 
        width: 100%; padding: 15px; border-radius: 10px; background: rgba(0,0,0,0.5); 
        text-align: center; font-size: 24px; font-weight: bold; color: #10b981; 
        border: 2px solid #64748b; outline: none; box-sizing: border-box; transition: 0.3s; 
    }
    .form-control-jumbo:focus { border-color: #10b981; box-shadow: 0 0 15px rgba(16, 185, 129, 0.4); }
    
    .selisih-box { text-align: center; padding: 15px; border-radius: 10px; font-size: 18px; font-weight: bold; background: #333; margin-bottom: 20px; }
    .selisih-pas { background: rgba(16, 185, 129, 0.2); color: #34d399; border: 1px solid #10b981; }
    .selisih-kurang { background: rgba(239, 68, 68, 0.2); color: #f87171; border: 1px solid #ef4444; }
    .selisih-lebih { background: rgba(245, 158, 11, 0.2); color: #fbbf24; border: 1px solid #f59e0b; }
    
    .btn-submit { 
        background: #3b82f6; color: #fff; border: none; padding: 15px; font-size: 16px; 
        border-radius: 8px; cursor: pointer; font-weight: bold; width: 100%; transition: 0.3s; 
    }
    .btn-submit:hover { background: #2563eb; box-shadow: 0 0 15px rgba(59,130,246,0.5); }
    
    .glass-table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 13px; }
    .glass-table th, .glass-table td { padding: 12px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.1); }
    .glass-table th { background: rgba(0,0,0,0.5); color: #38bdf8; text-transform: uppercase; }
    
    .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; }
    .alert-success { background: rgba(16, 185, 129, 0.2); border: 1px solid #10b981; color: #34d399; }
    .alert-danger { background: rgba(239, 68, 68, 0.2); border: 1px solid #ef4444; color: #f87171; }
</style>

<div class="main-content module-container">
    <h2 style="color: #38bdf8;">💰 Laporan Tutup Shift & Rekap Laci Kasir</h2>
    <p style="color: #94a3b8; margin-top: -15px;">Validasi pendapatan tunai dengan uang fisik sebelum mengakhiri jam kerja.</p>
    
    <?= $message ?>

    <div class="grid-container">
        <div class="panel-box">
            <h3 style="margin-top:0; border-bottom:1px solid #444; padding-bottom:10px;">📋 Hitung Uang Tunai</h3>
            <div class="uang-sistem">Rp <?= number_format($total_cash_sistem, 0, ',', '.') ?></div>
            
            <form method="POST" action="">
                <input type="hidden" name="total_cash_sistem" id="val_sistem" value="<?= $total_cash_sistem ?>">
                <input type="hidden" name="total_noncash_sistem" value="<?= $total_noncash_sistem ?>">

                <div class="form-group">
                    <label>Uang Tunai Fisik di Laci</label>
                    <input type="number" name="uang_fisik_laci" id="input_fisik" class="form-control-jumbo" placeholder="0" required onkeyup="hitungSelisih()">
                </div>
                
                <div id="box_selisih" class="selisih-box" style="display:none;">
                    <span id="teks_selisih">Menghitung...</span>
                </div>

                <div class="form-group">
                    <label>Catatan Tambahan (Opsional)</label>
                    <textarea name="catatan" class="form-control" style="width:100%; padding:10px; border-radius:8px; background:rgba(0,0,0,0.3); color:#fff; border:1px solid #444;" rows="2" placeholder="Contoh: Ada uang kembalian sobek, dll..."></textarea>
                </div>
                
                <button type="submit" name="simpan_tutup_shift" class="btn-submit">🔒 Kunci & Kirim Laporan ke WA</button>
            </form>
        </div>

        <div class="panel-box" style="padding: 20px;">
            <h3 style="margin-top:0; border-bottom:1px solid #444; padding-bottom:10px;">📜 Riwayat Tutup Shift</h3>
            <div style="max-height: 400px; overflow-y: auto;">
                <table class="glass-table">
                    <thead>
                        <tr>
                            <th>Waktu</th>
                            <th>Kasir</th>
                            <th style="text-align:right;">Selisih</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($q_riwayat && $q_riwayat->num_rows > 0): ?>
                            <?php while($r = $q_riwayat->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <b style="color:#fff;"><?= date('d M', strtotime($r['waktu_tutup'])) ?></b><br>
                                        <small><?= date('H:i', strtotime($r['waktu_tutup'])) ?></small>
                                    </td>
                                    <td><?= $r['nama_lengkap'] ?></td>
                                    <td style="text-align:right;">
                                        <?php if($r['selisih'] == 0): ?>
                                            <span style="color:#34d399">PAS</span>
                                        <?php elseif($r['selisih'] < 0): ?>
                                            <span style="color:#f87171">-<?= number_format(abs($r['selisih']),0,',','.') ?></span>
                                        <?php else: ?>
                                            <span style="color:#fbbf24">+<?= number_format($r['selisih'],0,',','.') ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="3" style="text-align:center;">Belum ada riwayat.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    function hitungSelisih() {
        let sistem = parseInt(document.getElementById('val_sistem').value) || 0;
        let fisik = parseInt(document.getElementById('input_fisik').value) || 0;
        let selisih = fisik - sistem;
        let box = document.getElementById('box_selisih'); 
        let teks = document.getElementById('teks_selisih');
        
        box.style.display = 'block'; 
        box.className = 'selisih-box'; 
        
        if (selisih === 0) { 
            box.classList.add('selisih-pas'); 
            teks.innerHTML = "✅ BALANCE"; 
        } else if (selisih < 0) { 
            box.classList.add('selisih-kurang'); 
            teks.innerHTML = "⚠️ MINUS : Rp " + Math.abs(selisih).toLocaleString('id-ID'); 
        } else { 
            box.classList.add('selisih-lebih'); 
            teks.innerHTML = "💡 SURPLUS : Rp " + selisih.toLocaleString('id-ID'); 
        }
    }
</script>

<?php require_once 'footer.php'; ?>
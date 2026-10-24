<?php
// ==========================================
// FILE: cetak_barcode.php
// FUNGSI: Mencetak Label Barcode Khusus Printer Thermal 80mm
// ==========================================
require_once 'config.php';

if (!isset($_SESSION['user_id'])) { die("Akses Ditolak! Silakan login."); }

$q_toko = $koneksi->query("SELECT nama_toko FROM pengaturan LIMIT 1");
$toko = $q_toko->fetch_assoc();
$nama_toko = $toko['nama_toko'] ?? 'BLITZ NOTEBOOK';

$id_barang_pilihan = isset($_GET['id_barang']) ? (int)$_GET['id_barang'] : 0;
$jumlah_cetak = isset($_GET['jumlah']) ? (int)$_GET['jumlah'] : 1;

$q_list = $koneksi->query("SELECT id, nama_barang, kode_barang FROM barang WHERE status = 'Aktif' ORDER BY nama_barang ASC");

$where = "WHERE status = 'Aktif'";
if ($id_barang_pilihan > 0) { $where .= " AND id = '$id_barang_pilihan'"; }
$q_barang = $koneksi->query("SELECT kode_barang, nama_barang, harga_jual FROM barang $where ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Barcode Thermal 80mm</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #18181b; color: #fff; margin: 0; padding: 20px; }
        .no-print-area { max-width: 600px; margin: 0 auto 30px auto; background: #27272a; border: 1px solid rgba(255,255,255,0.1); padding: 25px; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.5); }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-size: 14px; color: #ccc; font-weight: bold; }
        .form-control { width: 100%; padding: 10px; border-radius: 8px; background: #09090b; border: 1px solid rgba(255,255,255,0.2); color: #fff; outline: none; box-sizing: border-box; }
        .btn-print { background: #10b981; color: #fff; border: none; padding: 12px 20px; font-size: 15px; border-radius: 8px; cursor: pointer; font-weight: bold; width: 100%; transition: 0.3s; }
        .btn-print:hover { background: #059669; box-shadow: 0 0 15px #10b981; }
        .thermal-wrapper { display: flex; flex-direction: column; align-items: center; gap: 15px; }
        .label-thermal { width: 72mm; background: #fff; color: #000; padding: 8px; box-sizing: border-box; border: 1px dashed #666; text-align: center; border-radius: 4px; }
        .label-header { font-size: 11px; font-weight: bold; border-bottom: 1.5px solid #000; padding-bottom: 3px; margin-bottom: 5px; text-transform: uppercase; letter-spacing: 1px; }
        .label-title { font-size: 12px; font-weight: bold; margin: 3px 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .label-price { font-size: 14px; font-weight: 800; margin-bottom: 6px; }
        .barcode-img { max-width: 95%; height: 16mm; object-fit: contain; }
        .label-sn { font-size: 10px; font-weight: bold; letter-spacing: 2px; margin-top: 3px; }
        @media print {
            @page { size: 80mm auto; margin: 0; }
            body { background: #fff !important; padding: 0 !important; margin: 0 !important; }
            .no-print-area { display: none !important; }
            .thermal-wrapper { gap: 0 !important; }
            .label-thermal { width: 72mm !important; border: none !important; padding: 5mm 2mm !important; page-break-after: always; }
        }
    </style>
</head>
<body>
    <div class="no-print-area">
        <h3 style="margin-top:0; color:#f59e0b;">🖨️ Setting Cetak Barcode Thermal (80mm)</h3>
        <form method="GET" action="">
            <div class="form-group">
                <label>Pilih Barang Yang Ingin Dicetak:</label>
                <select name="id_barang" class="form-control" onchange="this.form.submit()">
                    <option value="0">-- CEK SEMUA BARANG AKTIF --</option>
                    <?php if($q_list && $q_list->num_rows > 0): ?>
                        <?php while($l = $q_list->fetch_assoc()): ?>
                            <option value="<?= $l['id'] ?>" <?= $id_barang_pilihan == $l['id'] ? 'selected' : '' ?>>
                                <?= $l['nama_barang'] ?> (SKU: <?= $l['kode_barang'] ?>)
                            </option>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </select>
            </div>
            <?php if($id_barang_pilihan > 0): ?>
            <div class="form-group">
                <label>Jumlah Stiker Yang Ingin Diprint:</label>
                <input type="number" name="jumlah" class="form-control" value="<?= $jumlah_cetak ?>" min="1" max="100">
            </div>
            <button type="submit" class="btn-print" style="background:#3b82f6; margin-bottom:10px;">Update Tampilan</button>
            <?php endif; ?>
        </form>
        <button onclick="window.print()" class="btn-print">🖨️ PRINT KE PRINTER THERMAL 80mm</button>
        <div style="text-align:center; margin-top:15px;"><a href="index.php" style="color: #ef4444; text-decoration: none; font-weight: bold; font-size:14px;">⬅️ Kembali ke Dashboard</a></div>
    </div>

    <div class="thermal-wrapper">
        <?php if($q_barang && $q_barang->num_rows > 0): ?>
            <?php while($b = $q_barang->fetch_assoc()): ?>
                <?php $loop_count = ($id_barang_pilihan > 0) ? $jumlah_cetak : 1; for($i = 0; $i < $loop_count; $i++): ?>
                    <div class="label-thermal">
                        <div class="label-header"><?= htmlspecialchars($nama_toko) ?></div>
                        <div class="label-title"><?= htmlspecialchars($b['nama_barang']) ?></div>
                        <div class="label-price">Rp <?= number_format($b['harga_jual'], 0, ',', '.') ?></div>
                        <img src="https://barcode.tec-it.com/barcode.ashx?data=<?= urlencode($b['kode_barang']) ?>&code=Code128&dpi=96" class="barcode-img" alt="Barcode">
                        <div class="label-sn"><?= htmlspecialchars($b['kode_barang']) ?></div>
                    </div>
                <?php endfor; ?>
            <?php endwhile; ?>
        <?php endif; ?>
    </div>
</body>
</html>
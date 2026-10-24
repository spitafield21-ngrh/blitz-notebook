<?php
// ==========================================
// FILE: detail.php (DETAIL PRODUK & FOTO UNIT ASLI)
// ==========================================
@include 'app/config.php';

$id_barang = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$query = $koneksi->query("SELECT b.*, k.nama_kategori FROM barang b LEFT JOIN kategori k ON b.id_kategori = k.id WHERE b.id = $id_barang AND b.status = 'Aktif'");
if (!$query || $query->num_rows == 0) {
    header("Location: index.php");
    exit;
}
$row = $query->fetch_assoc();

$q_pengaturan = $koneksi->query("SELECT * FROM pengaturan ORDER BY id DESC LIMIT 1");
$pengaturan = $q_pengaturan ? $q_pengaturan->fetch_assoc() : [];
$nama_toko = !empty($pengaturan['nama_toko']) ? $pengaturan['nama_toko'] : 'BLITZ NOTEBOOK';
$no_telp_raw = !empty($pengaturan['telepon']) ? $pengaturan['telepon'] : '081234567890';
$logo_toko = !empty($pengaturan['logo']) ? 'app/uploads/' . $pengaturan['logo'] : '';

$no_wa = preg_replace('/[^0-9]/', '', $no_telp_raw);
if (strpos($no_wa, '0') === 0) { $no_wa = '62' . substr($no_wa, 1); }

$harga_jual = number_format($row['harga_jual'], 0, ',', '.');
$pesan_wa = urlencode("Halo *{$nama_toko}*!\n\nSaya tertarik dengan unit ini:\n\n*{$row['nama_barang']}*\nSKU: {$row['kode_barang']}\nHarga: Rp {$harga_jual}\n\nApakah unit ini masih tersedia untuk dicek fisik/dikirim?");
$link_wa = "https://wa.me/{$no_wa}?text={$pesan_wa}";

// Ambil foto dari folder uploads admin, jika kosong gunakan default placeholder
$foto_unit = (!empty($row['foto']) && file_exists('app/uploads/' . $row['foto'])) 
             ? 'app/uploads/' . $row['foto'] 
             : 'https://images.unsplash.com/photo-1517336714731-489689fd1ca8?auto=format&fit=crop&w=800&q=80';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($row['nama_barang']) ?> | <?= htmlspecialchars($nama_toko) ?></title>
    <style>
        :root {
            --primary: #38bdf8;
            --secondary: #10b981;
            --dark-bg: #0f172a;
            --card-bg: #1e293b;
            --text-light: #f8fafc;
            --text-muted: #94a3b8;
            --border-glass: rgba(255, 255, 255, 0.08);
        }
        body, html {
            margin: 0; padding: 0;
            font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: var(--dark-bg);
            color: var(--text-light);
        }
        header {
            background: rgba(15, 23, 42, 0.95); backdrop-filter: blur(12px);
            position: fixed; width: 100%; top: 0; z-index: 1000;
            border-bottom: 1px solid var(--border-glass);
        }
        .navbar {
            display: flex; justify-content: space-between; align-items: center;
            max-width: 1200px; margin: 0 auto; padding: 12px 20px;
        }
        .logo-container { display: flex; align-items: center; text-decoration: none; }
        .logo-img { height: 36px; margin-right: 8px; border-radius: 4px; }
        .logo-text { font-size: 20px; font-weight: 800; color: #fff; letter-spacing: 1px; text-transform: uppercase; }
        .logo-text span { color: var(--primary); }
        .btn-back { color: var(--text-light); text-decoration: none; font-size: 14px; font-weight: 600; background: rgba(255,255,255,0.05); padding: 8px 16px; border-radius: 8px; border: 1px solid var(--border-glass); transition: 0.3s; }
        .btn-back:hover { background: var(--primary); color: #000; }

        .detail-container { max-width: 1100px; margin: 100px auto 50px; padding: 0 20px; }
        .detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; align-items: start; }
        
        .product-image-box {
            background: var(--card-bg); border: 1px solid var(--border-glass); border-radius: 16px; padding: 20px;
            text-align: center; position: relative; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        .product-image-box img {
            width: 100%; height: 380px; object-fit: cover; border-radius: 10px; background: #000;
        }
        .kondisi-badge {
            position: absolute; top: 35px; left: 35px; background: rgba(16, 185, 129, 0.9); color: #fff;
            padding: 6px 14px; font-size: 12px; font-weight: bold; border-radius: 20px; backdrop-filter: blur(5px);
        }

        .product-info-box {
            background: var(--card-bg); border: 1px solid var(--border-glass); border-radius: 16px; padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        .kategori-tag { color: var(--primary); font-size: 13px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px; display: inline-block; }
        .product-title { font-size: 26px; font-weight: 800; margin: 0 0 15px 0; color: #fff; }
        .product-price { font-size: 30px; font-weight: 800; color: var(--secondary); margin-bottom: 20px; }
        .spec-section { margin-bottom: 25px; border-top: 1px solid var(--border-glass); padding-top: 20px; }
        .spec-section h4 { margin: 0 0 10px 0; color: #fff; font-size: 15px; }
        .spec-content { color: var(--text-muted); font-size: 14px; line-height: 1.6; white-space: pre-line; }
        
        .stok-status { font-size: 14px; margin-bottom: 20px; font-weight: 600; }
        .stok-tersedia { color: var(--secondary); }
        .stok-habis-teks { color: #ef4444; }

        .btn-wa-order {
            display: block; width: 100%; background: #25D366; color: #fff; text-align: center;
            padding: 14px 0; text-decoration: none; font-weight: bold; font-size: 16px; border-radius: 8px; transition: 0.3s;
            box-shadow: 0 4px 15px rgba(37, 211, 102, 0.3);
        }
        .btn-wa-order:hover { background: #1ebd5a; transform: translateY(-2px); }
        .btn-disabled { background: #475569; color: #94a3b8; pointer-events: none; box-shadow: none; }

        @media (max-width: 768px) {
            .detail-grid { grid-template-columns: 1fr; gap: 20px; }
            .product-image-box img { height: 260px; }
        }
    </style>
</head>
<body>

    <header>
        <div class="navbar">
            <a href="index.php" class="logo-container">
                <?php if($logo_toko != '' && file_exists($logo_toko)): ?>
                    <img src="<?= $logo_toko ?>" alt="Logo" class="logo-img">
                <?php endif; ?>
                <span class="logo-text"><?= htmlspecialchars($nama_toko) ?></span>
            </a>
            <a href="index.php#katalog" class="btn-back">← Kembali ke Katalog</a>
        </div>
    </header>

    <div class="detail-container">
        <div class="detail-grid">
            <!-- SISI KIRI: FOTO ASLI UNIT LAPTOP -->
            <div class="product-image-box">
                <div class="kondisi-badge">✨ Unit Bekas Berkualitas / Siap Pakai</div>
                <img src="<?= $foto_unit ?>" alt="<?= htmlspecialchars($row['nama_barang']) ?>">
                <p style="font-size: 12px; color: var(--text-muted); margin-top: 15px;">*Foto fisik unit asli sesuai stok yang tersedia di toko.</p>
            </div>

            <!-- SISI KANAN: SPESIFIKASI & ORDER -->
            <div class="product-info-box">
                <span class="kategori-tag"><?= htmlspecialchars($row['nama_kategori'] ?? 'Laptop / Komputer') ?></span>
                <h1 class="product-title"><?= htmlspecialchars($row['nama_barang']) ?></h1>
                <div class="product-price">Rp <?= $harga_jual ?></div>

                <div class="stok-status">
                    Status Stok: 
                    <?php if($row['stok'] > 0): ?>
                        <span class="stok-tersedia">Tersedia (<?= $row['stok'] ?> Unit)</span>
                    <?php else: ?>
                        <span class="stok-habis-teks">Habis Terjual</span>
                    <?php endif; ?>
                </div>

                <div class="spec-section">
                    <h4>📋 Spesifikasi Lengkap & Kelengkapan</h4>
                    <div class="spec-content"><?= htmlspecialchars($row['spesifikasi']) ?></div>
                </div>

                <div class="spec-section">
                    <h4>🛡️ Jaminan & Garansi Toko</h4>
                    <div class="spec-content">Unit telah melalui tahap uji kualitas ketat (Quality Control). Bergaransi toko untuk menjamin kenyamanan transaksi Anda.</div>
                </div>

                <div style="margin-top: 30px;">
                    <?php if($row['stok'] > 0): ?>
                        <a href="<?= $link_wa ?>" target="_blank" class="btn-wa-order">💬 Tanya / Beli Unit Ini via WhatsApp</a>
                    <?php else: ?>
                        <a href="#" class="btn-wa-order btn-disabled">❌ Unit Sudah Terjual</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

</body>
</html>
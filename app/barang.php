<?php
// ==========================================
// FILE: barang.php
// FUNGSI: Master Data Barang (SKU) + Upload Foto Unit + Export/Import CSV + Simpan & Tambah Lagi
// ==========================================
require_once 'config.php';

// Pastikan user sudah login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$message = '';
$auto_open_modal = false; // Flag untuk "Simpan & Tambah Lagi"
$upload_dir = 'uploads/';

if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}
$allowed_ext = ['png', 'jpg', 'jpeg', 'webp'];

// ==========================================
// 🚀 ENDPOINT: DOWNLOAD TEMPLATE IMPORT CSV
// ==========================================
if (isset($_GET['download_template'])) {
    ob_clean();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=template_import_barang.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['kode_barang', 'id_kategori', 'nama_barang', 'spesifikasi', 'harga_beli', 'harga_jual', 'stok', 'id_supplier', 'status']);
    fputcsv($output, ['LAPTOP-001', '1', 'Laptop Asus ROG Strix', 'Core i7, 16GB RAM, RTX 3060', '15000000', '18500000', '10', '1', 'Aktif']);
    fputcsv($output, ['MOUSE-001', '2', 'Logitech G102 Prodigy', 'RGB, 8000 DPI', '200000', '275000', '50', '0', 'Aktif']);
    fclose($output);
    exit;
}

// ==========================================
// 🚀 ENDPOINT: EXPORT DATA BARANG (CSV)
// ==========================================
if (isset($_GET['export_csv'])) {
    ob_clean();
    $search = isset($_GET['search']) ? bersihkan_input($koneksi, $_GET['search']) : '';
    
    $where = "";
    if ($search != '') {
        $where = "WHERE b.nama_barang LIKE '%$search%' OR b.kode_barang LIKE '%$search%'";
    }

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Data_Barang_Master.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['No', 'Kode Barang (SKU)', 'Nama Barang', 'Kategori', 'Spesifikasi', 'Harga Modal', 'Harga Jual', 'Stok', 'Supplier', 'Status']);

    $q = "SELECT b.*, k.nama_kategori, s.nama_supplier 
          FROM barang b 
          LEFT JOIN kategori k ON b.id_kategori = k.id 
          LEFT JOIN supplier s ON b.id_supplier = s.id 
          $where ORDER BY b.id DESC";
    $res = $koneksi->query($q);
    $no = 1;
    if ($res && $res->num_rows > 0) {
        while ($r = $res->fetch_assoc()) {
            fputcsv($output, [
                $no++, 
                $r['kode_barang'], 
                $r['nama_barang'], 
                $r['nama_kategori'] ?? '-', 
                $r['spesifikasi'], 
                $r['harga_beli'], 
                $r['harga_jual'], 
                $r['stok'], 
                $r['nama_supplier'] ?? '-', 
                $r['status']
            ]);
        }
    }
    fclose($output);
    exit;
}

// ==========================================
// 1. PROSES SIMPAN & EDIT (+ FOTO UPLOAD)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && (isset($_POST['simpan_barang']) || isset($_POST['simpan_tambah_lagi']))) {
    $id          = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $kode_barang = bersihkan_input($koneksi, $_POST['kode_barang'] ?? '');
    $id_kategori = (int)($_POST['id_kategori'] ?? 0);
    $nama_barang = bersihkan_input($koneksi, $_POST['nama_barang'] ?? '');
    $spesifikasi = bersihkan_input($koneksi, $_POST['spesifikasi'] ?? '');
    $harga_beli  = (int)($_POST['harga_beli'] ?? 0);
    $harga_jual  = (int)($_POST['harga_jual'] ?? 0);
    $stok        = (int)($_POST['stok'] ?? 0);
    $id_supplier = (int)($_POST['id_supplier'] ?? 0);
    $status      = bersihkan_input($koneksi, $_POST['status'] ?? 'Aktif');

    // Handle Upload Foto Barang
    $foto_sql = "";
    if (isset($_FILES['foto_barang']) && $_FILES['foto_barang']['error'] == 0) {
        $ext = strtolower(pathinfo($_FILES['foto_barang']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed_ext)) {
            $new_name = 'unit_' . time() . '_' . rand(100,999) . '.' . $ext;
            if (move_uploaded_file($_FILES['foto_barang']['tmp_name'], $upload_dir . $new_name)) {
                $foto_sql = ", foto = '$new_name'";
            }
        }
    }

    // Validasi Anti-Duplikat SKU
    if ($id > 0) {
        $cek = $koneksi->query("SELECT id FROM barang WHERE kode_barang = '$kode_barang' AND id != $id");
    } else {
        $cek = $koneksi->query("SELECT id FROM barang WHERE kode_barang = '$kode_barang'");
    }

    if ($cek && $cek->num_rows > 0) {
        $message = "<div class='alert alert-danger'>Gagal! Kode Barang / SKU <b>'$kode_barang'</b> sudah terdaftar di sistem.</div>";
        if (isset($_POST['simpan_tambah_lagi'])) { $auto_open_modal = true; }
    } else {
        if ($id > 0) {
            $q = "UPDATE barang SET kode_barang='$kode_barang', id_kategori='$id_kategori', nama_barang='$nama_barang', spesifikasi='$spesifikasi', harga_beli='$harga_beli', harga_jual='$harga_jual', stok='$stok', id_supplier='$id_supplier', status='$status' $foto_sql WHERE id=$id";
        } else {
            // Jika mode tambah baru, cek apakah ada foto terupload
            $nama_foto_val = '';
            if (isset($_FILES['foto_barang']) && $_FILES['foto_barang']['error'] == 0) {
                $ext = strtolower(pathinfo($_FILES['foto_barang']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, $allowed_ext)) {
                    $nama_foto_val = 'unit_' . time() . '_' . rand(100,999) . '.' . $ext;
                    move_uploaded_file($_FILES['foto_barang']['tmp_name'], $upload_dir . $nama_foto_val);
                }
            }
            $q = "INSERT INTO barang (kode_barang, id_kategori, nama_barang, spesifikasi, harga_beli, harga_jual, stok, id_supplier, status, foto) VALUES ('$kode_barang', '$id_kategori', '$nama_barang', '$spesifikasi', '$harga_beli', '$harga_jual', '$stok', '$id_supplier', '$status', '$nama_foto_val')";
        }
        
        if ($koneksi->query($q)) {
            $message = "<div class='alert alert-success'>Data barang & foto unit berhasil disimpan!</div>";
            if (isset($_POST['simpan_tambah_lagi'])) {
                $auto_open_modal = true;
            }
        } else {
            $message = "<div class='alert alert-danger'>Error: " . $koneksi->error . "</div>";
        }
    }
}

// ==========================================
// 2. PROSES IMPORT CSV DATA BARANG
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['import_barang'])) {
    if (isset($_FILES['file_import']['tmp_name']) && !empty($_FILES['file_import']['tmp_name'])) {
        $file = $_FILES['file_import']['tmp_name'];
        $handle = fopen($file, "r");
        $countSuccess = 0;
        $countSkip = 0;
        $rowNum = 0;

        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $rowNum++;
            if ($rowNum == 1) continue;

            $kode = bersihkan_input($koneksi, $data[0] ?? '');
            $id_kat = (int)($data[1] ?? 0);
            $nama = bersihkan_input($koneksi, $data[2] ?? '');
            $spek = bersihkan_input($koneksi, $data[3] ?? '');
            $hbeli = (int)preg_replace('/[^0-9]/', '', $data[4] ?? '0');
            $hjual = (int)preg_replace('/[^0-9]/', '', $data[5] ?? '0');
            $stok = (int)preg_replace('/[^0-9]/', '', $data[6] ?? '0');
            $id_sup = (int)($data[7] ?? 0);
            $status = bersihkan_input($koneksi, $data[8] ?? 'Aktif');

            if (!empty($kode) && !empty($nama)) {
                $cek_sku = $koneksi->query("SELECT id FROM barang WHERE kode_barang = '$kode'");
                if ($cek_sku && $cek_sku->num_rows > 0) {
                    $countSkip++;
                } else {
                    $q = "INSERT INTO barang (kode_barang, id_kategori, nama_barang, spesifikasi, harga_beli, harga_jual, stok, id_supplier, status) VALUES ('$kode', '$id_kat', '$nama', '$spek', '$hbeli', '$hjual', '$stok', '$id_sup', '$status')";
                    if ($koneksi->query($q)) {
                        $countSuccess++;
                    }
                }
            } else {
                $countSkip++;
            }
        }
        fclose($handle);
        $message = "<div class='alert alert-success'>🎉 Import Selesai! Berhasil: <b>$countSuccess data</b>. Dilewati: <b>$countSkip data</b>.</div>";
    } else {
        $message = "<div class='alert alert-danger'>Gagal! Pilih file CSV terlebih dahulu.</div>";
    }
}

// ==========================================
// 3. PROSES HAPUS DATA
// ==========================================
if (isset($_GET['hapus'])) {
    $id_hapus = (int)$_GET['hapus'];
    // Ambil foto lama untuk dihapus filenya jika ada
    $q_f = $koneksi->query("SELECT foto FROM barang WHERE id = $id_hapus");
    if($q_f && $q_f->num_rows > 0) {
        $f_data = $q_f->fetch_assoc();
        if(!empty($f_data['foto']) && file_exists('uploads/' . $f_data['foto'])) {
            @unlink('uploads/' . $f_data['foto']);
        }
    }
    if($koneksi->query("DELETE FROM barang WHERE id = $id_hapus")){
        $message = "<div class='alert alert-success'>Data barang berhasil dihapus!</div>";
    }
}

// ==========================================
// 4. PAGINATION & SEARCH
// ==========================================
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? bersihkan_input($koneksi, $_GET['search']) : '';

$where = "";
if ($search != '') {
    $where = "WHERE b.nama_barang LIKE '%$search%' OR b.kode_barang LIKE '%$search%'";
}

$q_barang = $koneksi->query("SELECT b.*, k.nama_kategori, s.nama_supplier 
                             FROM barang b 
                             LEFT JOIN kategori k ON b.id_kategori = k.id 
                             LEFT JOIN supplier s ON b.id_supplier = s.id 
                             $where ORDER BY b.id DESC LIMIT $limit OFFSET $offset");

$q_total = $koneksi->query("SELECT COUNT(*) as total FROM barang b $where");
$total_data = $q_total->fetch_assoc()['total'];
$total_page = ceil($total_data / $limit);

$q_kat = $koneksi->query("SELECT * FROM kategori ORDER BY nama_kategori ASC");
$q_sup = $koneksi->query("SELECT * FROM supplier ORDER BY nama_supplier ASC");

$opt_kategori = ""; 
if($q_kat) {
    while($k = $q_kat->fetch_assoc()) { $opt_kategori .= "<option value='{$k['id']}'>{$k['nama_kategori']}</option>"; }
}

$opt_supplier = ""; 
if($q_sup) {
    while($s = $q_sup->fetch_assoc()) { $opt_supplier .= "<option value='{$s['id']}'>{$s['nama_supplier']}</option>"; }
}

require_once 'header.php';
require_once 'sidebar.php';
?>

<style>
    :root {
        --glow-color: #38bdf8;
        --glass-bg: rgba(36, 36, 36, 0.4);
        --glass-border: rgba(255, 255, 255, 0.1);
    }
    .module-container { padding: 30px; color: #fff; }
    .toolbar { display: flex; justify-content: space-between; margin-bottom: 20px; align-items: center; flex-wrap: wrap; gap: 15px; }
    .search-capsule {
        padding: 10px 20px; border-radius: 25px; background: rgba(0,0,0,0.3);
        border: 1px solid var(--glass-border); color: #fff; width: 300px; outline: none; transition: all 0.3s ease;
    }
    .search-capsule:focus { box-shadow: 0 0 15px rgba(56, 189, 248, 0.5); border-color: var(--glow-color); }
    .btn { padding: 10px 20px; border-radius: 8px; cursor: pointer; border: none; color: #fff; font-weight: bold; transition: 0.3s; text-decoration: none; display: inline-block; font-size: 13px;}
    .btn-primary { background: #38bdf8; color: #000; }
    .btn-primary:hover { box-shadow: 0 0 15px #38bdf8; }
    .btn-success { background: #10b981; color: #fff; }
    .btn-success:hover { box-shadow: 0 0 15px #10b981; }
    .btn-danger { background: #ef4444; color: #fff; padding: 6px 12px;}
    .btn-edit { background: #f59e0b; color: #000; padding: 6px 12px;}
    
    .glass-table {
        width: 100%; border-collapse: collapse; background: var(--glass-bg);
        backdrop-filter: blur(10px); border-radius: 8px; overflow: hidden; font-size: 13px;
    }
    .glass-table th, .glass-table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid var(--glass-border); vertical-align: middle; }
    .glass-table th { background: rgba(0,0,0,0.5); color: #38bdf8; text-transform: uppercase; }
    
    .badge { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; }
    .badge-aktif { background: rgba(16, 185, 129, 0.2); color: #34d399; border: 1px solid #10b981; }
    .badge-nonaktif { background: rgba(239, 68, 68, 0.2); color: #f87171; border: 1px solid #ef4444; }

    .thumb-foto { width: 45px; height: 45px; border-radius: 6px; object-fit: cover; border: 1px solid var(--glass-border); background: #000; }

    .modal-overlay {
        display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0, 0, 0, 0.7); backdrop-filter: blur(5px);
        justify-content: center; align-items: center; z-index: 1000; overflow-y: auto; padding: 20px;
    }
    .modal-content {
        background: rgba(25, 25, 35, 0.95); border: 1px solid var(--glow-color);
        border-radius: 12px; padding: 25px; width: 700px; max-width: 100%; box-shadow: 0 10px 30px rgba(0,0,0,0.5);
    }
    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
    .form-group { margin-bottom: 10px; }
    .form-group label { display: block; margin-bottom: 5px; color: #ccc; font-size: 13px; font-weight: bold; }
    .form-control {
        width: 100%; padding: 10px; border-radius: 6px; background: rgba(0,0,0,0.3);
        border: 1px solid var(--glass-border); color: #fff; outline: none; box-sizing: border-box; font-size: 13px;
    }
    .form-control:focus { border-color: var(--glow-color); }
    .pagination { margin-top: 20px; display: flex; gap: 5px; justify-content: flex-end; }
    .btn-page { background: var(--glass-bg); border: 1px solid var(--glass-border); color: #fff; padding: 6px 12px; cursor: pointer; border-radius: 5px; text-decoration: none; font-size: 13px;}
    .btn-page.active { background: var(--glow-color); color: #000; font-weight: bold; }
    .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-size: 14px;}
    .alert-success { background: rgba(16, 185, 129, 0.2); border: 1px solid #10b981; color: #34d399; }
    .alert-danger { background: rgba(239, 68, 68, 0.2); border: 1px solid #ef4444; color: #f87171; }
</style>

<div class="main-content module-container">
    <h2>📦 Master Data Barang</h2>
    <p style="color: #94a3b8; margin-top: -15px;">Kelola informasi stok, SKU, dan foto fisik unit produk.</p>
    
    <?= $message ?>

    <div class="toolbar">
        <form method="GET" style="display:flex; gap:10px;">
            <input type="text" name="search" class="search-capsule" placeholder="🔍 Cari Kode Barang/SKU atau Nama..." value="<?= htmlspecialchars($search) ?>">
            <button type="submit" class="btn btn-primary" style="padding: 10px 15px;">Cari</button>
        </form>
        
        <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
            <button class="btn btn-primary" onclick="openModalTambah()">+ Tambah Barang</button>
            <button class="btn btn-success" onclick="openModalImport()">📤 Import CSV</button>
            <button class="btn" style="background:#0284c7; color:#fff;" onclick="exportDataCSV()">📥 Export CSV</button>
        </div>
    </div>

    <table class="glass-table">
        <thead>
            <tr>
                <th style="width:5%; text-align:center;">No</th>
                <th style="text-align:center;">Foto</th>
                <th>SKU / KODE</th>
                <th>Nama Barang</th>
                <th>Kategori</th>
                <th style="text-align:right;">Harga Jual</th>
                <th style="text-align:center;">Stok</th>
                <th style="text-align:center;">Status</th>
                <th style="text-align:center; width:12%;">Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if($q_barang && $q_barang->num_rows > 0): ?>
                <?php $no = $offset + 1; while($row = $q_barang->fetch_assoc()): 
                    $dataJson = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
                    $img_src = (!empty($row['foto']) && file_exists('uploads/' . $row['foto'])) ? 'uploads/' . $row['foto'] : '';
                ?>
                <tr>
                    <td style="text-align:center;"><?= $no++ ?></td>
                    <td style="text-align:center;">
                        <?php if($img_src != ''): ?>
                            <img src="<?= $img_src ?>" class="thumb-foto" alt="Foto">
                        <?php else: ?>
                            <span style="font-size:10px; color:#777;">No Photo</span>
                        <?php endif; ?>
                    </td>
                    <td><b><?= $row['kode_barang'] ?></b></td>
                    <td><?= $row['nama_barang'] ?></td>
                    <td><?= $row['nama_kategori'] ?? '-' ?></td>
                    <td style="text-align:right; color:#34d399; font-weight:bold; white-space: nowrap;">Rp <?= number_format($row['harga_jual'],0,',','.') ?></td>
                    <td style="text-align:center; font-weight:bold; color: <?= $row['stok'] > 5 ? '#fff' : '#f87171' ?>;"><?= $row['stok'] ?></td>
                    <td style="text-align:center;">
                        <span class="badge <?= $row['status'] == 'Aktif' ? 'badge-aktif' : 'badge-nonaktif' ?>"><?= $row['status'] ?></span>
                    </td>
                    <td style="text-align:center;">
                        <button class="btn btn-edit" onclick='openModalEdit(<?= $dataJson ?>)'>✏️</button>
                        <a href="barang.php?hapus=<?= $row['id'] ?>" class="btn btn-danger" onclick="return confirm('Yakin ingin menghapus SKU ini secara permanen?')">🗑️</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="9" style="text-align:center; padding: 20px; color:#aaa;">Data barang tidak ditemukan.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="pagination">
        <?php for($i=1; $i<=$total_page; $i++): ?>
            <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>" class="btn-page <?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
</div>

<div class="modal-overlay" id="modalBarang">
    <div class="modal-content">
        <h3 style="margin-top:0; color:var(--glow-color); border-bottom:1px solid var(--glass-border); padding-bottom:10px;" id="modalTitle">Tambah Barang Baru</h3>
        
        <form method="POST" action="" enctype="multipart/form-data">
            <input type="hidden" name="id" id="input_id" value="0">
            
            <div class="form-grid">
                <div class="form-group">
                    <label>Kode Barang / SKU <span style="color:red;">*</span></label>
                    <input type="text" name="kode_barang" id="input_kode" class="form-control" required placeholder="Contoh: LAPTOP001">
                </div>
                <div class="form-group">
                    <label>Kategori <span style="color:red;">*</span></label>
                    <select name="id_kategori" id="input_kategori" class="form-control" required>
                        <option value="">-- Pilih Kategori --</option>
                        <?= $opt_kategori ?>
                    </select>
                </div>
                <div class="form-group" style="grid-column: span 2;">
                    <label>Nama / Merek / Tipe Barang <span style="color:red;">*</span></label>
                    <input type="text" name="nama_barang" id="input_nama" class="form-control" required placeholder="Contoh: Asus TUF Gaming i5 13th Gen">
                </div>
                <div class="form-group" style="grid-column: span 2;">
                    <label>Spesifikasi Detail</label>
                    <textarea name="spesifikasi" id="input_spek" class="form-control" rows="2" placeholder="Contoh: RAM 16GB, SSD 512GB, RTX 3050"></textarea>
                </div>
                <div class="form-group" style="grid-column: span 2;">
                    <label>Foto Fisik Unit (Format: JPG, PNG, WEBP)</label>
                    <input type="file" name="foto_barang" class="form-control" accept=".png, .jpg, .jpeg, .webp" style="padding: 6px;">
                    <small style="color: #aaa;">Unggah foto asli unit laptop second agar terlihat jelas oleh calon pembeli.</small>
                </div>
                <div class="form-group">
                    <label>Harga Beli / Modal (Rp)</label>
                    <input type="number" name="harga_beli" id="input_beli" class="form-control" required min="0" placeholder="0">
                </div>
                <div class="form-group">
                    <label>Harga Jual Dasar (Rp)</label>
                    <input type="number" name="harga_jual" id="input_jual" class="form-control" required min="0" placeholder="0">
                </div>
                <div class="form-group">
                    <label>Stok Awal Fisik</label>
                    <input type="number" name="stok" id="input_stok" class="form-control" required min="0" placeholder="0">
                </div>
                <div class="form-group">
                    <label>Supplier / Pemasok</label>
                    <select name="id_supplier" id="input_supplier" class="form-control">
                        <option value="0">-- Tanpa Supplier --</option>
                        <?= $opt_supplier ?>
                    </select>
                </div>
                <div class="form-group" style="grid-column: span 2;">
                    <label>Status Produk</label>
                    <select name="status" id="input_status" class="form-control">
                        <option value="Aktif">Aktif (Tampil di Kasir & Landing Page)</option>
                        <option value="Nonaktif">Nonaktif (Disembunyikan)</option>
                    </select>
                </div>
            </div>

            <div style="text-align: right; margin-top: 25px; display: flex; gap: 8px; justify-content: flex-end; flex-wrap: wrap;">
                <button type="button" class="btn" style="background:#52525b;" onclick="closeModal('modalBarang')">Batal</button>
                <button type="submit" name="simpan_tambah_lagi" id="btnTambahLagi" class="btn btn-success">➕ Simpan & Tambah Lagi</button>
                <button type="submit" name="simpan_barang" class="btn btn-primary">💾 Simpan Data</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="modalImport">
    <div class="modal-content" style="width: 500px;">
        <h3 style="margin-top:0; color:#10b981; border-bottom:1px solid rgba(255,255,255,0.2); padding-bottom:10px;">📤 Import Barang / SKU dari CSV</h3>
        <p style="color:#aaa; font-size:12px; margin-bottom:15px;">Gunakan template CSV resmi agar format data tidak salah saat diunggah.</p>
        
        <div style="margin-bottom: 20px;">
            <a href="barang.php?download_template=1" class="btn" style="background:#0284c7; color:#fff; width:100%; text-align:center; box-sizing:border-box;">
                📄 Download Template CSV Barang
            </a>
        </div>

        <form method="POST" action="" enctype="multipart/form-data">
            <div class="form-group">
                <label>Pilih File CSV <span style="color:red;">*</span></label>
                <input type="file" name="file_import" class="form-control" accept=".csv" required style="padding: 6px;">
            </div>

            <div style="text-align: right; margin-top: 20px;">
                <button type="button" class="btn" style="background:#52525b; margin-right:8px;" onclick="closeModal('modalImport')">Batal</button>
                <button type="submit" name="import_barang" class="btn btn-success">📤 Upload & Import Data</button>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        <?php if ($auto_open_modal): ?>
            openModalTambah();
        <?php endif; ?>
    });

    function openModalTambah() {
        document.getElementById('modalTitle').innerText = 'Tambah Barang / SKU Baru';
        document.getElementById('input_id').value = '0';
        document.getElementById('input_kode').value = '';
        document.getElementById('input_kategori').value = '';
        document.getElementById('input_nama').value = '';
        document.getElementById('input_spek').value = '';
        document.getElementById('input_beli').value = '';
        document.getElementById('input_jual').value = '';
        document.getElementById('input_stok').value = '';
        document.getElementById('input_supplier').value = '0';
        document.getElementById('input_status').value = 'Aktif';
        document.getElementById('btnTambahLagi').style.display = 'inline-block';
        document.getElementById('modalBarang').style.display = 'flex';
    }

    function openModalEdit(data) {
        document.getElementById('modalTitle').innerText = 'Edit Barang / SKU';
        document.getElementById('input_id').value = data.id;
        document.getElementById('input_kode').value = data.kode_barang;
        document.getElementById('input_kategori').value = data.id_kategori;
        document.getElementById('input_nama').value = data.nama_barang;
        document.getElementById('input_spek').value = data.spesifikasi;
        document.getElementById('input_beli').value = data.harga_beli;
        document.getElementById('input_jual').value = data.harga_jual;
        document.getElementById('input_stok').value = data.stok;
        document.getElementById('input_supplier').value = data.id_supplier || 0;
        document.getElementById('input_status').value = data.status;
        document.getElementById('btnTambahLagi').style.display = 'none';
        document.getElementById('modalBarang').style.display = 'flex';
    }

    function openModalImport() {
        document.getElementById('modalImport').style.display = 'flex';
    }

    function closeModal(modalId) {
        if (!modalId) modalId = 'modalBarang';
        document.getElementById(modalId).style.display = 'none';
    }

    function exportDataCSV() {
        let search = document.querySelector('input[name="search"]').value;
        window.location.href = `barang.php?export_csv=1&search=${encodeURIComponent(search)}`;
    }
</script>

<?php require_once 'footer.php'; ?>
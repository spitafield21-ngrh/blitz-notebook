<?php
// ==========================================
// FILE: pelanggan.php
// FUNGSI: Master Data Pelanggan (CRM) + Live Search + Bulk Import/Export CSV
// ==========================================
require_once 'config.php';

// Pastikan user sudah login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// ------------------------------------------
// 🛠️ AUTO-HEALING DATABASE: Bikin Tabel Otomatis Jika Belum Ada
// ------------------------------------------
$cek_tabel = $koneksi->query("SHOW TABLES LIKE 'pelanggan'");
if ($cek_tabel->num_rows == 0) {
    $koneksi->query("CREATE TABLE pelanggan (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        nama_pelanggan VARCHAR(150) NOT NULL,
        no_telp VARCHAR(20) NOT NULL,
        alamat TEXT,
        total_poin INT(11) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
}

// ==========================================
// 📄 1. FITUR DOWNLOAD TEMPLATE CSV FOR IMPORT
// ==========================================
if (isset($_GET['download_template'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=template_import_pelanggan.csv');
    $output = fopen('php://output', 'w');
    // Header CSV
    fputcsv($output, ['Nama Pelanggan', 'No WhatsApp', 'Alamat', 'Total Poin']);
    // Baris Contoh Data
    fputcsv($output, ['Budi Santoso', '081234567890', 'Jl. Merdeka No. 12 Jakarta', '10']);
    fputcsv($output, ['Siti Rahma', '085712345678', 'Jl. Sudirman No. 45 Bandung', '0']);
    fclose($output);
    exit;
}

// ==========================================
// 📤 2. FITUR EXPORT ALL DATA PELANGGAN TO CSV
// ==========================================
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=data_pelanggan_' . date('Y-m-d_H-i') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Nama Pelanggan', 'No WhatsApp', 'Alamat', 'Total Poin', 'Tanggal Terdaftar']);
    
    $q_export = $koneksi->query("SELECT * FROM pelanggan ORDER BY id DESC");
    while ($r = $q_export->fetch_assoc()) {
        fputcsv($output, [$r['id'], $r['nama_pelanggan'], $r['no_telp'], $r['alamat'], $r['total_poin'], $r['created_at']]);
    }
    fclose($output);
    exit;
}

$message = '';
$open_modal_lagi = false;

// ==========================================
// 📥 3. PROSES IMPORT BULK DATA PELANGGAN FROM CSV
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['import_pelanggan'])) {
    if (isset($_FILES['file_csv']) && $_FILES['file_csv']['error'] == 0) {
        $fileName = $_FILES['file_csv']['tmp_name'];
        $ext = strtolower(pathinfo($_FILES['file_csv']['name'], PATHINFO_EXTENSION));
        
        if ($ext !== 'csv') {
            $message = "<div class='alert alert-danger'>Gagal! File harus berformat <b>.CSV</b></div>";
        } else {
            $file = fopen($fileName, "r");
            $sukses = 0;
            $gagal = 0;
            $row_count = 0;
            
            while (($column = fgetcsv($file, 1000, ",")) !== FALSE) {
                $row_count++;
                // Skip baris judul/header CSV
                if ($row_count == 1 && (strtolower($column[0]) == 'nama pelanggan' || strtolower($column[0]) == 'nama')) {
                    continue;
                }
                
                $nama_pelanggan = bersihkan_input($koneksi, $column[0] ?? '');
                $no_telp        = bersihkan_input($koneksi, $column[1] ?? '');
                $alamat         = bersihkan_input($koneksi, $column[2] ?? '');
                $total_poin     = (int)($column[3] ?? 0);
                
                if (!empty($nama_pelanggan) && !empty($no_telp)) {
                    // Validasi Anti-Duplikat No WA
                    $cek = $koneksi->query("SELECT id FROM pelanggan WHERE no_telp = '$no_telp'");
                    if ($cek && $cek->num_rows > 0) {
                        $gagal++;
                    } else {
                        $q_ins = "INSERT INTO pelanggan (nama_pelanggan, no_telp, alamat, total_poin) VALUES ('$nama_pelanggan', '$no_telp', '$alamat', '$total_poin')";
                        if ($koneksi->query($q_ins)) {
                            $sukses++;
                        } else {
                            $gagal++;
                        }
                    }
                }
            }
            fclose($file);
            $message = "<div class='alert alert-success'>✅ Import Selesai! <b>$sukses data baru berhasil diimpor</b>, $gagal data dilewati (duplikat/invalid).</div>";
        }
    } else {
        $message = "<div class='alert alert-danger'>Gagal upload! Silakan pilih file CSV yang valid.</div>";
    }
}

// ==========================================
// 4. PROSES SIMPAN & EDIT MANUAL (SINGLE DATA)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && (isset($_POST['simpan_pelanggan']) || isset($_POST['simpan_lagi']))) {
    $id             = (int)($_POST['id'] ?? 0);
    $nama_pelanggan = bersihkan_input($koneksi, $_POST['nama_pelanggan'] ?? '');
    $no_telp        = bersihkan_input($koneksi, $_POST['no_telp'] ?? '');
    $alamat         = bersihkan_input($koneksi, $_POST['alamat'] ?? '');
    $total_poin     = (int)($_POST['total_poin'] ?? 0);

    // Validasi Anti-Duplikat Nomor WA
    if ($id > 0) {
        $cek = $koneksi->query("SELECT id FROM pelanggan WHERE no_telp = '$no_telp' AND id != $id");
    } else {
        $cek = $koneksi->query("SELECT id FROM pelanggan WHERE no_telp = '$no_telp'");
    }

    if ($cek && $cek->num_rows > 0) {
        $message = "<div class='alert alert-danger'>Gagal! Nomor WA / Telepon <b>'$no_telp'</b> sudah terdaftar untuk pelanggan lain.</div>";
    } else {
        if ($id > 0) {
            // UPDATE DATA
            $q = "UPDATE pelanggan SET nama_pelanggan='$nama_pelanggan', no_telp='$no_telp', alamat='$alamat', total_poin='$total_poin' WHERE id=$id";
        } else {
            // INSERT DATA BARU
            $q = "INSERT INTO pelanggan (nama_pelanggan, no_telp, alamat, total_poin) VALUES ('$nama_pelanggan', '$no_telp', '$alamat', '$total_poin')";
        }
        
        if ($koneksi->query($q)) {
            $message = "<div class='alert alert-success'>Data pelanggan <b>'$nama_pelanggan'</b> berhasil disimpan!</div>";
            if (isset($_POST['simpan_lagi'])) {
                $open_modal_lagi = true;
            }
        } else {
            $message = "<div class='alert alert-danger'>Error: " . $koneksi->error . "</div>";
        }
    }
}

// ==========================================
// 5. PROSES HAPUS DATA
// ==========================================
if (isset($_GET['hapus'])) {
    $id_hapus = (int)$_GET['hapus'];
    if($koneksi->query("DELETE FROM pelanggan WHERE id = $id_hapus")){
        $message = "<div class='alert alert-success'>Data pelanggan berhasil dihapus!</div>";
    }
}

// ==========================================
// 6. PAGINATION & SEARCH (10 BARIS PER HALAMAN)
// ==========================================
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? bersihkan_input($koneksi, $_GET['search']) : '';

$where = "";
if ($search != '') {
    $where = "WHERE nama_pelanggan LIKE '%$search%' OR no_telp LIKE '%$search%' OR alamat LIKE '%$search%'";
}

// Ambil Data Pelanggan
$q_pelanggan = $koneksi->query("SELECT * FROM pelanggan $where ORDER BY id DESC LIMIT $limit OFFSET $offset");

// Hitung Total Data untuk Pagination
$q_total = $koneksi->query("SELECT COUNT(*) as total FROM pelanggan $where");
$total_data = $q_total->fetch_assoc()['total'];
$total_page = ceil($total_data / $limit);

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
    .action-group { display: flex; gap: 10px; flex-wrap: wrap; }
    
    .search-capsule {
        padding: 10px 20px; border-radius: 25px; background: rgba(0,0,0,0.2);
        border: 1px solid var(--glass-border); color: #fff; width: 280px; outline: none; transition: all 0.3s ease;
    }
    .search-capsule:focus { box-shadow: 0 0 15px rgba(56, 189, 248, 0.5); border-color: var(--glow-color); }
    
    .btn { padding: 10px 18px; border-radius: 8px; cursor: pointer; border: none; color: #fff; font-weight: bold; transition: 0.3s; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; font-size: 14px; }
    .btn-primary { background: #38bdf8; color: #000; }
    .btn-primary:hover { box-shadow: 0 0 15px #38bdf8; }
    .btn-success { background: #10b981; color: #fff; }
    .btn-success:hover { box-shadow: 0 0 15px #10b981; }
    .btn-info { background: #0284c7; color: #fff; }
    .btn-info:hover { box-shadow: 0 0 15px #0284c7; }
    .btn-export { background: #059669; color: #fff; }
    .btn-export:hover { box-shadow: 0 0 15px #059669; }
    .btn-danger { background: #ef4444; color: #fff; padding: 6px 12px; font-size: 13px;}
    .btn-edit { background: #f59e0b; color: #000; padding: 6px 12px; font-size: 13px;}
    
    .glass-table {
        width: 100%; border-collapse: collapse; background: var(--glass-bg);
        backdrop-filter: blur(10px); border-radius: 8px; overflow: hidden; font-size: 14px;
    }
    .glass-table th, .glass-table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid var(--glass-border); }
    .glass-table th { background: rgba(0,0,0,0.5); color: #38bdf8; text-transform: uppercase; }
    
    .badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; }
    .badge-poin { background: rgba(245, 158, 11, 0.2); color: #fcd34d; border: 1px solid #f59e0b; }

    /* Modal Styling */
    .modal-overlay {
        display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0, 0, 0, 0.7); backdrop-filter: blur(5px);
        justify-content: center; align-items: center; z-index: 1000; overflow-y: auto; padding: 20px;
    }
    .modal-content {
        background: rgba(25, 25, 35, 0.95); border: 1px solid var(--glow-color);
        border-radius: 12px; padding: 25px; width: 550px; max-width: 100%; box-shadow: 0 10px 30px rgba(0,0,0,0.5);
    }
    .form-group { margin-bottom: 15px; }
    .form-group label { display: block; margin-bottom: 5px; color: #ccc; font-size: 13px; }
    .form-control {
        width: 100%; padding: 10px; border-radius: 6px; background: rgba(0,0,0,0.3);
        border: 1px solid var(--glass-border); color: #fff; outline: none; box-sizing: border-box;
    }
    .form-control:focus { border-color: var(--glow-color); }
    
    .pagination { margin-top: 20px; display: flex; gap: 5px; justify-content: flex-end; }
    .btn-page { background: var(--glass-bg); border: 1px solid var(--glass-border); color: #fff; padding: 8px 12px; cursor: pointer; border-radius: 5px; text-decoration: none;}
    .btn-page.active { background: var(--glow-color); color: #000; font-weight: bold; }
    
    .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; }
    .alert-success { background: rgba(16, 185, 129, 0.2); border: 1px solid #10b981; color: #34d399; }
    .alert-danger { background: rgba(239, 68, 68, 0.2); border: 1px solid #ef4444; color: #f87171; }
</style>

<div class="main-content module-container">
    <h2>👥 Master Data Pelanggan (CRM)</h2>
    <p style="color: var(--text-muted); margin-top: -15px;">Kelola database pelanggan, poin loyalitas, serta import/export massal.</p>
    
    <?= $message ?>

    <div class="toolbar">
        <form method="GET" id="formSearch" style="display:flex; gap:10px;">
            <input type="text" name="search" id="inputSearch" class="search-capsule" placeholder="Ketik Nama / No WA (Live Search)..." value="<?= htmlspecialchars($search) ?>" oninput="liveSearchTrigger()">
        </form>

        <div class="action-group">
            <button class="btn btn-info" onclick="openModalImport()">📥 Import CSV</button>
            <a href="pelanggan.php?export=csv" class="btn btn-export">📤 Export CSV</a>
            <button class="btn btn-primary" onclick="openModalTambah()">+ Tambah Pelanggan</button>
        </div>
    </div>

    <table class="glass-table">
        <thead>
            <tr>
                <th style="width:5%; text-align:center;">No</th>
                <th>Nama Pelanggan</th>
                <th>No WhatsApp</th>
                <th>Alamat</th>
                <th style="text-align:center;">Total Poin</th>
                <th style="text-align:center; width:15%;">Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if($q_pelanggan && $q_pelanggan->num_rows > 0): ?>
                <?php $no = $offset + 1; while($row = $q_pelanggan->fetch_assoc()): 
                    $dataJson = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
                ?>
                <tr>
                    <td style="text-align:center;"><?= $no++ ?></td>
                    <td><b><?= htmlspecialchars($row['nama_pelanggan']) ?></b></td>
                    <td><?= htmlspecialchars($row['no_telp']) ?></td>
                    <td><?= htmlspecialchars($row['alamat']) ?></td>
                    <td style="text-align:center;">
                        <span class="badge badge-poin">⭐ <?= $row['total_poin'] ?> Poin</span>
                    </td>
                    <td style="text-align:center;">
                        <button class="btn btn-edit" onclick='openModalEdit(<?= $dataJson ?>)'>✏️</button>
                        <a href="pelanggan.php?hapus=<?= $row['id'] ?>" class="btn btn-danger" onclick="return confirm('Yakin ingin menghapus data pelanggan ini?')">🗑️</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="6" style="text-align:center; padding: 25px; color:#aaa;">Data pelanggan tidak ditemukan.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="pagination">
        <?php for($i=1; $i<=$total_page; $i++): ?>
            <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>" class="btn-page <?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
</div>

<div class="modal-overlay" id="modalPelanggan">
    <div class="modal-content">
        <h3 style="margin-top:0; color:var(--glow-color); border-bottom:1px solid var(--glass-border); padding-bottom:10px;" id="modalTitle">Tambah Pelanggan Baru</h3>
        
        <form method="POST" action="">
            <input type="hidden" name="id" id="input_id" value="0">
            
            <div class="form-group">
                <label>Nama Lengkap <span style="color:red;">*</span></label>
                <input type="text" name="nama_pelanggan" id="input_nama" class="form-control" required placeholder="Contoh: Budi Santoso">
            </div>
            
            <div class="form-group">
                <label>Nomor WhatsApp / Telepon <span style="color:red;">*</span></label>
                <input type="text" name="no_telp" id="input_telp" class="form-control" required placeholder="Contoh: 081234567890">
            </div>
            
            <div class="form-group">
                <label>Alamat Domisili</label>
                <textarea name="alamat" id="input_alamat" class="form-control" rows="3" placeholder="Contoh: Jl. Merdeka No. 45"></textarea>
            </div>
            
            <div class="form-group">
                <label>Total Poin Loyalitas (Opsional)</label>
                <input type="number" name="total_poin" id="input_poin" class="form-control" value="0" min="0">
                <small style="color:#aaa; font-size: 11px;">*Poin dapat bertambah otomatis saat transaksi kasir berlangsung.</small>
            </div>

            <div style="text-align: right; margin-top: 25px; display: flex; gap: 10px; justify-content: flex-end; flex-wrap: wrap;">
                <button type="button" class="btn" style="background:#52525b;" onclick="closeModal()">Batal</button>
                <button type="submit" name="simpan_lagi" class="btn btn-success">➕ Simpan & Tambah Lagi</button>
                <button type="submit" name="simpan_pelanggan" class="btn btn-primary">💾 Simpan Data</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="modalImport">
    <div class="modal-content">
        <h3 style="margin-top:0; color:#0284c7; border-bottom:1px solid var(--glass-border); padding-bottom:10px;">📥 Import Data Pelanggan (Bulk Upload)</h3>
        
        <div style="background: rgba(2, 132, 199, 0.15); border: 1px solid #0284c7; padding: 12px; border-radius: 6px; margin-bottom: 20px; font-size: 13px; line-height: 1.5; color: #bae6fd;">
            💡 <b>Petunjuk Import CSV:</b>
            <ol style="margin: 5px 0 0 0; padding-left: 20px;">
                <li>Gunakan file berformat <b>.CSV</b>.</li>
                <li>Unduh template standar agar kolom sesuai dan anti-error.</li>
                <li>Nomor WhatsApp yang sudah terdaftar akan otomatis dilewati (anti-duplikat).</li>
            </ol>
            <div style="margin-top: 10px;">
                <a href="pelanggan.php?download_template=1" class="btn btn-success" style="padding: 6px 12px; font-size: 12px;">📄 Download Template CSV</a>
            </div>
        </div>

        <form method="POST" action="" enctype="multipart/form-data">
            <div class="form-group">
                <label>Pilih File CSV <span style="color:red;">*</span></label>
                <input type="file" name="file_csv" class="form-control" accept=".csv" required style="padding: 8px;">
            </div>

            <div style="text-align: right; margin-top: 25px; display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" class="btn" style="background:#52525b;" onclick="closeModalImport()">Batal</button>
                <button type="submit" name="import_pelanggan" class="btn btn-info">🚀 Mulai Import Data</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Live Search JS (Debounced Auto-Submit tanpa reload berisik)
    let searchTimer;
    function liveSearchTrigger() {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => {
            document.getElementById('formSearch').submit();
        }, 500); // Trigger otomatis 0.5 detik setelah berhenti mengetik
    }

    // Modal Form Single
    function openModalTambah() {
        document.getElementById('modalTitle').innerText = 'Tambah Pelanggan Baru';
        document.getElementById('input_id').value = '0';
        document.getElementById('input_nama').value = '';
        document.getElementById('input_telp').value = '';
        document.getElementById('input_alamat').value = '';
        document.getElementById('input_poin').value = '0';
        document.getElementById('modalPelanggan').style.display = 'flex';
        setTimeout(() => { document.getElementById('input_nama').focus(); }, 100);
    }

    function openModalEdit(data) {
        document.getElementById('modalTitle').innerText = 'Edit Data Pelanggan';
        document.getElementById('input_id').value = data.id;
        document.getElementById('input_nama').value = data.nama_pelanggan;
        document.getElementById('input_telp').value = data.no_telp;
        document.getElementById('input_alamat').value = data.alamat;
        document.getElementById('input_poin').value = data.total_poin;
        document.getElementById('modalPelanggan').style.display = 'flex';
    }

    function closeModal() {
        document.getElementById('modalPelanggan').style.display = 'none';
    }

    // Modal Import Bulk
    function openModalImport() {
        document.getElementById('modalImport').style.display = 'flex';
    }

    function closeModalImport() {
        document.getElementById('modalImport').style.display = 'none';
    }

    // Auto Re-Open Modal saat klik 'Simpan & Tambah Lagi'
    <?php if ($open_modal_lagi): ?>
    window.onload = function() {
        openModalTambah();
    };
    <?php endif; ?>

    // Posisikan Kursor di Akhir Teks Search Jika Sedang Mencari Data
    window.addEventListener('DOMContentLoaded', () => {
        const inputS = document.getElementById('inputSearch');
        if (inputS.value !== '') {
            inputS.focus();
            inputS.setSelectionRange(inputS.value.length, inputS.value.length);
        }
    });
</script>

<?php require_once 'footer.php'; ?>
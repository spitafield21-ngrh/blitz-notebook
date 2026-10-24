<?php
// ==========================================
// FILE: pengeluaran.php
// FUNGSI: Kelola Pengeluaran Toko + Export/Import CSV + Simpan & Tambah Lagi
// ==========================================
require_once 'config.php';

// AUTO-CREATE TABEL
$koneksi->query("CREATE TABLE IF NOT EXISTS pengeluaran (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tanggal DATE NOT NULL,
    nama_pengeluaran VARCHAR(150) NOT NULL,
    kategori VARCHAR(50) DEFAULT 'Operasional',
    nominal INT NOT NULL DEFAULT 0,
    keterangan TEXT,
    id_user INT NOT NULL
)");

// Auto-Migrasi Kolom Baru (Jika Tabel Versi Lama Sudah Ada)
$check_nama = $koneksi->query("SHOW COLUMNS FROM pengeluaran LIKE 'nama_pengeluaran'");
if ($check_nama && $check_nama->num_rows == 0) {
    $koneksi->query("ALTER TABLE pengeluaran ADD COLUMN nama_pengeluaran VARCHAR(150) NOT NULL DEFAULT '-' AFTER tanggal");
}

$check_kat = $koneksi->query("SHOW COLUMNS FROM pengeluaran LIKE 'kategori'");
if ($check_kat && $check_kat->num_rows == 0) {
    $koneksi->query("ALTER TABLE pengeluaran ADD COLUMN kategori VARCHAR(50) DEFAULT 'Operasional' AFTER nama_pengeluaran");
}

// ==========================================
// 🚀 ENDPOINT: DOWNLOAD TEMPLATE IMPORT CSV
// ==========================================
if (isset($_GET['download_template'])) {
    ob_clean();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=template_import_pengeluaran.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['tanggal', 'nama_pengeluaran', 'kategori', 'nominal', 'keterangan']);
    fputcsv($output, [date('Y-m-d'), 'Beli ATK Toko', 'Perlengkapan Toko', '150000', 'Nota Kertas & Tinta']);
    fputcsv($output, [date('Y-m-d'), 'Bayar Tagihan Wifi Speedy', 'Operasional', '350000', 'Bulan Ini']);
    fclose($output);
    exit;
}

// ==========================================
// 🚀 ENDPOINT: EXPORT DATA PENGELUARAN (CSV)
// ==========================================
if (isset($_GET['export_csv'])) {
    ob_clean();
    $tgl_mulai = isset($_GET['tgl_mulai']) ? bersihkan_input($koneksi, $_GET['tgl_mulai']) : date('Y-m-01');
    $tgl_selesai = isset($_GET['tgl_selesai']) ? bersihkan_input($koneksi, $_GET['tgl_selesai']) : date('Y-m-d');
    $search = isset($_GET['search']) ? bersihkan_input($koneksi, $_GET['search']) : '';

    $where = "WHERE p.tanggal BETWEEN '$tgl_mulai' AND '$tgl_selesai'";
    if ($search != '') {
        $where .= " AND (p.nama_pengeluaran LIKE '%$search%' OR p.kategori LIKE '%$search%' OR p.keterangan LIKE '%$search%')";
    }

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Data_Pengeluaran_' . $tgl_mulai . '_sd_' . $tgl_selesai . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['No', 'Tanggal', 'Nama Pengeluaran', 'Kategori', 'Nominal (Rp)', 'Keterangan', 'Petugas']);

    $q = "SELECT p.*, u.nama_lengkap FROM pengeluaran p LEFT JOIN users u ON p.id_user = u.id $where ORDER BY p.tanggal DESC, p.id DESC";
    $res = $koneksi->query($q);
    $no = 1;
    if ($res && $res->num_rows > 0) {
        while ($r = $res->fetch_assoc()) {
            fputcsv($output, [
                $no++,
                $r['tanggal'],
                $r['nama_pengeluaran'],
                $r['kategori'],
                $r['nominal'],
                $r['keterangan'],
                $r['nama_lengkap']
            ]);
        }
    }
    fclose($output);
    exit;
}

// ==========================================
// 1. ENDPOINT AJAX: LIVE SEARCH & PAGINATION
// ==========================================
if (isset($_GET['ajax_search'])) {
    ob_clean();
    header('Content-Type: application/json');
    
    $search = isset($_GET['search']) ? $koneksi->real_escape_string($_GET['search']) : '';
    $tgl_mulai = isset($_GET['tgl_mulai']) ? $koneksi->real_escape_string($_GET['tgl_mulai']) : date('Y-m-01');
    $tgl_selesai = isset($_GET['tgl_selesai']) ? $koneksi->real_escape_string($_GET['tgl_selesai']) : date('Y-m-d');
    
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = 10; // Presisi 10 baris
    $offset = ($page - 1) * $limit;

    $where = "WHERE p.tanggal BETWEEN '$tgl_mulai' AND '$tgl_selesai'";
    if ($search != '') {
        $where .= " AND (p.nama_pengeluaran LIKE '%$search%' OR p.kategori LIKE '%$search%' OR p.keterangan LIKE '%$search%')";
    }

    $query = "SELECT p.*, u.nama_lengkap 
              FROM pengeluaran p 
              LEFT JOIN users u ON p.id_user = u.id 
              $where ORDER BY p.tanggal DESC, p.id DESC LIMIT $limit OFFSET $offset";
    $result = $koneksi->query($query);

    $countQuery = $koneksi->query("SELECT COUNT(*) as total, SUM(nominal) as grand_total FROM pengeluaran p $where");
    $data_count = $countQuery->fetch_assoc();
    $totalData = $data_count['total'] ?? 0;
    $grandTotal = $data_count['grand_total'] ?? 0;
    $totalPages = ceil($totalData / $limit);

    $html = "";
    if ($result && $result->num_rows > 0) {
        $no = $offset + 1;
        while ($row = $result->fetch_assoc()) {
            $dataJson = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
            $tgl_format = date('d M Y', strtotime($row['tanggal']));
            $nom_format = number_format($row['nominal'], 0, ',', '.');
            $ket = $row['keterangan'] != '' ? $row['keterangan'] : '-';
            
            $html .= "<tr>
                <td>{$no}</td>
                <td><b style='color:#38bdf8;'>{$tgl_format}</b></td>
                <td><b>{$row['nama_pengeluaran']}</b></td>
                <td><span style='background:rgba(255,255,255,0.1); padding:3px 8px; border-radius:4px; font-size:11px;'>{$row['kategori']}</span></td>
                <td style='text-align:right; font-weight:bold; color:#f87171;'>Rp {$nom_format}</td>
                <td>{$ket}</td>
                <td><small style='color:#aaa;'>{$row['nama_lengkap']}</small></td>
                <td style='text-align:center;'>
                    <button class='btn btn-edit' style='padding:4px 8px; font-size:11px;' onclick='openModalEdit({$dataJson})'>✏️</button>
                    <a href='pengeluaran.php?hapus={$row['id']}' class='btn btn-danger' style='padding:4px 8px; font-size:11px;' onclick=\"return confirm('Yakin hapus data ini?')\">🗑️</a>
                </td>
            </tr>";
            $no++;
        }
    } else {
        $html = "<tr><td colspan='8' style='text-align:center; padding: 20px; color:#aaa;'>Tidak ada data pengeluaran.</td></tr>";
    }

    $pagination = "";
    for ($i = 1; $i <= $totalPages; $i++) {
        $active = ($i == $page) ? "style='background:#38bdf8; color:#000;'" : "";
        $pagination .= "<button class='btn btn-page' $active onclick='changePage($i)'>$i</button>";
    }

    echo json_encode([
        'table' => $html, 
        'pagination' => $pagination, 
        'grand_total' => number_format($grandTotal, 0, ',', '.')
    ]);
    exit;
}

// Pastikan user sudah login
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$message = '';
$auto_open_modal = false;

// ==========================================
// 2. PROSES SIMPAN / EDIT PENGELUARAN
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && (isset($_POST['simpan_pengeluaran']) || isset($_POST['simpan_tambah_lagi']))) {
    $id               = (int)($_POST['id'] ?? 0);
    $tanggal          = bersihkan_input($koneksi, $_POST['tanggal'] ?? date('Y-m-d'));
    $nama_pengeluaran = bersihkan_input($koneksi, $_POST['nama_pengeluaran'] ?? '');
    $kategori         = bersihkan_input($koneksi, $_POST['kategori'] ?? 'Operasional');
    $nominal          = (int)($_POST['nominal'] ?? 0);
    $keterangan       = bersihkan_input($koneksi, $_POST['keterangan'] ?? '');
    $id_user          = $_SESSION['user_id'];

    if ($id > 0) {
        $q = "UPDATE pengeluaran SET tanggal='$tanggal', nama_pengeluaran='$nama_pengeluaran', kategori='$kategori', nominal='$nominal', keterangan='$keterangan' WHERE id=$id";
    } else {
        $q = "INSERT INTO pengeluaran (tanggal, nama_pengeluaran, kategori, nominal, keterangan, id_user) VALUES ('$tanggal', '$nama_pengeluaran', '$kategori', '$nominal', '$keterangan', '$id_user')";
    }

    if ($koneksi->query($q)) {
        $message = "<div class='alert alert-success'>Data pengeluaran berhasil disimpan!</div>";
        if (isset($_POST['simpan_tambah_lagi'])) {
            $auto_open_modal = true; // Trigger untuk membuka kembali modal
        }
    } else {
        $message = "<div class='alert alert-danger'>Error: " . $koneksi->error . "</div>";
    }
}

// ==========================================
// 3. PROSES IMPORT CSV DATA PENGELUARAN
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['import_pengeluaran'])) {
    if (isset($_FILES['file_import']['tmp_name']) && !empty($_FILES['file_import']['tmp_name'])) {
        $file = $_FILES['file_import']['tmp_name'];
        $handle = fopen($file, "r");
        $countSuccess = 0;
        $rowNum = 0;
        $id_user = $_SESSION['user_id'];

        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $rowNum++;
            if ($rowNum == 1) continue; // Skip header CSV

            $tgl  = bersihkan_input($koneksi, $data[0] ?? date('Y-m-d'));
            $nama = bersihkan_input($koneksi, $data[1] ?? '');
            $kat  = bersihkan_input($koneksi, $data[2] ?? 'Operasional');
            $nom  = (int)preg_replace('/[^0-9]/', '', $data[3] ?? '0');
            $ket  = bersihkan_input($koneksi, $data[4] ?? '');

            if (!empty($nama) && $nom > 0) {
                $q = "INSERT INTO pengeluaran (tanggal, nama_pengeluaran, kategori, nominal, keterangan, id_user) VALUES ('$tgl', '$nama', '$kat', '$nom', '$ket', '$id_user')";
                if ($koneksi->query($q)) {
                    $countSuccess++;
                }
            }
        }
        fclose($handle);
        $message = "<div class='alert alert-success'>🎉 Berhasil mengimpor $countSuccess data pengeluaran!</div>";
    } else {
        $message = "<div class='alert alert-danger'>Gagal! Pilih file CSV terlebih dahulu.</div>";
    }
}

// ==========================================
// 4. PROSES HAPUS
// ==========================================
if (isset($_GET['hapus'])) {
    $id_hapus = (int)$_GET['hapus'];
    if ($koneksi->query("DELETE FROM pengeluaran WHERE id = $id_hapus")) {
        $message = "<div class='alert alert-success'>Data pengeluaran berhasil dihapus!</div>";
    }
}

require_once 'header.php';
require_once 'sidebar.php';
?>

<style>
    .module-container { padding: 30px; color: #fff; }
    .toolbar { display: flex; justify-content: space-between; margin-bottom: 20px; align-items: center; flex-wrap: wrap; gap: 15px; }
    
    .date-picker-mbanking {
        padding: 10px 15px; border-radius: 20px; background: rgba(0,0,0,0.4);
        border: 1px solid rgba(56, 189, 248, 0.4); color: #38bdf8; outline: none;
        cursor: pointer; font-family: inherit; font-weight: bold; font-size: 14px;
        transition: 0.3s; color-scheme: dark;
    }
    .date-picker-mbanking:focus, .date-picker-mbanking:hover {
        border-color: #38bdf8; box-shadow: 0 0 12px rgba(56, 189, 248, 0.5); background: rgba(0,0,0,0.7);
    }
    
    .search-capsule { padding: 10px 20px; border-radius: 25px; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.2); color: #fff; width: 220px; outline: none; transition: 0.3s; }
    .search-capsule:focus { box-shadow: 0 0 15px rgba(56, 189, 248, 0.5); border-color: #38bdf8; }

    .btn { padding: 10px 18px; border-radius: 8px; cursor: pointer; border: none; color: #fff; font-weight: bold; transition: 0.3s; font-size:13px; text-decoration: none; display: inline-block; }
    .btn-primary { background: #38bdf8; color: #000; }
    .btn-primary:hover { box-shadow: 0 0 15px #38bdf8; }
    .btn-success { background: #10b981; color: #fff; }
    .btn-success:hover { box-shadow: 0 0 15px #10b981; }
    .btn-danger { background: #ef4444; color: #fff; }
    .btn-edit { background: #f59e0b; color: #000; }
    .btn-page { background: rgba(0,0,0,0.3); border: 1px solid #444; color: #fff; padding: 6px 12px; margin: 0 2px; border-radius: 4px; cursor: pointer;}
    .btn-page:hover { background: #38bdf8; color: #000; }

    .card-total {
        background: rgba(30, 30, 40, 0.8); border: 1px solid rgba(239, 68, 68, 0.4);
        border-radius: 12px; padding: 15px 25px; display: inline-block; margin-bottom: 20px;
    }
    
    .glass-table { width: 100%; border-collapse: collapse; background: rgba(36, 36, 36, 0.4); backdrop-filter: blur(10px); border-radius: 8px; overflow: hidden; font-size: 13px; }
    .glass-table th, .glass-table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.1); }
    .glass-table th { background: rgba(0,0,0,0.5); color: #38bdf8; text-transform: uppercase; }

    .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.7); backdrop-filter: blur(5px); justify-content: center; align-items: center; z-index: 1000; padding: 20px; }
    .modal-content { background: rgba(25, 25, 35, 0.95); border: 1px solid #38bdf8; border-radius: 12px; padding: 25px; width: 520px; max-width: 100%; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
    .form-group { margin-bottom: 15px; }
    .form-group label { display: block; margin-bottom: 5px; color: #ccc; font-size: 13px; font-weight: bold; }
    .form-control { width: 100%; padding: 10px; border-radius: 6px; background: rgba(0,0,0,0.4); border: 1px solid rgba(255,255,255,0.2); color: #fff; outline: none; box-sizing: border-box; }
    .form-control:focus { border-color: #38bdf8; }
    .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; }
    .alert-success { background: rgba(16, 185, 129, 0.2); border: 1px solid #10b981; color: #34d399; }
    .alert-danger { background: rgba(239, 68, 68, 0.2); border: 1px solid #ef4444; color: #fca5a5; }
</style>

<div class="main-content module-container">
    <h2>💸 Pengeluaran Operasional Toko</h2>
    <p style="color: #94a3b8; margin-top: -15px;">Catat biaya listrik, gaji, sewa, dan pengeluaran harian toko.</p>

    <?= $message ?>

    <div class="toolbar">
        <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
            <label style="font-size:13px; color:#aaa;">Periode:</label>
            <input type="date" id="tgl_mulai" class="date-picker-mbanking" value="<?= date('Y-m-01') ?>" onclick="this.showPicker()">
            <span style="color:#aaa;">s/d</span>
            <input type="date" id="tgl_selesai" class="date-picker-mbanking" value="<?= date('Y-m-d') ?>" onclick="this.showPicker()">

            <input type="text" id="searchBar" class="search-capsule" placeholder="🔍 Cari kata kunci..." autocomplete="off">
        </div>

        <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
            <button class="btn btn-primary" onclick="openModalTambah()">+ Catat Pengeluaran</button>
            <button class="btn btn-success" onclick="openModalImport()">📤 Import CSV</button>
            <button class="btn" style="background:#0284c7; color:#fff;" onclick="exportDataCSV()">📥 Export CSV</button>
        </div>
    </div>

    <div class="card-total">
        <span style="font-size:12px; color:#f87171; font-weight:bold; text-transform:uppercase;">Total Pengeluaran Periode Ini</span>
        <div style="font-size:24px; font-weight:bold; color:#fff; margin-top:3px;">
            Rp <span id="displayGrandTotal">0</span>
        </div>
    </div>

    <table class="glass-table">
        <thead>
            <tr>
                <th style="width:5%;">No</th>
                <th>Tanggal</th>
                <th>Nama Pengeluaran</th>
                <th>Kategori</th>
                <th style="text-align:right;">Nominal (Rp)</th>
                <th>Keterangan</th>
                <th>Petugas</th>
                <th style="text-align:center;">Aksi</th>
            </tr>
        </thead>
        <tbody id="tableBody">
            <tr><td colspan="8" style="text-align:center; padding: 20px; color:#aaa;">Memuat data...</td></tr>
        </tbody>
    </table>

    <div id="paginationBox" style="margin-top: 20px; display: flex; gap: 5px; justify-content: flex-end;"></div>
</div>

<div class="modal-overlay" id="modalPengeluaran">
    <div class="modal-content">
        <h3 style="margin-top:0; color:#38bdf8; border-bottom:1px solid rgba(255,255,255,0.2); padding-bottom:10px;" id="modalTitle">Catat Pengeluaran Baru</h3>
        <form method="POST" action="">
            <input type="hidden" name="id" id="p_id" value="0">
            
            <div class="form-group">
                <label>Tanggal Pengeluaran <span style="color:red;">*</span></label>
                <input type="date" name="tanggal" id="p_tanggal" class="form-control date-picker-mbanking" style="width:100%;" required onclick="this.showPicker()">
            </div>

            <div class="form-group">
                <label>Nama Pengeluaran <span style="color:red;">*</span></label>
                <input type="text" name="nama_pengeluaran" id="p_nama" class="form-control" placeholder="Contoh: Bayar Tagihan PLN Bulan Ini" required>
            </div>

            <div class="form-group">
                <label>Kategori</label>
                <select name="kategori" id="p_kategori" class="form-control">
                    <option value="Operasional">Operasional (PLN, PDAM, Wifi)</option>
                    <option value="Gaji & Bonus">Gaji & Bonus Karyawan</option>
                    <option value="Sewa Tempat">Sewa Tempat / Ruko</option>
                    <option value="Perlengkapan Toko">Perlengkapan / ATK Toko</option>
                    <option value="Lain-lain">Lain-lain</option>
                </select>
            </div>

            <div class="form-group">
                <label>Nominal Pengeluaran (Rp) <span style="color:red;">*</span></label>
                <input type="number" name="nominal" id="p_nominal" class="form-control" placeholder="0" min="0" required>
            </div>

            <div class="form-group">
                <label>Keterangan Tambahan</label>
                <textarea name="keterangan" id="p_keterangan" class="form-control" rows="2" placeholder="Catatan opsional..."></textarea>
            </div>

            <div style="text-align: right; margin-top: 20px; display: flex; gap: 8px; justify-content: flex-end; flex-wrap: wrap;">
                <button type="button" class="btn" style="background:#52525b;" onclick="closeModal('modalPengeluaran')">Batal</button>
                <button type="submit" name="simpan_tambah_lagi" id="btnTambahLagi" class="btn btn-success">➕ Simpan & Tambah Lagi</button>
                <button type="submit" name="simpan_pengeluaran" class="btn btn-primary">💾 Simpan Data</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="modalImport">
    <div class="modal-content">
        <h3 style="margin-top:0; color:#10b981; border-bottom:1px solid rgba(255,255,255,0.2); padding-bottom:10px;">📤 Import Pengeluaran dari CSV</h3>
        <p style="color:#aaa; font-size:12px; margin-bottom:15px;">Gunakan template CSV resmi agar format data tidak salah saat diunggah.</p>
        
        <div style="margin-bottom: 20px;">
            <a href="pengeluaran.php?download_template=1" class="btn" style="background:#0284c7; color:#fff; width:100%; text-align:center;">
                📄 Download Template CSV Pengeluaran
            </a>
        </div>

        <form method="POST" action="" enctype="multipart/form-data">
            <div class="form-group">
                <label>Pilih File CSV <span style="color:red;">*</span></label>
                <input type="file" name="file_import" class="form-control" accept=".csv" required>
            </div>

            <div style="text-align: right; margin-top: 20px;">
                <button type="button" class="btn" style="background:#52525b; margin-right:8px;" onclick="closeModal('modalImport')">Batal</button>
                <button type="submit" name="import_pengeluaran" class="btn btn-success">📤 Upload & Import Data</button>
            </div>
        </form>
    </div>
</div>

<script>
    let currentPage = 1;
    let typingTimer;

    function loadData() {
        let search = document.getElementById('searchBar').value;
        let tgl_mulai = document.getElementById('tgl_mulai').value;
        let tgl_selesai = document.getElementById('tgl_selesai').value;

        fetch(`pengeluaran.php?ajax_search=1&search=${encodeURIComponent(search)}&tgl_mulai=${tgl_mulai}&tgl_selesai=${tgl_selesai}&page=${currentPage}`)
            .then(response => response.json())
            .then(data => {
                document.getElementById('tableBody').innerHTML = data.table;
                document.getElementById('paginationBox').innerHTML = data.pagination;
                document.getElementById('displayGrandTotal').innerText = data.grand_total;
            })
            .catch(error => console.error('Error fetching data:', error));
    }

    // Event Listener Live Search & Filter Tanggal
    document.getElementById('searchBar').addEventListener('input', function() {
        clearTimeout(typingTimer);
        typingTimer = setTimeout(() => { currentPage = 1; loadData(); }, 300);
    });

    document.getElementById('tgl_mulai').addEventListener('change', function() { currentPage = 1; loadData(); });
    document.getElementById('tgl_selesai').addEventListener('change', function() { currentPage = 1; loadData(); });

    function changePage(page) {
        currentPage = page;
        loadData();
    }

    document.addEventListener('DOMContentLoaded', () => {
        loadData();
        <?php if ($auto_open_modal): ?>
            openModalTambah(); // Otomatis buka kembali modal jika mengklik Simpan & Tambah Lagi
        <?php endif; ?>
    });

    function openModalTambah() {
        document.getElementById('modalTitle').innerText = 'Catat Pengeluaran Baru';
        document.getElementById('p_id').value = '0';
        document.getElementById('p_tanggal').value = '<?= date('Y-m-d') ?>';
        document.getElementById('p_nama').value = '';
        document.getElementById('p_kategori').value = 'Operasional';
        document.getElementById('p_nominal').value = '';
        document.getElementById('p_keterangan').value = '';
        document.getElementById('btnTambahLagi').style.display = 'inline-block'; // Tampilkan tombol Tambah Lagi
        document.getElementById('modalPengeluaran').style.display = 'flex';
    }

    function openModalEdit(data) {
        document.getElementById('modalTitle').innerText = 'Edit Data Pengeluaran';
        document.getElementById('p_id').value = data.id;
        document.getElementById('p_tanggal').value = data.tanggal;
        document.getElementById('p_nama').value = data.nama_pengeluaran;
        document.getElementById('p_kategori').value = data.kategori;
        document.getElementById('p_nominal').value = data.nominal;
        document.getElementById('p_keterangan').value = data.keterangan;
        document.getElementById('btnTambahLagi').style.display = 'none'; // Sembunyikan tombol Tambah Lagi saat Edit
        document.getElementById('modalPengeluaran').style.display = 'flex';
    }

    function openModalImport() {
        document.getElementById('modalImport').style.display = 'flex';
    }

    function closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
    }

    function exportDataCSV() {
        let search = document.getElementById('searchBar').value;
        let tgl_mulai = document.getElementById('tgl_mulai').value;
        let tgl_selesai = document.getElementById('tgl_selesai').value;

        window.location.href = `pengeluaran.php?export_csv=1&tgl_mulai=${tgl_mulai}&tgl_selesai=${tgl_selesai}&search=${encodeURIComponent(search)}`;
    }
</script>

<?php require_once 'footer.php'; ?>
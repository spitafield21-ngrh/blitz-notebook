<?php
// ==========================================
// FILE: stok_opname.php
// FUNGSI: Audit Stok Fisik vs Sistem & Penyesuaian Otomatis
// ==========================================
require_once 'config.php';

// ==========================================
// 1. ENDPOINT AJAX (JSON RESPONSE) - HARUS DI ATAS!
// ==========================================
if (isset($_GET['ajax_search'])) {
    ob_clean(); // Bersihkan output sebelum JSON
    header('Content-Type: application/json');

    $search = isset($_GET['search']) ? $koneksi->real_escape_string($_GET['search']) : '';
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = 10;
    $offset = ($page - 1) * $limit;

    $where = "";
    if ($search != '') {
        $where = "WHERE b.nama_barang LIKE '%$search%' 
                  OR b.kode_barang LIKE '%$search%' 
                  OR so.keterangan LIKE '%$search%'";
    }

    // Ambil histori opname + data relasi barang dan user
    $query = "SELECT so.*, b.kode_barang, b.nama_barang, u.nama_lengkap 
              FROM stok_opname so 
              JOIN barang b ON so.id_barang = b.id 
              JOIN users u ON so.id_user = u.id 
              $where ORDER BY so.id DESC LIMIT $limit OFFSET $offset";
    $result = $koneksi->query($query);

    // Hitung total untuk Pagination
    $countQuery = $koneksi->query("SELECT COUNT(*) as total FROM stok_opname so JOIN barang b ON so.id_barang = b.id $where");
    $totalData = $countQuery->fetch_assoc()['total'];
    $totalPages = ceil($totalData / $limit);

    $tableHtml = "";
    if ($result && $result->num_rows > 0) {
        $no = $offset + 1;
        while ($row = $result->fetch_assoc()) {
            $selisih = $row['selisih'];
            $selisihHtml = $selisih > 0 ? "<span style='color: #34d399; font-weight:bold;'>+$selisih</span>" : ($selisih < 0 ? "<span style='color: #f87171; font-weight:bold;'>$selisih</span>" : "<span style='color: #a1a1aa;'>0</span>");
            
            $tableHtml .= "<tr>
                <td>{$no}</td>
                <td>{$row['tanggal']}</td>
                <td><b>{$row['kode_barang']}</b></td>
                <td>{$row['nama_barang']}</td>
                <td>{$row['stok_sistem']}</td>
                <td><b>{$row['stok_fisik']}</b></td>
                <td>{$selisihHtml}</td>
                <td>{$row['keterangan']}</td>
                <td>{$row['nama_lengkap']}</td>
            </tr>";
            $no++;
        }
    } else {
        $tableHtml = "<tr><td colspan='9' style='text-align:center; color: var(--text-muted); padding: 30px;'>Belum ada histori stok opname / data tidak ditemukan.</td></tr>";
    }

    $paginationHtml = "";
    for ($i = 1; $i <= $totalPages; $i++) {
        $active = ($i == $page) ? "active" : "";
        $paginationHtml .= "<button class='btn-page $active' onclick='changePage($i)'>$i</button>";
    }

    echo json_encode(['table' => $tableHtml, 'pagination' => $paginationHtml]);
    exit;
}

// ==========================================
// 2. TAMPILAN HTML & LOGIKA PHP
// ==========================================
require_once 'header.php';
require_once 'sidebar.php';

// Pastikan hanya Owner yang bisa akses
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'owner') {
    echo "<script>alert('Akses Ditolak! Khusus Owner.'); window.location='index.php';</script>";
    exit;
}

$message = '';
// HANDLE SIMPAN STOK OPNAME
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['simpan_opname'])) {
    $id_barang   = (int)$_POST['id_barang'];
    $stok_fisik  = (int)$_POST['stok_fisik'];
    $keterangan  = bersihkan_input($koneksi, $_POST['keterangan']);
    
    // Sesuaikan session user_id jika di sistem menggunakan penamaan lain (misal $_SESSION['id'])
    $id_user     = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : (isset($_SESSION['id']) ? $_SESSION['id'] : 1); 
    
    $tanggal     = date('Y-m-d');

    $query_brg = $koneksi->query("SELECT stok FROM barang WHERE id = '$id_barang'");
    if ($query_brg->num_rows > 0) {
        $stok_sistem  = (int)$query_brg->fetch_assoc()['stok'];
        $selisih = $stok_fisik - $stok_sistem;

        $query_insert = "INSERT INTO stok_opname (tanggal, id_barang, stok_sistem, stok_fisik, selisih, keterangan, id_user) 
                         VALUES ('$tanggal', '$id_barang', '$stok_sistem', '$stok_fisik', '$selisih', '$keterangan', '$id_user')";
        
        if ($koneksi->query($query_insert)) {
            $koneksi->query("UPDATE barang SET stok = '$stok_fisik' WHERE id = '$id_barang'");
            $message = "<div class='alert alert-success'>Stok Opname tersimpan & stok barang otomatis disinkronkan!</div>";
        }
    }
}

// Fetch barang yang aktif saja
$barangList = $koneksi->query("SELECT id, kode_barang, nama_barang, stok FROM barang WHERE status = 'Aktif' ORDER BY nama_barang ASC");
?>

<style>
    :root {
        --glow-color: #f59e0b;
        --glass-bg: rgba(36, 36, 36, 0.4);
        --glass-border: rgba(255, 255, 255, 0.1);
        --border-color: rgba(255, 255, 255, 0.2);
        --btn-primary: #f59e0b;
        --btn-success: #10b981;
        --text-muted: #a1a1aa;
    }
    
    .module-container { padding: 30px; color: #fff; }
    .toolbar { display: flex; justify-content: space-between; margin-bottom: 20px; align-items: center; flex-wrap: wrap; gap: 15px; }
    
    .search-capsule {
        padding: 10px 20px; border-radius: 25px; background: rgba(0,0,0,0.2);
        border: 1px solid var(--border-color); color: #fff; width: 320px; outline: none; transition: all 0.3s ease;
    }
    .search-capsule:focus {
        box-shadow: 0 0 12px rgba(245, 158, 11, 0.4); border-color: var(--btn-primary); background: rgba(0,0,0,0.4);
    }

    .glass-table {
        width: 100%; border-collapse: collapse; background: var(--glass-bg);
        backdrop-filter: blur(10px); border-radius: 8px; overflow: hidden;
    }
    .glass-table th, .glass-table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid var(--glass-border); }
    .glass-table th { background: rgba(0,0,0,0.3); color: var(--btn-primary); }
    
    .pagination { margin-top: 20px; display: flex; gap: 5px; justify-content: flex-end; }
    .btn-page { background: var(--glass-bg); border: 1px solid var(--border-color); color: #fff; padding: 8px 12px; cursor: pointer; border-radius: 5px; }
    .btn-page.active { background: var(--btn-primary); color: #000; font-weight: bold; }

    .alert { padding: 15px; border-radius: 8px; margin-bottom: 15px; }
    .alert-success { background: rgba(16, 185, 129, 0.2); border: 1px solid var(--btn-success); color: #34d399; }
    
    /* =========================================
       CSS MODAL YANG DITAMBAHKAN AGAR SEMPURNA 
       ========================================= */
    .btn { padding: 10px 20px; border-radius: 8px; cursor: pointer; border: none; color: #fff; transition: 0.3s; font-weight: bold;}
    .btn-primary { background: var(--btn-primary); color: #000;}
    .btn-secondary { background: #666; }
    
    .modal-overlay {
        display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0, 0, 0, 0.6); backdrop-filter: blur(5px);
        justify-content: center; align-items: center; z-index: 1000;
    }
    .modal-box {
        background: rgba(30, 30, 40, 0.95); border: 1px solid var(--glass-border);
        border-radius: 15px; padding: 25px; width: 500px; max-width: 90%;
        box-shadow: 0 10px 30px rgba(0,0,0,0.5);
    }
    .form-group { margin-bottom: 15px; text-align: left; }
    .form-group label { display: block; margin-bottom: 5px; font-size: 14px; color: #ccc; }
    .form-control {
        width: 100%; padding: 10px; border-radius: 8px; background: rgba(0,0,0,0.2);
        border: 1px solid var(--glass-border); color: #fff; outline: none; box-sizing: border-box;
    }
    .form-control:focus { border-color: var(--btn-primary); }
    .modal-actions { text-align: right; margin-top: 20px; display: flex; justify-content: flex-end; gap: 10px; }
</style>

<div class="main-content module-container">
    <h2>🔄 Histori & Audit Stok Opname</h2>
    <?= $message ?>
    
    <div class="toolbar">
        <input type="text" id="searchBar" class="search-capsule" placeholder="Cari barang / alasan... 🔍" autocomplete="off">
        <button class="btn btn-primary" onclick="openModal('modalOpname')">+ Input Stok Opname Baru</button>
    </div>

    <div class="panel-glass table-container">
        <table class="glass-table">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Tanggal</th>
                    <th>Kode/Serial</th>
                    <th>Nama Barang</th>
                    <th>Stok Sistem</th>
                    <th>Stok Fisik</th>
                    <th>Selisih</th>
                    <th>Keterangan</th>
                    <th>Petugas</th>
                </tr>
            </thead>
            <tbody id="tableBody"></tbody>
        </table>
        
        <div class="pagination" id="paginationBox"></div>
    </div>
</div>

<div class="modal-overlay" id="modalOpname">
    <div class="modal-box" style="max-width: 500px;">
        <h3 style="margin-top:0; color:var(--btn-primary); text-align:center;">Form Audit Stok Opname</h3>
        <form method="POST">
            <div class="form-group">
                <label>Pilih Barang</label>
                <select name="id_barang" id="selectBarang" class="form-control" required onchange="cekStokSistem(this)">
                    <option value="">-- Pilih Barang --</option>
                    <?php 
                    if($barangList && $barangList->num_rows > 0) {
                        $barangList->data_seek(0);
                        while($b = $barangList->fetch_assoc()): 
                    ?>
                        <option value="<?= $b['id'] ?>" data-stok="<?= $b['stok'] ?>"><?= $b['kode_barang'] ?> - <?= $b['nama_barang'] ?> (Sisa: <?= $b['stok'] ?>)</option>
                    <?php 
                        endwhile; 
                    }
                    ?>
                </select>
            </div>
            <div class="form-group">
                <label>Stok Tercatat di Sistem</label>
                <input type="text" id="infoStokSistem" class="form-control" value="Pilih barang terlebih dahulu" readonly style="background: rgba(0,0,0,0.4); color: var(--text-muted);">
            </div>
            <div class="form-group">
                <label>Jumlah Stok Fisik (Hasil Hitung Riil)</label>
                <input type="number" name="stok_fisik" class="form-control" placeholder="Masukkan jumlah fisik..." required min="0">
            </div>
            <div class="form-group">
                <label>Keterangan / Alasan Penyesuaian</label>
                <textarea name="keterangan" class="form-control" rows="3" placeholder="Contoh: Barang hilang / salah input awal..." required></textarea>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalOpname')">Batal</button>
                <button type="submit" name="simpan_opname" class="btn btn-primary">Simpan & Sinkronkan</button>
            </div>
        </form>
    </div>
</div>

<script>
    let currentPage = 1;

    function loadData() {
        let keyword = document.getElementById('searchBar').value;
        fetch(`stok_opname.php?ajax_search=1&search=${keyword}&page=${currentPage}`)
            .then(response => response.json())
            .then(data => {
                document.getElementById('tableBody').innerHTML = data.table;
                document.getElementById('paginationBox').innerHTML = data.pagination;
            })
            .catch(error => console.error('Error fetching data:', error));
    }

    document.getElementById('searchBar').addEventListener('input', function() {
        currentPage = 1;
        loadData();
    });

    function changePage(page) {
        currentPage = page;
        loadData();
    }

    function openModal(id) { document.getElementById(id).style.display = 'flex'; }
    function closeModal(id) { document.getElementById(id).style.display = 'none'; }

    function cekStokSistem(select) {
        let selectedOption = select.options[select.selectedIndex];
        let stok = selectedOption.getAttribute('data-stok');
        document.getElementById('infoStokSistem').value = stok ? (stok + " unit") : "Pilih barang terlebih dahulu";
    }

    document.addEventListener('DOMContentLoaded', () => {
        loadData();
    });
</script>

<?php require_once 'footer.php'; ?>
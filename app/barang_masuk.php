<?php
// FILE: barang_masuk.php (Fase 5: Inventori Barang Masuk)
require_once 'config.php';

// ==========================================
// 1. ENDPOINT AJAX: LIVE SEARCH & PAGINATION
// ==========================================
if (isset($_GET['ajax_search'])) {
    header('Content-Type: application/json');
    $search = isset($_GET['search']) ? $koneksi->real_escape_string($_GET['search']) : '';
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = 10;
    $offset = ($page - 1) * $limit;

    $where = "";
    if ($search != '') {
        $where = "WHERE bm.no_faktur LIKE '%$search%' 
                  OR s.nama_supplier LIKE '%$search%' 
                  OR b.nama_barang LIKE '%$search%'
                  OR b.kode_barang LIKE '%$search%'";
    }

    $query = "SELECT bm.*, s.nama_supplier, b.nama_barang, b.kode_barang 
              FROM barang_masuk bm 
              LEFT JOIN supplier s ON bm.id_supplier = s.id 
              LEFT JOIN barang b ON bm.id_barang = b.id 
              $where ORDER BY bm.id DESC LIMIT $limit OFFSET $offset";
    $result = $koneksi->query($query);

    $countQuery = $koneksi->query("SELECT COUNT(*) as total FROM barang_masuk bm 
                                   LEFT JOIN supplier s ON bm.id_supplier = s.id 
                                   LEFT JOIN barang b ON bm.id_barang = b.id $where");
    $totalData = $countQuery ? $countQuery->fetch_assoc()['total'] : 0;
    $totalPages = ceil($totalData / $limit);

    $tableHtml = "";
    if ($result && $result->num_rows > 0) {
        $no = $offset + 1;
        while ($row = $result->fetch_assoc()) {
            $totalHarga = $row['jumlah'] * $row['harga_beli'];
            // MENYESUAIKAN DENGAN NAMA KOLOM DI DATABASE COACH: tanggal_masuk
            $tglFormatted = date('d-m-Y', strtotime($row['tanggal_masuk'])); 
            $dataJson = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
            
            $tableHtml .= "<tr>
                <td>{$no}</td>
                <td>{$tglFormatted}</td>
                <td><b>{$row['no_faktur']}</b></td>
                <td>{$row['nama_supplier']}</td>
                <td>{$row['nama_barang']} <br><small style='color:#00ffcc;'>SN: {$row['kode_barang']}</small></td>
                <td><b>{$row['jumlah']}</b></td>
                <td>Rp " . number_format($row['harga_beli'], 0, ',', '.') . "</td>
                <td>Rp " . number_format($totalHarga, 0, ',', '.') . "</td>
                <td>
                    <button class='btn-action btn-edit' onclick='openEditModal($dataJson)'>Edit</button>
                    <button class='btn-action btn-delete' onclick='openDeleteModal({$row['id']})'>Hapus</button>
                </td>
            </tr>";
            $no++;
        }
    } else {
        $tableHtml = "<tr><td colspan='9' style='text-align:center;'>Data barang masuk belum ada.</td></tr>";
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
// 2. HANDLE CRUD (POST REQUEST)
// ==========================================
$message = '';
$keepOpen = false;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['tambah'])) {
        $no_faktur = $koneksi->real_escape_string($_POST['no_faktur']);
        $id_supplier = (int)$_POST['id_supplier'];
        $id_barang = (int)$_POST['id_barang'];
        $jumlah = (int)$_POST['jumlah'];
        $harga_beli = (int)$_POST['harga_beli'];
        $tanggal_masuk = $koneksi->real_escape_string($_POST['tanggal_masuk']);
        $keterangan = isset($_POST['keterangan']) ? $koneksi->real_escape_string($_POST['keterangan']) : '';

        // Simpan Transaksi Barang Masuk (Kolom tanggal_masuk)
        $insert = $koneksi->query("INSERT INTO barang_masuk (no_faktur, id_supplier, id_barang, jumlah, harga_beli, tanggal_masuk, keterangan) 
                                  VALUES ('$no_faktur', '$id_supplier', '$id_barang', '$jumlah', '$harga_beli', '$tanggal_masuk', '$keterangan')");
        
        if ($insert) {
            // Update Stok & Harga Beli/HPP Barang Otomatis
            $koneksi->query("UPDATE barang SET stok = stok + $jumlah, harga_beli = '$harga_beli' WHERE id = $id_barang");
            $message = "<div class='alert alert-success'>Berhasil mencatat barang masuk & stok barang otomatis bertambah!</div>";
            if (isset($_POST['simpan_tambah'])) $keepOpen = true;
        } else {
            $message = "<div class='alert alert-danger'>Gagal menyimpan data: " . $koneksi->error . "</div>";
        }
    } elseif (isset($_POST['edit'])) {
        $id = (int)$_POST['id'];
        $no_faktur = $koneksi->real_escape_string($_POST['no_faktur']);
        $id_supplier = (int)$_POST['id_supplier'];
        $id_barang = (int)$_POST['id_barang'];
        $jumlah_baru = (int)$_POST['jumlah'];
        $harga_beli = (int)$_POST['harga_beli'];
        $tanggal_masuk = $koneksi->real_escape_string($_POST['tanggal_masuk']);
        $keterangan = isset($_POST['keterangan']) ? $koneksi->real_escape_string($_POST['keterangan']) : '';

        // Ambil data lama untuk kalkulasi penyesuaian stok
        $qLama = $koneksi->query("SELECT id_barang, jumlah FROM barang_masuk WHERE id = $id");
        if ($qLama && $dataLama = $qLama->fetch_assoc()) {
            $id_barang_lama = $dataLama['id_barang'];
            $jumlah_lama = $dataLama['jumlah'];

            // Update transaksi (Kolom tanggal_masuk)
            $update = $koneksi->query("UPDATE barang_masuk SET 
                                      no_faktur='$no_faktur', 
                                      id_supplier='$id_supplier', 
                                      id_barang='$id_barang', 
                                      jumlah='$jumlah_baru', 
                                      harga_beli='$harga_beli', 
                                      tanggal_masuk='$tanggal_masuk', 
                                      keterangan='$keterangan' 
                                      WHERE id=$id");
            if ($update) {
                // Adjust Stok Barang Lama & Baru
                $koneksi->query("UPDATE barang SET stok = stok - $jumlah_lama WHERE id = $id_barang_lama");
                $koneksi->query("UPDATE barang SET stok = stok + $jumlah_baru, harga_beli = '$harga_beli' WHERE id = $id_barang");

                $message = "<div class='alert alert-success'>Data barang masuk & stok berhasil diperbarui!</div>";
            }
        }
    } elseif (isset($_POST['hapus'])) {
        $id = (int)$_POST['id_hapus'];
        
        $qLama = $koneksi->query("SELECT id_barang, jumlah FROM barang_masuk WHERE id = $id");
        if ($qLama && $dataLama = $qLama->fetch_assoc()) {
            $id_barang = $dataLama['id_barang'];
            $jumlah = $dataLama['jumlah'];

            // Hapus data transaksi
            $delete = $koneksi->query("DELETE FROM barang_masuk WHERE id = $id");
            if ($delete) {
                // Kurangi stok barang karena transaksi dibatalkan/dihapus
                $koneksi->query("UPDATE barang SET stok = GREATEST(0, stok - $jumlah) WHERE id = $id_barang");
                $message = "<div class='alert alert-success'>Data barang masuk dihapus & stok telah dikurangi kembali!</div>";
            }
        }
    }
}

// Fetch Supplier & Barang untuk Form
$supplierList = $koneksi->query("SELECT id, nama_supplier FROM supplier ORDER BY nama_supplier ASC");
$barangList = $koneksi->query("SELECT id, kode_barang, nama_barang, harga_beli FROM barang ORDER BY nama_barang ASC");

// Auto Generate No Faktur Bawaan
$autoFaktur = "BM-" . date('Ymd') . "-" . rand(100, 999);

require_once 'header.php';
require_once 'sidebar.php';
?>

<style>
    /* CSS Scoped untuk Modul Barang Masuk */
    :root {
        --glow-color: #00ffcc;
        --glass-bg: rgba(255, 255, 255, 0.05);
        --glass-border: rgba(255, 255, 255, 0.1);
    }
    
    .module-container { padding: 20px; color: #fff; }
    .toolbar { display: flex; justify-content: space-between; margin-bottom: 20px; align-items: center; }
    
    /* Live Search Glow */
    .search-capsule {
        padding: 10px 20px;
        border-radius: 50px;
        background: var(--glass-bg);
        border: 1px solid var(--glass-border);
        color: #fff;
        width: 320px;
        outline: none;
        transition: all 0.3s ease;
    }
    .search-capsule:focus {
        box-shadow: 0 0 15px 2px var(--glow-color);
        border-color: var(--glow-color);
    }

    /* Glass Table */
    .glass-table {
        width: 100%; border-collapse: collapse; background: var(--glass-bg);
        backdrop-filter: blur(10px); border-radius: 15px; overflow: hidden;
    }
    .glass-table th, .glass-table td { padding: 15px; text-align: left; border-bottom: 1px solid var(--glass-border); }
    .glass-table th { background: rgba(0,0,0,0.3); color: var(--glow-color); }
    
    /* Buttons */
    .btn { padding: 10px 20px; border-radius: 8px; cursor: pointer; border: none; color: #fff; transition: 0.3s; font-weight: bold;}
    .btn-primary { background: #007bff; }
    .btn-success { background: #28a745; }
    .btn-success:hover { box-shadow: 0 0 10px #28a745; }
    .btn-action { padding: 5px 10px; border-radius: 5px; cursor: pointer; border: none; color: #fff; }
    .btn-edit { background: #ffc107; color: #000; }
    .btn-delete { background: #dc3545; }
    
    /* Modals */
    .modal-overlay {
        display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0, 0, 0, 0.6); backdrop-filter: blur(5px);
        justify-content: center; align-items: center; z-index: 1000;
    }
    .modal-overlay.active { display: flex; }
    .modal-content {
        background: rgba(30, 30, 40, 0.95); border: 1px solid var(--glass-border);
        border-radius: 15px; padding: 25px; width: 550px; max-width: 90%;
        box-shadow: 0 10px 30px rgba(0,0,0,0.5);
    }
    .form-group { margin-bottom: 15px; text-align: left; }
    .form-group label { display: block; margin-bottom: 5px; font-size: 14px; color: #aaa; }
    .form-control {
        width: 100%; padding: 10px; border-radius: 8px; background: rgba(0,0,0,0.2);
        border: 1px solid var(--glass-border); color: #fff; outline: none; box-sizing: border-box;
    }
    .form-control:focus { border-color: var(--glow-color); }
    
    /* Alert */
    .alert { padding: 15px; border-radius: 8px; margin-bottom: 15px; }
    .alert-success { background: rgba(40, 167, 69, 0.2); border: 1px solid #28a745; color: #28a745; }
    .alert-danger { background: rgba(220, 53, 69, 0.2); border: 1px solid #dc3545; color: #ff6b6b; }
    
    /* Pagination */
    .pagination { margin-top: 20px; display: flex; gap: 5px; justify-content: flex-end; }
    .btn-page { background: var(--glass-bg); border: 1px solid var(--glass-border); color: #fff; padding: 8px 12px; cursor: pointer; border-radius: 5px; }
    .btn-page.active { background: var(--glow-color); color: #000; font-weight: bold; }
</style>

<div class="main-content module-container">
    <h2>🏭 Barang Masuk (Restock Inventori)</h2>
    <?= $message ?>
    
    <div class="toolbar">
        <input type="text" id="searchBar" class="search-capsule" placeholder="Cari Faktur, Supplier, atau Barang... 🔍" autocomplete="off">
        <button class="btn btn-primary" onclick="openModal('modalTambah')">+ Catat Barang Masuk</button>
    </div>

    <table class="glass-table">
        <thead>
            <tr>
                <th>No</th>
                <th>Tanggal</th>
                <th>No. Faktur</th>
                <th>Supplier</th>
                <th>Barang (Serial Number)</th>
                <th>Qty</th>
                <th>Harga Beli</th>
                <th>Total</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody id="tableBody">
            </tbody>
    </table>
    
    <div class="pagination" id="paginationBox">
        </div>
</div>

<div class="modal-overlay" id="modalTambah">
    <div class="modal-content">
        <h3 style="margin-top:0; color:var(--glow-color); text-align:center;">Tambah Transaksi Barang Masuk</h3>
        <form method="POST">
            <div style="display:flex; gap:10px;">
                <div class="form-group" style="flex:1;">
                    <label>No. Faktur / Nota Pembelian</label>
                    <input type="text" name="no_faktur" class="form-control" value="<?= $autoFaktur ?>" required>
                </div>
                <div class="form-group" style="flex:1;">
                    <label>Tanggal Masuk</label>
                    <input type="date" name="tanggal_masuk" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label>Supplier</label>
                <select name="id_supplier" class="form-control" required>
                    <option value="">-- Pilih Supplier --</option>
                    <?php 
                    if($supplierList && $supplierList->num_rows > 0) {
                        $supplierList->data_seek(0);
                        while($s = $supplierList->fetch_assoc()): 
                    ?>
                        <option value="<?= $s['id'] ?>"><?= $s['nama_supplier'] ?></option>
                    <?php 
                        endwhile; 
                    } 
                    ?>
                </select>
            </div>

            <div class="form-group">
                <label>Pilih Barang</label>
                <select name="id_barang" id="tambah_barang" class="form-control" onchange="autoFillHargaBeli('tambah')" required>
                    <option value="">-- Pilih Barang --</option>
                    <?php 
                    if($barangList && $barangList->num_rows > 0) {
                        $barangList->data_seek(0);
                        while($b = $barangList->fetch_assoc()): 
                    ?>
                        <option value="<?= $b['id'] ?>" data-harga="<?= $b['harga_beli'] ?>">
                            [<?= $b['kode_barang'] ?>] <?= $b['nama_barang'] ?>
                        </option>
                    <?php 
                        endwhile; 
                    } 
                    ?>
                </select>
            </div>

            <div style="display:flex; gap:10px;">
                <div class="form-group" style="flex:1;">
                    <label>Jumlah Masuk (Qty)</label>
                    <input type="number" name="jumlah" class="form-control" min="1" value="1" required>
                </div>
                <div class="form-group" style="flex:1;">
                    <label>Harga Beli Satuan (HPP)</label>
                    <input type="number" name="harga_beli" id="tambah_harga_beli" class="form-control" required>
                </div>
            </div>

            <div class="form-group">
                <label>Keterangan (Opsional)</label>
                <textarea name="keterangan" class="form-control" rows="2" placeholder="Catatan tambahan..."></textarea>
            </div>

            <div style="text-align: right; margin-top: 20px;">
                <button type="button" class="btn" style="background:#666;" onclick="closeModal('modalTambah')">Batal</button>
                <button type="submit" name="tambah" class="btn btn-primary">Simpan</button>
                <button type="submit" name="tambah" value="1" class="btn btn-success" style="margin-left: 5px;" id="btnSimpanTambah">
                    <input type="hidden" name="simpan_tambah" value="1" id="inputSimpanTambah" disabled>
                    Simpan & Tambah Lagi
                </button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="modalEdit">
    <div class="modal-content">
        <h3 style="margin-top:0; color:#ffc107; text-align:center;">Edit Transaksi Barang Masuk</h3>
        <form method="POST">
            <input type="hidden" name="id" id="edit_id">
            <div style="display:flex; gap:10px;">
                <div class="form-group" style="flex:1;">
                    <label>No. Faktur</label>
                    <input type="text" name="no_faktur" id="edit_faktur" class="form-control" required>
                </div>
                <div class="form-group" style="flex:1;">
                    <label>Tanggal Masuk</label>
                    <input type="date" name="tanggal_masuk" id="edit_tanggal" class="form-control" required>
                </div>
            </div>

            <div class="form-group">
                <label>Supplier</label>
                <select name="id_supplier" id="edit_supplier" class="form-control" required>
                    <?php 
                    if($supplierList && $supplierList->num_rows > 0) {
                        $supplierList->data_seek(0);
                        while($s = $supplierList->fetch_assoc()): 
                    ?>
                        <option value="<?= $s['id'] ?>"><?= $s['nama_supplier'] ?></option>
                    <?php 
                        endwhile; 
                    } 
                    ?>
                </select>
            </div>

            <div class="form-group">
                <label>Pilih Barang</label>
                <select name="id_barang" id="edit_barang" class="form-control" required>
                    <?php 
                    if($barangList && $barangList->num_rows > 0) {
                        $barangList->data_seek(0);
                        while($b = $barangList->fetch_assoc()): 
                    ?>
                        <option value="<?= $b['id'] ?>">[<?= $b['kode_barang'] ?>] <?= $b['nama_barang'] ?></option>
                    <?php 
                        endwhile; 
                    } 
                    ?>
                </select>
            </div>

            <div style="display:flex; gap:10px;">
                <div class="form-group" style="flex:1;">
                    <label>Jumlah Masuk (Qty)</label>
                    <input type="number" name="jumlah" id="edit_jumlah" class="form-control" min="1" required>
                </div>
                <div class="form-group" style="flex:1;">
                    <label>Harga Beli Satuan (HPP)</label>
                    <input type="number" name="harga_beli" id="edit_harga_beli" class="form-control" required>
                </div>
            </div>

            <div class="form-group">
                <label>Keterangan</label>
                <textarea name="keterangan" id="edit_keterangan" class="form-control" rows="2"></textarea>
            </div>

            <div style="text-align: right; margin-top: 20px;">
                <button type="button" class="btn" style="background:#666;" onclick="closeModal('modalEdit')">Batal</button>
                <button type="submit" name="edit" class="btn btn-edit" style="color:#000;">Update Transaksi</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="modalHapus">
    <div class="modal-content" style="width: 400px; text-align: center;">
        <h3 style="color:#dc3545; margin-top:0;">Konfirmasi Hapus</h3>
        <p>Menghapus transaksi ini akan <b>mengurangi stok barang</b> terkait secara otomatis. Yakin?</p>
        <form method="POST">
            <input type="hidden" name="id_hapus" id="hapus_id">
            <div style="margin-top: 20px;">
                <button type="button" class="btn" style="background:#666;" onclick="closeModal('modalHapus')">Batal</button>
                <button type="submit" name="hapus" class="btn btn-delete">Ya, Hapus!</button>
            </div>
        </form>
    </div>
</div>

<script>
    // ==========================================
    // JS LOGIC: AJAX, LIVE SEARCH, & MODALS
    // ==========================================
    let currentPage = 1;

    function loadData() {
        let keyword = document.getElementById('searchBar').value;
        fetch(`barang_masuk.php?ajax_search=1&search=${keyword}&page=${currentPage}`)
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

    function openModal(id) { document.getElementById(id).classList.add('active'); }
    function closeModal(id) { document.getElementById(id).classList.remove('active'); }

    // Otomatis isi Harga Beli bawaan saat barang dipilih di dropdown
    function autoFillHargaBeli(mode) {
        let select = document.getElementById(mode + '_barang');
        let selectedOption = select.options[select.selectedIndex];
        let harga = selectedOption.getAttribute('data-harga');
        if (harga) {
            document.getElementById(mode + '_harga_beli').value = harga;
        }
    }

    function openEditModal(data) {
        document.getElementById('edit_id').value = data.id;
        document.getElementById('edit_faktur').value = data.no_faktur;
        document.getElementById('edit_tanggal').value = data.tanggal_masuk; // DISESUAIKAN
        document.getElementById('edit_supplier').value = data.id_supplier;
        document.getElementById('edit_barang').value = data.id_barang;
        document.getElementById('edit_jumlah').value = data.jumlah;
        document.getElementById('edit_harga_beli').value = data.harga_beli;
        document.getElementById('edit_keterangan').value = data.keterangan || '';
        openModal('modalEdit');
    }

    function openDeleteModal(id) {
        document.getElementById('hapus_id').value = id;
        openModal('modalHapus');
    }

    document.getElementById('btnSimpanTambah').addEventListener('click', function() {
        document.getElementById('inputSimpanTambah').disabled = false;
    });

    document.addEventListener('DOMContentLoaded', () => {
        loadData();
        <?php if ($keepOpen): ?>
            openModal('modalTambah');
        <?php endif; ?>
    });
</script>

<?php require_once 'footer.php'; ?>
<?php
// ==========================================
// FILE: supplier.php
// FUNGSI: Mengelola Data Master Supplier (Standar CRUD Premium)
// ==========================================

require_once 'header.php';
require_once 'sidebar.php';

// KEAMANAN: Hanya 'owner' yang bisa akses
if ($_SESSION['role'] != 'owner') {
    echo "<script>alert('Akses Ditolak! Anda bukan Owner.'); window.location='index.php';</script>";
    exit;
}

// ==========================================
// 1. LOGIKA CREATE (SIMPAN & SIMPAN TAMBAH LAGI)
// ==========================================
if (isset($_POST['simpan_supplier']) || isset($_POST['simpan_tambah_lagi'])) {
    $nama_supplier = bersihkan_input($koneksi, $_POST['nama_supplier']);
    $kontak        = bersihkan_input($koneksi, $_POST['kontak']);
    $alamat        = bersihkan_input($koneksi, $_POST['alamat']);
    
    // Cek duplikat nama supplier
    $cek_duplikat = $koneksi->query("SELECT id FROM supplier WHERE nama_supplier = '$nama_supplier'");
    
    if ($cek_duplikat->num_rows > 0) {
        echo "<script>alert('GAGAL! Supplier \"$nama_supplier\" sudah terdaftar.'); window.history.back();</script>";
    } else {
        $query_insert = "INSERT INTO supplier (nama_supplier, kontak, alamat) VALUES ('$nama_supplier', '$kontak', '$alamat')";
        if ($koneksi->query($query_insert)) {
            if (isset($_POST['simpan_tambah_lagi'])) {
                echo "<script>window.location='supplier.php?add_more=true';</script>";
            } else {
                echo "<script>window.location='supplier.php';</script>";
            }
        }
    }
}

// ==========================================
// 2. LOGIKA UPDATE
// ==========================================
if (isset($_POST['update_supplier'])) {
    $id_supplier   = bersihkan_input($koneksi, $_POST['id_supplier']);
    $nama_supplier = bersihkan_input($koneksi, $_POST['nama_supplier']);
    $kontak        = bersihkan_input($koneksi, $_POST['kontak']);
    $alamat        = bersihkan_input($koneksi, $_POST['alamat']);
    
    $cek_duplikat = $koneksi->query("SELECT id FROM supplier WHERE nama_supplier = '$nama_supplier' AND id != '$id_supplier'");
    
    if ($cek_duplikat->num_rows > 0) {
        echo "<script>alert('GAGAL UPDATE! Nama supplier \"$nama_supplier\" sudah dipakai supplier lain.'); window.history.back();</script>";
    } else {
        $query_update = "UPDATE supplier SET nama_supplier = '$nama_supplier', kontak = '$kontak', alamat = '$alamat' WHERE id = '$id_supplier'";
        if ($koneksi->query($query_update)) {
            echo "<script>window.location='supplier.php';</script>";
        }
    }
}

// ==========================================
// 3. LOGIKA DELETE
// ==========================================
if (isset($_GET['hapus'])) {
    $id_hapus = bersihkan_input($koneksi, $_GET['hapus']);
    $query_hapus = "DELETE FROM supplier WHERE id = '$id_hapus'";
    if ($koneksi->query($query_hapus)) {
        echo "<script>window.location='supplier.php';</script>";
    } else {
        echo "<script>alert('Gagal! Supplier sedang terikat dengan data pembelian barang masuk.'); window.location='supplier.php';</script>";
    }
}

// ==========================================
// 4. KONFIGURASI PAGINATION & SEARCH
// ==========================================
$limit = 10; 
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$search_query = "";
$search_keyword = "";
if (isset($_GET['search']) && $_GET['search'] != '') {
    $search_keyword = bersihkan_input($koneksi, $_GET['search']);
    $search_query = " WHERE nama_kategori LIKE '%$search_keyword%' OR nama_supplier LIKE '%$search_keyword%' OR kontak LIKE '%$search_keyword%' ";
    // Perbaikan query pencarian yang tepat untuk supplier
    $search_query = " WHERE nama_supplier LIKE '%$search_keyword%' OR kontak LIKE '%$search_keyword%' OR alamat LIKE '%$search_keyword%' ";
}

$query_total = $koneksi->query("SELECT COUNT(*) AS total FROM supplier $search_query");
$total_data = $query_total->fetch_assoc()['total'];
$total_pages = ceil($total_data / $limit);
?>

<!-- STYLE STANDAR WAJIB (SEARCH BOX GLOW & ROUNDED) -->
<style>
    .input-search-rounded {
        width: 100%; 
        padding: 10px 20px;
        border-radius: 25px;
        background: rgba(0,0,0,0.2); 
        border: 1px solid var(--border-color); 
        color: white; 
        outline: none;
        transition: all 0.3s ease;
    }
    .input-search-rounded:focus {
        border-color: var(--btn-primary);
        box-shadow: 0 0 12px rgba(245, 158, 11, 0.4);
        background: rgba(0,0,0,0.4);
    }
</style>

<!-- KONTEN UTAMA -->
<main class="main-content">
    <div class="header-content">
        <h2>🏢 Master Data Supplier</h2>
        <button class="btn btn-primary" id="btnBukaModal">+ Tambah Supplier</button>
    </div>

    <!-- AREA PENCARIAN (LIVE SEARCH) -->
    <div style="margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
        <form method="GET" action="supplier.php" id="formSearch" style="width: 100%; max-width: 350px;">
            <input type="text" name="search" id="inputSearch" class="input-search-rounded" 
                   value="<?php echo $search_keyword; ?>" 
                   placeholder="🔍 Cari nama supplier / no telp...">
        </form>
        <span style="color: var(--text-muted); font-size: 14px;">Total: <b style="color: var(--text-light);"><?php echo $total_data; ?></b> Supplier terdaftar</span>
    </div>

    <div class="panel-glass">
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th style="width: 50px;">No</th>
                        <th>Nama Supplier / Toko</th>
                        <th>Kontak / No Telp</th>
                        <th>Alamat</th>
                        <th style="width: 150px; text-align: center;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $query_tampil = "SELECT * FROM supplier $search_query ORDER BY id DESC LIMIT $limit OFFSET $offset";
                    $result = $koneksi->query($query_tampil);
                    
                    $no = $offset + 1; 

                    if ($result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) {
                    ?>
                            <tr>
                                <td><?php echo $no++; ?></td>
                                <td><b><?php echo $row['nama_supplier']; ?></b></td>
                                <td><?php echo $row['kontak']; ?></td>
                                <td><?php echo $row['alamat']; ?></td>
                                <td style="text-align: center;">
                                    <button onclick="bukaModalEdit('<?php echo $row['id']; ?>', '<?php echo addslashes($row['nama_supplier']); ?>', '<?php echo addslashes($row['kontak']); ?>', '<?php echo addslashes($row['alamat']); ?>')" class="btn btn-edit btn-sm">Edit</button>
                                    <button onclick="bukaModalHapus('<?php echo $row['id']; ?>', '<?php echo addslashes($row['nama_supplier']); ?>')" class="btn btn-danger btn-sm">Hapus</button>
                                </td>
                            </tr>
                    <?php 
                        } 
                    } else { 
                    ?>
                        <tr>
                            <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 30px 0;">
                                🤷‍♂️ Tidak ada data supplier yang ditemukan.
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
        
        <!-- AREA TOMBOL PAGINATION -->
        <?php if($total_pages > 1): ?>
        <div style="margin-top: 20px; display: flex; gap: 5px; justify-content: flex-end;">
            <?php for($i = 1; $i <= $total_pages; $i++): ?>
                <a href="supplier.php?page=<?php echo $i; ?><?php echo ($search_keyword != '') ? '&search='.$search_keyword : ''; ?>"
                   class="btn btn-sm" 
                   style="background-color: <?php echo ($page == $i) ? 'var(--btn-primary)' : 'var(--border-color)'; ?>;">
                   <?php echo $i; ?>
                </a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>
</main>

<!-- ================================================== -->
<!-- KUMPULAN MODAL -->
<!-- ================================================== -->

<!-- 1. MODAL TAMBAH SUPPLIER -->
<div class="modal-overlay" id="modalTemplate">
    <div class="modal-box">
        <h3>Tambah Supplier Baru</h3>
        <form method="POST" action="">
            <div class="form-group">
                <label>Nama Supplier / Toko</label>
                <input type="text" name="nama_supplier" id="inputSupplierBaru" placeholder="Contoh: PT. Jaya Komputer" required autocomplete="off">
            </div>
            <div class="form-group">
                <label>Kontak / No Telepon</label>
                <input type="text" name="kontak" placeholder="Contoh: 08123456789" required autocomplete="off">
            </div>
            <div class="form-group">
                <label>Alamat Supplier</label>
                <textarea name="alamat" rows="3" placeholder="Masukkan alamat lengkap..." required></textarea>
            </div>
            <div class="modal-actions" style="justify-content: space-between;">
                <button type="button" class="btn btn-secondary" onclick="tutupModalTambah()">Tutup</button>
                <div style="display: flex; gap: 10px;">
                    <button type="submit" name="simpan_tambah_lagi" class="btn btn-success">Simpan & Tambah Lagi</button>
                    <button type="submit" name="simpan_supplier" class="btn btn-primary">Simpan</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- 2. MODAL EDIT SUPPLIER -->
<div class="modal-overlay" id="modalEditGlobal">
    <div class="modal-box">
        <h3>Edit Data Supplier</h3>
        <form method="POST" action="">
            <input type="hidden" name="id_supplier" id="edit_id_supplier">
            <div class="form-group">
                <label>Nama Supplier / Toko</label>
                <input type="text" name="nama_supplier" id="edit_nama_supplier" required autocomplete="off">
            </div>
            <div class="form-group">
                <label>Kontak / No Telepon</label>
                <input type="text" name="kontak" id="edit_kontak" required autocomplete="off">
            </div>
            <div class="form-group">
                <label>Alamat Supplier</label>
                <textarea name="alamat" id="edit_alamat" rows="3" required></textarea>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('modalEditGlobal').style.display='none'">Batal</button>
                <button type="submit" name="update_supplier" class="btn btn-primary">Update Data</button>
            </div>
        </form>
    </div>
</div>

<!-- 3. MODAL HAPUS ESTETIK -->
<div class="modal-overlay" id="modalHapusEstetik">
    <div class="modal-box" style="text-align: center; width: 350px;">
        <div style="font-size: 50px; margin-bottom: 15px;">🗑️</div>
        <h3 style="color: var(--btn-danger); margin-top: 0;">Konfirmasi Hapus</h3>
        <p style="color: var(--text-muted); line-height: 1.5;">
            Apakah Anda yakin ingin menghapus supplier <br>
            <b id="teksNamaSupplier" style="color: var(--text-light); font-size: 18px;"></b> ?
        </p>
        <div class="modal-actions" style="justify-content: center; margin-top: 25px;">
            <button type="button" class="btn btn-secondary" onclick="document.getElementById('modalHapusEstetik').style.display='none'">Batal</button>
            <a href="#" id="linkConfirmHapus" class="btn btn-danger">Ya, Hapus!</a>
        </div>
    </div>
</div>

<!-- SCRIPT UTAMA -->
<script>
    <?php if(isset($_GET['add_more']) && $_GET['add_more'] == 'true'): ?>
        document.getElementById('modalTemplate').style.display = 'flex';
        setTimeout(() => { document.getElementById('inputSupplierBaru').focus(); }, 100);
    <?php endif; ?>

    function tutupModalTambah() {
        document.getElementById('modalTemplate').style.display = 'none';
        const urlParams = new URLSearchParams(window.location.search);
        if(urlParams.has('add_more')) {
            window.location = 'supplier.php';
        }
    }

    const searchInput = document.getElementById('inputSearch');
    let typingTimer;
    const doneTypingInterval = 700;

    if(searchInput) {
        searchInput.addEventListener('keyup', function () {
            clearTimeout(typingTimer);
            typingTimer = setTimeout(function() {
                document.getElementById('formSearch').submit();
            }, doneTypingInterval);
        });

        window.onload = function() {
            if (searchInput.value.length > 0) {
                searchInput.focus();
                let val = searchInput.value;
                searchInput.value = '';
                searchInput.value = val;
            }
        };
    }

    function bukaModalEdit(id, nama, kontak, alamat) {
        document.getElementById('edit_id_supplier').value = id;
        document.getElementById('edit_nama_supplier').value = nama;
        document.getElementById('edit_kontak').value = kontak;
        document.getElementById('edit_alamat').value = alamat;
        document.getElementById('modalEditGlobal').style.display = 'flex';
    }

    function bukaModalHapus(id, nama) {
        document.getElementById('teksNamaSupplier').innerText = nama;
        document.getElementById('linkConfirmHapus').href = 'supplier.php?hapus=' + id;
        document.getElementById('modalHapusEstetik').style.display = 'flex';
    }
</script>

<?php require_once 'footer.php'; ?>
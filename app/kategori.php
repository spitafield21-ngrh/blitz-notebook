<?php
// ==========================================
// FILE: kategori.php
// FUNGSI: Mengelola Data Master Kategori (CRUD + Live Search + Pagination + UI/UX Tweak)
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
if (isset($_POST['simpan_kategori']) || isset($_POST['simpan_tambah_lagi'])) {
    $nama_kategori = bersihkan_input($koneksi, $_POST['nama_kategori']);
    
    // Cek apakah nama kategori sudah ada di database
    $cek_duplikat = $koneksi->query("SELECT id FROM kategori WHERE nama_kategori = '$nama_kategori'");
    
    if ($cek_duplikat->num_rows > 0) {
        echo "<script>alert('GAGAL! Kategori \"$nama_kategori\" sudah ada. Jangan masukkan data ganda!'); window.history.back();</script>";
    } else {
        $query_insert = "INSERT INTO kategori (nama_kategori) VALUES ('$nama_kategori')";
        if ($koneksi->query($query_insert)) {
            // Cek tombol mana yang ditekan
            if (isset($_POST['simpan_tambah_lagi'])) {
                // Jika "Tambah Lagi", arahkan kembali dengan parameter ?add_more=true
                echo "<script>window.location='kategori.php?add_more=true';</script>";
            } else {
                // Jika "Simpan" biasa
                echo "<script>window.location='kategori.php';</script>";
            }
        }
    }
}

// ==========================================
// 2. LOGIKA UPDATE
// ==========================================
if (isset($_POST['update_kategori'])) {
    $id_kategori   = bersihkan_input($koneksi, $_POST['id_kategori']);
    $nama_kategori = bersihkan_input($koneksi, $_POST['nama_kategori']);
    
    $cek_duplikat = $koneksi->query("SELECT id FROM kategori WHERE nama_kategori = '$nama_kategori' AND id != '$id_kategori'");
    
    if ($cek_duplikat->num_rows > 0) {
        echo "<script>alert('GAGAL UPDATE! Nama kategori \"$nama_kategori\" sudah dipakai kategori lain.'); window.history.back();</script>";
    } else {
        $query_update = "UPDATE kategori SET nama_kategori = '$nama_kategori' WHERE id = '$id_kategori'";
        if ($koneksi->query($query_update)) {
            echo "<script>window.location='kategori.php';</script>";
        }
    }
}

// ==========================================
// 3. LOGIKA DELETE
// ==========================================
if (isset($_GET['hapus'])) {
    $id_hapus = bersihkan_input($koneksi, $_GET['hapus']);
    $query_hapus = "DELETE FROM kategori WHERE id = '$id_hapus'";
    if ($koneksi->query($query_hapus)) {
        echo "<script>window.location='kategori.php';</script>";
    } else {
        echo "<script>alert('Gagal! Kategori sedang digunakan oleh data barang.'); window.location='kategori.php';</script>";
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
    $search_query = " WHERE nama_kategori LIKE '%$search_keyword%' ";
}

$query_total = $koneksi->query("SELECT COUNT(*) AS total FROM kategori $search_query");
$total_data = $query_total->fetch_assoc()['total'];
$total_pages = ceil($total_data / $limit);
?>

<!-- STYLE TAMBAHAN KHUSUS HALAMAN INI -->
<style>
    /* Desain Kolom Pencarian (Rounded & Glow Effect) */
    .input-search-rounded {
        width: 100%; 
        padding: 10px 20px; /* Padding kiri-kanan lebih lebar agar teks tidak menabrak sisi kapsul */
        border-radius: 25px; /* Bentuk rounded kapsul */
        background: rgba(0,0,0,0.2); 
        border: 1px solid var(--border-color); 
        color: white; 
        outline: none;
        transition: all 0.3s ease; /* Transisi animasi lembut */
    }
    
    .input-search-rounded:focus {
        border-color: var(--btn-primary); /* Garis menyala warna tema (kuning/orange) */
        box-shadow: 0 0 12px rgba(245, 158, 11, 0.4); /* Efek cahaya (Glow) */
        background: rgba(0,0,0,0.4);
    }
</style>

<!-- KONTEN UTAMA -->
<main class="main-content">
    <div class="header-content">
        <h2>📦 Master Data Kategori</h2>
        <button class="btn btn-primary" id="btnBukaModal">+ Tambah Kategori</button>
    </div>

    <!-- AREA PENCARIAN (LIVE SEARCH) -->
    <div style="margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
        <form method="GET" action="kategori.php" id="formSearch" style="width: 100%; max-width: 350px;">
            <input type="text" name="search" id="inputSearch" class="input-search-rounded" 
                   value="<?php echo $search_keyword; ?>" 
                   placeholder="🔍 Ketik nama kategori untuk mencari...">
        </form>
        <span style="color: var(--text-muted); font-size: 14px;">Total: <b style="color: var(--text-light);"><?php echo $total_data; ?></b> Kategori terdaftar</span>
    </div>

    <div class="panel-glass">
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th style="width: 50px;">No</th>
                        <th>Nama Kategori</th>
                        <th style="width: 150px; text-align: center;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $query_tampil = "SELECT * FROM kategori $search_query ORDER BY id DESC LIMIT $limit OFFSET $offset";
                    $result = $koneksi->query($query_tampil);
                    
                    $no = $offset + 1; 

                    if ($result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) {
                    ?>
                            <tr>
                                <td><?php echo $no++; ?></td>
                                <td><?php echo $row['nama_kategori']; ?></td>
                                <td style="text-align: center;">
                                    <button onclick="bukaModalEdit('<?php echo $row['id']; ?>', '<?php echo addslashes($row['nama_kategori']); ?>')" class="btn btn-edit btn-sm">Edit</button>
                                    <button onclick="bukaModalHapus('<?php echo $row['id']; ?>', '<?php echo addslashes($row['nama_kategori']); ?>')" class="btn btn-danger btn-sm">Hapus</button>
                                </td>
                            </tr>
                    <?php 
                        } 
                    } else { 
                    ?>
                        <tr>
                            <td colspan="3" style="text-align: center; color: var(--text-muted); padding: 30px 0;">
                                🤷‍♂️ Tidak ada data kategori yang ditemukan.
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
                <a href="kategori.php?page=<?php echo $i; ?><?php echo ($search_keyword != '') ? '&search='.$search_keyword : ''; ?>"
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

<!-- 1. MODAL TAMBAH KATEGORI DENGAN TOMBOL "TAMBAH LAGI" -->
<div class="modal-overlay" id="modalTemplate">
    <div class="modal-box">
        <h3>Tambah Kategori Baru</h3>
        <form method="POST" action="">
            <div class="form-group">
                <label>Nama Kategori</label>
                <!-- Tambah id="inputKategoriBaru" agar bisa otomatis difokuskan kursornya -->
                <input type="text" name="nama_kategori" id="inputKategoriBaru" placeholder="Contoh: Laptop, Aksesoris..." required autocomplete="off">
            </div>
            <!-- Susunan Tombol Diubah -->
            <div class="modal-actions" style="justify-content: space-between;">
                <button type="button" class="btn btn-secondary" id="btnBatal" onclick="tutupModalTambah()">Tutup</button>
                <div style="display: flex; gap: 10px;">
                    <button type="submit" name="simpan_tambah_lagi" class="btn btn-success">Simpan & Tambah Lagi</button>
                    <button type="submit" name="simpan_kategori" class="btn btn-primary">Simpan</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- 2. MODAL EDIT KATEGORI -->
<div class="modal-overlay" id="modalEditGlobal">
    <div class="modal-box">
        <h3>Edit Kategori</h3>
        <form method="POST" action="">
            <input type="hidden" name="id_kategori" id="edit_id_kategori">
            <div class="form-group">
                <label>Nama Kategori</label>
                <input type="text" name="nama_kategori" id="edit_nama_kategori" required autocomplete="off">
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('modalEditGlobal').style.display='none'">Batal</button>
                <button type="submit" name="update_kategori" class="btn btn-primary">Update Data</button>
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
            Apakah Anda yakin ingin menghapus kategori <br>
            <b id="teksNamaKategori" style="color: var(--text-light); font-size: 18px;"></b> ?
        </p>
        <div class="modal-actions" style="justify-content: center; margin-top: 25px;">
            <button type="button" class="btn btn-secondary" onclick="document.getElementById('modalHapusEstetik').style.display='none'">Batal</button>
            <a href="#" id="linkConfirmHapus" class="btn btn-danger">Ya, Hapus!</a>
        </div>
    </div>
</div>

<!-- SCRIPT KHUSUS HALAMAN INI -->
<script>
    // CEK TRIGGER "TAMBAH LAGI" DARI URL (PHP)
    <?php if(isset($_GET['add_more']) && $_GET['add_more'] == 'true'): ?>
        document.getElementById('modalTemplate').style.display = 'flex';
        // Otomatis fokuskan kursor ke dalam form setelah reload
        setTimeout(() => { document.getElementById('inputKategoriBaru').focus(); }, 100);
    <?php endif; ?>

    // Fungsi tutup modal tambah khusus (hilangkan efek add_more di url jika ada)
    function tutupModalTambah() {
        document.getElementById('modalTemplate').style.display = 'none';
        const urlParams = new URLSearchParams(window.location.search);
        if(urlParams.has('add_more')) {
            window.location = 'kategori.php';
        }
    }

    // FUNGSI LIVE SEARCH DEBOUNCE
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

    function bukaModalEdit(id, nama) {
        document.getElementById('edit_id_kategori').value = id;
        document.getElementById('edit_nama_kategori').value = nama;
        document.getElementById('modalEditGlobal').style.display = 'flex';
    }

    function bukaModalHapus(id, nama) {
        document.getElementById('teksNamaKategori').innerText = nama;
        document.getElementById('linkConfirmHapus').href = 'kategori.php?hapus=' + id;
        document.getElementById('modalHapusEstetik').style.display = 'flex';
    }
</script>

<?php require_once 'footer.php'; ?>
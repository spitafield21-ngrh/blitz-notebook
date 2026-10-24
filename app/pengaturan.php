<?php
// ==========================================
// FILE: pengaturan.php
// FUNGSI: Mengatur Profil Toko, 11 Banner Slideshow, Teks Kustom, & Reset Data
// ==========================================
require_once 'config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'owner') {
    echo "<script>alert('Akses Ditolak! Halaman ini khusus untuk Owner.'); window.location='index.php';</script>";
    exit;
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_pengaturan'])) {
    $nama_toko      = bersihkan_input($koneksi, $_POST['nama_toko']);
    $alamat         = bersihkan_input($koneksi, $_POST['alamat']);
    $telepon        = bersihkan_input($koneksi, $_POST['telepon']);
    $pesan_struk    = bersihkan_input($koneksi, $_POST['pesan_struk']);
    
    $slide_title    = isset($_POST['slide_title']) ? bersihkan_input($koneksi, $_POST['slide_title']) : '';
    $slide_subtitle = isset($_POST['slide_subtitle']) ? bersihkan_input($koneksi, $_POST['slide_subtitle']) : '';

    $logo_sql = "";
    $banner_sql_arr = [];
    $upload_dir = 'uploads/';

    if (!is_dir($upload_dir)) { mkdir($upload_dir, 0777, true); }
    $allowed_ext = ['png', 'jpg', 'jpeg', 'webp'];

    // Handle Upload Logo
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] == 0) {
        $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed_ext)) {
            $new_name = 'logo_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['logo']['tmp_name'], $upload_dir . $new_name)) { $logo_sql = ", logo = '$new_name'"; }
        }
    }

    // Handle Upload 11 Banner Slideshow secara dinamis
    for ($i = 1; $i <= 11; $i++) {
        $field_name = 'banner_' . $i;
        if (isset($_FILES[$field_name]) && $_FILES[$field_name]['error'] == 0) {
            $ext = strtolower(pathinfo($_FILES[$field_name]['name'], PATHINFO_EXTENSION));
            if (in_array($ext, $allowed_ext)) {
                $new_name = 'banner' . $i . '_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES[$field_name]['tmp_name'], $upload_dir . $new_name)) {
                    $banner_sql_arr[] = "$field_name = '$new_name'";
                }
            }
        }
    }

    $extra_banner_sql = count($banner_sql_arr) > 0 ? ", " . implode(", ", $banner_sql_arr) : "";

    $query = "UPDATE pengaturan SET nama_toko = '$nama_toko', alamat = '$alamat', telepon = '$telepon', pesan_struk = '$pesan_struk', slide_title = '$slide_title', slide_subtitle = '$slide_subtitle' $logo_sql $extra_banner_sql WHERE id = 1";
    
    if ($koneksi->query($query)) {
        $message = "<div class='alert alert-success'>Pengaturan toko & 11 banner slideshow berhasil diperbarui!</div>";
    } else {
        $message = "<div class='alert alert-danger'>Gagal memperbarui pengaturan! " . $koneksi->error . "</div>";
    }
}

// Reset Data Transaksi Handler
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reset_data_transaksi'])) {
    $password_konfirmasi = $_POST['password_owner'];
    $user_id = $_SESSION['user_id'];
    $q_user = $koneksi->query("SELECT password FROM users WHERE id = '$user_id'");
    if ($q_user && $q_user->num_rows > 0) {
        $data_user = $q_user->fetch_assoc();
        $db_password = $data_user['password'];
        $password_valid = (password_verify($password_konfirmasi, $db_password) || $password_konfirmasi === $db_password || md5($password_konfirmasi) === $db_password);

        if ($password_valid) {
            $koneksi->query("TRUNCATE TABLE penjualan");
            $koneksi->query("TRUNCATE TABLE penjualan_detail");
            $koneksi->query("TRUNCATE TABLE barang_masuk");
            $koneksi->query("TRUNCATE TABLE stok_opname");
            $koneksi->query("TRUNCATE TABLE pengeluaran");
            $koneksi->query("UPDATE barang SET stok = 0");
            $message = "<div class='alert alert-success'>🔥 <b>BERHASIL!</b> Seluruh histori transaksi dibersihkan.</div>";
        } else {
            $message = "<div class='alert alert-danger'>⚠️ Password konfirmasi salah.</div>";
        }
    }
}

$query_toko = $koneksi->query("SELECT * FROM pengaturan LIMIT 1");
$toko = $query_toko ? $query_toko->fetch_assoc() : [];

require_once 'header.php';
require_once 'sidebar.php';
?>

<style>
    :root { --glow-color: #f59e0b; --glass-bg: rgba(36, 36, 36, 0.4); --glass-border: rgba(255, 255, 255, 0.05); }
    .module-container { padding: 30px; color: #fff; max-width: 800px; margin: 0 auto; }
    .panel-setting { background: var(--glass-bg); backdrop-filter: blur(10px); border: 1px solid var(--glass-border); border-radius: 12px; padding: 25px; box-shadow: 0 8px 32px rgba(0,0,0,0.3); margin-bottom: 30px; }
    .danger-zone { background: rgba(239, 68, 68, 0.1); border: 1px solid #ef4444; border-radius: 12px; padding: 25px; }
    .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; line-height: 1.5; }
    .alert-success { background: rgba(16, 185, 129, 0.2); border: 1px solid #10b981; color: #34d399; }
    .alert-danger { background: rgba(239, 68, 68, 0.2); border: 1px solid #ef4444; color: #f87171; }
    .form-group { margin-bottom: 20px; }
    .form-group label { display: block; margin-bottom: 8px; font-weight: bold; color: #ccc; }
    .form-group input[type="text"], .form-group input[type="password"], .form-group textarea {
        width: 100%; padding: 12px; border-radius: 8px; background: rgba(0,0,0,0.3);
        border: 1px solid var(--glass-border); color: #fff; outline: none; box-sizing: border-box; font-family: inherit;
    }
    .form-group input:focus, .form-group textarea:focus { border-color: var(--glow-color); }
    .logo-preview { width: 100px; height: 100px; border-radius: 8px; border: 1px solid var(--glass-border); object-fit: cover; background: #000; display: flex; align-items: center; justify-content: center; margin-bottom: 10px; }
    .banner-preview { width: 180px; height: 100px; border-radius: 8px; border: 1px solid var(--glass-border); object-fit: cover; background: #000; margin-bottom: 10px; }
    .btn { border: none; border-radius: 8px; cursor: pointer; font-weight: bold; color: #fff; transition: 0.3s; }
    .btn-primary { background: #f59e0b; color: #000; }
    .btn-primary:hover { box-shadow: 0 0 15px #f59e0b; }
    .btn-danger { background: #ef4444; color: #fff; }
    .btn-secondary { background: #52525b; color: #fff; }
    .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.7); backdrop-filter: blur(5px); justify-content: center; align-items: center; z-index: 1000; }
    .modal-content { background: rgba(25, 25, 35, 0.95); border: 1px solid #ef4444; border-radius: 15px; padding: 30px; width: 500px; max-width: 90%; }
</style>

<main class="main-content">
    <div class="module-container">
        <h2>🛠️ Pengaturan Toko & 11 Slideshow Web</h2>
        <p style="color: var(--text-muted); margin-bottom: 25px;">Kelola identitas perusahaan, teks promosi, dan 11 slot banner slideshow estetik 3D.</p>
        
        <?= $message ?>

        <div class="panel-setting">
            <h3 style="margin-top:0; color: var(--glow-color); border-bottom: 1px solid var(--glass-border); padding-bottom: 10px;">🏢 Profil Toko & Struk</h3>
            <form method="POST" action="" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Nama Toko / Perusahaan</label>
                    <input type="text" name="nama_toko" value="<?= htmlspecialchars($toko['nama_toko'] ?? '') ?>" required autocomplete="off">
                </div>
                <div class="form-group">
                    <label>Alamat Lengkap</label>
                    <textarea name="alamat" rows="3" required><?= htmlspecialchars(str_replace('<', '', $toko['alamat'] ?? '')) ?></textarea>
                </div>
                <div class="form-group">
                    <label>Nomor Telepon / WhatsApp</label>
                    <input type="text" name="telepon" value="<?= htmlspecialchars($toko['telepon'] ?? '') ?>" required autocomplete="off">
                </div>
                <div class="form-group">
                    <label>Pesan Bawah Struk (Thermal 80mm)</label>
                    <input type="text" name="pesan_struk" value="<?= htmlspecialchars($toko['pesan_struk'] ?? '') ?>" required autocomplete="off">
                </div>
                <div class="form-group">
                    <label>Logo Toko (Format: PNG, JPG, WEBP)</label>
                    <?php if(!empty($toko['logo']) && file_exists('uploads/' . $toko['logo'])): ?>
                        <div><img src="uploads/<?= $toko['logo'] ?>" class="logo-preview" alt="Logo"></div>
                    <?php endif; ?>
                    <input type="file" name="logo" accept=".png, .jpg, .jpeg, .webp" style="padding: 8px;">
                </div>

                <hr style="border: 0; border-top: 1px solid var(--glass-border); margin: 30px 0;">

                <h3 style="color: var(--glow-color); margin-bottom: 15px;">✍️ Kustomisasi Teks Slideshow Landing Page</h3>
                <div class="form-group">
                    <label>Judul Utama (Headline Tengah)</label>
                    <input type="text" name="slide_title" value="<?= htmlspecialchars($toko['slide_title'] ?? '') ?>" placeholder="Contoh: Pusat Teknologi & Laptop Berkualitas" required>
                </div>
                <div class="form-group">
                    <label>Sub-Judul (Deskripsi Singkat di Bawah Judul)</label>
                    <textarea name="slide_subtitle" rows="2" placeholder="Contoh: Bergaransi resmi, harga bersaing..." required><?= htmlspecialchars($toko['slide_subtitle'] ?? '') ?></textarea>
                </div>

                <hr style="border: 0; border-top: 1px solid var(--glass-border); margin: 30px 0;">

                <h3 style="color: var(--glow-color); margin-bottom: 15px;">🖼️ Manajemen 11 Banner Slideshow 3D</h3>
                <p style="font-size: 13px; color: #aaa; margin-bottom: 20px;">Unggah hingga 11 gambar wallpaper bernuansa 3D tech, cyberpunk, dan brand global.</p>

                <?php for($i = 1; $i <= 11; $i++): ?>
                    <div class="form-group">
                        <label>Banner Slideshow <?= $i ?></label>
                        <?php 
                        $b_col = 'banner_' . $i;
                        if(!empty($toko[$b_col]) && file_exists('uploads/' . $toko[$b_col])): 
                        ?>
                            <div><img src="uploads/<?= $toko[$b_col] ?>" class="banner-preview"></div>
                        <?php endif; ?>
                        <input type="file" name="banner_<?= $i ?>" accept=".png, .jpg, .jpeg, .webp" style="padding: 8px;">
                    </div>
                <?php endfor; ?>

                <div style="margin-top: 30px; text-align: right;">
                    <button type="submit" name="update_pengaturan" class="btn btn-primary" style="padding: 12px 25px; font-size: 15px;">💾 Simpan Perubahan</button>
                </div>
            </form>
        </div>

        <div class="danger-zone">
            <h3 style="margin-top: 0; color: #ef4444;">🚨 Danger Zone (Go-Live)</h3>
            <button type="button" class="btn btn-danger" style="padding: 12px 20px; font-size: 14px;" onclick="openModal('modalReset')">🔥 Reset Semua Data Transaksi</button>
        </div>
    </div>
</main>

<div class="modal-overlay" id="modalReset">
    <div class="modal-content">
        <h3 style="margin-top:0; color: #ef4444;">⚠️ KONFIRMASI RESET DATABASE</h3>
        <form method="POST" action="">
            <div class="form-group">
                <label style="color: #f87171;">Masukkan Password Login Owner:</label>
                <input type="password" name="password_owner" required autocomplete="off">
            </div>
            <div style="text-align: right; margin-top: 25px; display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" class="btn btn-secondary" style="padding: 10px 18px;" onclick="closeModal('modalReset')">Batal</button>
                <button type="submit" name="reset_data_transaksi" class="btn btn-danger" style="padding: 10px 18px;">Ya, Reset!</button>
            </div>
        </form>
    </div>
</div>
<script>
    function openModal(id) { document.getElementById(id).style.display = 'flex'; }
    function closeModal(id) { document.getElementById(id).style.display = 'none'; }
</script>
<?php require_once 'footer.php'; ?>
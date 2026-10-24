<?php
// ==========================================
// FILE: servis.php
// FUNGSI: Modul Service Center (Penerimaan, SN Unit Anti-Tukar, & Tracking Servis)
// ==========================================
require_once 'config.php';

// AUTO-CREATE TABEL SERVIS JIKA BELUM ADA
$koneksi->query("CREATE TABLE IF NOT EXISTS servis (
    id INT AUTO_INCREMENT PRIMARY KEY,
    no_servis VARCHAR(50) NOT NULL,
    tanggal_masuk DATETIME NOT NULL,
    nama_pelanggan VARCHAR(150) NOT NULL,
    no_telp VARCHAR(50),
    tipe_perangkat VARCHAR(150) NOT NULL,
    sn_perangkat VARCHAR(100) DEFAULT '-',
    keluhan TEXT NOT NULL,
    kelengkapan VARCHAR(255),
    status VARCHAR(50) DEFAULT 'Antrean',
    biaya_jasa INT DEFAULT 0,
    tanggal_selesai DATETIME NULL
)");

// MANTRA PENYELAMAT: Cek apakah kolom sn_perangkat sudah ada (Auto-Migrasi Kebal Error PHP 8)
$check_col = $koneksi->query("SHOW COLUMNS FROM servis LIKE 'sn_perangkat'");
if ($check_col && $check_col->num_rows == 0) {
    $koneksi->query("ALTER TABLE servis ADD COLUMN sn_perangkat VARCHAR(100) DEFAULT '-'");
}

// Pastikan user sudah login
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$message = '';

// 1. PROSES SIMPAN / TERIMA SERVIS BARU / EDIT
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['simpan_servis'])) {
    $id             = (int)($_POST['id'] ?? 0);
    $nama_pelanggan = bersihkan_input($koneksi, $_POST['nama_pelanggan'] ?? '');
    $no_telp        = bersihkan_input($koneksi, $_POST['no_telp'] ?? '');
    $tipe_perangkat = bersihkan_input($koneksi, $_POST['tipe_perangkat'] ?? '');
    $sn_perangkat   = bersihkan_input($koneksi, $_POST['sn_perangkat'] ?? '');
    $keluhan        = bersihkan_input($koneksi, $_POST['keluhan'] ?? '');
    $kelengkapan    = bersihkan_input($koneksi, $_POST['kelengkapan'] ?? '');
    
    if (empty($sn_perangkat)) { $sn_perangkat = '-'; }

    if ($id > 0) {
        // Edit Data
        $q = "UPDATE servis SET 
                nama_pelanggan='$nama_pelanggan', 
                no_telp='$no_telp', 
                tipe_perangkat='$tipe_perangkat', 
                sn_perangkat='$sn_perangkat', 
                keluhan='$keluhan', 
                kelengkapan='$kelengkapan' 
              WHERE id=$id";
    } else {
        // Insert Baru
        $no_servis = 'SVC-' . date('YmdHis');
        $tgl_masuk = date('Y-m-d H:i:s');
        $q = "INSERT INTO servis (no_servis, tanggal_masuk, nama_pelanggan, no_telp, tipe_perangkat, sn_perangkat, keluhan, kelengkapan) 
              VALUES ('$no_servis', '$tgl_masuk', '$nama_pelanggan', '$no_telp', '$tipe_perangkat', '$sn_perangkat', '$keluhan', '$kelengkapan')";
    }
    
    if ($koneksi->query($q)) {
        $message = "<div class='alert alert-success'>Data Servis berhasil disimpan!</div>";
    } else {
        $message = "<div class='alert alert-danger'>Error: " . $koneksi->error . "</div>";
    }
}

// 2. PROSES UPDATE STATUS & BIAYA
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status_servis'])) {
    $id_servis   = (int)$_POST['id_servis'];
    $status_baru = bersihkan_input($koneksi, $_POST['status']);
    $biaya_jasa  = (int)($_POST['biaya_jasa'] ?? 0);
    
    $tgl_selesai_query = "";
    if ($status_baru == 'Selesai' || $status_baru == 'Diambil') {
        $tgl_selesai_query = ", tanggal_selesai = NOW() ";
    }

    $q_update = "UPDATE servis SET status = '$status_baru', biaya_jasa = '$biaya_jasa' $tgl_selesai_query WHERE id = $id_servis";
    if ($koneksi->query($q_update)) {
        $message = "<div class='alert alert-success'>Status Servis berhasil diperbarui menjadi <b>$status_baru</b>!</div>";
    }
}

// 3. PROSES HAPUS
if (isset($_GET['hapus'])) {
    $id_hapus = (int)$_GET['hapus'];
    if($koneksi->query("DELETE FROM servis WHERE id = $id_hapus")){
        $message = "<div class='alert alert-success'>Data servis berhasil dihapus.</div>";
    }
}

// PAGINATION & PENCARIAN
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? bersihkan_input($koneksi, $_GET['search']) : '';

$where = "WHERE 1=1";
if ($search != '') { 
    $where .= " AND (no_servis LIKE '%$search%' OR nama_pelanggan LIKE '%$search%' OR tipe_perangkat LIKE '%$search%' OR sn_perangkat LIKE '%$search%')"; 
}

$q_servis = $koneksi->query("SELECT * FROM servis $where ORDER BY id DESC LIMIT $limit OFFSET $offset");
$q_total = $koneksi->query("SELECT COUNT(*) as total FROM servis $where");
$total_data = $q_total->fetch_assoc()['total'];
$total_page = ceil($total_data / $limit);

require_once 'header.php';
require_once 'sidebar.php';

// Fungsi Warna Status
function badgeStatus($status) {
    if($status == 'Antrean') return "<span class='badge' style='background:#f59e0b; color:#fff;'>Antrean</span>";
    if($status == 'Dikerjakan') return "<span class='badge' style='background:#3b82f6; color:#fff;'>Dikerjakan</span>";
    if($status == 'Selesai') return "<span class='badge' style='background:#10b981; color:#fff;'>Selesai</span>";
    if($status == 'Diambil') return "<span class='badge' style='background:#064e3b; color:#34d399; border:1px solid #10b981;'>Diambil (Lunas)</span>";
    if($status == 'Batal') return "<span class='badge' style='background:#ef4444; color:#fff;'>Batal</span>";
    return "<span class='badge'>$status</span>";
}
?>

<style>
    .module-container { padding: 30px; color: #fff; }
    .toolbar { display: flex; justify-content: space-between; margin-bottom: 20px; align-items: center; flex-wrap: wrap; gap: 15px; }
    .search-capsule { padding: 10px 20px; border-radius: 25px; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.2); color: #fff; width: 320px; outline: none; transition: 0.3s; }
    .search-capsule:focus { box-shadow: 0 0 15px rgba(56, 189, 248, 0.5); border-color: #38bdf8; }
    
    .btn { padding: 10px 15px; border-radius: 8px; cursor: pointer; border: none; color: #fff; font-weight: bold; transition: 0.3s; font-size:13px; }
    .btn-primary { background: #38bdf8; color: #000; }
    .btn-primary:hover { box-shadow: 0 0 15px #38bdf8; }
    .btn-status { background: #6366f1; color: #fff; }
    .btn-danger { background: #ef4444; color: #fff; }
    .btn-edit { background: #f59e0b; color: #000; }
    
    .glass-table { width: 100%; border-collapse: collapse; background: rgba(36, 36, 36, 0.4); backdrop-filter: blur(10px); border-radius: 8px; overflow: hidden; font-size: 13px; }
    .glass-table th, .glass-table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.1); }
    .glass-table th { background: rgba(0,0,0,0.5); color: #38bdf8; text-transform: uppercase; }
    
    .badge { padding: 5px 10px; border-radius: 6px; font-weight: bold; font-size: 11px; display: inline-block; text-align: center;}
    
    .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.7); backdrop-filter: blur(5px); justify-content: center; align-items: center; z-index: 1000; overflow-y: auto; padding: 20px; }
    .modal-content { background: rgba(25, 25, 35, 0.95); border: 1px solid #38bdf8; border-radius: 12px; padding: 25px; width: 600px; max-width: 100%; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
    
    .form-group { margin-bottom: 15px; }
    .form-group label { display: block; margin-bottom: 5px; color: #ccc; font-size: 13px; font-weight: bold;}
    .form-control { width: 100%; padding: 10px; border-radius: 6px; background: rgba(0,0,0,0.4); border: 1px solid rgba(255,255,255,0.2); color: #fff; outline: none; box-sizing: border-box; }
    .form-control:focus { border-color: #38bdf8; }
    .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; }
    .alert-success { background: rgba(16, 185, 129, 0.2); border: 1px solid #10b981; color: #34d399; }
</style>

<div class="main-content module-container">
    <h2>🛠️ Service Center (Pusat Perbaikan)</h2>
    <p style="color: #94a3b8; margin-top: -15px;">Kelola antrean servis, pencatatan SN unit, status perbaikan, dan biaya jasa.</p>
    
    <?= $message ?>

    <div class="toolbar">
        <form method="GET" style="display:flex; gap:10px;">
            <input type="text" name="search" class="search-capsule" placeholder="Cari No Servis, Pelanggan, Tipe, atau SN..." value="<?= htmlspecialchars($search) ?>">
            <button type="submit" class="btn btn-primary">🔍</button>
        </form>
        <button class="btn btn-primary" onclick="openModalServis()">+ Terima Servisan Baru</button>
    </div>

    <table class="glass-table">
        <thead>
            <tr>
                <th style="width:5%;">No</th>
                <th>Tgl Masuk & No Servis</th>
                <th>Pelanggan & Perangkat</th>
                <th>Keluhan & Kelengkapan</th>
                <th style="text-align:center;">Status</th>
                <th style="text-align:right;">Biaya Jasa</th>
                <th style="text-align:center;">Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if($q_servis && $q_servis->num_rows > 0): ?>
                <?php $no = $offset + 1; while($row = $q_servis->fetch_assoc()): 
                    $dataJson = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
                ?>
                <tr>
                    <td><?= $no++ ?></td>
                    <td>
                        <b style="color:#38bdf8; font-size:14px;"><?= $row['no_servis'] ?></b><br>
                        <small style="color:#aaa;"><?= date('d M Y, H:i', strtotime($row['tanggal_masuk'])) ?></small>
                    </td>
                    <td>
                        <b><?= $row['nama_pelanggan'] ?></b> <small>(<?= $row['no_telp'] ?>)</small><br>
                        <span style="color:#e2e8f0; font-size:12px;"><?= $row['tipe_perangkat'] ?></span><br>
                        <span style="color:#38bdf8; font-size:11px; font-weight:bold;">SN: <?= $row['sn_perangkat'] != '' ? $row['sn_perangkat'] : '-' ?></span>
                    </td>
                    <td>
                        <span style="color:#f87171; font-weight:bold; font-size:12px;">Keluhan:</span> <?= $row['keluhan'] ?><br>
                        <span style="color:#fbbf24; font-weight:bold; font-size:12px;">Bawaan:</span> <?= $row['kelengkapan'] != '' ? $row['kelengkapan'] : 'Unit Only' ?>
                    </td>
                    <td style="text-align:center;">
                        <?= badgeStatus($row['status']) ?>
                    </td>
                    <td style="text-align:right; font-weight:bold; color:#34d399;">
                        Rp <?= number_format($row['biaya_jasa'],0,',','.') ?>
                    </td>
                    <td style="text-align:center;">
                        <button class="btn btn-status" title="Update Status" onclick='openModalStatus(<?= $dataJson ?>)'>🔄</button>
                        <button class="btn btn-edit" title="Edit Data" onclick='openModalServis(<?= $dataJson ?>)'>✏️</button>
                        <a href="servis.php?hapus=<?= $row['id'] ?>" class="btn btn-danger" onclick="return confirm('Yakin hapus data servis ini?')" title="Hapus">🗑️</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="7" style="text-align:center; padding: 20px; color:#aaa;">Belum ada antrean servis.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="modal-overlay" id="modalServis">
    <div class="modal-content">
        <h3 style="margin-top:0; color:#38bdf8; border-bottom:1px solid rgba(255,255,255,0.2); padding-bottom:10px;" id="modalServisTitle">Terima Servisan Baru</h3>
        <form method="POST" action="">
            <input type="hidden" name="id" id="s_id" value="0">
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                <div class="form-group">
                    <label>Nama Pelanggan <span style="color:red;">*</span></label>
                    <input type="text" name="nama_pelanggan" id="s_nama" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>No. Telp / WA</label>
                    <input type="text" name="no_telp" id="s_telp" class="form-control">
                </div>
                <div class="form-group">
                    <label>Tipe / Model Perangkat <span style="color:red;">*</span></label>
                    <input type="text" name="tipe_perangkat" id="s_tipe" class="form-control" placeholder="Contoh: Asus ROG G513..." required>
                </div>
                <div class="form-group">
                    <label>Serial Number (SN) Unit <span style="color:#38bdf8;">(Anti-Tukar)</span></label>
                    <input type="text" name="sn_perangkat" id="s_sn" class="form-control" placeholder="Scan/Ketik SN di bodi laptop...">
                </div>
                <div class="form-group" style="grid-column: span 2;">
                    <label>Keluhan / Kerusakan <span style="color:red;">*</span></label>
                    <textarea name="keluhan" id="s_keluhan" class="form-control" rows="2" required></textarea>
                </div>
                <div class="form-group" style="grid-column: span 2;">
                    <label>Kelengkapan (Bawaan)</label>
                    <input type="text" name="kelengkapan" id="s_kelengkapan" class="form-control" placeholder="Contoh: Unit, Charger, Tas (Kosongkan jika Unit Only)">
                </div>
            </div>
            <div style="text-align: right; margin-top: 20px;">
                <button type="button" class="btn" style="background:#52525b; margin-right:10px;" onclick="closeModal()">Batal</button>
                <button type="submit" name="simpan_servis" class="btn btn-primary">💾 Simpan Servisan</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="modalStatus">
    <div class="modal-content" style="width: 450px;">
        <h3 style="margin-top:0; color:#10b981; border-bottom:1px solid rgba(255,255,255,0.2); padding-bottom:10px;">🔄 Update Status Servis</h3>
        <form method="POST" action="">
            <input type="hidden" name="id_servis" id="st_id">
            <div class="form-group">
                <label>No Servis & Pelanggan</label>
                <input type="text" id="st_info" class="form-control" disabled style="color:#aaa;">
            </div>
            <div class="form-group">
                <label>Update Status</label>
                <select name="status" id="st_status" class="form-control">
                    <option value="Antrean">Antrean (Belum Dicek)</option>
                    <option value="Dikerjakan">Dikerjakan (Sedang Diservis)</option>
                    <option value="Selesai">Selesai (Siap Diambil)</option>
                    <option value="Diambil">Diambil (Sudah Lunas & Selesai Total)</option>
                    <option value="Batal">Batal (Tidak Jadi Servis)</option>
                </select>
            </div>
            <div class="form-group">
                <label>Biaya Jasa (Rp)</label>
                <input type="number" name="biaya_jasa" id="st_biaya" class="form-control" min="0">
                <small style="color:#94a3b8;">*Isi nominal biaya jika status sudah Selesai/Diambil.</small>
            </div>
            <div style="text-align: right; margin-top: 25px;">
                <button type="button" class="btn" style="background:#52525b; margin-right:10px;" onclick="closeModal()">Batal</button>
                <button type="submit" name="update_status_servis" class="btn" style="background:#10b981;">💾 Update Sekarang</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openModalServis(data = null) {
        if(data) {
            document.getElementById('modalServisTitle').innerText = 'Edit Data Servisan';
            document.getElementById('s_id').value = data.id;
            document.getElementById('s_nama').value = data.nama_pelanggan;
            document.getElementById('s_telp').value = data.no_telp;
            document.getElementById('s_tipe').value = data.tipe_perangkat;
            document.getElementById('s_sn').value = data.sn_perangkat || '';
            document.getElementById('s_keluhan').value = data.keluhan;
            document.getElementById('s_kelengkapan').value = data.kelengkapan;
        } else {
            document.getElementById('modalServisTitle').innerText = 'Terima Servisan Baru';
            document.getElementById('s_id').value = '0';
            document.getElementById('s_nama').value = '';
            document.getElementById('s_telp').value = '';
            document.getElementById('s_tipe').value = '';
            document.getElementById('s_sn').value = '';
            document.getElementById('s_keluhan').value = '';
            document.getElementById('s_kelengkapan').value = '';
        }
        document.getElementById('modalServis').style.display = 'flex';
    }

    function openModalStatus(data) {
        document.getElementById('st_id').value = data.id;
        document.getElementById('st_info').value = data.no_servis + ' - ' + data.nama_pelanggan;
        document.getElementById('st_status').value = data.status;
        document.getElementById('st_biaya').value = data.biaya_jasa;
        document.getElementById('modalStatus').style.display = 'flex';
    }

    function closeModal() {
        document.getElementById('modalServis').style.display = 'none';
        document.getElementById('modalStatus').style.display = 'none';
    }
</script>

<?php require_once 'footer.php'; ?>
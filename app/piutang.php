<?php
// ==========================================
// FILE: piutang.php
// FUNGSI: Mengelola Buku Piutang & Pembayaran Cicilan Kasbon
// ==========================================
require_once 'config.php';

// Pastikan hanya Owner (atau Kasir jika Coach mengizinkan, di sini kita set Owner & Kasir bisa akses)
if (!isset($_SESSION['role'])) {
    echo "<script>alert('Silakan login terlebih dahulu!'); window.location='login.php';</script>";
    exit;
}

// ==========================================
// 1. ENDPOINT AJAX: LIVE SEARCH & PAGINATION
// ==========================================
if (isset($_GET['ajax_search'])) {
    ob_clean();
    header('Content-Type: application/json');
    
    $search = isset($_GET['search']) ? $koneksi->real_escape_string($_GET['search']) : '';
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = 10;
    $offset = ($page - 1) * $limit;

    $where = "";
    if ($search != '') {
        $where = "WHERE no_transaksi LIKE '%$search%' OR nama_pelanggan LIKE '%$search%'";
    }

    $query = "SELECT * FROM piutang $where ORDER BY status ASC, jatuh_tempo ASC LIMIT $limit OFFSET $offset";
    $result = $koneksi->query($query);

    $countQuery = $koneksi->query("SELECT COUNT(*) as total FROM piutang $where");
    $totalData = $countQuery ? $countQuery->fetch_assoc()['total'] : 0;
    $totalPages = ceil($totalData / $limit);

    $tableHtml = "";

    if ($result && $result->num_rows > 0) {
        $no = $offset + 1;
        while ($row = $result->fetch_assoc()) {
            
            // Pewarnaan Status
            if ($row['status'] == 'Lunas') {
                $badge_status = "<span style='background:#10b981; color:#fff; padding:4px 8px; border-radius:4px; font-size:12px; font-weight:bold;'>LUNAS</span>";
                $btn_bayar = "<button class='btn-action' style='background:#52525b; cursor:not-allowed;' disabled>✔️ Selesai</button>";
            } else {
                // Cek apakah sudah lewat jatuh tempo
                $tgl_sekarang = date('Y-m-d');
                $is_terlambat = ($row['jatuh_tempo'] < $tgl_sekarang);
                $warna_badge = $is_terlambat ? '#ef4444' : '#f59e0b';
                $teks_status = $is_terlambat ? 'JATUH TEMPO' : 'BELUM LUNAS';
                
                $badge_status = "<span style='background:{$warna_badge}; color:#000; padding:4px 8px; border-radius:4px; font-size:12px; font-weight:bold;'>{$teks_status}</span>";
                
                $dataJson = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
                $btn_bayar = "<button class='btn-action btn-edit' onclick='openBayarModal($dataJson)'>💳 Bayar/Cicil</button>";
            }

            $tableHtml .= "<tr>
                <td style='text-align:center;'>{$no}</td>
                <td><b>{$row['no_transaksi']}</b><br><small style='color:var(--text-muted);'>JT: " . date('d M Y', strtotime($row['jatuh_tempo'])) . "</small></td>
                <td><b>{$row['nama_pelanggan']}</b><br><small style='color:var(--text-muted);'>{$row['no_telp']}</small></td>
                <td style='text-align:right;'>Rp " . number_format($row['total_hutang'], 0, ',', '.') . "</td>
                <td style='text-align:right; color:#34d399;'>Rp " . number_format($row['jumlah_terbayar'], 0, ',', '.') . "</td>
                <td style='text-align:right; color:#ef4444; font-weight:bold;'>Rp " . number_format($row['sisa_hutang'], 0, ',', '.') . "</td>
                <td style='text-align:center;'>{$badge_status}</td>
                <td style='text-align:center;'>{$btn_bayar}</td>
            </tr>";
            $no++;
        }
    } else {
        $tableHtml = "<tr><td colspan='8' style='text-align:center; color: #a1a1aa; padding: 20px;'>Data piutang tidak ditemukan.</td></tr>";
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
// 2. HANDLE PEMBAYARAN CICILAN / PELUNASAN
// ==========================================
$message = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['proses_bayar'])) {
    $id_piutang = (int)$_POST['id_piutang'];
    $nominal_bayar = (int)$_POST['nominal_bayar'];

    // Ambil data piutang saat ini
    $q_cek = $koneksi->query("SELECT * FROM piutang WHERE id = $id_piutang");
    if ($q_cek && $q_cek->num_rows > 0) {
        $p = $q_cek->fetch_assoc();
        
        $terbayar_baru = $p['jumlah_terbayar'] + $nominal_bayar;
        $sisa_baru = $p['sisa_hutang'] - $nominal_bayar;
        
        // Cek jika kelebihan bayar
        if ($sisa_baru < 0) {
            $sisa_baru = 0;
            $terbayar_baru = $p['total_hutang'];
        }

        $status_baru = ($sisa_baru <= 0) ? 'Lunas' : 'Belum Lunas';

        // Update Tabel Piutang
        $update = $koneksi->query("UPDATE piutang SET jumlah_terbayar = '$terbayar_baru', sisa_hutang = '$sisa_baru', status = '$status_baru' WHERE id = $id_piutang");
        
        if ($update) {
            // Opsional: Coach bisa menambahkan tabel riwayat_cicilan di masa depan jika ingin detail kapan saja mereka nyicil.
            // Untuk saat ini, mengupdate nilai di tabel piutang sudah cukup mewakili.
            $message = "<div class='alert alert-success'>Pembayaran senilai Rp " . number_format($nominal_bayar,0,',','.') . " berhasil diproses!</div>";
        } else {
            $message = "<div class='alert alert-danger'>Gagal memproses pembayaran!</div>";
        }
    }
}

// ==========================================
// 3. TAMPILAN HTML
// ==========================================
require_once 'header.php';
require_once 'sidebar.php';
?>

<style>
    :root {
        --glow-color: #f59e0b; /* Amber/Orange untuk Piutang */
        --glass-bg: rgba(36, 36, 36, 0.4);
        --glass-border: rgba(255, 255, 255, 0.1);
        --btn-primary: #f59e0b;
    }
    
    .module-container { padding: 30px; color: #fff; }
    .toolbar { display: flex; justify-content: space-between; margin-bottom: 20px; align-items: center; }
    
    .search-capsule {
        padding: 10px 20px; border-radius: 25px; background: rgba(0,0,0,0.2);
        border: 1px solid var(--glass-border); color: #fff; width: 320px; outline: none; transition: all 0.3s ease; font-family: inherit;
    }
    .search-capsule:focus { box-shadow: 0 0 12px rgba(245, 158, 11, 0.5); border-color: var(--btn-primary); }

    .glass-table {
        width: 100%; border-collapse: collapse; background: var(--glass-bg);
        backdrop-filter: blur(10px); border-radius: 8px; overflow: hidden;
    }
    .glass-table th, .glass-table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid var(--glass-border); }
    .glass-table th { background: rgba(0,0,0,0.3); color: var(--btn-primary); }
    
    .btn { padding: 10px 20px; border-radius: 8px; cursor: pointer; border: none; color: #fff; transition: 0.3s; font-weight: bold;}
    .btn-action { padding: 6px 12px; border-radius: 5px; cursor: pointer; border: none; color: #fff; font-weight: bold;}
    .btn-edit { background: #3b82f6; }
    .btn-edit:hover { background: #2563eb; box-shadow: 0 0 10px #3b82f6; }

    .modal-overlay {
        display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0, 0, 0, 0.6); backdrop-filter: blur(5px);
        justify-content: center; align-items: center; z-index: 1000;
    }
    .modal-content {
        background: rgba(30, 30, 40, 0.95); border: 1px solid var(--glass-border);
        border-radius: 15px; padding: 25px; width: 450px; max-width: 90%;
        box-shadow: 0 10px 30px rgba(0,0,0,0.5);
    }
    .form-group { margin-bottom: 15px; text-align: left; }
    .form-group label { display: block; margin-bottom: 5px; font-size: 14px; color: #ccc; }
    .form-control {
        width: 100%; padding: 12px; border-radius: 8px; background: rgba(0,0,0,0.2);
        border: 1px solid var(--glass-border); color: #fff; outline: none; box-sizing: border-box; font-size: 16px;
    }
    .form-control:focus { border-color: var(--btn-primary); }
    
    .pagination { margin-top: 20px; display: flex; gap: 5px; justify-content: flex-end; }
    .btn-page { background: var(--glass-bg); border: 1px solid var(--glass-border); color: #fff; padding: 8px 12px; cursor: pointer; border-radius: 5px; }
    .btn-page.active { background: var(--btn-primary); color: #000; font-weight: bold; }
    
    .alert { padding: 15px; border-radius: 8px; margin-bottom: 15px; }
    .alert-success { background: rgba(16, 185, 129, 0.2); border: 1px solid #10b981; color: #34d399; }
    .alert-danger { background: rgba(239, 68, 68, 0.2); border: 1px solid #ef4444; color: #f87171; }
</style>

<div class="main-content module-container">
    <h2>📒 Buku Piutang & Tagihan</h2>
    <p style="color: var(--text-muted); margin-top: -15px; margin-bottom: 20px;">Kelola tagihan pelanggan dari transaksi Tempo / Kasbon.</p>
    
    <?= $message ?>
    
    <div class="toolbar">
        <input type="text" id="searchBar" class="search-capsule" placeholder="Cari No. TRX atau Nama Pelanggan... 🔍" autocomplete="off">
    </div>

    <table class="glass-table">
        <thead>
            <tr>
                <th style="width: 5%; text-align:center;">No</th>
                <th>No. Transaksi</th>
                <th>Pelanggan</th>
                <th style="text-align:right;">Total Hutang</th>
                <th style="text-align:right;">Telah Dibayar</th>
                <th style="text-align:right;">Sisa Tagihan</th>
                <th style="text-align:center;">Status</th>
                <th style="text-align:center;">Aksi</th>
            </tr>
        </thead>
        <tbody id="tableBody"></tbody>
    </table>
    
    <div class="pagination" id="paginationBox"></div>
</div>

<div class="modal-overlay" id="modalBayar">
    <div class="modal-content">
        <h3 style="margin-top:0; color:var(--btn-primary); text-align:center;">💳 Proses Pembayaran</h3>
        <p style="text-align:center; color:#ccc; font-size:14px; margin-top:-10px;">Pelanggan: <b id="namaPelangganBayar" style="color:#fff;"></b></p>
        
        <div style="background: rgba(239, 68, 68, 0.1); border: 1px dashed #ef4444; padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center;">
            <span style="color: #f87171; font-size: 13px;">SISA HUTANG SAAT INI</span><br>
            <b style="color: #ef4444; font-size: 24px;" id="sisaHutangText">Rp 0</b>
        </div>

        <form method="POST">
            <input type="hidden" name="id_piutang" id="id_piutang_input">
            
            <div class="form-group">
                <label>Nominal Pembayaran (Rp)</label>
                <input type="number" name="nominal_bayar" id="nominal_bayar_input" class="form-control" placeholder="0" required min="1">
            </div>
            
            <div style="text-align: right; margin-top: 25px;">
                <button type="button" class="btn" style="background:#52525b;" onclick="closeModal('modalBayar')">Batal</button>
                <button type="submit" name="proses_bayar" class="btn" style="background:#10b981; color:#fff;">✔️ Proses Pembayaran</button>
            </div>
        </form>
    </div>
</div>

<script>
    let currentPage = 1;

    function loadData() {
        let keyword = document.getElementById('searchBar').value;
        fetch(`piutang.php?ajax_search=1&search=${keyword}&page=${currentPage}`)
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

    function openBayarModal(data) {
        document.getElementById('id_piutang_input').value = data.id;
        document.getElementById('namaPelangganBayar').innerText = data.nama_pelanggan;
        document.getElementById('sisaHutangText').innerText = "Rp " + parseInt(data.sisa_hutang).toLocaleString('id-ID');
        
        // Set otomatis nominal full pelunasan ke input, tapi bisa diganti jika cuma mau nyicil
        document.getElementById('nominal_bayar_input').value = data.sisa_hutang;
        document.getElementById('nominal_bayar_input').max = data.sisa_hutang; // Cegah bayar lebih dari sisa hutang
        
        openModal('modalBayar');
    }

    document.addEventListener('DOMContentLoaded', () => {
        loadData();
    });
</script>

<?php require_once 'footer.php'; ?>
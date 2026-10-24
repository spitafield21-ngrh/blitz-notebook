<?php
// ==========================================
// FILE: riwayat_penjualan.php
// FUNGSI: Menampilkan Histori Transaksi & Detail Pembelian
// ==========================================
require_once 'config.php';

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
        $where = "WHERE p.no_transaksi LIKE '%$search%' 
                  OR p.nama_customer LIKE '%$search%' 
                  OR u.nama_lengkap LIKE '%$search%'";
    }

    $query = "SELECT p.*, u.nama_lengkap 
              FROM penjualan p 
              JOIN users u ON p.id_user = u.id 
              $where ORDER BY p.id DESC LIMIT $limit OFFSET $offset";
    $result = $koneksi->query($query);

    $countQuery = $koneksi->query("SELECT COUNT(*) as total FROM penjualan p JOIN users u ON p.id_user = u.id $where");
    $totalData = $countQuery->fetch_assoc()['total'];
    $totalPages = ceil($totalData / $limit);

    $tableHtml = "";
    if ($result && $result->num_rows > 0) {
        $no = $offset + 1;
        while ($row = $result->fetch_assoc()) {
            $tgl = date('d-m-Y H:i', strtotime($row['tanggal_transaksi']));
            $total = "Rp " . number_format($row['total_harga'], 0, ',', '.');
            $customer = $row['nama_customer'] != '' ? $row['nama_customer'] : '<i style="color: var(--text-muted);">Umum</i>';
            
            $tableHtml .= "<tr>
                <td>{$no}</td>
                <td>{$tgl}</td>
                <td><b style='color: var(--btn-primary);'>{$row['no_transaksi']}</b></td>
                <td>{$customer}</td>
                <td><b style='color: #34d399;'>{$total}</b></td>
                <td>{$row['metode_pembayaran']}</td>
                <td>{$row['nama_lengkap']}</td>
                <td style='text-align: center; white-space: nowrap;'>
                    <button class='btn btn-success btn-sm' onclick='lihatDetail({$row['id']}, \"{$row['no_transaksi']}\")'>Detail</button>
                    <a href='cetak_struk.php?no={$row['no_transaksi']}' target='_blank' class='btn btn-edit btn-sm'>Struk</a>
                    <a href='cetak_invoice.php?no={$row['no_transaksi']}' target='_blank' class='btn btn-primary btn-sm' style='background: #8b5cf6;'>Invoice A4</a>
                </td>
            </tr>";
            $no++;
        }
    } else {
        $tableHtml = "<tr><td colspan='8' style='text-align:center; color: var(--text-muted); padding: 30px;'>Tidak ada riwayat transaksi.</td></tr>";
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
// 2. ENDPOINT AJAX: MENGAMBIL DETAIL ITEM (MODAL)
// ==========================================
if (isset($_GET['ajax_detail'])) {
    ob_clean();
    header('Content-Type: text/html');
    
    $id_penjualan = (int)$_GET['id'];
    $query = "SELECT d.*, b.nama_barang, b.kode_barang 
              FROM penjualan_detail d 
              JOIN barang b ON d.id_barang = b.id 
              WHERE d.id_penjualan = '$id_penjualan'";
    $result = $koneksi->query($query);
    
    if ($result && $result->num_rows > 0) {
        $html = "<table class='glass-table' style='width: 100%; margin-top: 10px;'>
                 <thead><tr><th>Nama Barang</th><th>Harga</th><th>Qty</th><th>Subtotal</th><th>Garansi</th></tr></thead><tbody>";
        while($r = $result->fetch_assoc()) {
            $harga = number_format($r['harga_jual'], 0, ',', '.');
            $sub = number_format($r['subtotal'], 0, ',', '.');
            $html .= "<tr>
                        <td><b>{$r['nama_barang']}</b><br><small style='color: var(--text-muted);'>SN: {$r['kode_barang']}</small></td>
                        <td>Rp {$harga}</td>
                        <td>{$r['qty']}</td>
                        <td style='color: var(--btn-primary); font-weight:bold;'>Rp {$sub}</td>
                        <td>{$r['masa_garansi']}</td>
                      </tr>";
        }
        $html .= "</tbody></table>";
        echo $html;
    } else {
        echo "<p style='color: red;'>Gagal memuat detail barang.</p>";
    }
    exit;
}

// ==========================================
// 3. TAMPILAN HTML
// ==========================================
require_once 'header.php';
require_once 'sidebar.php';
?>

<style>
    :root {
        --glow-color: #f59e0b;
        --glass-bg: rgba(36, 36, 36, 0.4);
        --glass-border: rgba(255, 255, 255, 0.05);
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
</style>

<div class="main-content module-container">
    <h2>📜 Riwayat Penjualan</h2>
    
    <div class="toolbar">
        <input type="text" id="searchBar" class="search-capsule" placeholder="Cari No Nota, Pelanggan, Kasir... 🔍" autocomplete="off">
        <a href="kasir.php" class="btn btn-primary">+ Transaksi Baru</a>
    </div>

    <div class="panel-glass table-container">
        <table class="glass-table">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Waktu Transaksi</th>
                    <th>No. Nota</th>
                    <th>Pelanggan</th>
                    <th>Total Belanja</th>
                    <th>Pembayaran</th>
                    <th>Kasir</th>
                    <th style="text-align: center;">Aksi</th>
                </tr>
            </thead>
            <tbody id="tableBody"></tbody>
        </table>
        
        <div class="pagination" id="paginationBox"></div>
    </div>
</div>

<!-- MODAL DETAIL TRANSAKSI -->
<div class="modal-overlay" id="modalDetail">
    <div class="modal-box" style="max-width: 650px; width: 95%;">
        <h3 style="margin-top:0; color:var(--btn-primary);">Detail Transaksi: <span id="judulNota" style="color: white;"></span></h3>
        
        <div class="table-container" id="kontenDetail" style="max-height: 400px; overflow-y: auto;">
            <div style="text-align: center; color: var(--text-muted); padding: 20px;">Memuat detail...</div>
        </div>

        <div class="modal-actions">
            <button type="button" class="btn btn-secondary" onclick="closeModal('modalDetail')">Tutup</button>
        </div>
    </div>
</div>

<script>
    let currentPage = 1;

    function loadData() {
        let keyword = document.getElementById('searchBar').value;
        fetch(`riwayat_penjualan.php?ajax_search=1&search=${keyword}&page=${currentPage}`)
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

    // Fungsi memanggil Modal Detail via AJAX ke file ini sendiri
    function lihatDetail(id_penjualan, no_nota) {
        document.getElementById('judulNota').innerText = no_nota;
        document.getElementById('kontenDetail').innerHTML = '<div style="text-align: center; padding: 20px;">Memuat data... ⏳</div>';
        document.getElementById('modalDetail').style.display = 'flex';

        fetch(`riwayat_penjualan.php?ajax_detail=1&id=${id_penjualan}`)
            .then(response => response.text())
            .then(html => {
                document.getElementById('kontenDetail').innerHTML = html;
            })
            .catch(error => console.error('Error:', error));
    }

    function closeModal(id) { document.getElementById(id).style.display = 'none'; }

    document.addEventListener('DOMContentLoaded', () => {
        loadData();
    });
</script>

<?php require_once 'footer.php'; ?>
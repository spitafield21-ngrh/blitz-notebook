<?php
// ==========================================
// FILE: sidebar.php
// FUNGSI: Menampilkan Menu Navigasi Sesuai Role (Tree-View Minimalis)
// ==========================================

$current_page = basename($_SERVER['PHP_SELF']);
?>

<style>
    /* Styling Internal Sidebar Tree-View Minimalis */
    .sidebar-tree-wrapper {
        padding: 10px 15px;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    .tree-group {
        margin-bottom: 5px;
    }

    /* Menu Induk (Parent) */
    .tree-title {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 12px;
        color: #d1d5db;
        cursor: pointer;
        font-size: 14px;
        font-weight: 600;
        border-radius: 6px;
        transition: 0.3s;
        user-select: none;
    }

    .tree-title:hover {
        background: rgba(255, 255, 255, 0.05);
        color: #fff;
    }

    .tree-title::after {
        content: '›';
        font-size: 16px;
        transition: transform 0.3s ease;
        color: #6b7280;
    }
    
    .tree-title.open::after {
        transform: rotate(90deg);
    }

    /* Sub Menu (Anak) dengan Garis Vertikal */
    .tree-submenu {
        list-style: none;
        padding: 0;
        margin: 5px 0 10px 20px; /* Jarak ke dalam */
        border-left: 1px solid #4b5563; /* Garis Vertikal Penghubung */
        display: none; 
    }
    .tree-submenu.open {
        display: block;
    }

    .tree-submenu li {
        position: relative;
    }

    .tree-submenu a {
        display: block;
        padding: 8px 15px 8px 20px;
        color: #9ca3af;
        text-decoration: none;
        font-size: 13px;
        transition: 0.3s;
    }

    .tree-submenu a:hover {
        color: #fff;
    }

    /* Efek Titik (Dot) di Garis Vertikal */
    .tree-submenu a::before {
        content: '';
        position: absolute;
        left: -4px; /* Posisi titik pas menimpa garis border-left */
        top: 50%;
        transform: translateY(-50%);
        width: 7px;
        height: 7px;
        background: #6b7280; /* Titik Mati */
        border-radius: 50%;
        transition: 0.3s;
    }

    /* Titik Menyala Saat Hover atau Aktif */
    .tree-submenu a:hover::before, .tree-submenu a.active::before {
        background: #38bdf8;
        box-shadow: 0 0 8px #38bdf8;
    }

    .tree-submenu a.active {
        color: #38bdf8;
        font-weight: bold;
    }

    /* Menu Tanpa Anak (Single Link) */
    .tree-single {
        display: block;
        padding: 10px 12px;
        color: #d1d5db;
        text-decoration: none;
        font-size: 14px;
        font-weight: 600;
        border-radius: 6px;
        transition: 0.3s;
        margin-bottom: 5px;
    }
    .tree-single:hover, .tree-single.active {
        background: rgba(255, 255, 255, 0.05);
        color: #fff;
    }
    .text-danger { color: #f87171 !important; }
    .text-danger:hover { background: rgba(239, 68, 68, 0.1) !important; color: #ef4444 !important;}
</style>

<!-- Pembungkus Asli Tidak Diubah Agar Layout Tidak Rusak -->
<aside class="sidebar" id="sidebar">
    
    <div class="sidebar-tree-wrapper">
        
        <!-- DASHBOARD (Single) -->
        <a href="index.php" class="tree-single <?= ($current_page == 'index.php') ? 'active' : '' ?>">
            Dashboard Area
        </a>

        <!-- KASIR & TRANSAKSI -->
        <div class="tree-group">
            <div class="tree-title" onclick="toggleTreeMenu(this)">Kasir & Transaksi</div>
            <ul class="tree-submenu">
                <li><a href="kasir.php" class="<?= ($current_page == 'kasir.php') ? 'active' : '' ?>">Point of Sale (POS)</a></li>
                <li><a href="piutang.php" class="<?= ($current_page == 'piutang.php') ? 'active' : '' ?>">Buku Piutang</a></li>
                <li><a href="riwayat_penjualan.php" class="<?= ($current_page == 'riwayat_penjualan.php') ? 'active' : '' ?>">Riwayat Penjualan</a></li>
                <li><a href="tutup_shift.php" class="<?= ($current_page == 'tutup_shift.php') ? 'active' : '' ?>">Laporan Tutup Shift</a></li>
            </ul>
        </div>

        <!-- LAYANAN PELANGGAN -->
        <div class="tree-group">
            <div class="tree-title" onclick="toggleTreeMenu(this)">Layanan & CRM</div>
            <ul class="tree-submenu">
                <li><a href="pelanggan.php" class="<?= ($current_page == 'pelanggan.php') ? 'active' : '' ?>">Master Pelanggan (CRM)</a></li>
                <li><a href="cek_garansi.php" class="<?= ($current_page == 'cek_garansi.php') ? 'active' : '' ?>">Pelacak Garansi (SN)</a></li>
                <li><a href="servis.php" class="<?= ($current_page == 'servis.php') ? 'active' : '' ?>">Service Center</a></li>
            </ul>
        </div>

        <?php if(isset($_SESSION['role']) && $_SESSION['role'] == 'owner'): ?>
        
        <!-- MASTER DATA -->
        <div class="tree-group">
            <div class="tree-title" onclick="toggleTreeMenu(this)">Master Data</div>
            <ul class="tree-submenu">
                <li><a href="kategori.php" class="<?= ($current_page == 'kategori.php') ? 'active' : '' ?>">Kategori Barang</a></li>
                <li><a href="supplier.php" class="<?= ($current_page == 'supplier.php') ? 'active' : '' ?>">Data Supplier</a></li>
                <li><a href="cetak_barcode.php" class="<?= ($current_page == 'cetak_barcode.php') ? 'active' : '' ?>">Cetak Barcode</a></li>
                <li><a href="barang.php" class="<?= ($current_page == 'barang.php') ? 'active' : '' ?>">Data Barang</a></li>
            </ul>
        </div>

        <!-- INVENTORI GUDANG -->
        <div class="tree-group">
            <div class="tree-title" onclick="toggleTreeMenu(this)">Inventori Gudang</div>
            <ul class="tree-submenu">
                <li><a href="barang_masuk.php" class="<?= ($current_page == 'barang_masuk.php') ? 'active' : '' ?>">Barang Masuk (Restock)</a></li>
                <li><a href="stok_opname.php" class="<?= ($current_page == 'stok_opname.php') ? 'active' : '' ?>">Stok Opname</a></li>
            </ul>
        </div>

        <!-- KEUANGAN & LAPORAN -->
        <div class="tree-group">
            <div class="tree-title" onclick="toggleTreeMenu(this)">Keuangan & Laporan</div>
            <ul class="tree-submenu">
                <li><a href="pengeluaran.php" class="<?= ($current_page == 'pengeluaran.php') ? 'active' : '' ?>">Pengeluaran Toko</a></li>
                <li><a href="laporan.php" class="<?= ($current_page == 'laporan.php') ? 'active' : '' ?>">Laporan Laba / Rugi</a></li>
            </ul>
        </div>

        <!-- PENGATURAN -->
        <div class="tree-group">
            <div class="tree-title" onclick="toggleTreeMenu(this)">Pengaturan</div>
            <ul class="tree-submenu">
                <li><a href="pengaturan.php" class="<?= ($current_page == 'pengaturan.php') ? 'active' : '' ?>">Pengaturan Toko</a></li>
            </ul>
        </div>

        <?php endif; ?>

        <!-- LOGOUT -->
        <div style="margin-top: 20px;">
            <a href="logout.php" class="tree-single text-danger">Keluar (Logout)</a>
        </div>

    </div>
</aside>

<script>
    // Interaksi Buka-Tutup Menu (Accordion)
    function toggleTreeMenu(element) {
        element.classList.toggle('open');
        let subMenu = element.nextElementSibling;
        if (subMenu) {
            subMenu.classList.toggle('open');
        }
    }

    // Auto-buka folder menu induk jika kursor sedang berada di halaman anaknya
    document.addEventListener("DOMContentLoaded", function() {
        let activeLink = document.querySelector('.tree-submenu a.active');
        if (activeLink) {
            let parentSubMenu = activeLink.closest('.tree-submenu');
            let parentTitle = parentSubMenu.previousElementSibling;
            if (parentSubMenu && parentTitle) {
                parentSubMenu.classList.add('open');
                parentTitle.classList.add('open');
            }
        }
    });
</script>
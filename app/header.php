<?php
// ==========================================
// FILE: header.php
// FUNGSI: Cek Login, Bagian Atas HTML, CSS & Topbar
// ==========================================

require_once 'config.php';

// CEK KEAMANAN
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blitz Notebook</title>
    <style>
        /* =========================================
           1. PALET WARNA & VARIABEL DASAR
           ========================================= */
        :root {
            --bg-topbar: #111111; 
            --bg-main: #0f0f0f; 
            --bg-sidebar: #1a1a1a; 
            --bg-panel: #242424;
            --text-light: #ffffff; 
            --text-muted: #a1a1aa; 
            --border-color: #3f3f46;
            
            --btn-primary: #f59e0b; --btn-hover: #d97706; 
            --btn-danger: #ef4444; --btn-danger-hover: #b91c1c; 
            --btn-edit: #3b82f6; --btn-edit-hover: #1d4ed8; 
            --btn-success: #10b981; --btn-success-hover: #059669; 
        }
        
        /* FIX: Memastikan padding tidak membuat elemen meluber keluar layar */
        * {
            box-sizing: border-box; 
        }

        /* Layout Dasar */
        body { 
            margin: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background-color: var(--bg-main); color: var(--text-light); 
            display: flex; flex-direction: column; 
            height: 100dvh; 
            overflow: hidden; 
        }
        
        /* =========================================
           2. HEADER / TOPBAR
           ========================================= */
        .topbar { 
            background-color: var(--bg-topbar); padding: 15px 25px; 
            border-bottom: 1px solid var(--border-color); 
            display: flex; align-items: center; justify-content: space-between; 
            z-index: 1000; flex-shrink: 0;
        }
        .topbar-brand { 
            font-size: 18px; font-weight: bold; color: var(--text-light); letter-spacing: 0.5px; 
        }
        
        .btn-toggle-sidebar {
            background: transparent; border: none; color: var(--text-light);
            font-size: 20px; cursor: pointer; margin-right: 15px;
            display: inline-flex; align-items: center; justify-content: center;
        }
        .btn-toggle-sidebar:hover { color: var(--btn-primary); }

        /* =========================================
           3. WRAPPER & SIDEBAR
           ========================================= */
        .app-wrapper { display: flex; flex-grow: 1; overflow: hidden; width: 100%; position: relative; }
        
        .sidebar { 
            width: 260px; flex-shrink: 0; 
            background: rgba(26, 26, 26, 0.4); 
            backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); 
            border-right: 1px solid rgba(255, 255, 255, 0.15); 
            box-shadow: 4px 0 15px rgba(0, 0, 0, 0.5); 
            padding: 20px 0; display: flex; flex-direction: column; overflow-y: auto; 
            transition: margin-left 0.3s ease; 
        }
        
        .sidebar.collapsed { margin-left: -260px; }

        .menu-group { margin-bottom: 20px; }
        .menu-title { 
            padding: 10px 20px; font-size: 13px; text-transform: uppercase; 
            color: var(--text-muted); font-weight: bold; cursor: pointer; 
            display: flex; justify-content: space-between; align-items: center; user-select: none; 
        }
        .menu-title:hover { color: var(--text-light); }
        .sub-menu { display: block; }
        .menu-item { 
            display: flex; align-items: center; gap: 10px; text-decoration: none; 
            padding: 10px 20px 10px 30px; cursor: pointer; color: var(--text-muted); 
            transition: 0.3s; font-size: 14px; 
        }
        .menu-item:hover, .menu-item.active { 
            background-color: var(--bg-panel); color: var(--text-light); 
            border-left: 3px solid var(--btn-primary); 
        }
        
        /* =========================================
           4. KONTEN UTAMA & FORM
           ========================================= */
        .main-content { flex-grow: 1; padding: 30px; overflow-y: auto; overflow-x: hidden; width: 100%; }
        .header-content { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px; }
        .header-content h2 { margin: 0; }
        
        .panel-glass { 
            background: rgba(36, 36, 36, 0.4); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); 
            border-radius: 8px; padding: 20px; border: 1px solid rgba(255, 255, 255, 0.05); 
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.3); margin-bottom: 20px;
        }

        .table-container { 
            overflow-x: auto; width: 100%; -webkit-overflow-scrolling: touch; 
        }
        
        table { width: 100%; border-collapse: collapse; margin-top: 10px; white-space: nowrap; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid rgba(255, 255, 255, 0.05); }
        
        .form-group { margin-bottom: 15px; width: 100%; }
        .form-group label { display: block; margin-bottom: 5px; color: var(--text-muted); font-size: 14px;}
        
        .form-group input, .form-group select, .form-group textarea, .search-capsule, .input-search-rounded { 
            width: 100%; padding: 10px; background-color: rgba(0,0,0,0.2); 
            border: 1px solid var(--border-color); color: var(--text-light); 
            border-radius: 4px; font-family: inherit;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus, .search-capsule:focus, .input-search-rounded:focus {
            outline: none; border-color: var(--btn-primary);
        }

        .btn { padding: 10px 15px; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; color: #fff; text-decoration: none; display: inline-block; }
        .btn-sm { padding: 5px 10px; font-size: 12px; margin-right: 5px; }
        .btn-primary { background-color: var(--btn-primary); } .btn-primary:hover { background-color: var(--btn-hover); }
        .btn-secondary { background-color: var(--border-color); } .btn-secondary:hover { background-color: #52525b; }
        .btn-danger { background-color: var(--btn-danger); } .btn-danger:hover { background-color: var(--btn-danger-hover); }
        .btn-edit { background-color: var(--btn-edit); } .btn-edit:hover { background-color: var(--btn-edit-hover); }
        .btn-success { background-color: var(--btn-success); } .btn-success:hover { background-color: var(--btn-success-hover); }
        
        /* =========================================
           5. MODAL POP-UP
           ========================================= */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.7); display: none; justify-content: center; align-items: center; z-index: 1000; }
        .modal-box, .modal-content { background-color: var(--bg-panel); padding: 25px; border-radius: 8px; width: 90%; max-width: 450px; border: 1px solid var(--border-color); }
        .modal-box h3, .modal-content h3 { margin-top: 0; }
        .modal-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 25px; }

        /* =========================================
           6. RESPONSIVE MEDIA QUERIES (HP & TABLET)
           ========================================= */
        @media (max-width: 768px) {
            .sidebar {
                position: absolute; height: 100%; z-index: 999; margin-left: -260px;
            }
            .sidebar.mobile-open { margin-left: 0; }
            .topbar-brand { font-size: 14px; }
            .user-info { display: none; } 
            
            .header-content, .toolbar, .search-container-mobile { 
                flex-direction: column !important; 
                align-items: flex-start !important; 
                gap: 15px !important; 
            }
            
            .main-content { padding: 15px; }

            /* FIX: TRIK AJAIB AUTO SCROLL TABEL DI MOBILE */
            table, .glass-table {
                display: block !important;
                overflow-x: auto !important;
                white-space: nowrap !important;
                -webkit-overflow-scrolling: touch;
            }
        }
    </style>
    <style>
        @media screen and (max-width: 768px) {
            /* 1. Membuat Tabel Bisa Di-Swipe (Scroll Horizontal) */
            .glass-table {
                display: block;
                width: 100%;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch; /* Efek swipe mulus di HP */
                white-space: nowrap; /* Mencegah teks bertumpuk ke bawah */
            }
            
            /* 2. Merapikan Toolbar (Search & Tombol Tambah) agar tersusun ke bawah */
            .toolbar {
                flex-direction: column !important;
                align-items: stretch !important;
            }
            
            .toolbar form {
                flex-direction: column;
                width: 100%;
            }
            
            .search-capsule, .date-picker-mbanking {
                width: 100% !important;
                box-sizing: border-box;
                margin-bottom: 5px;
            }
            
            .btn {
                width: 100%;
                text-align: center;
                margin-top: 5px;
            }
        }
    </style>
</head>
<body>

    <!-- TOPBAR -->
    <header class="topbar">
        <div style="display: flex; align-items: center;">
            <button id="btnToggleSidebar" class="btn-toggle-sidebar">☰</button>
            <div class="topbar-brand">BLITZ NOTEBOOK</div>
        </div>
        <div style="display: flex; align-items: center; gap: 15px;">
            <span class="user-info" style="font-size: 14px;">
                Halo, <b><?php echo $_SESSION['nama_lengkap']; ?></b> 
                (<span style="color: var(--btn-primary); text-transform: capitalize;"><?php echo $_SESSION['role']; ?></span>)
            </span>
            <a href="logout.php" class="btn btn-danger btn-sm" onclick="return confirm('Yakin ingin keluar?');">Keluar</a>
        </div>
    </header>

    <!-- WRAPPER -->
    <div class="app-wrapper">
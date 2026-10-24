<?php
// ==========================================
// FILE: index.php (BLITZ NOTEBOOK ENTERPRISE ULTIMATE)
// ==========================================

@include 'app/config.php'; 

$q_pengaturan = $koneksi->query("SELECT * FROM pengaturan ORDER BY id DESC LIMIT 1");
$pengaturan = $q_pengaturan ? $q_pengaturan->fetch_assoc() : [];

$nama_toko = !empty($pengaturan['nama_toko']) ? $pengaturan['nama_toko'] : 'BLITZ NOTEBOOK';
$alamat_toko = !empty($pengaturan['alamat']) ? $pengaturan['alamat'] : 'Jl. Jenderal Sudirman No. 123, Kota Teknologi';
$no_telp_raw = !empty($pengaturan['telepon']) ? $pengaturan['telepon'] : '081234567890';
$logo_toko = !empty($pengaturan['logo']) ? 'app/uploads/' . $pengaturan['logo'] : '';

$slide_title = !empty($pengaturan['slide_title']) ? $pengaturan['slide_title'] : 'Pusat Teknologi & Solusi IT Profesional';
$slide_subtitle = !empty($pengaturan['slide_subtitle']) ? $pengaturan['slide_subtitle'] : 'Mitra terpercaya pengadaan hardware, software, jaringan, dan pengembangan sistem berskala enterprise.';

// Kumpulkan 11 Banner
$banners = [];
for ($i = 1; $i <= 11; $i++) {
    $col = 'banner_' . $i;
    if (!empty($pengaturan[$col]) && file_exists('app/uploads/' . $pengaturan[$col])) {
        $banners[] = 'app/uploads/' . $pengaturan[$col];
    }
}
if (empty($banners)) {
    $banners = [
        'https://images.unsplash.com/photo-1618005182384-a83a8bd57fbe?auto=format&fit=crop&w=1400&q=85',
        'https://images.unsplash.com/photo-1634017839464-5c339ebe3cb4?auto=format&fit=crop&w=1400&q=85',
        'https://images.unsplash.com/photo-1550745165-9bc0b252726f?auto=format&fit=crop&w=1400&q=85',
        'https://images.unsplash.com/photo-1620641788421-7a1c342ea42e?auto=format&fit=crop&w=1400&q=85',
        'https://images.unsplash.com/photo-1517336714731-489689fd1ca8?auto=format&fit=crop&w=1400&q=85',
        'https://images.unsplash.com/photo-1614680376593-902f749f7ffc?auto=format&fit=crop&w=1400&q=85',
        'https://images.unsplash.com/photo-1587202372775-e229f172b9d7?auto=format&fit=crop&w=1400&q=85',
        'https://images.unsplash.com/photo-1633167606207-d840b5070fc2?auto=format&fit=crop&w=1400&q=85',
        'https://images.unsplash.com/photo-1526738549149-8e07eca6c147?auto=format&fit=crop&w=1400&q=85',
        'https://images.unsplash.com/photo-1618005182384-a83a8bd57fbe?auto=format&fit=crop&w=1400&q=85',
        'https://images.unsplash.com/photo-1593642632823-8f785ba67e45?auto=format&fit=crop&w=1400&q=85'
    ];
}

$no_wa = preg_replace('/[^0-9]/', '', $no_telp_raw);
if (strpos($no_wa, '0') === 0) { $no_wa = '62' . substr($no_wa, 1); }

$search = isset($_GET['search']) ? bersihkan_input($koneksi, $_GET['search']) : '';
$kategori_filter = isset($_GET['kategori']) ? (int)$_GET['kategori'] : 0;

$where = "WHERE b.status = 'Aktif'";
if ($search != '') { $where .= " AND (b.nama_barang LIKE '%$search%' OR b.spesifikasi LIKE '%$search%')"; }
if ($kategori_filter > 0) { $where .= " AND b.id_kategori = $kategori_filter"; }

$q_kat = $koneksi->query("SELECT * FROM kategori ORDER BY nama_kategori ASC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($nama_toko) ?> | Pusat Teknologi & Solusi IT Profesional</title>
    <style>
        :root {
            --primary: #38bdf8;
            --secondary: #10b981;
            --dark-bg: #0f172a;
            --card-bg: #1e293b;
            --text-light: #f8fafc;
            --text-muted: #94a3b8;
            --border-glass: rgba(255, 255, 255, 0.08);
        }

        body, html {
            margin: 0; padding: 0;
            font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: var(--dark-bg);
            color: var(--text-light);
            scroll-behavior: smooth;
            overflow-x: hidden;
        }

        header {
            background: rgba(15, 23, 42, 0.95);
            backdrop-filter: blur(12px);
            position: fixed; width: 100%; top: 0; z-index: 1000;
            border-bottom: 1px solid var(--border-glass);
        }
        .navbar {
            display: flex; justify-content: space-between; align-items: center;
            max-width: 1200px; margin: 0 auto; padding: 12px 20px;
        }
        .logo-container { display: flex; align-items: center; text-decoration: none; }
        .logo-img { height: 36px; margin-right: 8px; border-radius: 4px; }
        .logo-text { font-size: 20px; font-weight: 800; color: #fff; letter-spacing: 1px; text-transform: uppercase; }
        .logo-text span { color: var(--primary); }
        
        .nav-links { display: flex; align-items: center; gap: 15px; }
        .nav-links a { color: var(--text-light); text-decoration: none; font-size: 13px; font-weight: 600; transition: 0.3s; }
        .nav-links a:hover { color: var(--primary); }
        .btn-login { background: transparent; border: 1px solid var(--primary); color: var(--primary); padding: 5px 10px; border-radius: 6px; }
        .btn-login:hover { background: var(--primary); color: #000; }

        .hero-slideshow {
            position: relative; height: 480px; margin-top: 60px; overflow: hidden;
            display: flex; align-items: center; justify-content: center; text-align: center;
        }
        .slide {
            position: absolute; top: 0; left: 0; width: 100%; height: 100%;
            background-size: cover; background-position: center;
            opacity: 0; transition: opacity 1.8s ease-in-out; z-index: 1;
        }
        .slide.active { opacity: 1; }
        .slide-overlay {
            position: absolute; top: 0; left: 0; width: 100%; height: 100%;
            background: linear-gradient(to bottom, rgba(15, 23, 42, 0.6), rgba(15, 23, 42, 0.95));
            z-index: 2;
        }
        .hero-content { position: relative; z-index: 3; max-width: 800px; padding: 0 20px; }
        .hero-content h1 { font-size: 40px; margin-bottom: 12px; text-transform: uppercase; letter-spacing: 1px; text-shadow: 0 2px 10px rgba(0,0,0,0.5); }
        .hero-content h1 span { color: var(--primary); }
        .hero-content p { font-size: 16px; color: var(--text-muted); line-height: 1.5; margin: 0; text-shadow: 0 1px 5px rgba(0,0,0,0.5); }

        section[id] { scroll-margin-top: 90px; }

        .section-container { max-width: 1200px; margin: 50px auto; padding: 0 20px; }
        .section-title { text-align: center; font-size: 30px; font-weight: 800; margin-bottom: 15px; text-transform: uppercase; letter-spacing: 1px; }
        .section-title span { color: var(--primary); }
        .section-subtitle { text-align: center; color: var(--text-muted); max-width: 700px; margin: 0 auto 40px; font-size: 15px; line-height: 1.6; }

        /* PROFIL & VISI */
        .profil-box {
            background: var(--card-bg); border: 1px solid var(--border-glass); border-radius: 16px; padding: 40px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        .profil-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; align-items: center; }
        .profil-text h3 { color: var(--primary); font-size: 22px; margin-top: 0; }
        .profil-text p { color: var(--text-muted); line-height: 1.7; font-size: 14px; margin-bottom: 15px; }
        .visi-misi-box { background: rgba(15, 23, 42, 0.6); padding: 25px; border-radius: 12px; border-left: 4px solid var(--primary); }
        .visi-misi-box h4 { margin: 0 0 10px 0; color: #fff; font-size: 16px; }
        .visi-misi-box ul { margin: 0; padding-left: 20px; color: var(--text-muted); font-size: 13px; line-height: 1.6; }

        /* SEKTOR BISNIS RAPI & SEJAJAR (EQUAL HEIGHT) */
        .bisnis-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px; }
        .bisnis-card {
            background: var(--card-bg); border: 1px solid var(--border-glass); border-radius: 12px; padding: 25px;
            transition: 0.3s; display: flex; flex-direction: column; justify-content: space-between;
        }
        .bisnis-card:hover { transform: translateY(-5px); border-color: var(--primary); box-shadow: 0 10px 25px rgba(56, 189, 248, 0.15); }
        .bisnis-icon { font-size: 32px; margin-bottom: 15px; }
        .bisnis-card h4 { font-size: 18px; margin: 0 0 10px 0; color: #fff; }
        .bisnis-card p { font-size: 13px; color: var(--text-muted); line-height: 1.6; margin: 0; flex-grow: 1; }

        /* VALUE CARDS */
        .value-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; }
        .value-card {
            background: linear-gradient(135deg, rgba(30, 41, 59, 0.8), rgba(15, 23, 42, 0.9));
            border: 1px solid var(--border-glass); border-radius: 14px; padding: 25px; text-align: center;
            transition: 0.3s; box-shadow: 0 8px 25px rgba(0,0,0,0.2);
        }
        .value-card:hover { border-color: var(--secondary); box-shadow: 0 0 20px rgba(16, 185, 129, 0.2); transform: translateY(-3px); }
        .value-icon { font-size: 36px; margin-bottom: 15px; color: var(--secondary); }
        .value-card h4 { font-size: 18px; margin: 0 0 10px 0; color: #fff; }
        .value-card p { font-size: 13px; color: var(--text-muted); line-height: 1.5; margin: 0; }

        /* SUPPORT BRAND LOGO BERKELAS */
        .brand-container {
            background: rgba(30, 41, 59, 0.4); border: 1px solid var(--border-glass); border-radius: 16px; padding: 35px; text-align: center;
        }
        .brand-grid { display: flex; flex-wrap: wrap; justify-content: center; gap: 15px; margin-top: 25px; align-items: center; }
        .brand-logo-card {
            background: rgba(15, 23, 42, 0.8); border: 1px solid var(--border-glass); padding: 15px 25px;
            border-radius: 10px; font-weight: 800; font-size: 16px; color: #cbd5e1; letter-spacing: 1.5px;
            transition: 0.3s; display: flex; align-items: center; gap: 8px;
        }
        .brand-logo-card:hover { color: var(--primary); border-color: var(--primary); transform: translateY(-3px); box-shadow: 0 5px 15px rgba(56, 189, 248, 0.2); }
        .brand-logo-card span { font-size: 12px; color: var(--text-muted); font-weight: normal; }

        /* FILTER & PENCARIAN */
        .filter-section {
            max-width: 1200px; margin: 25px auto; padding: 0 15px;
            display: flex; flex-direction: column; align-items: center; gap: 15px;
        }
        .search-bar { display: flex; width: 100%; max-width: 600px; }
        .search-bar input {
            flex: 1; padding: 12px 18px; border-radius: 30px 0 0 30px; border: 1px solid rgba(255,255,255,0.2);
            background: rgba(255,255,255,0.05); color: #fff; outline: none; font-size: 15px;
        }
        .search-bar input:focus { border-color: var(--primary); }
        .search-bar button {
            padding: 12px 25px; border-radius: 0 30px 30px 0; border: none;
            background: var(--primary); color: #000; font-weight: bold; font-size: 15px; cursor: pointer; transition: 0.3s;
        }

        .category-chips { display: flex; gap: 8px; flex-wrap: wrap; justify-content: center; }
        .chip {
            padding: 6px 14px; border-radius: 20px; border: 1px solid rgba(255,255,255,0.2);
            color: var(--text-light); text-decoration: none; font-size: 13px; transition: 0.3s;
            background: rgba(255,255,255,0.05);
        }
        .chip:hover, .chip.active { background: var(--primary); color: #000; border-color: var(--primary); font-weight: bold; }

        /* ETALASE PRODUK GRID (KLIK MEMBUKA DETAIL.PHP) */
        .katalog-section { max-width: 1200px; margin: 15px auto 40px; padding: 0 15px; }
        .grid-produk { display: grid; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); gap: 20px; }
        .card-produk {
            background: var(--card-bg); border: 1px solid var(--border-glass);
            border-radius: 12px; padding: 18px; text-align: center; transition: 0.3s;
            position: relative; overflow: hidden; display: flex; flex-direction: column; text-decoration: none; color: inherit;
        }
        .card-produk:hover { transform: translateY(-5px); border-color: var(--primary); box-shadow: 0 10px 25px rgba(0,0,0,0.5); }
        .kategori-badge {
            background: rgba(56, 189, 248, 0.2); color: var(--primary); align-self: center;
            font-size: 11px; padding: 4px 10px; border-radius: 20px; display: inline-block; margin-bottom: 12px; font-weight: bold;
        }
        .nama-produk { font-size: 17px; font-weight: bold; margin-bottom: 8px; color: #fff; }
        .spek-produk { font-size: 12px; color: var(--text-muted); margin-bottom: 12px; flex-grow: 1; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
        .harga-produk { font-size: 20px; font-weight: bold; color: var(--secondary); margin-bottom: 15px; }
        
        .btn-detail-card {
            display: block; width: 100%; background: rgba(56, 189, 248, 0.15); color: var(--primary); padding: 10px 0;
            text-decoration: none; font-weight: bold; border-radius: 6px; font-size: 13px; transition: 0.3s; border: 1px solid rgba(56, 189, 248, 0.3);
        }
        .btn-detail-card:hover { background: var(--primary); color: #000; }
        .stok-habis { background: #475569; color: #94a3b8; pointer-events: none; border-color: transparent; }

        footer { background: #0b1121; text-align: center; padding: 40px 15px; margin-top: 60px; border-top: 1px solid var(--border-glass); color: var(--text-muted); font-size: 13px; }

        @media (max-width: 768px) {
            .navbar { padding: 10px 15px; }
            .logo-text { font-size: 16px; }
            .nav-links a { font-size: 11px; margin-left: 6px; }
            .btn-login { padding: 4px 8px; font-size: 10px; }
            .hero-slideshow { height: 350px; }
            .hero-content h1 { font-size: 24px; }
            .profil-grid { grid-template-columns: 1fr; gap: 20px; }
            .profil-box { padding: 20px; }
            .grid-produk { grid-template-columns: repeat(2, 1fr); gap: 12px; }
        }
        @media (max-width: 480px) {
            .grid-produk { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body id="home">

    <header>
        <div class="navbar">
            <a href="#home" class="logo-container">
                <?php if($logo_toko != '' && file_exists($logo_toko)): ?>
                    <img src="<?= $logo_toko ?>" alt="Logo" class="logo-img">
                <?php endif; ?>
                <span class="logo-text"><?= htmlspecialchars($nama_toko) ?></span>
            </a>
            <div class="nav-links">
                <a href="#home">Home</a>
                <a href="#profil">Profil & Visi</a>
                <a href="#bisnis">Sektor Bisnis</a>
                <a href="#katalog">Katalog</a>
                <a href="app/login.php" target="_blank" class="btn-login">⚙️ Admin</a>
            </div>
        </div>
    </header>

    <section class="hero-slideshow">
        <?php foreach($banners as $index => $banner): ?>
            <div class="slide <?= $index === 0 ? 'active' : '' ?>" style="background-image: url('<?= $banner ?>');"></div>
        <?php endforeach; ?>
        <div class="slide-overlay"></div>
        <div class="hero-content">
            <h1><?= htmlspecialchars($slide_title) ?></h1>
            <p><?= htmlspecialchars($slide_subtitle) ?></p>
        </div>
    </section>

    <section id="profil" class="section-container">
        <div class="profil-box">
            <div class="profil-grid">
                <div class="profil-text">
                    <h3>Tentang <?= htmlspecialchars($nama_toko) ?></h3>
                    <p>
                        <b><?= htmlspecialchars($nama_toko) ?></b> hadir sebagai pionir solusi teknologi informasi terdepan yang berdedikasi tinggi untuk menghadirkan produk dan layanan berkualitas tinggi. Kami memahami bahwa performa perangkat dan sistem IT adalah jantung dari produktivitas Anda, baik untuk kebutuhan personal, profesional, maupun skala korporat.
                    </p>
                    <p>
                        Dengan komitmen penuh pada keaslian produk bergaransi resmi, dukungan teknis handal, serta pelayanan purna jual yang transparan, kami memastikan setiap pelanggan mendapatkan pengalaman berbelanja dan kerjasama terbaik yang aman dan memuaskan.
                    </p>
                </div>
                <div>
                    <div class="visi-misi-box" style="margin-bottom: 15px;">
                        <h4>🎯 Visi Perusahaan</h4>
                        <p style="margin: 0; color: #fff; font-size: 13px; line-height: 1.5;">Menjadi pusat penyedia perangkat keras (hardware), solusi jaringan, dan pengembangan sistem teknologi informasi terpercaya, inovatif, dan berstandar tinggi di Indonesia.</p>
                    </div>
                    <div class="visi-misi-box">
                        <h4>🚀 Misi & Komitmen Kami</h4>
                        <ul>
                            <li>Menyediakan lini produk laptop, komputer, dan aksesoris original bergaransi resmi.</li>
                            <li>Memberikan pelayanan konsultasi dan purna jual yang responsif serta profesional.</li>
                            <li>Mengembangkan solusi bisnis terintegrasi mulai dari sewa perangkat IT hingga pembuatan aplikasi berbasis AI.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- SEKTOR BISNIS (RAPI SEJAJAR / EQUAL HEIGHT) -->
    <section id="bisnis" class="section-container">
        <h2 class="section-title">Sektor <span>Bisnis & Layanan</span></h2>
        <p class="section-subtitle">Tidak hanya melayani penjualan retail perangkat komputer, Blitz Notebook adalah ekosistem penyedia solusi teknologi komprehensif untuk mendukung efisiensi operasional bisnis Anda.</p>
        
        <div class="bisnis-grid">
            <div class="bisnis-card">
                <div>
                    <div class="bisnis-icon">💻</div>
                    <h4>Penyewaan Laptop & Perangkat</h4>
                    <p>Layanan sewa unit laptop, PC, dan perangkat kantor dalam jumlah fleksibel (harian/bulanan) untuk menunjang event, training, maupun operasional perusahaan.</p>
                </div>
            </div>
            <div class="bisnis-card">
                <div>
                    <div class="bisnis-icon">🖥️</div>
                    <h4>Penyediaan Server & Infrastruktur</h4>
                    <p>Pengadaan, konfigurasi, dan maintenance server handal berkinerja tinggi guna mengamankan data dan operasional sistem database perusahaan.</p>
                </div>
            </div>
            <div class="bisnis-card">
                <div>
                    <div class="bisnis-icon">🌐</div>
                    <h4>Jaringan Komputer & LAN</h4>
                    <p>Perancangan topologi jaringan, instalasi kabel LAN/Fiber Optic, setup router enterprise, dan keamanan jaringan nirkabel yang stabil dan cepat.</p>
                </div>
            </div>
            <div class="bisnis-card">
                <div>
                    <div class="bisnis-icon">⚡</div>
                    <h4>Pembuatan Aplikasi & ERP</h4>
                    <p>Pengembangan sistem software kustom, aplikasi POS/kasir, dan sistem ERP terintegrasi untuk mendigitalisasi proses bisnis secara optimal.</p>
                </div>
            </div>
            <div class="bisnis-card">
                <div>
                    <div class="bisnis-icon">🚀</div>
                    <h4>Pembuatan Website & Landing Page</h4>
                    <p>Jasa pembuatan company profile, toko online, dan landing page profesional berkecepatan tinggi dengan desain modern dan ramah SEO.</p>
                </div>
            </div>
            <div class="bisnis-card">
                <div>
                    <div class="bisnis-icon">🛡️</div>
                    <h4>Maintenance & IT Support</h4>
                    <p>Dukungan teknisi profesional berdedikasi untuk perawatan berkala, perbaikan perangkat keras, dan konsultasi strategi teknologi informasi.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="section-container">
        <h2 class="section-title">Blitz Notebook <span>Value</span></h2>
        <p class="section-subtitle">Nilai-nilai fundamental yang menjadi fondasi kami dalam membangun hubungan jangka panjang yang kokoh dan terpercaya bersama setiap pelanggan.</p>
        
        <div class="value-grid">
            <div class="value-card">
                <div class="value-icon">💎</div>
                <h4>Integrity</h4>
                <p>Menjunjung tinggi kejujuran absolut dalam setiap transaksi, keaslian barang, serta profesionalisme kerja.</p>
            </div>
            <div class="value-card">
                <div class="value-icon">💡</div>
                <h4>Innovation</h4>
                <p>Terus berinovasi dalam pengembangan teknologi, sistem aplikasi cerdas, dan integrasi kecerdasan buatan (AI).</p>
            </div>
            <div class="value-card">
                <div class="value-icon">🤝</div>
                <h4>Collaboration</h4>
                <p>Membangun sinergi yang kuat, komunikasi transparan, dan kemitraan strategis jangka panjang dengan customer.</p>
            </div>
            <div class="value-card">
                <div class="value-icon">🏆</div>
                <h4>Excellence</h4>
                <p>Berkomitmen penuh untuk selalu memberikan standar kualitas produk dan pelayanan terbaik tanpa kompromi.</p>
            </div>
        </div>
    </section>

    <!-- SUPPORT BRAND BERBENTUK LOGO KORPORAT -->
    <section class="section-container">
        <div class="brand-container">
            <h3 style="margin-top: 0; color: #fff; font-size: 20px; text-transform: uppercase; letter-spacing: 1px;">Didukung Oleh Brand Global Terbaik</h3>
            <p style="color: var(--text-muted); font-size: 13px; margin-bottom: 20px;">Kami bermitra resmi dengan merek-merek terkemuka dunia untuk menjamin kualitas performa tanpa kompromi.</p>
            <div class="brand-grid">
                <div class="brand-logo-card">ASUS <span>ROG / ZenBook</span></div>
                <div class="brand-logo-card">HP <span>Pavilion / Omen</span></div>
                <div class="brand-logo-card">LENOVO <span>ThinkPad / Legion</span></div>
                <div class="brand-logo-card">DELL <span>Latitude / XPS</span></div>
                <div class="brand-logo-card" style="color: #10b981;">NVIDIA <span>GeForce RTX</span></div>
                <div class="brand-logo-card" style="color: #ef4444;">AMD <span>Radeon Graphics</span></div>
                <div class="brand-logo-card" style="color: #38bdf8;">INTEL <span>Core i3/i5/i7/i9</span></div>
                <div class="brand-logo-card" style="color: #f59e0b;">RYZEN <span>Processor</span></div>
            </div>
        </div>
    </section>

    <!-- FILTER & PENCARIAN KATALOG -->
    <section id="katalog" class="section-container" style="margin-bottom: 20px;">
        <h2 class="section-title">Etalase <span>Digital Produk</span></h2>
        <p class="section-subtitle">Klik pada unit laptop atau komputer untuk melihat galeri foto dan spesifikasi lengkapnya.</p>
        
        <div class="filter-section">
            <form action="index.php#katalog" method="GET" class="search-bar">
                <?php if($kategori_filter > 0): ?>
                    <input type="hidden" name="kategori" value="<?= $kategori_filter ?>">
                <?php endif; ?>
                <input type="text" name="search" placeholder="🔍 Cari laptop, PC, spesifikasi..." value="<?= htmlspecialchars($search) ?>">
                <button type="submit">Cari</button>
            </form>

            <div class="category-chips">
                <a href="index.php#katalog" class="chip <?= $kategori_filter == 0 ? 'active' : '' ?>">Semua</a>
                <?php 
                if($q_kat && $q_kat->num_rows > 0) {
                    while($k = $q_kat->fetch_assoc()) {
                        $active = ($kategori_filter == $k['id']) ? 'active' : '';
                        $link = "index.php?kategori={$k['id']}#katalog";
                        if($search != '') $link .= "&search=" . urlencode($search);
                        echo "<a href='{$link}' class='chip {$active}'>{$k['nama_kategori']}</a>";
                    }
                }
                ?>
            </div>
        </div>
    </section>

    <!-- KATALOG PRODUK (MENGARAH KE DETAIL.PHP) -->
    <section class="katalog-section">
        <?php if($search != '' || $kategori_filter > 0): ?>
            <div style="margin-bottom: 15px; color: var(--primary); font-size: 13px; text-align: center;">
                <i>Hasil filter aktif. <a href="index.php#katalog" style="color: #fff; text-decoration: underline;">Reset Filter</a></i>
            </div>
        <?php endif; ?>

        <div class="grid-produk">
            <?php
            $query_produk = $koneksi->query("SELECT b.*, k.nama_kategori FROM barang b LEFT JOIN kategori k ON b.id_kategori = k.id $where ORDER BY b.id DESC LIMIT 20");
            
            if($query_produk && $query_produk->num_rows > 0) {
                while($row = $query_produk->fetch_assoc()) {
                    $harga_jual = number_format($row['harga_jual'], 0, ',', '.');
                    $kategori_label = $row['nama_kategori'] ? $row['nama_kategori'] : 'Umum';

                    if($row['stok'] > 0) {
                        echo "
                        <a href='detail.php?id={$row['id']}' class='card-produk'>
                            <div class='kategori-badge'>{$kategori_label}</div>
                            <div class='nama-produk'>{$row['nama_barang']}</div>
                            <div class='spek-produk'>{$row['spesifikasi']}</div>
                            <div class='harga-produk'>Rp {$harga_jual}</div>
                            <div class='btn-detail-card'>🔍 Lihat Detail Unit</div>
                        </a>";
                    } else {
                        echo "
                        <div class='card-produk' style='opacity: 0.7;'>
                            <div class='kategori-badge'>{$kategori_label}</div>
                            <div class='nama-produk'>{$row['nama_barang']}</div>
                            <div class='spek-produk'>{$row['spek-produk']}</div>
                            <div class='harga-produk'>Rp {$harga_jual}</div>
                            <div class='btn-detail-card stok-habis'>❌ Stok Habis</div>
                        </div>";
                    }
                }
            } else {
                echo "<p style='grid-column: 1 / -1; text-align: center; color: var(--text-muted); padding: 40px 0;'>Produk tidak ditemukan.</p>";
            }
            ?>
        </div>
    </section>

    <footer>
        <h3 style="color: var(--text-light); text-transform: uppercase; margin-bottom: 5px;"><?= htmlspecialchars($nama_toko) ?></h3>
        <p style="margin: 5px 0;"><?= nl2br(htmlspecialchars($alamat_toko)) ?></p>
        <p>WhatsApp CS: <b><?= htmlspecialchars($no_telp_raw) ?></b></p>
        <p style="margin-top: 25px; font-size: 11px;">&copy; <?= date('Y') ?> <?= htmlspecialchars($nama_toko) ?>. All Rights Reserved. Enterprise Solution.</p>
    </footer>

    <script>
        let currentSlide = 0;
        const slides = document.querySelectorAll('.hero-slideshow .slide');
        function nextSlide() {
            if(slides.length === 0) return;
            slides[currentSlide].classList.remove('active');
            currentSlide = (currentSlide + 1) % slides.length;
            slides[currentSlide].classList.add('active');
        }
        if(slides.length > 1) { setInterval(nextSlide, 4500); }
    </script>
</body>
</html>
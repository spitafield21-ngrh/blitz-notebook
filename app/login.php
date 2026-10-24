<?php
// ==========================================
// FILE: login.php
// FUNGSI: Halaman Autentikasi Pengguna (Hybrid Password & Kebal Enter)
// ==========================================
require_once 'config.php';

// Jika sudah login, langsung arahkan ke index.php
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// 1. AMBIL DATA LOGO DARI DATABASE PENGATURAN
$query_toko = $koneksi->query("SELECT logo FROM pengaturan LIMIT 1");
$toko = $query_toko ? $query_toko->fetch_assoc() : null;

$error = '';

// 2. LOGIKA LOGIN SAKTI (KEBAL TOMBOL ENTER & HYBRID PASSWORD)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $koneksi->real_escape_string($_POST['username']);
    $password = $_POST['password'];

    $query = "SELECT * FROM users WHERE username = '$username'";
    $result = $koneksi->query($query);

    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        // Verifikasi Hybrid (Cek password_hash, Plain Text, atau MD5)
        $password_valid = false;
        if (password_verify($password, $user['password'])) {
            $password_valid = true;
        } elseif ($password === $user['password']) {
            $password_valid = true;
        } elseif (md5($password) === $user['password']) {
            $password_valid = true;
        }

        if ($password_valid) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
            $_SESSION['role'] = $user['role'];
            
            header("Location: index.php");
            exit;
        } else {
            $error = "Password yang Anda masukkan salah!";
        }
    } else {
        $error = "Username tidak ditemukan di sistem!";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - BLITZ NOTEBOOK</title>
    <style>
        :root {
            --glow-color: #3b82f6; /* Biru Mewah */
            --glass-bg: rgba(20, 20, 25, 0.65);
            --glass-border: rgba(255, 255, 255, 0.1);
        }
        
        body {
            margin: 0; padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #0f172a, #000000);
            height: 100vh;
            display: flex; flex-direction: column;
            justify-content: center; align-items: center;
            color: #fff;
            overflow: hidden;
        }

        /* Ornamen Cahaya Aesthetic di background */
        .circle-1, .circle-2 {
            position: absolute;
            border-radius: 50%;
            filter: blur(90px);
            z-index: 1;
            opacity: 0.6;
        }
        .circle-1 {
            width: 350px; height: 350px;
            background: rgba(59, 130, 246, 0.4);
            top: -100px; left: -100px;
        }
        .circle-2 {
            width: 300px; height: 300px;
            background: rgba(139, 92, 246, 0.3);
            bottom: -50px; right: -50px;
        }

        /* Kotak Login Glassmorphism */
        .login-container {
            background: var(--glass-bg);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 40px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.6);
            text-align: center;
            position: relative;
            z-index: 10;
        }

        /* Area Logo */
        .logo-area { margin-bottom: 30px; }
        .logo-area img {
            max-width: 120px;
            max-height: 120px;
            height: auto;
            margin-bottom: 15px;
            border-radius: 10px;
            filter: drop-shadow(0 0 15px rgba(255,255,255,0.2));
            object-fit: contain;
        }
        .logo-area h2 {
            margin: 0; font-size: 26px; letter-spacing: 2px;
            background: linear-gradient(90deg, #ffffff, #94a3b8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .logo-area p { margin: 5px 0 0 0; color: #94a3b8; font-size: 13px; letter-spacing: 1px; }

        /* Form Controls */
        .form-group { margin-bottom: 20px; text-align: left; }
        .form-group label { display: block; margin-bottom: 8px; font-size: 13px; color: #cbd5e1; font-weight: bold; }
        .form-control {
            width: 100%; padding: 14px 15px; border-radius: 10px;
            background: rgba(0,0,0,0.4); border: 1px solid var(--glass-border);
            color: #fff; outline: none; box-sizing: border-box; font-size: 15px; transition: all 0.3s ease;
        }
        .form-control:focus {
            border-color: var(--glow-color); background: rgba(0,0,0,0.6);
            box-shadow: 0 0 15px rgba(59, 130, 246, 0.3);
        }

        /* Tombol Login */
        .btn-login {
            width: 100%; padding: 15px; border-radius: 10px; border: none;
            background: var(--glow-color); color: #fff; font-size: 16px; font-weight: bold;
            cursor: pointer; transition: all 0.3s ease; margin-top: 15px; letter-spacing: 1px;
        }
        .btn-login:hover {
            box-shadow: 0 0 20px var(--glow-color); transform: translateY(-2px);
        }

        /* Pesan Error */
        .alert {
            padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 14px;
            background: rgba(239, 68, 68, 0.15); border: 1px solid #ef4444; color: #f87171;
        }

        /* Area Copyright di Luar Modal */
        .copyright {
            margin-top: 35px; color: #64748b; font-size: 13px; z-index: 10; text-align: center;
        }
        .copyright b { color: #94a3b8; }
    </style>
</head>
<body>

    <div class="circle-1"></div>
    <div class="circle-2"></div>

    <div class="login-container">
        <div class="logo-area">
            <?php if(!empty($toko['logo']) && file_exists('uploads/' . $toko['logo'])): ?>
                <img src="uploads/<?= $toko['logo'] ?>" alt="Logo Toko">
            <?php else: ?>
                <div style="font-size: 65px; margin-bottom: 10px; filter: drop-shadow(0 0 15px rgba(245,158,11,0.6));">🔥</div>
            <?php endif; ?>

            <h2>BLITZ NOTEBOOK</h2>
            <p>ERP & POINT OF SALE SYSTEM</p>
        </div>

        <?php if ($error != ''): ?>
            <div class="alert"><?= $error ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="form_login_submitted" value="1">
            <div class="form-group">
                <label>USERNAME</label>
                <input type="text" name="username" class="form-control" placeholder="Ketik username Anda..." required autocomplete="off">
            </div>
            <div class="form-group">
                <label>PASSWORD</label>
                <input type="password" name="password" class="form-control" placeholder="Ketik password Anda..." required>
            </div>
            <button type="submit" class="btn-login">LOGIN SYSTEM</button>
        </form>
    </div>

    <div class="copyright">
        &copy; <?= date('Y') ?> <b>Blitz Notebook</b>. VERSI 2.0 (Stable Build) <br>
        <span style="font-size: 11px; opacity: 0.6; margin-top:5px; display:inline-block;">Spesial Crafted by : E. Nugraha for Mujianto Blitz Notebook</span>
    </div>

</body>
</html>
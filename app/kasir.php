<?php
// ==========================================
// FILE: kasir.php
// FUNGSI: Mesin Point of Sale (POS) + Integrasi CRM & Sistem Multi-SN (Anti-Ganda)
// ==========================================
require_once 'config.php';

// ------------------------------------------
// 🛠️ AUTO-HEALING DATABASE: Tabel Piutang & Kolom Baru untuk Sistem Poin
// ------------------------------------------
$koneksi->query("CREATE TABLE IF NOT EXISTS piutang (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_penjualan INT NOT NULL,
    no_transaksi VARCHAR(50) NOT NULL,
    nama_pelanggan VARCHAR(150) NOT NULL,
    no_telp VARCHAR(50),
    total_hutang INT NOT NULL,
    jumlah_terbayar INT NOT NULL DEFAULT 0,
    sisa_hutang INT NOT NULL,
    jatuh_tempo DATE,
    status VARCHAR(20) DEFAULT 'Belum Lunas'
)");

$cek_diskon = $koneksi->query("SHOW COLUMNS FROM penjualan LIKE 'diskon'");
if ($cek_diskon->num_rows == 0) {
    $koneksi->query("ALTER TABLE penjualan ADD COLUMN diskon INT DEFAULT 0 AFTER total_harga");
    $koneksi->query("ALTER TABLE penjualan ADD COLUMN poin_digunakan INT DEFAULT 0 AFTER diskon");
    $koneksi->query("ALTER TABLE penjualan ADD COLUMN poin_didapat INT DEFAULT 0 AFTER poin_digunakan");
}
$cek_tgl = $koneksi->query("SHOW COLUMNS FROM pelanggan LIKE 'last_transaksi'");
if ($cek_tgl->num_rows == 0) {
    $koneksi->query("ALTER TABLE pelanggan ADD COLUMN last_transaksi DATE AFTER total_poin");
}

// ==========================================
// 1A. ENDPOINT AJAX: LIVE SEARCH BARANG (JSON)
// ==========================================
if (isset($_GET['ajax_search'])) {
    ob_clean();
    header('Content-Type: application/json');
    $search = isset($_GET['search']) ? $koneksi->real_escape_string($_GET['search']) : '';
    
    $where = "WHERE status = 'Aktif' AND stok > 0";
    if ($search != '') {
        $where .= " AND (kode_barang LIKE '%$search%' OR nama_barang LIKE '%$search%')";
    }
    
    $query = "SELECT id, kode_barang, nama_barang, harga_jual, stok FROM barang $where ORDER BY nama_barang ASC LIMIT 20";
    $result = $koneksi->query($query);
    
    $html = "";
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $nama_aman = addslashes($row['nama_barang']);
            $html .= "
            <div style='background: rgba(255,255,255,0.05); padding: 12px; border: 1px solid var(--border-color); border-radius: 8px; margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center; transition: 0.3s;'>
                <div>
                    <b style='color: var(--btn-primary); font-size: 15px;'>{$row['nama_barang']}</b><br>
                    <small style='color: var(--text-muted);'>SKU: {$row['kode_barang']} | Sisa Stok: <b>{$row['stok']}</b></small>
                </div>
                <div style='text-align: right;'>
                    <div style='margin-bottom: 5px; font-weight: bold; color: var(--text-light);'>Rp " . number_format($row['harga_jual'], 0, ',', '.') . "</div>
                    <button class='btn btn-success btn-sm' onclick='addToCart({$row['id']}, \"{$nama_aman}\", {$row['harga_jual']}, {$row['stok']})'>+ Pilih</button>
                </div>
            </div>";
        }
    } else {
        $html = "<div style='text-align:center; padding: 20px; color: var(--text-muted);'>Barang tidak ditemukan atau stok habis.</div>";
    }
    
    echo json_encode(['html' => $html]);
    exit;
}

// ==========================================
// 1B. ENDPOINT AJAX: CEK MEMBER & POIN (JSON)
// ==========================================
if (isset($_GET['cek_member'])) {
    ob_clean();
    header('Content-Type: application/json');
    $telp = bersihkan_input($koneksi, $_GET['telp']);
    
    $q = $koneksi->query("SELECT * FROM pelanggan WHERE no_telp = '$telp' LIMIT 1");
    if ($q && $q->num_rows > 0) {
        $p = $q->fetch_assoc();
        
        $last_active = $p['last_transaksi'] ? $p['last_transaksi'] : date('Y-m-d', strtotime($p['created_at']));
        $diff_days = date_diff(date_create($last_active), date_create(date('Y-m-d')))->days;
        
        $is_expired = ($diff_days > 90);
        $poin_aktif = $is_expired ? 0 : (int)$p['total_poin'];
        
        if ($is_expired && $p['total_poin'] > 0) {
            $koneksi->query("UPDATE pelanggan SET total_poin = 0 WHERE id = " . $p['id']);
        }
        
        echo json_encode([
            'status' => 'found',
            'nama' => $p['nama_pelanggan'],
            'poin' => $poin_aktif,
            'rupiah' => $poin_aktif * 10000
        ]);
    } else {
        echo json_encode(['status' => 'not_found']);
    }
    exit;
}

// ==========================================
// 2. LOGIKA PROSES TRANSAKSI (SIMPAN KE DB)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['proses_transaksi'])) {
    $nama_customer = bersihkan_input($koneksi, $_POST['nama_customer']);
    $no_telp       = bersihkan_input($koneksi, $_POST['no_telp']);
    $metode_bayar  = bersihkan_input($koneksi, $_POST['metode_pembayaran']);
    $total_harga_awal = (int)$_POST['total_harga_hidden']; 
    $bayar         = (int)$_POST['nominal_bayar']; 
    $id_user       = $_SESSION['user_id'];
    $tanggal_full  = date('Y-m-d H:i:s');
    $tanggal_hari  = date('Y-m-d');
    $no_transaksi  = 'TRX-' . date('YmdHis'); 
    $cart_data     = json_decode($_POST['cart_data'], true);

    // ------------------------------------------
    // VALIDASI BACKEND: SN WAJIB & ANTI-GANDA! 
    // ------------------------------------------
    $all_sn_array = [];

    if (is_array($cart_data) && count($cart_data) > 0) {
        foreach ($cart_data as $item) {
            $sn_list = is_array($item['sn']) ? $item['sn'] : [];
            
            // 1. Cek Kesesuaian Jumlah Qty dan SN
            if (count($sn_list) != $item['qty']) {
                echo "<script>alert('Sistem Error! Jumlah SN tidak sesuai dengan Qty barang.'); window.history.back();</script>";
                exit;
            }

            // 2. Cek Apakah Ada yang Kosong
            foreach ($sn_list as $sn_val) {
                $sn_val_bersih = trim($sn_val);
                if ($sn_val_bersih === '') {
                    echo "<script>alert('GAGAL! Terdapat Serial Number (SN) untuk barang " . addslashes($item['name']) . " yang belum diisi. Jika tidak ada SN, wajib isi dengan tanda strip (-).'); window.history.back();</script>";
                    exit;
                }

                // Masukkan ke array koleksi SN, kecuali tanda strip (-) tidak dianggap ganda
                if ($sn_val_bersih !== '-') {
                    $all_sn_array[] = strtoupper($sn_val_bersih);
                }
            }
        }

        // 3. Cek Apakah Ada SN yang Berulang/Ganda di dalam transaksi ini
        $sn_counts = array_count_values($all_sn_array);
        foreach ($sn_counts as $kode_sn => $jumlah_muncul) {
            if ($jumlah_muncul > 1) {
                echo "<script>alert('⛔ GAGAL! Serial Number [$kode_sn] terdeteksi GANDA/KEMBAR. Pastikan setiap fisik unit memiliki SN yang unik!'); window.history.back();</script>";
                exit;
            }
        }

    } else {
        echo "<script>alert('Gagal! Keranjang kosong.'); window.history.back();</script>";
        exit;
    }

    // ------------------------------------------
    // LOGIKA PERHITUNGAN DISKON & POIN LOYALITAS
    // ------------------------------------------
    $poin_digunakan = 0;
    $diskon_rp = 0;
    if (isset($_POST['gunakan_poin']) && $_POST['gunakan_poin'] == '1') {
        $poin_digunakan = (int)$_POST['poin_dimiliki'];
        $diskon_rp = $poin_digunakan * 10000;
    }
    if ($diskon_rp > $total_harga_awal) {
        $diskon_rp = $total_harga_awal;
        $poin_digunakan = ceil($diskon_rp / 10000); 
    }
    $total_bayar_akhir = $total_harga_awal - $diskon_rp;
    $poin_didapat = ($total_harga_awal >= 1000000) ? 1 : 0;
    
    // VALIDASI PEMBAYARAN
    if ($metode_bayar != 'Tempo/Kasbon' && $bayar < $total_bayar_akhir) {
        echo "<script>alert('Gagal! Uang bayar tidak mencukupi.'); window.history.back();</script>";
        exit;
    }
    if ($metode_bayar == 'Tempo/Kasbon' && empty($nama_customer)) {
        echo "<script>alert('Gagal! Transaksi Kasbon WAJIB mengisi Nama Pelanggan.'); window.history.back();</script>";
        exit;
    }

    // PROSES SIMPAN KE DATABASE CRM
    if (!empty($no_telp) && !empty($nama_customer)) {
        $cek_cust = $koneksi->query("SELECT * FROM pelanggan WHERE no_telp = '$no_telp'");
        if ($cek_cust && $cek_cust->num_rows > 0) {
            $data_pel = $cek_cust->fetch_assoc();
            $id_pel = (int)$data_pel['id'];
            $poin_sekarang = (int)$data_pel['total_poin'];
            $poin_baru = $poin_sekarang - $poin_digunakan + $poin_didapat;
            if ($poin_baru < 0) { $poin_baru = 0; }
            $koneksi->query("UPDATE pelanggan SET total_poin = $poin_baru, last_transaksi = '$tanggal_hari' WHERE id = $id_pel");
        } else {
            $koneksi->query("INSERT INTO pelanggan (nama_pelanggan, no_telp, total_poin, last_transaksi) VALUES ('$nama_customer', '$no_telp', '$poin_didapat', '$tanggal_hari')");
        }
    }

    $query_jual = "INSERT INTO penjualan (no_transaksi, tanggal_transaksi, nama_customer, no_telp, total_harga, diskon, poin_digunakan, poin_didapat, metode_pembayaran, id_user) 
                   VALUES ('$no_transaksi', '$tanggal_full', '$nama_customer', '$no_telp', '$total_bayar_akhir', '$diskon_rp', '$poin_digunakan', '$poin_didapat', '$metode_bayar', '$id_user')";
    
    if ($koneksi->query($query_jual)) {
        $id_penjualan = $koneksi->insert_id; 

        // Simpan Detail Barang & Gabungkan SN Array menjadi String Koma
        foreach ($cart_data as $item) {
            $id_brg   = (int)$item['id'];
            $harga    = (int)$item['price'];
            $qty      = (int)$item['qty'];
            $subtotal = $harga * $qty;
            
            // Bersihkan SN dari Spasi ekstra dan buat string dipisah koma
            $sn_array_bersih = array_map('trim', $item['sn']);
            $sn_string = implode(", ", $sn_array_bersih);
            $sn_fisik = bersihkan_input($koneksi, $sn_string);

            $koneksi->query("INSERT INTO penjualan_detail (id_penjualan, id_barang, harga_jual, qty, subtotal, masa_garansi, sn_fisik) 
                             VALUES ('$id_penjualan', '$id_brg', '$harga', '$qty', '$subtotal', '1 Bulan', '$sn_fisik')");
            
            $koneksi->query("UPDATE barang SET stok = stok - $qty WHERE id = '$id_brg'");
        }

        if ($metode_bayar == 'Tempo/Kasbon') {
            $sisa_hutang = $total_bayar_akhir - $bayar;
            $jatuh_tempo = bersihkan_input($koneksi, $_POST['jatuh_tempo']);
            $status_hutang = ($sisa_hutang <= 0) ? 'Lunas' : 'Belum Lunas';
            $koneksi->query("INSERT INTO piutang (id_penjualan, no_transaksi, nama_pelanggan, no_telp, total_hutang, jumlah_terbayar, sisa_hutang, jatuh_tempo, status) 
                             VALUES ('$id_penjualan', '$no_transaksi', '$nama_customer', '$no_telp', '$total_bayar_akhir', '$bayar', '$sisa_hutang', '$jatuh_tempo', '$status_hutang')");
        }

        echo "<script>window.location='cetak_struk.php?no=$no_transaksi&bayar=$bayar';</script>";
        exit;
    }
}

// ==========================================
// 3. TAMPILAN UI/UX
// ==========================================
require_once 'header.php';
require_once 'sidebar.php';
?>

<style>
    .pos-container { display: flex; flex-wrap: wrap; gap: 20px; align-items: flex-start; }
    .pos-left { flex: 1.5; min-width: 320px; }
    .pos-right { flex: 1; min-width: 320px; background: rgba(36, 36, 36, 0.6); border: 1px solid var(--btn-primary); border-radius: 8px; padding: 20px; position: sticky; top: 20px; }
    
    .search-capsule {
        padding: 12px 20px; border-radius: 25px; background: rgba(0,0,0,0.3); border: 1px solid var(--border-color); color: #fff; width: 100%; outline: none; transition: all 0.3s ease; font-size: 15px; margin-bottom: 15px;
    }
    .search-capsule:focus { box-shadow: 0 0 15px rgba(245, 158, 11, 0.5); border-color: var(--btn-primary); background: rgba(0,0,0,0.5); }
    
    .cart-item { display: flex; flex-direction: column; padding: 12px 0; border-bottom: 1px dashed var(--border-color); }
    .cart-item-top { display: flex; justify-content: space-between; align-items: center; width: 100%; margin-bottom: 8px; }
    
    .qty-btn { background: var(--bg-panel); border: 1px solid var(--border-color); color: white; width: 25px; height: 25px; border-radius: 4px; cursor: pointer; }
    .qty-btn:hover { background: var(--btn-primary); color: #000; }
    
    .sn-input-wrapper { display: flex; flex-direction: column; gap: 5px; width: 100%; }
    .sn-input {
        width: 100%; padding: 8px 10px; border-radius: 5px; background: rgba(0,0,0,0.4);
        border: 1px dashed #ef4444; color: #fff; outline: none; font-size: 13px; box-sizing: border-box; transition: 0.3s;
    }
    .sn-input:focus { border-color: #38bdf8; background: rgba(0,0,0,0.8); }
    .sn-input.filled { border-color: #10b981; }

    .total-display { background: #000; color: var(--btn-primary); font-size: 26px; font-weight: bold; text-align: right; padding: 15px; border-radius: 8px; margin: 15px 0; border: 1px solid var(--border-color); line-height: 1.2; }
    
    .form-group label { display: block; margin-bottom: 5px; font-size: 13px; color: #cbd5e1; font-weight: bold; }
    .form-control { width: 100%; padding: 10px; border-radius: 8px; background: rgba(0,0,0,0.3); border: 1px solid var(--border-color); color: #fff; outline: none; box-sizing: border-box; }
    .form-control:focus { border-color: var(--btn-primary); }

    .alert-sn {
        display: none; background: rgba(239, 68, 68, 0.15); border: 1px solid #ef4444; color: #fca5a5;
        padding: 10px; border-radius: 6px; text-align: center; font-size: 12px; font-weight: bold; margin-bottom: 15px; line-height: 1.4;
    }
</style>

<main class="main-content">
    <div class="header-content">
        <h2>🛒 Mesin Kasir & Point of Sale</h2>
    </div>

    <div class="pos-container">
        <div class="pos-left">
            <input type="text" id="searchPos" class="search-capsule" placeholder="🔍 Ketik nama barang atau scan Kode Barang/SKU..." autocomplete="off">
            <div class="panel-glass" style="padding: 15px; min-height: 400px; max-height: 600px; overflow-y: auto;" id="productList">
                <div style="text-align:center; padding: 20px; color: var(--text-muted);">Ketik nama barang di atas untuk memulai.</div>
            </div>
        </div>

        <div class="pos-right">
            <h3 style="margin-top: 0; color: white; border-bottom: 2px solid var(--btn-primary); padding-bottom: 10px;">Keranjang Belanja</h3>
            
            <div id="cartList" style="min-height: 150px; max-height: 350px; overflow-y: auto; margin-bottom: 10px;">
                <div style="text-align: center; color: var(--text-muted); padding: 20px;">Keranjang kosong.</div>
            </div>

            <div id="alertSn" class="alert-sn">⚠️ Harap ketik/scan Serial Number di SEMUA Unit!</div>

            <div class="total-display" id="displayTotalContainer">
                Rp <span id="displayTotal">0</span>
            </div>

            <form method="POST" action="" id="formTransaksi">
                <input type="hidden" name="cart_data" id="cart_data">
                <input type="hidden" name="total_harga_hidden" id="total_harga_hidden" value="0">
                
                <div class="form-group" style="display: flex; gap: 10px; margin-bottom: 10px;">
                    <div style="flex: 1;">
                        <label>No. Telp / WA <span style="color:#38bdf8; font-size:11px; font-weight:normal;">*Ketik u/ Cari</span></label>
                        <input type="text" name="no_telp" id="inputTelp" class="form-control" placeholder="08..." autocomplete="off" onblur="cekMemberPoin(this.value)">
                    </div>
                    <div style="flex: 1;">
                        <label>Nama Pelanggan <span id="reqCustomer" style="color:red; display:none;">*</span></label>
                        <input type="text" name="nama_customer" id="inputCustomer" class="form-control" placeholder="Nama..." onkeyup="hitungKembalian()">
                    </div>
                </div>

                <div class="form-group" id="areaPoin" style="display: none; background: rgba(245, 158, 11, 0.2); padding: 10px; border-radius: 8px; border: 1px solid #f59e0b; margin-bottom: 10px;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <span style="font-size: 11px; color: #fcd34d;">Status Member:</span><br>
                            <b style="color: #fff; font-size: 13px;" id="textPoin">⭐ Punya 0 Poin (Rp 0)</b>
                        </div>
                        <div>
                            <label style="cursor: pointer; display: flex; align-items: center; gap: 5px; color: #fff; font-size: 13px; font-weight: bold; background: rgba(0,0,0,0.5); padding: 5px 10px; border-radius: 4px;">
                                <input type="checkbox" name="gunakan_poin" id="chkPoin" onchange="hitungKembalian()" value="1"> Pakai Poin
                            </label>
                        </div>
                    </div>
                    <input type="hidden" name="poin_dimiliki" id="poinDimiliki" value="0">
                </div>

                <div class="form-group" style="display: flex; gap: 10px; margin-bottom: 10px;">
                    <div style="flex: 1;">
                        <label>Metode Bayar</label>
                        <select name="metode_pembayaran" id="metodeBayar" class="form-control" required onchange="ubahMetodeBayar()">
                            <option value="Cash">💵 Tunai (Cash)</option>
                            <option value="Transfer Bank">💳 Transfer Bank</option>
                            <option value="QRIS">📱 QRIS</option>
                            <option value="Tempo/Kasbon" style="color:#f87171; font-weight:bold;">📒 Tempo / Kasbon</option>
                        </select>
                    </div>
                    <div style="flex: 1;">
                        <label id="labelBayar">Nominal Bayar (Rp)</label>
                        <input type="number" name="nominal_bayar" id="inputBayar" class="form-control" placeholder="0" required min="0" onkeyup="hitungKembalian()">
                    </div>
                </div>

                <div class="form-group" id="areaJatuhTempo" style="display: none; margin-bottom: 10px;">
                    <label style="color: #f87171;">📅 Tanggal Jatuh Tempo (Hutang)</label>
                    <input type="date" name="jatuh_tempo" class="form-control" value="<?= date('Y-m-d', strtotime('+7 days')) ?>">
                </div>

                <div style="text-align: right; margin-bottom: 15px; color: var(--text-muted);">
                    <span id="labelKembalian">Kembalian:</span> <b style="color: #34d399; font-size: 18px;">Rp <span id="displayKembalian">0</span></b>
                </div>

                <button type="submit" name="proses_transaksi" class="btn btn-primary" style="width: 100%; font-size: 16px; padding: 15px; border:none; cursor:pointer; font-weight:bold; border-radius:8px;" id="btnProses" disabled>
                    BAYAR & PROSES
                </button>
            </form>
        </div>
    </div>
</main>

<script>
    let cart = [];
    let totalHarga = 0;
    const searchInput = document.getElementById('searchPos');
    let typingTimer;

    function loadProducts() {
        let keyword = searchInput.value;
        fetch(`kasir.php?ajax_search=1&search=${keyword}`)
            .then(response => response.json())
            .then(data => { document.getElementById('productList').innerHTML = data.html; })
            .catch(error => console.error('Error fetching data:', error));
    }
    searchInput.addEventListener('input', function() {
        clearTimeout(typingTimer);
        typingTimer = setTimeout(loadProducts, 300); 
    });
    loadProducts();

    function cekMemberPoin(telp) {
        if(telp.length < 8) {
            document.getElementById('areaPoin').style.display = 'none';
            document.getElementById('poinDimiliki').value = 0;
            document.getElementById('chkPoin').checked = false;
            hitungKembalian();
            return;
        }

        fetch(`kasir.php?cek_member=1&telp=${telp}`)
            .then(res => res.json())
            .then(data => {
                if(data.status === 'found') {
                    document.getElementById('inputCustomer').value = data.nama;
                    document.getElementById('areaPoin').style.display = 'block';
                    document.getElementById('textPoin').innerText = `⭐ Punya ${data.poin} Poin (Senilai Rp ${data.rupiah.toLocaleString('id-ID')})`;
                    document.getElementById('poinDimiliki').value = data.poin;
                    
                    if(data.poin == 0) {
                        document.getElementById('chkPoin').disabled = true;
                        document.getElementById('chkPoin').checked = false;
                    } else {
                        document.getElementById('chkPoin').disabled = false;
                    }
                } else {
                    document.getElementById('areaPoin').style.display = 'none';
                    document.getElementById('poinDimiliki').value = 0;
                    document.getElementById('chkPoin').checked = false;
                }
                hitungKembalian();
            });
    }

    function addToCart(id, name, price, maxStok) {
        let existing = cart.find(item => item.id === id);
        if (existing) {
            if (existing.qty < maxStok) { 
                existing.qty += 1; 
                existing.sn.push(''); 
            } 
            else { alert('Stok tidak mencukupi!'); return; }
        } else {
            cart.push({ id: id, name: name, price: price, qty: 1, max: maxStok, sn: [''] });
        }
        renderCart();
    }

    function renderCart() {
        const cartList = document.getElementById('cartList');
        totalHarga = 0;
        
        if (cart.length === 0) {
            cartList.innerHTML = '<div style="text-align: center; color: var(--text-muted); padding: 20px;">Keranjang masih kosong.</div>';
            document.getElementById('displayTotalContainer').innerHTML = 'Rp 0';
            document.getElementById('total_harga_hidden').value = 0;
            // Panggil hitungKembalian agar logic alert dan tombol reset
            hitungKembalian();
            return;
        }

        let html = '';
        cart.forEach((item, index) => {
            let subtotal = item.price * item.qty;
            totalHarga += subtotal;
            
            // Generate Input SN berdasarkan jumlah Qty
            let snInputsHTML = '';
            for (let i = 0; i < item.qty; i++) {
                let valSn = item.sn[i] ? item.sn[i] : '';
                let filledClass = valSn.trim() !== '' ? 'filled' : '';
                let placeholder = `*Scan SN Unit ke-${i+1} (Ketik '-' jika tdk ada)`;
                
                snInputsHTML += `<input type="text" class="sn-input ${filledClass}" placeholder="${placeholder}" value="${valSn}" oninput="simpanSN(${index}, ${i}, this)">`;
            }
            
            html += `
            <div class="cart-item">
                <div class="cart-item-top">
                    <div style="flex: 2;">
                        <div style="color: white; font-size: 14px;">${item.name}</div>
                        <div style="color: var(--text-muted); font-size: 12px;">Rp ${item.price.toLocaleString('id-ID')} x ${item.qty}</div>
                    </div>
                    <div style="flex: 1; text-align: center;">
                        <button type="button" class="qty-btn" onclick="ubahQty(${index}, -1)">-</button>
                        <span style="margin: 0 10px; color: white;">${item.qty}</span>
                        <button type="button" class="qty-btn" onclick="ubahQty(${index}, 1)">+</button>
                    </div>
                    <div style="flex: 1; text-align: right; color: var(--btn-primary); font-weight: bold; font-size: 14px;">
                        Rp ${subtotal.toLocaleString('id-ID')}
                    </div>
                </div>
                <div class="sn-input-wrapper">
                    ${snInputsHTML}
                </div>
            </div>`;
        });
        
        cartList.innerHTML = html;
        document.getElementById('total_harga_hidden').value = totalHarga;
        document.getElementById('cart_data').value = JSON.stringify(cart);
        
        hitungKembalian();
    }

    function ubahQty(index, change) {
        let item = cart[index];
        if (change > 0 && item.qty >= item.max) { alert('Maksimal stok tercapai!'); return; }
        
        item.qty += change;
        
        if (item.qty <= 0) { 
            cart.splice(index, 1); 
        } else {
            if (change > 0) {
                item.sn.push(''); 
            } else {
                item.sn.pop();    
            }
        }
        renderCart();
    }

    function simpanSN(cartIndex, snIndex, inputElement) {
        let nilai = inputElement.value;
        cart[cartIndex].sn[snIndex] = nilai;
        
        if (nilai.trim() !== '') {
            inputElement.classList.add('filled');
        } else {
            inputElement.classList.remove('filled');
        }

        document.getElementById('cart_data').value = JSON.stringify(cart);
        hitungKembalian(); 
    }

    function ubahMetodeBayar() {
        let metode = document.getElementById('metodeBayar').value;
        let jt = document.getElementById('areaJatuhTempo');
        let reqCust = document.getElementById('reqCustomer');
        let labelBayar = document.getElementById('labelBayar');

        if (metode === 'Tempo/Kasbon') {
            jt.style.display = 'block'; 
            reqCust.style.display = 'inline'; 
            labelBayar.innerText = 'Bayar DP (Isi 0 jika tidak DP)';
        } else {
            jt.style.display = 'none';
            reqCust.style.display = 'none';
            labelBayar.innerText = 'Nominal Bayar (Rp)';
        }
        hitungKembalian();
    }

    function hitungKembalian() {
        let usePoin = document.getElementById('chkPoin') && document.getElementById('chkPoin').checked;
        let poinDimiliki = parseInt(document.getElementById('poinDimiliki').value) || 0;
        let diskonRp = usePoin ? (poinDimiliki * 10000) : 0;
        
        if (diskonRp > totalHarga) { diskonRp = totalHarga; }
        let totalAkhir = totalHarga - diskonRp;

        let displayTotalHTML = '';
        if (usePoin && diskonRp > 0) {
            displayTotalHTML = `<span style="text-decoration:line-through; font-size:16px; color:#aaa;">Rp ${totalHarga.toLocaleString('id-ID')}</span><br>Rp ${totalAkhir.toLocaleString('id-ID')} <br><small style='font-size:12px; color:#34d399;'>(- Diskon Poin Rp ${diskonRp.toLocaleString('id-ID')})</small>`;
        } else {
            displayTotalHTML = `Rp ${totalHarga.toLocaleString('id-ID')}`;
        }
        document.getElementById('displayTotalContainer').innerHTML = displayTotalHTML;

        let bayar = parseInt(document.getElementById('inputBayar').value) || 0;
        let metode = document.getElementById('metodeBayar').value;
        let selisih = bayar - totalAkhir;
        let display = document.getElementById('displayKembalian');
        let label = document.getElementById('labelKembalian');
        
        if (metode === 'Tempo/Kasbon') {
            label.innerText = 'Sisa Hutang:';
            if (selisih < 0) {
                display.innerText = Math.abs(selisih).toLocaleString('id-ID'); display.style.color = '#f87171'; 
            } else {
                display.innerText = '0 (Lunas)'; display.style.color = '#34d399';
            }
        } else {
            label.innerText = 'Kembalian:';
            if (selisih >= 0 && totalAkhir >= 0) {
                display.innerText = selisih.toLocaleString('id-ID'); display.style.color = '#34d399'; 
            } else {
                display.innerText = '0 (Uang Kurang)'; display.style.color = '#f87171'; 
            }
        }
        cekTombolProses(totalAkhir);
    }

    function cekTombolProses(totalAkhir) {
        let bayar = parseInt(document.getElementById('inputBayar').value) || 0;
        let metode = document.getElementById('metodeBayar').value;
        let customer = document.getElementById('inputCustomer').value.trim();
        const btn = document.getElementById('btnProses');
        const alertSn = document.getElementById('alertSn');
        
        // Cek Kelengkapan & Anti-Ganda
        let snLengkap = true;
        let semuaSn = [];
        let hasDuplicate = false;

        if(cart.length > 0) {
            for (let item of cart) {
                for (let s of item.sn) {
                    let val = s.trim();
                    if (val === '') {
                        snLengkap = false;
                    } else if (val !== '-') { 
                        semuaSn.push(val.toUpperCase()); // Kumpulkan untuk cek duplikat (abaikan strip '-')
                    }
                }
            }
            
            // Logika Cek Array Duplikat menggunakan Set
            let unikSn = new Set(semuaSn);
            if (unikSn.size !== semuaSn.length) {
                hasDuplicate = true;
            }
        }

        // Tampilkan peringatan merah
        if (cart.length > 0) {
            if (!snLengkap) {
                alertSn.innerText = '⚠️ Harap ketik/scan Serial Number di SEMUA Unit!';
                alertSn.style.display = 'block';
            } else if (hasDuplicate) {
                alertSn.innerText = '⛔ ERROR: Terdapat Serial Number (SN) yang ganda/kembar!';
                alertSn.style.display = 'block';
            } else {
                alertSn.style.display = 'none';
            }
        } else {
            alertSn.style.display = 'none';
        }

        // Logika Aktif/Nonaktif Tombol
        if (cart.length > 0 && totalHarga > 0 && snLengkap && !hasDuplicate) {
            if (metode === 'Tempo/Kasbon') { 
                btn.disabled = (customer === '') ? true : false; 
            } else { 
                btn.disabled = (bayar >= totalAkhir) ? false : true; 
            }
        } else { 
            btn.disabled = true; 
        }
    }
</script>

<?php require_once 'footer.php'; ?>
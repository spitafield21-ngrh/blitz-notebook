<?php
// ==========================================
// FILE: footer.php
// FUNGSI: Penutup HTML, Script Modal, & Copyright
// ==========================================
?>
    </div> <!-- Penutup <div class="app-wrapper"> dari header.php -->

    <!-- FOOTER COPYRIGHT BLITZ NOTEBOOK -->
    <footer style="text-align: center; padding: 12px; background-color: var(--bg-topbar); border-top: 1px solid var(--border-color); color: var(--text-muted); font-size: 13px; z-index: 1000; flex-shrink: 0; width: 100%;">
        &copy; 2026 Blitz Notebook. VERSI 2.0 (Stable Build).
    </footer>

    <!-- SCRIPT DASAR UI/UX -->
    <script>
        // 1. Logika Toggle Sidebar Responsif
        const btnToggleSidebar = document.getElementById('btnToggleSidebar');
        const sidebar = document.querySelector('.sidebar');

        if(btnToggleSidebar && sidebar) {
            btnToggleSidebar.addEventListener('click', function() {
                if (window.innerWidth <= 768) {
                    sidebar.classList.toggle('mobile-open');
                } else {
                    sidebar.classList.toggle('collapsed');
                }
            });
        }

        // 2. MENGEMBALIKAN LOGIKA MODAL POP-UP YANG HILANG (Untuk tombol "Tambah")
        const modalOverlay = document.getElementById('modalTemplate');
        const btnBukaModal = document.getElementById('btnBukaModal');
        const btnBatal = document.getElementById('btnBatal');

        if(btnBukaModal && modalOverlay) {
            btnBukaModal.addEventListener('click', function() {
                modalOverlay.style.display = 'flex';
            });
        }
        
        if(btnBatal && modalOverlay) {
            btnBatal.addEventListener('click', function() {
                modalOverlay.style.display = 'none';
            });
        }
    </script>
</body>
</html>
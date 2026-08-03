/**
 * Toggle drawer sidebar di layar mobile - dipakai bersama oleh
 * semua halaman yang punya .section-sidebar (lihat tombol
 * hamburger & tombol close di masing-masing file .php).
 */
function toggleSidebar() {
    document.getElementById('sectionSidebar').classList.toggle('sidebar-open');
    document.getElementById('sidebarBackdrop').classList.toggle('sidebar-open');
    document.getElementById('sidebarToggleBtn').classList.toggle('sidebar-hidden');
}

function closeSidebar() {
    document.getElementById('sectionSidebar').classList.remove('sidebar-open');
    document.getElementById('sidebarBackdrop').classList.remove('sidebar-open');
    document.getElementById('sidebarToggleBtn').classList.remove('sidebar-hidden');
}

/* Tutup otomatis kalau layar diperbesar melewati breakpoint mobile,
   supaya sidebar tidak "nyangkut" dalam kondisi terbuka saat
   pengguna memutar layar / resize window ke ukuran desktop. */
window.addEventListener('resize', function () {
    if (window.innerWidth > 900) {
        closeSidebar();
    }
});

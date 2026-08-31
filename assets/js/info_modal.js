/* =============================================
   MODAL PENJELASAN GRAFIK & PREFERENSI MODEL
   (hasil UAT DPA - penjelasan singkat, bahasa awam)

   Cara pakai di HTML: bungkus judul/label dengan class
   "info-clickable-text" dan onclick="showInfoModal('key')" -
   klik teksnya langsung membuka popup, tanpa tombol terpisah.

   Contoh:
   <h4 class="info-clickable-text" onclick="showInfoModal('pie_kategori')">
       Grafik Sebaran Kategori
   </h4>

   Semua teks didefinisikan sekali di sini (INFO_TEXTS) supaya
   konsisten di seluruh halaman.
   ============================================= */

const INFO_TEXTS = {
    pie_kategori: {
        judul: "Grafik Sebaran Kategori",
        isi: `
            Menunjukkan jumlah mahasiswa di tiap kategori:
            <strong style="color:#ff6b6b;">Kritis</strong>,
            <strong style="color:#e6a400;">Waspada</strong>,
            <strong style="color:#3aa845;">Aman</strong>,
            <strong style="color:#00875a;">Sangat Baik</strong>.
            Makin besar potongan Kritis/Waspada, makin banyak
            mahasiswa yang perlu diprioritaskan dibimbing.
        `
    },

    scatter_topsis: {
        judul: "Grafik Sebaran Mahasiswa (TOPSIS)",
        isi: `
            Tiap titik = satu mahasiswa. Sumbu <strong>D+</strong> (mendatar)
            = jarak dari kondisi ideal, sumbu <strong>D-</strong> (tegak)
            = jarak dari kondisi terburuk.
            <br><br>
            Titik di <strong>kiri-atas</strong> = performa paling baik.
            Titik di <strong>kanan-bawah</strong> = paling perlu perhatian.
            <br><br>
            Klik titik untuk buka detail mahasiswa.
        `
    },

    grafik_ipk_semester: {
        judul: "Grafik Tren IP Semester",
        isi: `
            Menampilkan IP mahasiswa ini tiap semester. Garis naik =
            performa membaik, garis turun = perlu dibimbing lebih intensif.
        `
    },

    grafik_tren_topsis: {
        judul: "Grafik Tren Skor TOPSIS",
        isi: `
            Menampilkan perubahan skor TOPSIS mahasiswa ini dari periode
            ke periode. Skor 0-1, makin mendekati 1.0 makin baik.
        `
    },

    preferensi_model: {
        judul: "Preferensi Model",
        isi: `
            Nilai korelasi (rs) antara ranking TOPSIS dan ranking SAW,
            hasil Uji Spearman. Makin mendekati 1, makin konsisten
            kedua metode ini - makin bisa dipercaya hasil rankingnya.
        `
    },

    nilai_preferensi_topsis: {
        judul: "Nilai Preferensi TOPSIS",
        isi: `
            Skor 0-1 yang menunjukkan seberapa dekat kondisi akademik
            mahasiswa dengan kondisi ideal, dibanding teman seangkatan/
            semesternya. Makin mendekati 1.0, makin baik posisinya.
        `
    },

    kategori_status: {
        judul: "Arti Kategori Status",
        isi: `
            Ditentukan dari skor TOPSIS:
            <ul style="margin:6px 0 0 18px;padding:0;">
                <li><strong style="color:#ff6b6b;">Kritis</strong> (0,00-0,25)</li>
                <li><strong style="color:#e6a400;">Waspada</strong> (0,26-0,50)</li>
                <li><strong style="color:#3aa845;">Aman</strong> (0,51-0,75)</li>
                <li><strong style="color:#00875a;">Sangat Baik</strong> (0,76-1,00)</li>
            </ul>
        `
    }
};

function showInfoModal(key) {
    const data = INFO_TEXTS[key];
    if (!data) return;

    let overlay = document.getElementById('infoModalOverlay');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'infoModalOverlay';
        overlay.className = 'info-modal-overlay';
        overlay.innerHTML = `
            <div class="info-modal-box">
                <div class="info-modal-header">
                    <h4 id="infoModalTitle"></h4>
                    <button type="button" class="info-modal-close" onclick="closeInfoModal()">&times;</button>
                </div>
                <div class="info-modal-body" id="infoModalBody"></div>
            </div>
        `;
        document.body.appendChild(overlay);
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) closeInfoModal();
        });
    }

    document.getElementById('infoModalTitle').textContent = data.judul;
    document.getElementById('infoModalBody').innerHTML = data.isi;
    overlay.classList.add('open');
}

function closeInfoModal() {
    const overlay = document.getElementById('infoModalOverlay');
    if (overlay) overlay.classList.remove('open');
}

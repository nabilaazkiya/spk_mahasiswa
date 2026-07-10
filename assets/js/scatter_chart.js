/**
 * scatter_chart.js
 * Fungsi reusable untuk menggambar grafik scatter TOPSIS dengan:
 * - Warna titik berdasarkan kategori (Kritis/Waspada/Aman/Sangat Baik)
 * - Zona latar belakang per kategori
 * - Tooltip custom (nama, kategori, nilai preferensi, D+, D-)
 * - Zoom & pan (scroll/pinch)
 * - Klik titik untuk detail mahasiswa
 *
 * Digunakan oleh: dashboard_kaprodi.php, dashboard_dpa.php, detail_mahasiswa.php
 *
 * Membutuhkan library:
 * - chart.js
 * - hammer.js
 * - chartjs-plugin-zoom
 *
 * @param {string} canvasId      ID elemen <canvas>
 * @param {Array}  dataPoints    Array hasil dari ambilDataScatter() PHP, sudah di-json_encode
 * @param {string} resetZoomBtnId  (opsional) ID tombol reset zoom, jika ada
 * @param {function} onClickPoint  (opsional) Callback custom saat titik diklik.
 *                                  Jika tidak diisi, default akan menampilkan alert().
 * @returns {Chart} instance Chart.js, agar bisa dipanggil .resetZoom() dari luar
 */
function renderScatterChart(canvasId, dataPoints, resetZoomBtnId, onClickPoint) {

    /* Palet diselaraskan dengan grafik tren TOPSIS di detail_mahasiswa.php */
    var kategoriWarna = {
        'Kritis'         : '#dc3545',
        'Waspada'        : '#fd7e14',
        'Aman'           : '#0d9f6e',
        'Sangat Baik'    : '#198754',
        'Belum Diproses' : '#95a5a6'
    };

    var urutanKategori = ['Kritis', 'Waspada', 'Aman', 'Sangat Baik', 'Belum Diproses'];

    /* PERBAIKAN DESAIN: sebelumnya semua titik jadi 1 dataset
       tanpa legend (legend: display:false) sehingga warna
       kategori tidak terbaca tanpa hover satu-satu. Sekarang
       dipecah jadi 1 dataset PER KATEGORI, supaya Chart.js
       otomatis menampilkan legend berwarna & bisa diklik
       untuk sembunyikan/tampilkan kategori tertentu. */
    var datasetPerKategori = urutanKategori.map(function (kategori) {
        var titikKategoriIni = dataPoints.filter(function (p) {
            return (p.kategori || 'Belum Diproses') === kategori;
        });

        return {
            label: kategori,
            data: titikKategoriIni,
            pointRadius: 7,
            pointHoverRadius: 10,
            backgroundColor: kategoriWarna[kategori],
            borderColor: '#ffffff',
            borderWidth: 1.5,
            hoverBorderWidth: 2
        };
    }).filter(function (ds) {
        return ds.data.length > 0;
    });

    /* ── PLUGIN GARIS BATAS ZONA (DISEDERHANAKAN) ──
       PERBAIKAN DESAIN: versi sebelumnya menggambar 4 area
       warna latar + teks label mengambang untuk tiap zona.
       Di layar kecil / saat di-zoom dekat, label-labelnya
       saling tumpang tindih dan warna area saling bercampur
       jadi "kotor" secara visual - sulit dibaca.

       Sekarang HANYA digambar garis batas tipis putus-putus
       (tanpa isi warna, tanpa teks). Kategori tetap 100% jelas
       terbaca dari WARNA TITIK + LEGEND yang sudah ada di atas
       chart - garis ini murni referensi visual tambahan, bukan
       satu-satunya sumber informasi, jadi tidak masalah kalau
       di beberapa level zoom sebagian garis tidak terlihat. */
    var zonaPlugin = {
        id: 'zonaLatar_' + canvasId,
        beforeDraw: function(chart) {
            var ctx    = chart.ctx;
            var xScale = chart.scales.x;
            var yScale = chart.scales.y;
            var area   = chart.chartArea;

            var xMax = Math.max(xScale.max, 0.0001);
            var yMax = Math.max(yScale.max, 0.0001);
            var jauh = (xMax + yMax) * 50;

            function pxOrigin() {
                return { x: xScale.getPixelForValue(0), y: yScale.getPixelForValue(0) };
            }
            function pxSlope(slope) {
                return { x: xScale.getPixelForValue(jauh), y: yScale.getPixelForValue(slope * jauh) };
            }

            ctx.save();
            ctx.beginPath();
            ctx.rect(area.left, area.top, area.right - area.left, area.bottom - area.top);
            ctx.clip();

            ctx.strokeStyle = 'rgba(150,150,150,0.35)';
            ctx.setLineDash([4, 4]);
            ctx.lineWidth = 1;

            var o = pxOrigin();

            /* 3 garis batas: skor 0.25 (kemiringan 1/3), 0.50
               (kemiringan 1), dan 0.75 (kemiringan 3) */
            [1/3, 1, 3].forEach(function (slope) {
                var p = pxSlope(slope);
                ctx.beginPath();
                ctx.moveTo(o.x, o.y);
                ctx.lineTo(p.x, p.y);
                ctx.stroke();
            });

            ctx.setLineDash([]);
            ctx.restore();
        }
    };

    var canvasEl = document.getElementById(canvasId);
    if (!canvasEl) {
        console.warn('renderScatterChart: canvas dengan id "' + canvasId + '" tidak ditemukan.');
        return null;
    }

    var chartInstance = new Chart(canvasEl, {
        type: 'scatter',
        plugins: [zonaPlugin],
        data: {
            datasets: datasetPerKategori
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,

            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                    labels: {
                        usePointStyle: true,
                        pointStyle: 'circle',
                        boxWidth: 8,
                        padding: 16,
                        font: { size: 12, family: '"Segoe UI", Arial, sans-serif' }
                    }
                },

                tooltip: {
                    backgroundColor: '#1f2937',
                    padding: 10,
                    titleFont: { size: 13, weight: 'bold' },
                    bodyFont: { size: 12 },
                    callbacks: {
                        title: function() { return ''; },
                        label: function(ctx) {
                            var p = ctx.raw;
                            return [
                                '👤 ' + (p.nama || '-'),
                                '📊 Kategori : ' + (p.kategori || '-'),
                                '⭐ Preferensi: ' + (p.nilaiPreferensi !== undefined ? p.nilaiPreferensi : '-'),
                                '➕ D+ (positif): ' + p.x,
                                '➖ D- (negatif): ' + p.y
                            ];
                        }
                    }
                },

                zoom: {
                    zoom: {
                        wheel: { enabled: true },
                        pinch: { enabled: true },
                        mode: 'xy'
                    },
                    pan: {
                        enabled: true,
                        mode: 'xy'
                    }
                }
            },

            onClick: function(evt, elements) {
                if (elements.length === 0) return;

                var el       = elements[0];
                var dataset  = datasetPerKategori[el.datasetIndex];
                var titik    = dataset.data[el.index];

                if (typeof onClickPoint === 'function') {
                    onClickPoint(titik);
                } else {
                    var pesan =
                        'Mahasiswa : ' + (titik.nama || '-') + '\n' +
                        'Kategori  : ' + (titik.kategori || '-') + '\n' +
                        'Preferensi: ' + (titik.nilaiPreferensi !== undefined ? titik.nilaiPreferensi : '-') + '\n' +
                        'D+        : ' + titik.x + '\n' +
                        'D-        : ' + titik.y;
                    alert(pesan);
                }
            },

            scales: {
                x: {
                    title: { display: true, text: 'Jarak ke Solusi Ideal Positif (D+)', font: { size: 12 } },
                    beginAtZero: true,
                    grid: { color: 'rgba(0,0,0,0.05)' }
                },
                y: {
                    title: { display: true, text: 'Jarak ke Solusi Ideal Negatif (D-)', font: { size: 12 } },
                    beginAtZero: true,
                    grid: { color: 'rgba(0,0,0,0.05)' }
                }
            }
        }
    });

    if (resetZoomBtnId) {
        var btnReset = document.getElementById(resetZoomBtnId);
        if (btnReset) {
            btnReset.addEventListener('click', function() {
                chartInstance.resetZoom();
            });
        }
    }

    return chartInstance;
}
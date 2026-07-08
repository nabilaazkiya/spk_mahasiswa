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

    var kategoriWarna = {
        'Kritis'         : '#e74c3c',
        'Waspada'        : '#f39c12',
        'Aman'           : '#2ecc71',
        'Sangat Baik'    : '#27ae60',
        'Belum Diproses' : '#95a5a6'
    };

    var titikWarna = dataPoints.map(function(p) {
        return kategoriWarna[p.kategori] || '#95a5a6';
    });

    /* ── PLUGIN ZONA LATAR BELAKANG ── */
    var zonaPlugin = {
        id: 'zonaLatar_' + canvasId,
        beforeDraw: function(chart) {
            var ctx    = chart.ctx;
            var xScale = chart.scales.x;
            var yScale = chart.scales.y;

            /* Gunakan min/max AKTUAL dari skala saat ini (berubah saat zoom/pan),
               bukan asumsi sumbu selalu mulai dari 0. Ini memastikan zona
               kategori tetap proporsional dan menutupi seluruh area yang
               sedang ditampilkan, termasuk saat di-zoom out ke area negatif. */
            var xMin = xScale.min;
            var xMax = xScale.max;
            var yMin = yScale.min;
            var yMax = yScale.max;

            var xMid = (xMin + xMax) / 2;
            var yMid = (yMin + yMax) / 2;

            /* Zona Kritis: D+ kecil, D- kecil (kiri bawah) */
            ctx.fillStyle = 'rgba(231,76,60,0.08)';
            ctx.fillRect(
                xScale.getPixelForValue(xMin),
                yScale.getPixelForValue(yMid),
                xScale.getPixelForValue(xMid) - xScale.getPixelForValue(xMin),
                yScale.getPixelForValue(yMin) - yScale.getPixelForValue(yMid)
            );

            /* Zona Waspada: D+ besar, D- kecil (kanan bawah) */
            ctx.fillStyle = 'rgba(243,156,18,0.08)';
            ctx.fillRect(
                xScale.getPixelForValue(xMid),
                yScale.getPixelForValue(yMid),
                xScale.getPixelForValue(xMax) - xScale.getPixelForValue(xMid),
                yScale.getPixelForValue(yMin) - yScale.getPixelForValue(yMid)
            );

            /* Zona Aman: D+ kecil, D- besar (kiri atas) */
            ctx.fillStyle = 'rgba(46,204,113,0.08)';
            ctx.fillRect(
                xScale.getPixelForValue(xMin),
                yScale.getPixelForValue(yMax),
                xScale.getPixelForValue(xMid) - xScale.getPixelForValue(xMin),
                yScale.getPixelForValue(yMid) - yScale.getPixelForValue(yMax)
            );

            /* Zona Sangat Baik: D+ besar, D- besar (kanan atas) */
            ctx.fillStyle = 'rgba(39,174,96,0.08)';
            ctx.fillRect(
                xScale.getPixelForValue(xMid),
                yScale.getPixelForValue(yMax),
                xScale.getPixelForValue(xMax) - xScale.getPixelForValue(xMid),
                yScale.getPixelForValue(yMid) - yScale.getPixelForValue(yMax)
            );

            /* Label zona */
            ctx.font      = 'bold 11px Arial';
            ctx.fillStyle = 'rgba(100,100,100,0.5)';
            ctx.fillText('Kritis',      xScale.getPixelForValue(xMin) + 6, yScale.getPixelForValue(yMin) - 6);
            ctx.fillText('Waspada',     xScale.getPixelForValue(xMid) + 6, yScale.getPixelForValue(yMin) - 6);
            ctx.fillText('Aman',        xScale.getPixelForValue(xMin) + 6, yScale.getPixelForValue(yMax) + 14);
            ctx.fillText('Sangat Baik', xScale.getPixelForValue(xMid) + 6, yScale.getPixelForValue(yMax) + 14);
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
            datasets: [{
                label: 'Mahasiswa',
                data: dataPoints,
                pointRadius: 8,
                pointHoverRadius: 11,
                backgroundColor: titikWarna,
                borderColor: titikWarna,
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,

            plugins: {
                legend: { display: false },

                tooltip: {
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

                var idx   = elements[0].index;
                var titik = dataPoints[idx];

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
                    title: { display: true, text: 'Jarak ke Solusi Ideal Positif (D+)' },
                    beginAtZero: true
                },
                y: {
                    title: { display: true, text: 'Jarak ke Solusi Ideal Negatif (D-)' },
                    beginAtZero: true
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
<?php
session_start();
include 'koneksi.php';
include 'header.php';
include 'sidebar.php';

// Quick Stats (Optional query)
$result = mysqli_query($conn, "SELECT COUNT(*) as total, SUM(penjualan) as total_sales, AVG(penjualan) as avg_sales FROM penjualan_toyota");
$row = mysqli_fetch_assoc($result);
$total_data = $row['total'];
$total_sales = $row['total_sales'];
$avg_sales = $row['avg_sales'];
?>

<div class="container-fluid pt-4 px-4">
    <!-- Welcome Banner -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card-custom bg-white p-4 d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="fw-bold text-primary mb-1">Selamat Datang, Kakak-Kakak! 👋</h2>
                    <p class="text-muted mb-0">Sistem Peramalan Penjualan Mobil Toyota - Metode Dekomposisi</p>
                </div>
                <!-- <div class="d-none d-md-block">
                    <i class="fas fa-chart-line text-primary" style="font-size: 80px; opacity: 0.8;"></i>
                </div> -->
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row g-4 mb-5">
        <div class="col-md-4">
            <div class="card-custom stat-card">
                <div>
                    <h6 class="text-muted mb-2">Total Data Bulan</h6>
                    <h3 class="fw-bold mb-0"><?= number_format($total_data) ?></h3>
                </div>
                <div class="stat-icon bg-primary-soft">
                    <i class="fas fa-calendar-alt"></i>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card-custom stat-card">
                <div>
                    <h6 class="text-muted mb-2">Total Penjualan</h6>
                    <h3 class="fw-bold mb-0"><?= number_format($total_sales) ?></h3>
                </div>
                <div class="stat-icon bg-success-soft">
                    <i class="fas fa-shopping-cart"></i>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card-custom stat-card">
                <div>
                    <h6 class="text-muted mb-2">Rata-rata Penjualan</h6>
                    <h3 class="fw-bold mb-0"><?= number_format($avg_sales, 0) ?></h3>
                </div>
                <div class="stat-icon bg-warning-soft">
                    <i class="fas fa-chart-bar"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- PANDUAN DEKOMPOSISI -->
    <div class="row g-4 mb-4">
        <div class="col-12">
            <div class="card-custom">
                <div class="d-flex align-items-center mb-3">
                    <div class="bg-primary text-white rounded-circle p-2 me-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                        <i class="fas fa-book-open"></i>
                    </div>
                    <div>
                        <h5 class="card-title fw-bold mb-0">Tahapan Metode Dekomposisi</h5>
                        <p class="text-muted small mb-0">Panduan langkah demi langkah cara kerja sistem</p>
                    </div>
                </div>

                <div class="accordion accordion-flush" id="accordionGuide">
                    
                    <!-- STEP 1: TREND -->
                    <div class="accordion-item border rounded mb-2">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed fw-bold text-primary" type="button" data-bs-toggle="collapse" data-bs-target="#guide1">
                                <span class="badge bg-primary me-2">1</span> Menentukan Trend (CMAT)
                            </button>
                        </h2>
                        <div id="guide1" class="accordion-collapse collapse" data-bs-parent="#accordionGuide">
                            <div class="accordion-body text-muted small">
                                <p>Sistem menggunakan <strong>Regresi Linear Sederhana</strong> untuk menentukan garis tren:</p>
                                <div class="bg-light p-2 rounded mb-2 text-center">
                                    <strong>Y = a + bX</strong>
                                </div>
                                <ul class="mb-0">
                                    <li><strong>a (Konstanta):</strong> Nilai dasar penjualan saat periode ke-0.</li>
                                    <li><strong>b (Koefisien Trend):</strong> Kenaikan/penurunan penjualan per periode.</li>
                                    <li><strong>X:</strong> Periode waktu (0, 1, 2, dst).</li>
                                </ul>
                                <p class="mt-2 text-secondary fst-italic" style="font-size: 0.85em;">
                                    Rumus:<br>
                                    <code>b = (nΣXY - ΣXΣY) / (nΣX² - (ΣX)²)</code><br>
                                    <code>a = (ΣY - bΣX) / n</code>
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- STEP 2: MA -->
                    <div class="accordion-item border rounded mb-2">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed fw-bold text-primary" type="button" data-bs-toggle="collapse" data-bs-target="#guide2">
                                <span class="badge bg-primary me-2">2</span> Menghitung Moving Average (MA)
                            </button>
                        </h2>
                        <div id="guide2" class="accordion-collapse collapse" data-bs-parent="#accordionGuide">
                            <div class="accordion-body text-muted small">
                                <p>Moving Average (Rata-rata Bergerak) digunakan untuk memuluskan data. Kami menggunakan <strong>Centered Moving Average (CMA)</strong> periode 12 bulan.</p>
                                <div class="bg-light p-2 rounded mb-2 text-center">
                                    <code>MA = (Σ Data 12 Bulan) / 12</code>
                                </div>
                                <p class="mb-0">CMA diambil dari rata-rata dua nilai MA yang berurutan untuk menyesuaikan letak data tepat di tengah bulan.</p>
                            </div>
                        </div>
                    </div>

                    <!-- STEP 3: SI -->
                    <div class="accordion-item border rounded mb-2">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed fw-bold text-primary" type="button" data-bs-toggle="collapse" data-bs-target="#guide3">
                                <span class="badge bg-primary me-2">3</span> Menghitung Indeks Musiman (SI)
                            </button>
                        </h2>
                        <div id="guide3" class="accordion-collapse collapse" data-bs-parent="#accordionGuide">
                            <div class="accordion-body text-muted small">
                                <p>Kami menggunakan metode <strong>CFA (Combined Factor Actual)</strong> yang menghitung kontribusi setiap bulan terhadap total penjualan:</p>
                                <ol>
                                    <li><strong>Hitung CFA:</strong> Jumlahkan penjualan bulan tersebut di semua tahun.
                                        <br><code>CFA = Σ Penjualan Bulan_i</code>
                                    </li>
                                    <li><strong>Hitung Rasio:</strong> Bandingkan CFA dengan Total Seluruh Penjualan.
                                        <br><code>Rasio = CFA / Grand Total</code>
                                    </li>
                                    <li><strong>Hitung SI:</strong> Skalakan rasio ke 12 bulan.
                                        <br><code>SI = Rasio × 12</code>
                                    </li>
                                </ol>
                            </div>
                        </div>
                    </div>

                    <!-- STEP 4: FORECAST -->
                    <div class="accordion-item border rounded mb-2">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed fw-bold text-success" type="button" data-bs-toggle="collapse" data-bs-target="#guide4">
                                <span class="badge bg-success me-2">4</span> Peramalan Akhir (Forecasting)
                            </button>
                        </h2>
                        <div id="guide4" class="accordion-collapse collapse" data-bs-parent="#accordionGuide">
                            <div class="accordion-body text-muted small">
                                <p>Nilai peramalan (Forecast) memperhitungkan Trend (T), Siklus (CF), dan Musiman (SI). Kami membandingkan dua model:</p>
                                <div class="row g-2">
                                    <div class="col-md-6">
                                        <div class="border p-2 rounded h-100 bg-light">
                                            <strong class="text-primary d-block mb-1">Model Multiplikatif</strong>
                                            <code>F = T × CF × SI</code>
                                            <p class="mb-0 mt-1" style="font-size: 0.8em;">Cocok jika fluktuasi musiman meningkat seiring besarnya data.</p>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="border p-2 rounded h-100 bg-light">
                                            <strong class="text-primary d-block mb-1">Model Aditif</strong>
                                            <code>F = T + CF + SI</code>
                                            <p class="mb-0 mt-1" style="font-size: 0.8em;">Cocok jika fluktuasi musiman relatif konstan/tetap.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions / Description -->
    <div class="row g-4">
        <div class="col-md-8">
            <div class="card-custom">
                <h5 class="card-title">Tentang Metode Dekomposisi</h5>
                <p class="text-muted">Metode dekomposisi memecah data deret waktu menjadi beberapa komponen: trend, musiman, siklus, dan error. Sistem ini membantu Anda menganalisis pola penjualan dan memprediksi penjualan di masa depan untuk mendukung pengambilan keputusan.</p>
                
                <div class="mt-4">
                    <a href="data.php" class="btn btn-primary-custom me-2"><i class="fas fa-upload me-2"></i>Upload Data</a>
                    <a href="perhitungan.php" class="btn btn-outline-primary"><i class="fas fa-calculator me-2"></i>Lihat Perhitungan</a>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card-custom">
                <h5 class="card-title">Status Sistem</h5>
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between align-items-center border-0 px-0">
                        Database
                        <span class="badge bg-success rounded-pill">Connected</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center border-0 px-0">
                        Metode
                        <span class="badge bg-primary rounded-pill">Dekomposisi</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center border-0 px-0">
                        Last Update
                        <span class="text-muted text-end small"><?= date('d F Y') ?></span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
<?php
session_start();
include 'koneksi.php';
include 'header.php';
include 'sidebar.php';

// CONFIG
$ma_period = isset($_SESSION['ma_period']) ? $_SESSION['ma_period'] : 4; 
if(isset($_GET['ma'])) $ma_period = max(2, (int)$_GET['ma']);

// Logic (Sync with perhitungan for Forecast)
if(isset($_SESSION['is_simulasi']) && $_SESSION['is_simulasi'] && isset($_SESSION['simulasi_data'])){
    $data = $_SESSION['simulasi_data'];
} else {
    $data = [];
    $q = mysqli_query($conn, "SELECT * FROM penjualan_toyota ORDER BY bulan ASC");
    while($r = mysqli_fetch_assoc($q)) $data[] = $r;
}
$n = count($data);

// 1. Regresi CMAT
$sumX=0;$sumY=0;$sumXY=0;$sumX2=0;
for($i=0;$i<$n;$i++){ $x=$i; $y=$data[$i]['penjualan']; $sumX+=$x; $sumY+=$y; $sumXY+=($x*$y); $sumX2+=($x*$x); }
$denom = ($n*$sumX2)-($sumX**2);
$a=0; $b=0;
if($denom!=0){ $b=(($n*$sumXY)-($sumX*$sumY))/$denom; $a=($sumY-($b*$sumX))/$n; }

// 2. MA & CMA (Pre-calculation needed for CF)
$ma_values=array_fill(0,$n,null); $cma_values=array_fill(0,$n,null); $cf_values=array_fill(0,$n,null);
$is_even=($ma_period%2==0);

for($i=$ma_period; $i<$n; $i++){
    $sum=0; for($k=1;$k<=$ma_period;$k++) $sum+=$data[$i-$k]['penjualan'];
    $ma_values[$i] = $sum/$ma_period;
}
if($is_even){
    for($i=$ma_period+1; $i<$n; $i++){
        if(isset($ma_values[$i-1]) && isset($ma_values[$i])){
            $cma_values[$i] = ($ma_values[$i-1]+$ma_values[$i])/2;
            $T = $a+($b*$i);
            if($T!=0) $cf_values[$i] = $cma_values[$i]/$T;
        }
    }
} else {
    for($i=$ma_period; $i<$n; $i++){
        $cma_values[$i] = $ma_values[$i];
        $T = $a+($b*$i);
        if($T!=0) $cf_values[$i] = $cma_values[$i]/$T;
    }
}

// 3. SI LOGIC (CFA Method)
$month_totals = array_fill(0,12,0); 
$grand_total = 0;
foreach($data as $row){ 
    $m=(int)explode('-',$row['bulan'])[1]-1; 
    $month_totals[$m]+=$row['penjualan']; 
    $grand_total+=$row['penjualan']; 
}
$grand_avg = ($n>0) ? $grand_total/$n : 0;

$si_mul = []; $si_add = [];
for($m=0; $m<12; $m++){
    $cfa = $month_totals[$m];
    $ratio = ($grand_total!=0) ? ($cfa / $grand_total) : 0;
    $si_val = $ratio * 12;
    $si_mul[$m] = $si_val;
    $si_add[$m] = ($si_val - 1) * $grand_avg;
}

// 4. Forecast Series Application
$res_mul=['f'=>[],'mape'=>0]; $res_add=['f'=>[],'mape'=>0];
$sum_mape_mul=0; $cnt_mul=0; $sum_mape_add=0; $cnt_add=0;

for($i=0;$i<$n;$i++){
    $m=(int)explode('-',$data[$i]['bulan'])[1]-1;
    $T = $a + ($b*$i);
    $CF = $cf_values[$i];
    $SI = $si_mul[$m];

    $F_mul=null; $F_add=null;
    
    if($CF !== null){
        // Mul: CMAT * CF * SI
        $F_mul = $T * $CF * $SI;
        $sum_mape_mul += ($data[$i]['penjualan']!=0) ? abs(($data[$i]['penjualan']-$F_mul)/$data[$i]['penjualan'])*100 : 0;
        $cnt_mul++;

        // Add: CMAT + CF + SI (User Request)
        $F_add = $T + $CF + $SI;
        $sum_mape_add += ($data[$i]['penjualan']!=0) ? abs(($data[$i]['penjualan']-$F_add)/$data[$i]['penjualan'])*100 : 0;
        $cnt_add++;
    }

    $res_mul['f'][$i] = $F_mul;
    $res_add['f'][$i] = $F_add;
}
$res_mul['mape'] = ($cnt_mul>0)?$sum_mape_mul/$cnt_mul:0;
$res_add['mape'] = ($cnt_add>0)?$sum_mape_add/$cnt_add:0;

// Best Model Analysis
$best_model = ($res_mul['mape'] <= $res_add['mape']) ? "Multiplikatif" : "Aditif";
$best_mape  = ($res_mul['mape'] <= $res_add['mape']) ? $res_mul['mape'] : $res_add['mape'];
$analysis_text = "";
if($best_mape < 10) $analysis_text = "Sangat Baik (Akurasi Tinggi). Model ini sangat direkomendasikan.";
else if($best_mape < 20) $analysis_text = "Baik. Model memiliki akurasi yang dapat diterima.";
else if($best_mape < 50) $analysis_text = "Cukup. Disarankan untuk menambah data historis.";
else $analysis_text = "Kurang Akurat. Data mungkin tidak memiliki pola musiman yang kuat.";

// Charts
$labels=[]; $act=[]; 
$ser_mul=[]; $ser_add=[];
for($i=0;$i<$n;$i++){
    $labels[]=$data[$i]['bulan'];
    $act[]=$data[$i]['penjualan'];
    $ser_mul[] = ($res_mul['f'][$i]!==null) ? round($res_mul['f'][$i],2) : null;
    $ser_add[] = ($res_add['f'][$i]!==null) ? round($res_add['f'][$i],2) : null;
}

// Future
$fut_labels=[]; $fut_mul=[]; $fut_add=[];
$last_ym = ($n>0)?$data[$n-1]['bulan']:date('Y-m');
for($k=1;$k<=12;$k++){
    $idx = $n + $k - 1; 
    $date = date('Y-m', strtotime($last_ym . " +$k month"));
    $m = (int)date('m',strtotime($date))-1;
    $T = $a+($b*$idx);

    $fut_labels[] = $date;
    // Future: Assume CF = 1 (Neutral Ratio)
    // Mul: T * 1 * SI
    $fut_mul[] = round($T * 1 * $si_mul[$m]);
    // Add: T + 1 + SI
    $fut_add[] = round($T + 1 + $si_mul[$m]);
}
// Merge for chart
$chart_labels = array_merge($labels, $fut_labels);
$chart_act = $act;
for($k=0;$k<12;$k++) $chart_act[] = null;

$chart_mul = array_merge($ser_mul, $fut_mul);
$chart_add = array_merge($ser_add, $fut_add);
?>

<div class="container-fluid pt-4 px-4 pb-5">
    
    <!-- SIMULATION ALERT -->
    <?php if(isset($_SESSION['is_simulasi']) && $_SESSION['is_simulasi']): ?>
    <div class="alert alert-warning shadow-sm border-warning d-flex align-items-center mb-4" role="alert">
        <i class="fas fa-flask fa-2x me-3 text-warning"></i>
        <div>
            <h5 class="alert-heading fw-bold mb-1">Mode Simulasi Aktif</h5>
            <p class="mb-0 small">Grafik ini menampilkan hasil prediksi dari <strong>Data Sementara</strong>. Hasil tidak disimpan. <a href="proses_simulasi.php?reset=1" class="btn btn-sm btn-danger ms-2">Reset</a></p>
        </div>
    </div>
    <?php endif; ?>

    <!-- EXPLANATION CARD -->
    <div class="card-custom mb-4 bg-primary text-white no-print">
        <div class="d-flex align-items-start">
            <div class="me-3 fs-1 opacity-50"><i class="fas fa-info-circle"></i></div>
            <div>
                <h5 class="fw-bold mb-2">Panduan Membaca Evaluasi</h5>
                <p class="mb-1 small">Halaman ini menampilkan hasil peramalan masa depan dan evaluasi akurasi model.</p>
                <ul class="mb-0 small ps-3">
                    <li><strong>MAPE (Mean Absolute Percentage Error):</strong> Rata-rata persentase kesalahan prediksi. Semakin kecil nilainya (mendekati 0%), semakin akurat modelnya.</li>
                    <li><strong>MSE (Mean Squared Error):</strong> Rata-rata kuadrat kesalahan. Digunakan untuk melihat seberapa jauh penyimpangan data, namun nilainya sensitif terhadap data besar.</li>
                    <li><strong>Grafik:</strong> Garis putus-putus menunjukkan <em>Prediksi Masa Depan</em>. Jika garis mendekati data aktual (biru), maka prediksi cukup baik.</li>
                </ul>
            </div>
        </div>
    </div>



    <div class="d-flex justify-content-between align-items-center mb-4 no-print">
        <div>
            <h3 class="fw-bold text-primary">Visualisasi & Evaluasi Akhir</h3>
        </div>
        <div>
           <a href="perhitungan.php" class="btn btn-outline-primary me-2"><i class="fas fa-arrow-left me-2"></i>Kembali</a>
           <button class="btn btn-primary-custom" onclick="window.print()"><i class="fas fa-print me-2"></i>Cetak</button>
        </div>
    </div>

    <!-- TABS -->
    <ul class="nav nav-pills nav-fill mb-4 p-1 bg-white rounded shadow-sm" id="viewTab" role="tablist">
        <li class="nav-item">
            <button class="nav-link active fw-bold" data-bs-toggle="pill" data-bs-target="#tabMulti" type="button">
                Multiplikatif (MAPE: <?= number_format($res_mul['mape'], 2) ?>%)
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link fw-bold" data-bs-toggle="pill" data-bs-target="#tabAdd" type="button">
                Aditif (MAPE: <?= number_format($res_add['mape'], 2) ?>%)
            </button>
        </li>
    </ul>
<!-- CONCLUSION CARD (Moved) -->
    <div class="card-custom mb-4 bg-white border-start border-4 border-success shadow-sm no-print mt-4">
        <div class="p-3">
            <h5 class="fw-bold text-success mb-2"><i class="fas fa-check-circle me-2"></i>Kesimpulan & Rekomendasi</h5>
            <p class="mb-2">Berdasarkan perhitungan error menggunakan <strong>MAPE (Mean Absolute Percentage Error)</strong>:</p>
            <ul class="mb-3">
                <li>Error Model <strong>Multiplikatif</strong>: <strong><?= number_format($res_mul['mape'], 2) ?>%</strong></li>
                <li>Error Model <strong>Aditif</strong>: <strong><?= number_format($res_add['mape'], 2) ?>%</strong></li>
            </ul>
            <div class="alert alert-success bg-opacity-10 border-success text-success mb-0">
                <strong>Rekomendasi:</strong> 
                Maka metode terbaik untuk data ini adalah <strong>Model <?= $best_model ?></strong> karena memiliki tingkat kesalahan yang lebih kecil.
                <?= ($best_mape < 10) ? "Model ini sangat akurat untuk digunakan." : "Model ini cukup baik digunakan sebagai acuan." ?>
            </div>
            <hr>
            <h6 class="fw-bold mb-2 small text-muted text-uppercase">Penjelasan Visualisasi</h6>
            <div class="d-flex gap-4 small">
                <div class="d-flex align-items-center">
                    <span style="width:20px; height:3px; background:#333; margin-right:8px;"></span>
                    <span><strong>Garis Hitam (Solid):</strong> Data Penjualan Aktual (Sejarah)</span>
                </div>
                <div class="d-flex align-items-center">
                    <span style="width:20px; height:3px; border-top:3px dashed #4361ee; margin-right:8px;"></span>
                    <span><strong>Garis Putus-putus:</strong> Hasil Peramalan (Forecast)</span>
                </div>
            </div>
        </div>
    </div>
    <div class="tab-content">
        <!-- Tab MUL -->
        <div class="tab-pane fade show active" id="tabMulti">
            <div class="alert alert-primary border-0 text-white shadow-sm mb-4" style="background: linear-gradient(135deg, #4361ee, #3f37c9);">
                <div class="d-flex align-items-center">
                    <i class="fas fa-chart-line fa-2x me-3"></i>
                    <div>
                        <h6 class="fw-bold mb-1">Analisis Model Multiplikatif</h6>
                        <p class="mb-0 small opacity-75"><?= $analysis_text ?></p>
                    </div>
                </div>
            </div>
            <div class="card-custom mb-4">
                <div style="position: relative; height:450px; width:100%">
                    <canvas id="chartMulCanvas"></canvas>
                </div>
            </div>
            <!-- Forecast Table -->
            <div class="card-custom">
                <h6 class="fw-bold mb-3">Prediksi 12 Bulan Kedepan (Multiplikatif)</h6>
                <div class="table-responsive">
                    <table class="table table-hover table-sm text-center">
                        <thead class="table-dark"><tr><th>Bulan (Periode Masa Depan)</th><th>Nilai Prediksi</th></tr></thead>
                        <tbody>
                            <?php for($i=0;$i<12;$i++): ?>
                            <tr><td class="fw-bold"><?= $fut_labels[$i] ?></td><td class="text-primary fw-bold fs-5"><?= number_format($fut_mul[$i]) ?> Unit</td></tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Tab ADD -->
        <div class="tab-pane fade" id="tabAdd">
             <div class="alert alert-success border-0 text-white shadow-sm mb-4" style="background: linear-gradient(135deg, #2ec4b6, #20a4f3);">
                <div class="d-flex align-items-center">
                    <i class="fas fa-chart-line fa-2x me-3"></i>
                    <div>
                        <h6 class="fw-bold mb-1">Analisis Model Aditif</h6>
                        <p class="mb-0 small opacity-75">Perbandingan error dengan model multiplikatif untuk menentukan kecocokan data.</p>
                    </div>
                </div>
            </div>
            <div class="card-custom mb-4">
                <div style="position: relative; height:450px; width:100%">
                    <canvas id="chartAddCanvas"></canvas>
                </div>
            </div>
            <!-- Forecast Table -->
            <div class="card-custom">
                <h6 class="fw-bold mb-3">Prediksi 12 Bulan Kedepan (Aditif)</h6>
                <div class="table-responsive">
                    <table class="table table-hover table-sm text-center">
                        <thead class="table-dark"><tr><th>Bulan (Periode Masa Depan)</th><th>Nilai Prediksi</th></tr></thead>
                        <tbody>
                            <?php for($i=0;$i<12;$i++): ?>
                            <tr><td class="fw-bold"><?= $fut_labels[$i] ?></td><td class="text-success fw-bold fs-5"><?= number_format($fut_add[$i]) ?> Unit</td></tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const labels = <?= json_encode($chart_labels) ?>;
const act = <?= json_encode($chart_act) ?>;

const cfg = {
    type: 'line',
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'top' } },
        interaction: { mode: 'index', intersect: false },
        scales: {
             y: { beginAtZero: false, grid: { borderDash: [2,2] } },
             x: { ticks: { maxTicksLimit: 12 } }
        }
    }
};

// MULTI CHART
new Chart(document.getElementById('chartMulCanvas'), {
    ...cfg,
    data: {
        labels: labels,
        datasets: [
            { label: 'Data Aktual', data: act, borderColor: '#333', backgroundColor:'rgba(0,0,0,0.05)', borderWidth: 2, fill: true, tension:0.1 },
            { label: 'Forecast (Multiplikatif)', data: <?= json_encode($chart_mul) ?>, borderColor: '#4361ee', borderDash: [5,5], borderWidth: 3, pointRadius:0, tension:0.3 }
        ]
    }
});

// ADD CHART
new Chart(document.getElementById('chartAddCanvas'), {
    ...cfg,
    data: {
        labels: labels,
        datasets: [
            { label: 'Data Aktual', data: act, borderColor: '#333', backgroundColor:'rgba(0,0,0,0.05)', borderWidth: 2, fill: true, tension:0.1 },
            { label: 'Forecast (Aditif)', data: <?= json_encode($chart_add) ?>, borderColor: '#2ec4b6', borderDash: [5,5], borderWidth: 3, pointRadius:0, tension:0.3 }
        ]
    }
});
</script>
<?php include 'footer.php'; ?>

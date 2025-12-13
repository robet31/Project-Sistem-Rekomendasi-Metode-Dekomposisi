<?php
session_start();
include 'koneksi.php';
include 'header.php';
include 'sidebar.php';

// CONFIG
$ma_period = isset($_GET['ma']) ? (int)$_GET['ma'] : 4; 
if($ma_period < 2) $ma_period = 2;

// DATA
// DATA LOADING
if(isset($_SESSION['is_simulasi']) && $_SESSION['is_simulasi'] && isset($_SESSION['simulasi_data'])){
    $data = $_SESSION['simulasi_data'];
} else {
    $data = [];
    $q = mysqli_query($conn, "SELECT * FROM penjualan_toyota ORDER BY bulan ASC");
    while($r = mysqli_fetch_assoc($q)){ $data[] = $r; }
}
$n = count($data);

// 1. CMAT
$sumX=0; $sumY=0; $sumXY=0; $sumX2=0; $reg_data=[];
for($i=0; $i<$n; $i++){
    $x=$i; $y=$data[$i]['penjualan']; 
    $cur_xy = $x * $y;
    $cur_x2 = $x * $x;
    
    $sumX += $x; 
    $sumY += $y; 
    $sumXY += $cur_xy; 
    $sumX2 += $cur_x2;
    
    $reg_data[] = ['x'=>$x, 'y'=>$y, 'x2'=>$cur_x2, 'xy'=>$cur_xy];
}
$a=0; $b=0; $denom=($n*$sumX2)-($sumX**2);
if($denom!=0){ $b=(($n*$sumXY)-($sumX*$sumY))/$denom; $a=($sumY-($b*$sumX))/$n; }

$cmat_values=[];
for($i=0; $i<$n; $i++) $cmat_values[$i] = $a + ($b * $i);

// 2. MA & CMA
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
            if($cmat_values[$i]!=0) $cf_values[$i] = $cma_values[$i]/$cmat_values[$i];
        }
    }
} else {
    for($i=$ma_period; $i<$n; $i++){
        $cma_values[$i] = $ma_values[$i];
        if($cmat_values[$i]!=0) $cf_values[$i] = $cma_values[$i]/$cmat_values[$i];
    }
}

// 3. SI LOGIC (CFA Method - User Specific)
// CFA = Sum of sales for that month across all years
// RASIO = CFA / GrandTotalCFA
// SI = RASIO * 12

$month_totals = array_fill(0,12,0); 
$grand_total = 0;

// Calculate CFA (Month Totals)
foreach($data as $row){ 
    $m = (int)explode('-',$row['bulan'])[1]-1; 
    $month_totals[$m] += $row['penjualan']; 
    $grand_total += $row['penjualan']; 
}
$grand_avg = ($n>0) ? $grand_total/$n : 0;

$si_mul = []; 
$si_add = [];
$cfa_data = []; // Store for table display

for($m=0; $m<12; $m++){
    $cfa = $month_totals[$m];
    $ratio = ($grand_total!=0) ? ($cfa / $grand_total) : 0;
    $si_val = $ratio * 12;

    $si_mul[$m] = $si_val;
    // Additive Derived: (SI - 1) * GrandAvg
    $si_add[$m] = ($si_val - 1) * $grand_avg;

    $cfa_data[$m] = ['cfa'=>$cfa, 'ratio'=>$ratio, 'si'=>$si_val];
}

// 4. FORECAST & EVAL
// Formulas from User:
// Aditif = CMAT + CF + SI
// Multiplikatif = CMAT * CF * SI
$res_add = ['forecast'=>[], 'error'=>[], 'mape'=>0, 'mse'=>0, 'sum_mape'=>0, 'sum_mse'=>0, 'count'=>0];
$res_mul = ['forecast'=>[], 'error'=>[], 'mape'=>0, 'mse'=>0, 'sum_mape'=>0, 'sum_mse'=>0, 'count'=>0];

// CF valid range:
// if even: start = ma_period/2, end = n - ma_period/2
// Code uses array: cf_values[i]. If cf_values[i] is defined/not null, we calculate.

for($i=0; $i<$n; $i++){
    $m_idx = (int)explode('-', $data[$i]['bulan'])[1] - 1;
    $act = $data[$i]['penjualan'];
    $T = $cmat_values[$i];     // CMAT
    $CF = $cf_values[$i];      // CF
    $SI = $si_mul[$m_idx];     // SI (Using Multiplicative/Ratio SI for both)

    // Default null
    $F_add = null; $E_add = null;
    $F_mul = null; $E_mul = null;

    // Check if CF exists (meaning within MA window)
    if($CF !== null) {
        // ADITIF: CMAT + CF + SI
        $F_add = $T + $CF + $SI;
        $E_add = $act - $F_add;
        $res_add['sum_mse'] += ($E_add * $E_add);
        $res_add['sum_mape'] += ($act!=0 ? abs($E_add/$act)*100 : 0);
        $res_add['count']++;

        // MULTIPLIKATIF: CMAT * CF * SI
        $F_mul = $T * $CF * $SI;
        $E_mul = $act - $F_mul;
        $res_mul['sum_mse'] += ($E_mul * $E_mul);
        $res_mul['sum_mape'] += ($act!=0 ? abs($E_mul/$act)*100 : 0);
        $res_mul['count']++;
    }

    $res_add['forecast'][$i] = $F_add;
    $res_add['error'][$i] = $E_add;
    $res_mul['forecast'][$i] = $F_mul;
    $res_mul['error'][$i] = $E_mul;
}

$res_add['mape'] = ($res_add['count']>0) ? $res_add['sum_mape']/$res_add['count'] : 0;
$res_add['mse']  = ($res_add['count']>0) ? $res_add['sum_mse'] /$res_add['count'] : 0;
$res_mul['mape'] = ($res_mul['count']>0) ? $res_mul['sum_mape']/$res_mul['count'] : 0;
$res_mul['mse']  = ($res_mul['count']>0) ? $res_mul['sum_mse'] /$res_mul['count'] : 0;
?>

<div class="container-fluid pt-4 px-4 pb-5">

    <!-- HEADER & CONFIG -->
    <div class="card-custom mb-4 gradient-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h4 class="card-title text-primary mb-1 fw-bold"><i class="fas fa-calculator me-2"></i>Perhitungan Dekomposisi</h4>
                <p class="text-muted small mb-0"><strong>Metode:</strong> Aditif & Multiplikatif</p>
            </div>
            <div class="d-flex gap-2">
                <?php if(isset($_SESSION['is_simulasi']) && $_SESSION['is_simulasi']): ?>
                    <a href="proses_simulasi.php?reset=1" class="btn btn-danger btn-sm shadow-sm">
                        <i class="fas fa-trash-alt me-1"></i> Reset Data Asli
                    </a>
                <?php else: ?>
                    <button type="button" class="btn btn-warning btn-sm shadow-sm text-dark" data-bs-toggle="modal" data-bs-target="#modalSimulasi">
                        <i class="fas fa-flask me-1"></i> Simulasi Data
                    </button>
                <?php endif; ?>
                
                <form method="GET" class="d-flex bg-white p-2 rounded shadow-sm align-items-center gap-2">
                    <label class="fw-bold mb-0 text-dark small">MA Period:</label>
                    <input type="number" name="ma" value="<?= $ma_period ?>" min="2" max="24" class="form-control form-control-sm text-center border-primary" style="width: 70px; font-weight:bold;">
                    <button type="submit" class="btn btn-primary-custom btn-sm">Update</button>
                </form>
            </div>
        </div>
    </div>

    <!-- SIMULATION ALERT -->
    <?php if(isset($_SESSION['is_simulasi']) && $_SESSION['is_simulasi']): ?>
    <div class="alert alert-warning shadow-sm border-warning d-flex align-items-center mb-4" role="alert">
        <i class="fas fa-flask fa-2x me-3 text-warning"></i>
        <div>
            <h5 class="alert-heading fw-bold mb-1">Mode Simulasi Aktif</h5>
            <p class="mb-0 small">Anda sedang menggunakan <strong>Data Sementara</strong>. Perhitungan di bawah ini tidak disimpan ke database. Klik "Reset Data Asli" untuk kembali.</p>
        </div>
    </div>
    <?php endif; ?>

    <!-- Modal Simulasi -->
    <div class="modal fade" id="modalSimulasi" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title fw-bold"><i class="fas fa-flask me-2"></i>Upload Data Simulasi</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="proses_simulasi.php" method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Pilih File CSV</label>
                            <input type="file" name="file_simulasi" class="form-control" accept=".csv" required>
                            <div class="form-text">Format: Bulan (YYYY-MM), Penjualan</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="upload_simulasi" class="btn btn-warning">Gunakan Data Ini</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- TABS -->
    <ul class="nav nav-pills nav-fill mb-4 gap-3" id="pills-tab" role="tablist">
        <li class="nav-item"><button class="nav-link active rounded-pill shadow-sm fw-bold" data-bs-toggle="pill" data-bs-target="#step1">1. Trend (Regresi)</button></li>
        <li class="nav-item"><button class="nav-link rounded-pill shadow-sm fw-bold" data-bs-toggle="pill" data-bs-target="#step2">2. MA & CMA</button></li>
        <li class="nav-item"><button class="nav-link rounded-pill shadow-sm fw-bold" data-bs-toggle="pill" data-bs-target="#step3">3. Seasonal Index</button></li>
        <li class="nav-item"><button class="nav-link rounded-pill shadow-sm fw-bold" data-bs-toggle="pill" data-bs-target="#step4">4. Evaluasi</button></li>
    </ul>

    <div class="tab-content">
        <!-- STEP 1: REGRESI -->
        <div class="tab-pane fade show active" id="step1">
            <div class="card-custom">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="card-title fw-bold">Regresi Linear (Trend/CMAT)</h5>
                    <div class="alert alert-primary mb-0 py-1 px-3 small rounded-pill">
                        <i class="fas fa-function me-1"></i> Y = a + bX
                    </div>
                </div>
                
                <div class="row mb-4 text-center g-3">
                    <div class="col-6">
                        <div class="p-3 bg-white rounded shadow-sm border border-primary-subtle">
                            <span class="text-muted small text-uppercase fw-bold">Intercept (a)</span>
                            <h3 class="fw-bold text-primary mb-0"><?= number_format($a, 4) ?></h3>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 bg-white rounded shadow-sm border border-info-subtle">
                            <span class="text-muted small text-uppercase fw-bold">Slope (b)</span>
                            <h3 class="fw-bold text-info mb-0"><?= number_format($b, 4) ?></h3>
                        </div>
                    </div>
                </div>

                <div class="table-responsive table-scrollable" style="max-height: 500px; overflow-y: auto;">
                    <table class="table table-bordered text-center table-hover align-middle">
                        <thead class="table-dark sticky-top" style="z-index: 10;">
                            <tr><th>No</th><th>Bulan</th><th>Y</th><th>X</th><th>XY</th><th>X²</th><th class="bg-primary text-white">Trend (T)</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach($reg_data as $row): ?>
                            <tr>
                                <td><?= $row['x']+1 ?></td>
                                <td><?= $data[$row['x']]['bulan'] ?></td>
                                <td><?= number_format($row['y']) ?></td>
                                <td><?= $row['x'] ?></td>
                                <td><?= number_format($row['xy']) ?></td>
                                <td><?= number_format($row['x2']) ?></td>
                                <td class="fw-bold text-primary bg-light"><?= number_format($cmat_values[$row['x']], 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- STEP 2: MA -->
        <div class="tab-pane fade" id="step2">
            <div class="card-custom">
                 <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="card-title fw-bold">Moving Average (MA) & Centered MA</h5>
                    <span class="badge bg-warning text-dark"><i class="fas fa-info-circle me-1"></i> MA Period: <?= $ma_period ?></span>
                </div>
                <div class="table-responsive table-scrollable" style="max-height: 500px; overflow-y: auto;">
                    <table class="table table-bordered text-center table-hover align-middle">
                        <thead class="bg-primary text-white sticky-top" style="z-index: 10;">
                            <tr><th>Bulan</th><th>Y</th><th>MA(<?= $ma_period ?>)</th><th>CMA</th><th>Trend (CMAT)</th><th>CF (CMA/Trend)</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach($data as $i => $row): ?>
                            <tr>
                                <td><?= $row['bulan'] ?></td>
                                <td><?= number_format($row['penjualan']) ?></td>
                                <td><?= isset($ma_values[$i]) ? number_format($ma_values[$i], 2) : '<span class="text-muted">-</span>' ?></td>
                                <td class="fw-bold bg-light text-dark"><?= isset($cma_values[$i]) ? number_format($cma_values[$i], 2) : '<span class="text-muted">-</span>' ?></td>
                                <td><?= number_format($cmat_values[$i], 2) ?></td>
                                <td class="text-success fw-bold"><?= isset($cf_values[$i]) ? number_format($cf_values[$i], 5) : '<span class="text-muted">-</span>' ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- STEP 3: SI -->
        <div class="tab-pane fade" id="step3">
            <div class="card-custom">
                <h5 class="card-title fw-bold">MENGHITUNG NILAI SI</h5>
                <p class="text-muted small">Metode: CFA (Combined Factor Actual) -> Rasio -> SI</p>
                <div class="table-responsive">
                    <table class="table table-bordered text-center vertical-align-middle shadow-sm">
                        <thead class="bg-warning text-dark">
                            <tr>
                                <th class="align-middle fw-bold">Bulan</th>
                                <th class="align-middle fw-bold">CFA</th>
                                <th class="align-middle fw-bold">RASIO</th>
                                <th class="align-middle fw-bold">SI</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $bulanNames = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agt','Sep','Okt','Nov','Des'];
                            $tot_cfa = 0; $tot_rasio = 0; $tot_si = 0;
                            for($m=0; $m<12; $m++): 
                                $row = $cfa_data[$m];
                                $tot_cfa += $row['cfa'];
                                $tot_rasio += $row['ratio'];
                                $tot_si += $row['si'];
                            ?>
                            <tr>
                                <td class="fw-bold text-start ps-4"><?= $bulanNames[$m] ?></td>
                                <td><?= number_format($row['cfa'], 0, ',', '.') ?></td>
                                <td><?= number_format($row['ratio'], 6) ?></td>
                                <td class="fw-bold bg-light"><?= number_format($row['si'], 6) ?></td>
                            </tr>
                            <?php endfor; ?>
                            <tr class="fw-bold bg-light">
                                <td class="text-end pe-3">TOTAL</td>
                                <td><?= number_format($tot_cfa, 0, ',', '.') ?></td>
                                <td><?= number_format($tot_rasio, 0) ?></td>
                                <td><?= number_format($tot_si, 6) ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- STEP 4: EVAL -->
        <div class="tab-pane fade" id="step4">
            <div class="card-custom">
                <div class="row g-4 mb-4">
                    <div class="col-md-6">
                        <div class="p-3 border rounded shadow-sm bg-white border-start border-4 border-success h-100">
                            <h6 class="fw-bold text-success mb-3"><i class="fas fa-plus-circle me-2"></i>Model Aditif</h6>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-muted">MAPE (Error %)</span>
                                <strong class="fs-4"><?= number_format($res_add['mape'], 2) ?>%</strong>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-muted">MSE (Squared)</span>
                                <strong class="fs-6"><?= number_format($res_add['mse'], 0) ?></strong>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 border rounded shadow-sm bg-white border-start border-4 border-primary h-100">
                            <h6 class="fw-bold text-primary mb-3"><i class="fas fa-times-circle me-2"></i>Model Multiplikatif</h6>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-muted">MAPE (Error %)</span>
                                <strong class="fs-4"><?= number_format($res_mul['mape'], 2) ?>%</strong>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-muted">MSE (Squared)</span>
                                <strong class="fs-6"><?= number_format($res_mul['mse'], 0) ?></strong>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="alert alert-light border shadow-sm small mb-3">
                    <i class="fas fa-lightbulb text-warning me-2"></i>
                    <strong>Analisis:</strong> Semakin kecil nilai MAPE, semakin akurat model tersebut. Dari hasil di atas, perhatikan model mana yang memiliki persentase error lebih rendah.
                </div>

                <div class="table-responsive table-scrollable" style="max-height: 500px; overflow-y: auto;">
                    <table class="table table-sm text-center table-hover table-striped align-middle">
                        <thead class="table-dark sticky-top" style="z-index: 10;">
                            <tr>
                                <th rowspan="2" class="align-middle">Bulan</th>
                                <th rowspan="2" class="align-middle">Aktual (Y)</th>
                                <th colspan="3" class="bg-success text-white">Aditif</th>
                                <th colspan="3" class="bg-primary text-white">Multiplikatif</th>
                            </tr>
                            <tr>
                                <th class="text-white-50 small">Forecast</th>
                                <th class="text-white-50 small">Error</th>
                                <th class="text-white small">MAPE (%)</th>
                                <th class="text-white-50 small">Forecast</th>
                                <th class="text-white-50 small">Error</th>
                                <th class="text-white small">MAPE (%)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($data as $i => $row): ?>
                            <tr>
                                <td><?= $row['bulan'] ?></td>
                                <td><?= number_format($row['penjualan']) ?></td>
                                
                                <!-- Additive -->
                                <td class="fw-bold text-success"><?= ($res_add['forecast'][$i]!==null) ? number_format($res_add['forecast'][$i], 2) : '-' ?></td>
                                <td><?= ($res_add['error'][$i]!==null) ? number_format($res_add['error'][$i], 2) : '-' ?></td>
                                <td><?= ($res_add['error'][$i]!==null && $row['penjualan']!=0) ? number_format(abs($res_add['error'][$i]/$row['penjualan'])*100, 2).'%' : '-' ?></td>
                                
                                <!-- Multiplicative -->
                                <td class="fw-bold text-primary"><?= ($res_mul['forecast'][$i]!==null) ? number_format($res_mul['forecast'][$i], 2) : '-' ?></td>
                                <td><?= ($res_mul['error'][$i]!==null) ? number_format($res_mul['error'][$i], 2) : '-' ?></td>
                                <td><?= ($res_mul['error'][$i]!==null && $row['penjualan']!=0) ? number_format(abs($res_mul['error'][$i]/$row['penjualan'])*100, 2).'%' : '-' ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<style>
/* Custom Scrollbar for Tables */
.table-scrollable::-webkit-scrollbar { width: 8px; height: 8px; }
.table-scrollable::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 4px; }
.table-scrollable::-webkit-scrollbar-thumb { background: #c1c1c1; border-radius: 4px; }
.table-scrollable::-webkit-scrollbar-thumb:hover { background: #a8a8a8; }
</style>
<?php include 'footer.php'; ?>

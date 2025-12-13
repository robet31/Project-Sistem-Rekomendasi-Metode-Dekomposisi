<?php
/**
 * Calculates Decomposition Method Steps (Matches Custom User Excel)
 * 
 * @param array $data Array of rows ['penjualan', 'bulan']
 * @param int $ma_period Moving Average Period
 * @return array Calculated values and metrics
 */
function calculate_decomposition($data, $ma_period) {
    $n = count($data);

    // ============================================
    // STEP 1: CMAT (TREND ESTIMATION)
    // Formula: CMAT = a + b * x
    // ============================================
    $sumX = 0; $sumY = 0; $sumXY = 0; $sumX2 = 0;
    $reg_data = [];

    for($i=0; $i<$n; $i++){
        $x = $i; 
        $y = $data[$i]['penjualan'];
        
        $xy = $x * $y;
        $x2 = $x * $x;
        
        $sumX += $x;
        $sumY += $y;
        $sumXY += $xy;
        $sumX2 += $x2;
        
        $reg_data[] = [
            'x' => $x,
            'y' => $y,
            'x2' => $x2,
            'xy' => $xy
        ];
    }

    $a_trend = 0; $b_trend = 0;
    $denom = ($n * $sumX2) - ($sumX**2);
    if($denom != 0){
        $b_trend = (($n * $sumXY) - ($sumX * $sumY)) / $denom;
        $a_trend = ($sumY - ($b_trend * $sumX)) / $n;
    }

    $cmat_values = [];
    for($i=0; $i<$n; $i++){
        $cmat_values[$i] = $a_trend + ($b_trend * $i);
    }

    // ============================================
    // STEP 2: MA, CMA, CF
    // MA Starts at Index = Period (Trailing)
    // CMA Starts at Index = Period + 1 (Centered Gap) for Even
    // ============================================
    $ma_values = array_fill(0, $n, null);
    $cma_values = array_fill(0, $n, null);
    $cf_values = array_fill(0, $n, null);
    $is_even = ($ma_period % 2 == 0);
    $start_calc_index = $is_even ? $ma_period + 1 : $ma_period;

    // 1. Calculate MA (Avg of prev L)
    for ($i = $ma_period; $i < $n; $i++) {
        $sum = 0;
        for ($k = 1; $k <= $ma_period; $k++) {
            $sum += $data[$i - $k]['penjualan'];
        }
        $ma_values[$i] = $sum / $ma_period;
    }

    // 2. Calculate CMA & CF
    if ($is_even) {
        for ($i = $start_calc_index; $i < $n; $i++) {
            if (isset($ma_values[$i-1]) && isset($ma_values[$i])) {
                $cma = ($ma_values[$i-1] + $ma_values[$i]) / 2;
                $cma_values[$i] = $cma;
                
                $cmat = $cmat_values[$i];
                if ($cmat != 0) $cf_values[$i] = $cma / $cmat;
            }
        }
    } else {
        for ($i = $start_calc_index; $i < $n; $i++) {
            $cma = $ma_values[$i];
            $cma_values[$i] = $cma;
            
            $cmat = $cmat_values[$i];
            if($cmat != 0) $cf_values[$i] = $cma / $cmat;
        }
    }

    // ============================================
    // STEP 3: SI (TOTALS METHOD)
    // ============================================
    $month_totals = array_fill(0, 12, 0);
    $grand_total_sales = 0;
    foreach($data as $row){
        $m_idx = (int)explode('-', $row['bulan'])[1] - 1;
        $month_totals[$m_idx] += $row['penjualan'];
        $grand_total_sales += $row['penjualan'];
    }

    $si_final = [];
    $raw_ratios = []; // For debugging/display
    for($m=0; $m<12; $m++){
        $cfa = $month_totals[$m];
        $ratio = ($grand_total_sales != 0) ? ($cfa / $grand_total_sales) : 0;
        $raw_ratios[$m] = $ratio;
        $si_final[$m] = $ratio * 12;
    }

    // ============================================
    // STEP 4: RESULT (FORECAST & ERROR)
    // Forecast = CMAT (Trend)
    // Error = Actual - Forecast
    // ============================================
    $forecasts = [];
    $errors = [];
    $mape_sum = 0;
    $mse_sum = 0;
    $err_count = 0;

    for($i=0; $i<$n; $i++){
        $F = null;
        $E = null;
        
        if($i >= $start_calc_index) {
            $F = $cmat_values[$i]; 
            
            $act = $data[$i]['penjualan'];
            $E = $act - $F;
            $E2 = $E * $E;
            $PE = ($act != 0) ? abs($E / $act) : 0;
            
            $mape_sum += $PE;
            $mse_sum += $E2;
            $err_count++;
        }
        
        $forecasts[$i] = $F;
        $errors[$i] = $E;
    }

    $mape = ($err_count > 0) ? ($mape_sum / $err_count) : 0;
    $mse = ($err_count > 0) ? ($mse_sum / $err_count) : 0;

    return [
        'reg_data' => $reg_data,
        'a_trend' => $a_trend,
        'b_trend' => $b_trend,
        'cmat_values' => $cmat_values,
        'ma_values' => $ma_values,
        'cma_values' => $cma_values,
        'cf_values' => $cf_values,
        'month_totals' => $month_totals,
        'grand_total_sales' => $grand_total_sales,
        'raw_ratios' => $raw_ratios,
        'si_final' => $si_final,
        'forecasts' => $forecasts,
        'errors' => $errors,
        'mape' => $mape,
        'mse' => $mse,
        'ma_period' => $ma_period,
        'start_calc_index' => $start_calc_index
    ];
}
?>

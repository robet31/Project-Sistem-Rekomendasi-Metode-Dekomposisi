<?php
session_start();

if(isset($_POST['upload_simulasi'])){
    $file = $_FILES['file_simulasi']['tmp_name'];
    
    if(empty($file)){
        echo "<script>alert('Pilih file CSV terlebih dahulu!'); window.location='perhitungan.php';</script>";
        exit;
    }

    $handle = fopen($file, "r");
    $data_simulasi = [];
    $row = 0;

    while(($data = fgetcsv($handle, 1000, ",")) !== FALSE){
        $row++;
        // Skip header if deemed header (checking if first col is not date-like)
        if($row == 1 && !preg_match('/^\d{4}-\d{2}$/', $data[0]) && !is_numeric($data[1])){
            continue; 
        }

        // Format: Col 0 = Bulan (YYYY-MM), Col 1 = Penjualan
        $bulan = $data[0];
        $penjualan = (float)$data[1];

        if(!empty($bulan)){
            $data_simulasi[] = [
                'bulan' => $bulan,
                'penjualan' => $penjualan
            ];
        }
    }
    fclose($handle);

    // Sort by Date to be safe
    usort($data_simulasi, function($a, $b) {
        return strtotime($a['bulan']) - strtotime($b['bulan']);
    });

    $_SESSION['simulasi_data'] = $data_simulasi;
    $_SESSION['is_simulasi'] = true;

    echo "<script>alert('Data Simulasi Berhasil Diupload! Mode Simulasi Aktif.'); window.location='perhitungan.php';</script>";
    exit;
}

if(isset($_GET['reset'])){
    unset($_SESSION['simulasi_data']);
    unset($_SESSION['is_simulasi']);
    header("Location: perhitungan.php");
    exit;
}
?>

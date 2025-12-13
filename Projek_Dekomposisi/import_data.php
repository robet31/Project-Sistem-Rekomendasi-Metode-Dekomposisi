<?php
session_start();
include 'koneksi.php';

if (isset($_POST['upload'])) {
    $file = $_FILES['file']['tmp_name'];
    $fileName = $_FILES['file']['name'];
    $fileType = pathinfo($fileName, PATHINFO_EXTENSION);

    // Validate file type
    if ($fileType !== 'csv') {
        echo "<script>alert('Format file harus CSV!'); window.location='data.php';</script>";
        exit;
    }

    $handle = fopen($file, "r");
    $c = 0;
    $success = 0;
    
    // Skip header if exists (optional, assuming first row is header)
    // fgetcsv($handle); 

    while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
        $c++;
        if ($c == 1 && !is_numeric($row[1])) { continue; } // Skip header row logic if col 2 is not number

        // Format CSV expectation: Column 1 = YYYY-MM (or appropriate date), Column 2 = Sales Amount
        $bulan = $row[0];
        $penjualan = $row[1];

        // Simple validation
        if (!empty($bulan) && is_numeric($penjualan)) {
            // Check if exists
            $check = mysqli_query($conn, "SELECT id FROM penjualan_toyota WHERE bulan = '$bulan'");
            if (mysqli_num_rows($check) > 0) {
                // Update
                $q = "UPDATE penjualan_toyota SET penjualan = '$penjualan' WHERE bulan = '$bulan'";
            } else {
                // Insert
                $q = "INSERT INTO penjualan_toyota (bulan, penjualan) VALUES ('$bulan', '$penjualan')";
            }
            if (mysqli_query($conn, $q)) {
                $success++;
            }
        }
    }
    fclose($handle);

    echo "<script>alert('Berhasil mengimpor $success data!'); window.location='data.php';</script>";
}
?>

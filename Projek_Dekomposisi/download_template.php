<?php
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="template_data_penjualan.csv"');

// Create a file pointer connected to the output stream
$output = fopen('php://output', 'w');

// Output the column headings
fputcsv($output, array('Bulan (YYYY-MM)', 'Penjualan'));

// Output sample data
fputcsv($output, array('2011-01', '27619'));
fputcsv($output, array('2011-02', '25532'));
fputcsv($output, array('2011-03', '32275'));

fclose($output);
exit;
?>

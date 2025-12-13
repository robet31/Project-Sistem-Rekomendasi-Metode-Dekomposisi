<?php
session_start();
include 'koneksi.php';

if(isset($_GET['id'])){
    $id = $_GET['id'];
    $q = mysqli_query($conn, "DELETE FROM penjualan_toyota WHERE id = '$id'");
    
    if($q){
        echo "<script>alert('Data berhasil dihapus!'); window.location='data.php';</script>";
    } else {
        echo "<script>alert('Gagal menghapus data!'); window.location='data.php';</script>";
    }
} else {
    header("Location: data.php");
}
?>

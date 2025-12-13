php
<?php
// Cek apakah berjalan di Environment Cloud (Vercel) atau Local
$servername = getenv('DB_HOST') ? getenv('DB_HOST') : "localhost";
$username = getenv('DB_USER') ? getenv('DB_USER') : "root";
$password = getenv('DB_PASS') ? getenv('DB_PASS') : "";
$dbname = getenv('DB_NAME') ? getenv('DB_NAME') : "dekompo";
$port = getenv('DB_PORT') ? getenv('DB_PORT') : 3307; // Default Laragon Port

// Koneksi ke database
$conn = new mysqli($servername, $username, $password, $dbname, $port);

// Cek koneksi
if ($conn->connect_error) {
    die("Koneksi gagal (" . $conn->connect_errno . "): " . $conn->connect_error);
}
?>
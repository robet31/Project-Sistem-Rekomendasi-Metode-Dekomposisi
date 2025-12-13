<!-- Sidebar -->
<div class="sidebar">
    <div class="sidebar-header">
        <h4><i class="fas fa-chart-line me-2"></i>FORECAST</h4>
    </div>
    <nav class="nav flex-column">
        <a href="index.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>">
            <i class="fas fa-home"></i> <span>Dashboard</span>
        </a>
        <a href="data.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'data.php' ? 'active' : '' ?>">
            <i class="fas fa-database"></i> <span>Data Penjualan</span>
        </a>
        <a href="perhitungan.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'perhitungan.php' ? 'active' : '' ?>">
            <i class="fas fa-calculator"></i> <span>Perhitungan</span>
        </a>
        <a href="hasil.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'hasil.php' ? 'active' : '' ?>">
            <i class="fas fa-file-alt"></i> <span>Hasil Forecast</span>
        </a>

    </nav>
</div>

<!-- Main Content Wrapper Start -->
<div class="content">
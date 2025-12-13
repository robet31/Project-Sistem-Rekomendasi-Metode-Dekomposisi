<?php
session_start();
include 'header.php';
include 'sidebar.php';
include 'koneksi.php';

// ================= PAGINATION =================
$limit = 12; // 12 months view per page
$page  = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page  = ($page < 1) ? 1 : $page;
$start = ($page - 1) * $limit;

// Stats
$totalRow = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as tot, SUM(penjualan) as sales FROM penjualan_toyota"));
$totalData = $totalRow['tot'];
$totalSales = $totalRow['sales'];
$totalPage  = ceil($totalData / $limit);

// Data Query
$query = mysqli_query($conn, "SELECT * FROM penjualan_toyota ORDER BY bulan ASC LIMIT $start, $limit");

// Helper Indo Month
$bulanIndo = [
    '01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April',
    '05'=>'Mei','06'=>'Juni','07'=>'Juli','08'=>'Agustus',
    '09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember'
];
?>

<div class="container-fluid pt-4 px-4">
    <!-- Header Stats -->
    <div class="row g-4 mb-4">
        <div class="col-sm-6 col-xl-6">
            <div class="card-custom stat-card">
                <div>
                    <h6 class="text-muted mb-2">Total Data</h6>
                    <h3 class="fw-bold mb-0 text-primary"><?= number_format($totalData) ?> <small class="fs-6 text-muted">Bulan</small></h3>
                </div>
                <div class="stat-icon bg-primary-soft">
                    <i class="fas fa-database"></i>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-6">
            <div class="card-custom stat-card">
                <div>
                    <h6 class="text-muted mb-2">Total Penjualan</h6>
                    <h3 class="fw-bold mb-0 text-success"><?= number_format($totalSales) ?> <small class="fs-6 text-muted">Unit</small></h3>
                </div>
                <div class="stat-icon bg-success-soft">
                    <i class="fas fa-chart-pie"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- MAIN DATA CARD -->
    <div class="card-custom">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h5 class="card-title mb-0">Data Penjualan Bulanan</h5>
                <p class="text-muted small mb-0">Kelola data historis penjualan untuk forecasting. Gunakan CSV untuk upload massal.</p>
            </div>
            <div>
                <a href="download_template.php" class="btn btn-outline-success me-2">
                    <i class="fas fa-download me-2"></i>Template CSV
                </a>
                <button type="button" class="btn btn-primary-custom" data-bs-toggle="modal" data-bs-target="#uploadModal">
                    <i class="fas fa-file-upload me-2"></i>Import Data
                </button>
                <a href="tambah.php" class="btn btn-outline-primary ms-2">
                    <i class="fas fa-plus"></i> Tambah
                </a>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-custom table-hover">
                <thead>
                    <tr>
                        <th width="10%" class="text-center">No</th>
                        <th>Periode Bulan</th>
                        <th class="text-end">Jumlah Penjualan</th>
                        <th width="15%" class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                if(mysqli_num_rows($query) > 0){
                    $no = $start + 1;
                    while ($row = mysqli_fetch_assoc($query)) {
                        [$tahun, $bulan] = explode('-', $row['bulan']);
                        $bulanTampil = (isset($bulanIndo[$bulan]) ? $bulanIndo[$bulan] : $bulan) . ' ' . $tahun;
                ?>
                    <tr>
                        <td class="text-center fw-bold text-muted"><?= $no++; ?></td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="bg-light rounded p-2 me-3 text-primary">
                                    <i class="fas fa-calendar-day"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0 fw-semibold"><?= $bulanTampil; ?></h6>
                                    <small class="text-muted"><?= $row['bulan']; ?></small>
                                </div>
                            </div>
                        </td>
                        <td class="text-end fw-bold fs-5 text-dark">
                            <?= number_format($row['penjualan']); ?>
                            <small class="text-muted fs-6 ms-1">unit</small>
                        </td>
                        <td class="text-center">
                            <div class="btn-group" role="group">
                                <a href="edit.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="hapus.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-danger" title="Hapus" onclick="return confirm('Yakin ingin menghapus data ini?')">
                                    <i class="fas fa-trash-alt"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php 
                    } 
                } else {
                    echo "<tr><td colspan='4' class='text-center py-5 text-muted'>
                        <img src='https://cdn-icons-png.flaticon.com/512/7486/7486776.png' width='60' class='mb-3 opacity-50'><br>
                        Belum ada data penjualan.<br>Silakan upload CSV atau tambah data secara manual.</td></tr>";
                }
                ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if($totalPage > 1): ?>
        <nav aria-label="Page navigation" class="mt-4">
            <ul class="pagination justify-content-center">
                <?php for ($i = 1; $i <= $totalPage; $i++) { ?>
                    <li class="page-item <?= ($page == $i) ? 'active' : ''; ?>">
                        <a class="page-link shadow-none border-0 rounded-circle mx-1" href="data.php?page=<?= $i; ?>">
                            <?= $i; ?>
                        </a>
                    </li>
                <?php } ?>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
</div>

<!-- Upload Modal -->
<div class="modal fade" id="uploadModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-primary">Import Data CSV</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 pt-2">
                <form action="import_data.php" method="POST" enctype="multipart/form-data">
                    <div class="text-center mb-4 p-4 bg-light rounded-3 border border-dashed">
                        <i class="fas fa-cloud-upload-alt text-primary mb-3" style="font-size: 3rem;"></i>
                        <h6 class="fw-bold">Upload File CSV</h6>
                        <p class="text-muted small mb-0">Pastikan format kolom sesuai template:<br><code>Bulan (YYYY-MM), Penjualan</code></p>
                    </div>

                    <div class="alert alert-info d-flex align-items-center small mb-3">
                        <i class="fas fa-info-circle me-2 fs-5"></i>
                        <div>
                            Jika data bulan sudah ada, sistem akan <strong>memperbarui</strong> nilai penjualan.
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="file" class="form-label fw-semibold">Pilih File</label>
                        <input class="form-control form-control-lg" type="file" id="file" name="file" accept=".csv" required>
                    </div>
                    <div class="d-grid gap-2">
                        <button type="submit" name="upload" class="btn btn-primary-custom btn-lg shadow-sm">
                            <i class="fas fa-upload me-2"></i> Proses Import
                        </button>
                        <a href="download_template.php" class="btn btn-light text-muted">
                            <i class="fas fa-download me-2"></i> Download Template CSV
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>

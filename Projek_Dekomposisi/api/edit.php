<?php
session_start();
include 'koneksi.php';
include 'header.php';
include 'sidebar.php';

$id = $_GET['id'] ?? null;
if (!$id) {
    echo "<script>alert('ID tidak ditemukan!'); window.location='data.php';</script>";
    exit;
}

// AMBIL DATA
$q = mysqli_query($conn, "SELECT * FROM penjualan_toyota WHERE id = '$id'");
$data = mysqli_fetch_assoc($q);
if (!$data) {
    echo "<script>alert('Data tidak ditemukan!'); window.location='data.php';</script>";
    exit;
}

$bulanData = $data['bulan']; // YYYY-MM
$tahunVal = explode('-', $bulanData)[0];
$bulanVal = explode('-', $bulanData)[1];
$penjualanVal = $data['penjualan'];

$pesan = "";

// UPDATE DATA
if (isset($_POST['update'])) {
    $bulan_baru = $_POST['tahun'] . '-' . $_POST['bulan'];
    $penjualan_baru = (int) $_POST['penjualan'];
    
    // Cek duplikasi jika bulan berubah
    $cek = mysqli_query($conn, "SELECT id FROM penjualan_toyota WHERE bulan='$bulan_baru' AND id != '$id'");
    if (mysqli_num_rows($cek) > 0) {
        $pesan = "<div class='alert alert-danger'>❌ Gagal! Data bulan <b>$bulan_baru</b> sudah ada lain.</div>";
    } else {
        mysqli_query($conn, "UPDATE penjualan_toyota SET bulan='$bulan_baru', penjualan='$penjualan_baru' WHERE id='$id'");
        echo "<script>alert('Data Berhasil Diupdate!'); window.location='data.php';</script>";
        exit;
    }
}
?>

<div class="container-fluid pt-4 px-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card-custom">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="card-title text-primary fw-bold"><i class="fas fa-edit me-2"></i>Edit Data Penjualan</h4>
                </div>

                <?= $pesan ?>

                <form method="POST">
                    <div class="row">
                        <!-- BULAN -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Bulan</label>
                            <select name="bulan" class="form-select" required>
                                <?php
                                $bulanIndo = [
                                    '01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April',
                                    '05'=>'Mei','06'=>'Juni','07'=>'Juli','08'=>'Agustus',
                                    '09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember'
                                ];
                                foreach ($bulanIndo as $key => $val) {
                                    $sel = ($key == $bulanVal) ? 'selected' : '';
                                    echo "<option value='$key' $sel>$val</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <!-- TAHUN -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Tahun</label>
                            <input type="number" name="tahun" class="form-control" value="<?= $tahunVal ?>" min="2000" max="2100" required>
                        </div>
                    </div>

                    <!-- PENJUALAN -->
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Jumlah Penjualan Unit</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="fas fa-car"></i></span>
                            <input type="number" name="penjualan" class="form-control" value="<?= $penjualanVal ?>" required>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="data.php" class="btn btn-outline-secondary">Batal</a>
                        <button type="submit" name="update" class="btn btn-primary-custom">
                            <i class="fas fa-save me-2"></i>Update Data
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>

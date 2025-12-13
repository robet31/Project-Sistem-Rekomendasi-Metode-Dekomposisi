<?php
session_start();
include 'koneksi.php';
include 'header.php';
include 'sidebar.php';

/* ================= HITUNG TOTAL DATA ================= */
$qTotal = mysqli_query($conn, "SELECT COUNT(*) AS total FROM penjualan_toyota");
$totalData = mysqli_fetch_assoc($qTotal)['total'];

/* ================= AMBIL BULAN TERAKHIR ================= */
$qLast = mysqli_query($conn, "
    SELECT bulan 
    FROM penjualan_toyota 
    ORDER BY bulan DESC 
    LIMIT 1
");
$lastRow = mysqli_fetch_assoc($qLast);
$bulanTerakhir = $lastRow['bulan'] ?? null;

$pesan = "";
$warning = "";

/* ================= SIMPAN DATA ================= */
if (isset($_POST['simpan'])) {

    // 🔐 GABUNG TAHUN + BULAN → YYYY-MM
    $bulan = $_POST['tahun'] . '-' . $_POST['bulan'];
    $penjualan = (int) $_POST['penjualan'];

    /* 1️⃣ CEK DUPLIKASI BULAN */
    $cek = mysqli_query($conn, "SELECT id FROM penjualan_toyota WHERE bulan='$bulan'");
    if (mysqli_num_rows($cek) > 0) {

        $pesan = "
        <div class='alert alert-danger'>
            ❌ Data bulan <b>$bulan</b> sudah ada.
        </div>";

    } else {

        /* 2️⃣ CEK URUTAN BULAN (TIDAK LOMPAT) */
        if ($bulanTerakhir) {
            $bulanSeharusnya = date(
                'Y-m',
                strtotime($bulanTerakhir . '-01 +1 month')
            );

            if ($bulan != $bulanSeharusnya) {
                $warning .= "
                <div class='alert alert-warning'>
                    ⚠️ <b>Peringatan Urutan Waktu</b><br>
                    Data terakhir: <b>$bulanTerakhir</b><br>
                    Bulan berikutnya seharusnya: <b>$bulanSeharusnya</b>
                </div>";
            }
        }

        /* 3️⃣ SIMPAN DATA */
        mysqli_query($conn, "
            INSERT INTO penjualan_toyota (bulan, penjualan)
            VALUES ('$bulan', '$penjualan')
        ");

        $totalData++;

        /* 4️⃣ PERINGATAN SIKLUS 12 BULAN */
        if ($totalData % 12 != 0) {
            $warning .= "
            <div class='alert alert-warning'>
                ⚠️ <b>Peringatan Akademis</b><br>
                Total data sekarang <b>$totalData</b> bulan.<br>
                Disarankan kelipatan <b>12 bulan</b>.
            </div>";
        }

        $pesan = "
        <div class='alert alert-success'>
            ✅ Data bulan <b>$bulan</b> berhasil ditambahkan.
        </div>";
    }
}
?>

<div class="container-fluid pt-4 px-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card-custom">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="card-title"><i class="fas fa-plus-circle text-primary me-2"></i>Tambah Data Penjualan</h4>
                </div>

                <?= $pesan ?>
                <?= $warning ?>

                <form method="POST">
                    <div class="row">
                        <!-- BULAN -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Bulan</label>
                            <select name="bulan" class="form-select" required>
                                <option value="">-- Pilih Bulan --</option>
                                <?php
                                $bulanIndo = [
                                    '01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April',
                                    '05'=>'Mei','06'=>'Juni','07'=>'Juli','08'=>'Agustus',
                                    '09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember'
                                ];
                                foreach ($bulanIndo as $key => $val) {
                                    echo "<option value='$key'>$val</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <!-- TAHUN -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Tahun</label>
                            <input type="number" name="tahun" class="form-control" value="<?= date('Y') ?>" min="2000" max="2100" required>
                        </div>
                    </div>

                    <!-- PENJUALAN -->
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Jumlah Penjualan Unit</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="fas fa-car"></i></span>
                            <input type="number" name="penjualan" class="form-control" placeholder="Masukkan jumlah penjualan..." required>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="data.php" class="btn btn-outline-secondary">Batal</a>
                        <button type="submit" name="simpan" class="btn btn-primary-custom">
                            <i class="fas fa-save me-2"></i>Simpan Data
                        </button>
                    </div>
                </form>

                <hr class="my-4">

                <div class="alert alert-info border-0 bg-info-soft mb-0">
                    <div class="d-flex">
                        <div class="me-3 text-info fs-4"><i class="fas fa-info-circle"></i></div>
                        <div>
                            <h6 class="fw-bold text-info">Informasi Data</h6>
                            <p class="mb-0 text-muted small">Data terakhir tercatat pada bulan <b><?= $bulanTerakhir ?? '-' ?></b>. Total data saat ini: <b><?= $totalData ?></b> bulan.</p>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>

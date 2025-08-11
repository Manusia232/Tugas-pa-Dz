<?php
session_start();
include('connect.php'); // Menyertakan file koneksi ke database

// Ambil data barang dari database
$sql = "SELECT * FROM barang";
$result = $conn->query($sql);
$barang = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $barang[] = $row;
    }
}

$search_query = ''; // Default pencarian kosong
$preview_struk = false; // Variabel untuk menampilkan preview struk

// Proses pencarian
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Pencarian berdasarkan nama atau ID
    if (isset($_POST['search_query'])) {
        $search_query = trim($_POST['search_query']);
        $sql = "SELECT * FROM barang WHERE nama LIKE '%$search_query%' OR id LIKE '%$search_query%'";
        $result = $conn->query($sql);
        $barang = [];
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $barang[] = $row;
            }
        }
    }

    // Menambah barang ke keranjang
    if (isset($_POST['add_to_cart'])) {
        $id_barang = $_POST['id_barang'];
        $jumlah = $_POST['jumlah'];

        // Cari barang berdasarkan ID di database
        $sql = "SELECT * FROM barang WHERE id = $id_barang";
        $result = $conn->query($sql);
        $barang_terpilih = $result->fetch_assoc();

        if ($barang_terpilih) {
            // Cek apakah barang sudah ada di keranjang
            $found = false;
            foreach ($_SESSION['keranjang'] as &$item) {
                if ($item['id'] == $barang_terpilih['id']) {
                    // Jika barang sudah ada, update jumlahnya
                    $item['jumlah'] += $jumlah;
                    $item['total'] = $item['harga'] * $item['jumlah'];
                    $found = true;
                    break;
                }
            }
            // Jika barang belum ada di keranjang, tambahkan barang baru
            if (!$found) {
                $_SESSION['keranjang'][] = [
                    'id' => $barang_terpilih['id'],
                    'nama' => $barang_terpilih['nama'],
                    'harga' => $barang_terpilih['harga'],
                    'jumlah' => $jumlah,
                    'total' => $barang_terpilih['harga'] * $jumlah
                ];
            }
        }
    }

    // Update jumlah barang di keranjang
    if (isset($_POST['update_cart'])) {
        $update_value = $_POST['update_cart']; // Mengambil nilai tombol (+ atau -)
        $id_barang = $_POST['id_barang'];
        $jumlah_baru = $_POST['jumlah'];

        // Jika tombol 'decrease', kita kurangi jumlah
        if (strpos($update_value, 'decrease') !== false) {
            $jumlah_baru = max(1, $jumlah_baru - 1); // Jangan sampai jumlah jadi 0
        }
        
        // Jika tombol 'increase', kita tambah jumlah
        if (strpos($update_value, 'increase') !== false) {
            $jumlah_baru++;
        }

        // Update jumlah barang di keranjang
        foreach ($_SESSION['keranjang'] as &$item) {
            if ($item['id'] == $id_barang) {
                $item['jumlah'] = $jumlah_baru;
                $item['total'] = $item['harga'] * $jumlah_baru;
                break;
            }
        }
    }

    // Fitur menghitung total dan menampilkan struk
    if (isset($_POST['hitung'])) {
        $total_belanja = 0;
        foreach ($_SESSION['keranjang'] as $k) {
            $total_belanja += $k['total'];
        }
        // Diskon 10% jika lebih dari 50.000
        $diskon = 0;
        if ($total_belanja > 50000) {
            $diskon = 0.1 * $total_belanja;
        }
        $total_setelah_diskon = $total_belanja - $diskon;

        // Menyimpan total dan diskon di session
        $_SESSION['total_belanja'] = $total_belanja;
        $_SESSION['diskon'] = $diskon;
        $_SESSION['total_setelah_diskon'] = $total_setelah_diskon;

        // Arahkan langsung ke preview struk
        header("Location: ?preview_struk=true");
        exit();
    }

    // Hapus item dari keranjang
    if (isset($_POST['remove_item'])) {
        $id_barang = $_POST['remove_item'];
        foreach ($_SESSION['keranjang'] as $key => $item) {
            if ($item['id'] == $id_barang) {
                unset($_SESSION['keranjang'][$key]);
                $_SESSION['keranjang'] = array_values($_SESSION['keranjang']); // Reindex array setelah dihapus
                break;
            }
        }
    }

    // Tombol back (reset keranjang) di preview struk
    if (isset($_POST['back'])) {
        $_SESSION['keranjang'] = []; // Hapus semua item dari keranjang
        header("Location: index.php"); // Kembali ke halaman kasir
        exit();
    }
}

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mesin Kasir Sederhana</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        h1 { text-align: center; }
        .barang-list { display: flex; flex-wrap: wrap; gap: 20px; justify-content: center; }
        .barang-item { border: 1px solid #ddd; padding: 10px; width: 200px; text-align: center; }
        .barang-item img { width: 100px; height: 100px; object-fit: cover; }
        .keranjang { margin-top: 30px; }
        .keranjang table { width: 100%; border-collapse: collapse; }
        .keranjang table, .keranjang th, .keranjang td { border: 1px solid #ddd; }
        .keranjang th, .keranjang td { padding: 10px; text-align: center; }
        .keranjang .jumlah-btn { cursor: pointer; padding: 0 10px; font-size: 20px; }
        .struk { max-width: 400px; margin: auto; padding: 20px; border: 1px solid #ddd; }
        .struk h2 { text-align: center; }
        .struk table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .struk th, .struk td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        .struk .total { font-weight: bold; font-size: 18px; }
    </style>
</head>
<body>

<?php if (isset($_GET['preview_struk']) && $_GET['preview_struk'] == 'true'): ?>
    <!-- Preview Struk -->
    <div class="struk">
        <h2>Struk Pembayaran</h2>
        <table>
            <tr>
                <th>Nama Barang</th>
                <th>Jumlah</th>
                <th>Total</th>
            </tr>
            <?php foreach ($_SESSION['keranjang'] as $k): ?>
                <tr>
                    <td><?= $k['nama'] ?></td>
                    <td><?= $k['jumlah'] ?></td>
                    <td>Rp <?= number_format($k['total'], 0, ',', '.') ?></td>
                </tr>
            <?php endforeach; ?>
        </table>

        <table>
            <tr>
                <td>Subtotal</td>
                <td>Rp <?= number_format($_SESSION['total_belanja'], 0, ',', '.') ?></td>
            </tr>
            <tr>
                <td>Diskon (10%)</td>
                <td>Rp <?= number_format($_SESSION['diskon'], 0, ',', '.') ?></td>
            </tr>
            <tr class="total">
                <td>Total</td>
                <td>Rp <?= number_format($_SESSION['total_setelah_diskon'], 0, ',', '.') ?></td>
            </tr>
        </table>

        <button onclick="window.print()">Cetak Struk</button>
        <form method="POST" style="margin-top: 20px;">
            <button type="submit" name="back">Kembali ke Kasir</button>
        </form>
    </div>

<?php else: ?>
    <!-- Halaman Kasir -->
    <h1>Kasir Sederhana</h1>

    <!-- Pencarian Barang -->
    <form method="POST">
        <input type="text" name="search_query" placeholder="Cari barang berdasarkan nama atau ID..." value="<?= $search_query ?>">
        <button type="submit">Cari</button>
    </form>

    <!-- Daftar Barang -->
    <div class="barang-list">
        <?php foreach ($barang as $b): ?>
            <?php
            // Filter berdasarkan pencarian ID atau nama
            if (!empty($search_query) && (strpos(strtolower($b['nama']), strtolower($search_query)) === false && strpos($b['id'], $search_query) === false)) {
                continue;
            }
            ?>
            <div class="barang-item">
                <img src="images/<?= $b['gambar'] ?>" alt="<?= $b['nama'] ?>">
                <h4><?= $b['nama'] ?></h4>
                <p>Rp <?= number_format($b['harga'], 0, ',', '.') ?></p>
                <form method="POST">
                    <input type="number" name="jumlah" min="1" value="1" required>
                    <input type="hidden" name="id_barang" value="<?= $b['id'] ?>">
                    <button type="submit" name="add_to_cart">Tambah ke Keranjang</button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Menambahkan barang menggunakan ID -->
    <h2>Tambah Barang dengan ID</h2>
    <form method="POST">
        <input type="number" name="id_barang" placeholder="Masukkan ID Barang" required>
        <input type="number" name="jumlah" min="1" value="1" required>
        <button type="submit" name="add_to_cart">Tambah ke Keranjang</button>
    </form>

    <!-- Keranjang -->
    <div class="keranjang">
        <h2>Keranjang Belanja</h2>
        <table>
            <tr>
                <th>Nama Barang</th>
                <th>Harga</th>
                <th>Jumlah</th>
                <th>Total</th>
                <th>Aksi</th>
            </tr>
            <?php foreach ($_SESSION['keranjang'] as $k): ?>
                <tr>
                    <td><?= $k['nama'] ?></td>
                    <td>Rp <?= number_format($k['harga'], 0, ',', '.') ?></td>
                    <td>
                        <form method="POST">
                            <input type="number" name="jumlah" value="<?= $k['jumlah'] ?>" min="1" required>
                            <input type="hidden" name="id_barang" value="<?= $k['id'] ?>">
                            <button type="submit" name="update_cart" value="decrease">-</button>
                            <button type="submit" name="update_cart" value="increase">+</button>
                        </form>
                    </td>
                    <td>Rp <?= number_format($k['total'], 0, ',', '.') ?></td>
                    <td>
                        <form method="POST">
                            <button type="submit" name="remove_item" value="<?= $k['id'] ?>">Hapus</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>

        <form method="POST" style="margin-top: 20px;">
            <button type="submit" name="hitung">Hitung Total</button>
        </form>
    </div>
<?php endif; ?>

</body>
</html>

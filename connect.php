<?php
// connect.php
$host = 'localhost';  // Host untuk database
$user = 'root';       // Username untuk database (default XAMPP)
$password = '';       // Password untuk database (default XAMPP tidak ada password)
$dbname = 'kasir';    // Nama database yang telah dibuat
$port = 8712;

// Buat koneksi
$conn = new mysqli($host, $user, $password, $dbname, $port);

// Cek koneksi
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}
echo "Koneksi berhasil!";
?>

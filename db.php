<?php
// db.php
$host = 'localhost';
$dbname = 'oyun_forum';
$user = 'root';
$pass = ''; // WampServer/XAMPP varsayılan şifre boştur

try {
    $db = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Siber Bağlantı Hatası: " . $e->getMessage());
}
?>
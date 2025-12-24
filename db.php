<?php
/**
 * Veritabanı Bağlantı Konfigürasyonu
 * Projenin genelinde kullanılacak olan MySQL veritabanı bağlantısını
 * bu dosyada PDO (PHP Data Objects) yapısını kullanarak oluşturdum.
 */

// Yerel geliştirme ortamı (Localhost) için gerekli sunucu bilgilerini tanımladım.
$host = 'localhost';
$dbname = 'oyun_forum';
$user = 'root';
$pass = ''; // WampServer/XAMPP varsayılan kurulumunda şifre boş olduğu için boş bıraktım.

try {
    // PDO sınıfını başlatarak veritabanı ile köprüyü kurdum.
    // Türkçe karakter sorunu yaşamamak adına karakter setini 'utf8' olarak belirledim.
    $db = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);

    // Hata ayıklama sürecini kolaylaştırmak için PDO hata modunu 'Exception' fırlatacak şekilde ayarladım.
    // Böylece SQL hatalarını try-catch blokları ile yakalayabileceğim.
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (PDOException $e) {
    // Bağlantı aşamasında kritik bir hata oluşursa, işlemi durdurup hata mesajını ekrana bastırdım.
    die("Veritabanı Bağlantı Hatası: " . $e->getMessage());
}
?>

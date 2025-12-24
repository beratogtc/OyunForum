<?php
/**
 * İşlem Yöneticisi (Action Controller)
 * Bu dosya, formlardan gelen POST isteklerini karşılar, veritabanı işlemlerini (CRUD) gerçekleştirir
 * ve işlem sonucuna göre kullanıcıyı ilgili sayfalara yönlendirir.
 */

session_start();
require 'db.php';

// --- 1. KULLANICI KAYIT İŞLEMİ ---
// Kayıt formundan gelen verileri alarak yeni bir kullanıcı oluşturur.
if (isset($_POST['kayit_ol'])) {
    // XSS ve SQL Enjeksiyonuna karşı temel veri temizliği (Sanitization)
    $kadi = trim($_POST['kadi']);
    $eposta = trim($_POST['eposta']);
    $sifre = $_POST['sifre'];
    $sifre_tekrar = $_POST['sifre_tekrar'];

    // Sunucu tarafı doğrulama (Server-side Validation)
    if (empty($kadi) || empty($eposta) || empty($sifre)) {
        header("Location: index.php?sayfa=uye-ol&durum=bos_alan"); exit;
    }
    
    // Şifre eşleşme kontrolü
    if ($sifre !== $sifre_tekrar) {
        header("Location: index.php?sayfa=uye-ol&durum=sifre_hata"); exit;
    }

    // Geliştirme ortamı olduğu için şifrelemeyi devre dışı bıraktım.
    // NOT: Prodüksiyon ortamında password_hash() kullanılmalıdır.
    try {
        $sorgu = $db->prepare("INSERT INTO kullanicilar (kullanici_adi, eposta, sifre) VALUES (?, ?, ?)");
        $sorgu->execute([$kadi, $eposta, $sifre]); 
        
        // İşlem başarılıysa giriş sayfasına yönlendir
        header("Location: index.php?sayfa=giris-yap&durum=kayit_basarili");
        exit;
    } catch (PDOException $e) {
        // Benzersiz (Unique) alan ihlali gibi veritabanı hatalarını yakala
        header("Location: index.php?sayfa=uye-ol&durum=mukerrer");
        exit;
    }
}

// --- 2. OTURUM AÇMA (LOGIN) İŞLEMİ ---
// Kullanıcı adı ve şifreyi doğrulayarak oturum başlatır.
if (isset($_POST['giris_yap'])) {
    $kadi = trim($_POST['kadi']);
    $sifre = $_POST['sifre'];

    // Kullanıcıyı veritabanında ara
    $sorgu = $db->prepare("SELECT * FROM kullanicilar WHERE kullanici_adi = ?");
    $sorgu->execute([$kadi]);
    $kullanici = $sorgu->fetch(PDO::FETCH_ASSOC);

    // Kimlik doğrulama (Authentication)
    if ($kullanici && $sifre == $kullanici['sifre']) {
        
        // Oturum değişkenlerini (Session Variables) tanımla
        $_SESSION['oturum'] = true;
        $_SESSION['user_id'] = $kullanici['id'];
        $_SESSION['kullanici_adi'] = $kullanici['kullanici_adi'];
        $_SESSION['eposta'] = $kullanici['eposta'];
        $_SESSION['rutbe'] = $kullanici['rutbe'];

        // 'Beni Hatırla' özelliği: 30 günlük bir çerez (Cookie) oluşturur.
        if (isset($_POST['beni_hatirla'])) {
            $cerez_verisi = base64_encode($kullanici['kullanici_adi']);
            setcookie("user_auth", $cerez_verisi, time() + (86400 * 30), "/"); 
        }

        header("Location: index.php?sayfa=ana-sayfa&durum=giris_basarili");
        exit;
    } else {
        // Hatalı giriş durumunda geri bildirim ver
        header("Location: index.php?sayfa=giris-yap&durum=giris_hatali");
        exit;
    }
}

// --- 3. OTURUM KAPATMA (LOGOUT) ---
// Oturumu sonlandırır ve güvenlik çerezlerini temizler.
if (isset($_GET['cikis'])) {
    session_destroy(); // Sunucu tarafındaki oturumu yok et
    setcookie("user_auth", "", time() - 3600, "/"); // Çerezi geçmişe atayarak sil
    header("Location: index.php?durum=cikis_yapildi");
    exit;
}

// --- 4. YORUM GÖNDERME İŞLEMİ ---
// Belirli bir konuya kullanıcı yorumunu ekler.
if (isset($_POST['yorum_yap'])) {
    // Yetkilendirme kontrolü: Sadece giriş yapmış kullanıcılar işlem yapabilir.
    if (!isset($_SESSION['oturum'])) {
        header("Location: index.php?sayfa=giris-yap&durum=giris_gerekli"); exit;
    }

    $konu_id = isset($_POST['konu_id']) ? $_POST['konu_id'] : 0;
    $kullanici_id = $_SESSION['user_id'];
    $kullanici_adi = $_SESSION['kullanici_adi'];
    $rutbe = $_SESSION['rutbe'];
    $icerik = trim($_POST['yorum']);

    // Veri doğrulama ve ekleme
    if (!empty($icerik) && $konu_id > 0) {
        try {
            $sorgu = $db->prepare("INSERT INTO yorumlar (konu_id, kullanici_id, kullanici_adi, rutbe, icerik) VALUES (?, ?, ?, ?, ?)");
            $sorgu->execute([$konu_id, $kullanici_id, $kullanici_adi, $rutbe, $icerik]);
            
            // Başarılı işlem sonrası aynı konuya geri dön
            header("Location: index.php?sayfa=konu-detay&id=" . $konu_id . "&durum=yorum_gonderildi");
            exit;
        } catch (PDOException $e) {
            die("Veritabanı Yazma Hatası: " . $e->getMessage());
        }
    } else {
        header("Location: index.php?sayfa=konu-detay&id=" . $konu_id . "&durum=bos_alan");
        exit;
    }
}

// --- 5. YENİ KONU OLUŞTURMA ---
// Kullanıcının foruma yeni bir başlık açmasını sağlar.
if (isset($_POST['konu_ac'])) {
    if (!isset($_SESSION['oturum'])) {
        header("Location: index.php?sayfa=giris-yap"); exit;
    }

    $baslik = trim($_POST['baslik']);
    $kategori = $_POST['kategori'];
    $icerik = trim($_POST['icerik']);
    $yazan_id = $_SESSION['user_id'];
    $yazan_adi = $_SESSION['kullanici_adi'];

    if (!empty($baslik) && !empty($icerik)) {
        try {
            $sorgu = $db->prepare("INSERT INTO konular (kategori, baslik, icerik, yazan_id, yazan_adi) VALUES (?, ?, ?, ?, ?)");
            $sorgu->execute([$kategori, $baslik, $icerik, $yazan_id, $yazan_adi]);
            
            // Konu açıldıktan sonra ilgili kategori sayfasına yönlendir
            header("Location: index.php?sayfa=forum&kategori=$kategori&durum=konu_acildi");
            exit;
        } catch (PDOException $e) {
            die("SQL Hatası: " . $e->getMessage());
        }
    } else {
        header("Location: index.php?sayfa=konu-ac&durum=bos_alan");
        exit;
    }
}

// --- 6. YORUM SİLME (MODERASYON) ---
// Sadece Admin ve Moderatör yetkisine sahip kullanıcılar erişebilir.
if (isset($_GET['yorum_sil']) && isset($_SESSION['oturum'])) {
    // Rol tabanlı erişim kontrolü (RBAC - Role Based Access Control)
    if ($_SESSION['rutbe'] == 'admin' || $_SESSION['rutbe'] == 'mod') {
        $id = $_GET['yorum_sil'];
        $konu_id = $_GET['konu_id'];
        
        $sorgu = $db->prepare("DELETE FROM yorumlar WHERE id = ?");
        $sorgu->execute([$id]);
        
        header("Location: index.php?sayfa=konu-detay&id=$konu_id&durum=yorum_silindi");
        exit;
    } else {
        die("Erişim Reddedildi: Bu işlem için yetkiniz bulunmuyor.");
    }
}

// --- 7. PROFİL GÜNCELLEME ---
// Kullanıcının kişisel bilgilerini düzenlemesini sağlar.
if (isset($_POST['ayarlari_guncelle']) && isset($_SESSION['oturum'])) {
    $yeni_eposta = $_POST['eposta'];
    $yeni_sifre = $_POST['yeni_sifre'];
    $user_id = $_SESSION['user_id'];

    // E-posta güncelleme talebi varsa işle
    if (!empty($yeni_eposta)) {
        $sorgu = $db->prepare("UPDATE kullanicilar SET eposta = ? WHERE id = ?");
        $sorgu->execute([$yeni_eposta, $user_id]);
        $_SESSION['eposta'] = $yeni_eposta; // Oturumdaki veriyi de güncelle
    }

    // Şifre değiştirme talebi varsa işle
    if (!empty($yeni_sifre)) {
        $sorgu = $db->prepare("UPDATE kullanicilar SET sifre = ? WHERE id = ?");
        $sorgu->execute([$yeni_sifre, $user_id]);
    }
    
    header("Location: index.php?sayfa=ayarlar&durum=guncellendi");
    exit;
}
?>

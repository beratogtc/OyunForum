<?php
session_start();
require 'db.php';

// --- 1. KAYIT OLMA İŞLEMİ ---
if (isset($_POST['kayit_ol'])) {
    $kadi = trim($_POST['kadi']);
    $eposta = trim($_POST['eposta']);
    $sifre = $_POST['sifre'];
    $sifre_tekrar = $_POST['sifre_tekrar'];

    if (empty($kadi) || empty($eposta) || empty($sifre)) {
        header("Location: index.php?sayfa=uye-ol&durum=bos_alan"); exit;
    }
    if ($sifre !== $sifre_tekrar) {
        header("Location: index.php?sayfa=uye-ol&durum=sifre_hata"); exit;
    }

    // ŞİFRELEME KALDIRILDI: Direkt $sifre'yi kaydediyoruz
    try {
        $sorgu = $db->prepare("INSERT INTO kullanicilar (kullanici_adi, eposta, sifre) VALUES (?, ?, ?)");
        $sorgu->execute([$kadi, $eposta, $sifre]); // Hash yok, düz şifre var
        header("Location: index.php?sayfa=giris-yap&durum=kayit_basarili");
        exit;
    } catch (PDOException $e) {
        header("Location: index.php?sayfa=uye-ol&durum=mukerrer");
        exit;
    }
}

// --- 2. GİRİŞ YAPMA İŞLEMİ ---
if (isset($_POST['giris_yap'])) {
    $kadi = trim($_POST['kadi']);
    $sifre = $_POST['sifre'];

    $sorgu = $db->prepare("SELECT * FROM kullanicilar WHERE kullanici_adi = ?");
    $sorgu->execute([$kadi]);
    $kullanici = $sorgu->fetch(PDO::FETCH_ASSOC);

    // ŞİFRELEME KONTROLÜ KALDIRILDI: Düz karşılaştırma (==) yapıyoruz
    if ($kullanici && $sifre == $kullanici['sifre']) {
        
        // Session Bilgilerini Oluştur
        $_SESSION['oturum'] = true;
        $_SESSION['user_id'] = $kullanici['id'];
        $_SESSION['kullanici_adi'] = $kullanici['kullanici_adi'];
        $_SESSION['eposta'] = $kullanici['eposta'];
        $_SESSION['rutbe'] = $kullanici['rutbe'];

        // Beni Hatırla Kontrolü
        if (isset($_POST['beni_hatirla'])) {
            $cerez_verisi = base64_encode($kullanici['kullanici_adi']);
            setcookie("user_auth", $cerez_verisi, time() + (86400 * 30), "/"); 
        }

        header("Location: index.php?sayfa=ana-sayfa&durum=giris_basarili");
        exit;
    } else {
        header("Location: index.php?sayfa=giris-yap&durum=giris_hatali");
        exit;
    }
}

// --- 3. ÇIKIŞ YAPMA İŞLEMİ ---
if (isset($_GET['cikis'])) {
    session_destroy();
    setcookie("user_auth", "", time() - 3600, "/");
    header("Location: index.php");
    exit;
}

// --- 4. YORUM YAPMA İŞLEMİ ---
if (isset($_POST['yorum_yap'])) {
    if (!isset($_SESSION['oturum'])) {
        header("Location: index.php?sayfa=giris-yap&durum=giris_gerekli"); exit;
    }

    $konu_id = isset($_POST['konu_id']) ? $_POST['konu_id'] : 0;
    $kullanici_id = $_SESSION['user_id'];
    $kullanici_adi = $_SESSION['kullanici_adi'];
    $rutbe = $_SESSION['rutbe'];
    $icerik = trim($_POST['yorum']);

    if (!empty($icerik) && $konu_id > 0) {
        try {
            $sorgu = $db->prepare("INSERT INTO yorumlar (konu_id, kullanici_id, kullanici_adi, rutbe, icerik) VALUES (?, ?, ?, ?, ?)");
            $sorgu->execute([$konu_id, $kullanici_id, $kullanici_adi, $rutbe, $icerik]);
            header("Location: index.php?sayfa=konu-detay&id=" . $konu_id . "&durum=yorum_gonderildi");
            exit;
        } catch (PDOException $e) {
            die("Yorum kaydedilemedi: " . $e->getMessage());
        }
    } else {
        header("Location: index.php?sayfa=konu-detay&id=" . $konu_id . "&durum=bos_alan");
        exit;
    }
}

// --- 5. YENİ KONU AÇMA İŞLEMİ ---
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
            header("Location: index.php?sayfa=forum&kategori=$kategori&durum=konu_acildi");
            exit;
        } catch (PDOException $e) {
            die("Hata: " . $e->getMessage());
        }
    } else {
        header("Location: index.php?sayfa=konu-ac&durum=bos_alan");
        exit;
    }
}

// --- 6. YORUM SİLME ---
if (isset($_GET['yorum_sil']) && isset($_SESSION['oturum'])) {
    if ($_SESSION['rutbe'] == 'admin' || $_SESSION['rutbe'] == 'mod') {
        $id = $_GET['yorum_sil'];
        $konu_id = $_GET['konu_id'];
        
        $sorgu = $db->prepare("DELETE FROM yorumlar WHERE id = ?");
        $sorgu->execute([$id]);
        
        header("Location: index.php?sayfa=konu-detay&id=$konu_id&durum=yorum_silindi");
        exit;
    } else {
        die("Yetkisiz erişim denemesi!");
    }
}

// --- 7. AYARLARI GÜNCELLEME ---
if (isset($_POST['ayarlari_guncelle']) && isset($_SESSION['oturum'])) {
    $yeni_eposta = $_POST['eposta'];
    $yeni_sifre = $_POST['yeni_sifre'];
    $user_id = $_SESSION['user_id'];

    if (!empty($yeni_eposta)) {
        $sorgu = $db->prepare("UPDATE kullanicilar SET eposta = ? WHERE id = ?");
        $sorgu->execute([$yeni_eposta, $user_id]);
        $_SESSION['eposta'] = $yeni_eposta;
    }

    if (!empty($yeni_sifre)) {
        // ŞİFRELEME KALDIRILDI: Direkt güncelliyoruz
        $sorgu = $db->prepare("UPDATE kullanicilar SET sifre = ? WHERE id = ?");
        $sorgu->execute([$yeni_sifre, $user_id]);
    }
    
    header("Location: index.php?sayfa=ayarlar&durum=guncellendi");
    exit;
}
?>
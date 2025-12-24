<?php
session_start();
require 'db.php'; 

// --- LOADING KONTROL ---
$loader_goster = true;
if (isset($_GET['durum']) || $_SERVER['REQUEST_METHOD'] == 'POST') {
    $loader_goster = false;
}

// --- 1. GÜVENLİK VE GİRİŞ KONTROLLERİ ---
if (!isset($_SESSION['oturum']) && isset($_COOKIE['user_auth'])) {
    $cerez_kadi = base64_decode($_COOKIE['user_auth']);
    $sorgu = $db->prepare("SELECT * FROM kullanicilar WHERE kullanici_adi = ?");
    $sorgu->execute([$cerez_kadi]);
    $user = $sorgu->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $_SESSION['oturum'] = true;
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['kullanici_adi'] = $user['kullanici_adi'];
        $_SESSION['rutbe'] = $user['rutbe'];
        $_SESSION['eposta'] = isset($user['eposta']) ? $user['eposta'] : '';
    }
}

// --- 2. FONKSİYONLAR ---
function rutbeRozeti($rutbe) {
    if ($rutbe == 'admin') return '<span class="badge bg-danger ms-2 shadow-sm"><i class="bi bi-shield-fill"></i> YÖNETİCİ</span>';
    if ($rutbe == 'mod') return '<span class="badge bg-info text-dark ms-2 shadow-sm"><i class="bi bi-hammer"></i> MODERATÖR</span>'; 
    return '<span class="badge bg-secondary ms-2"><i class="bi bi-person"></i> ÜYE</span>';
}

function kategoriRozeti($kat) {
    if($kat == 'Valorant') return 'bg-danger shadow-sm';
    if($kat == 'CS2') return 'bg-warning text-dark shadow-sm';
    if($kat == 'Metin2') return 'bg-info text-dark shadow-sm';
    return 'bg-secondary';
}

// Kullanıcı İstatistiklerini Hesapla
function getUserStats($db, $user_id) {
    try {
        $msg = $db->prepare("SELECT COUNT(*) FROM yorumlar WHERE kullanici_id = ?");
        $msg->execute([$user_id]);
        $m_sayisi = $msg->fetchColumn();
        
        $konu = $db->prepare("SELECT COUNT(*) FROM konular WHERE yazan_id = ?");
        $konu->execute([$user_id]);
        $k_sayisi = $konu->fetchColumn();
        
        $total = $m_sayisi + $k_sayisi;
        $lvl = floor($total / 5) + 1;
        $xp = ($total % 5) * 20;
        
        return ['msg' => $m_sayisi, 'konu' => $k_sayisi, 'lvl' => $lvl, 'xp' => $xp];
    } catch (PDOException $e) {
        return ['msg' => 0, 'konu' => 0, 'lvl' => 1, 'xp' => 0];
    }
}

// --- 3. OTURUM SAHİBİ BİLGİLERİ ---
$my_stats = ['lvl'=>1, 'xp'=>0];
if (isset($_SESSION['oturum'])) {
    $sorgu = $db->prepare("SELECT rutbe, kayit_tarihi FROM kullanicilar WHERE id = ?");
    $sorgu->execute([$_SESSION['user_id']]);
    $guncel_user = $sorgu->fetch(PDO::FETCH_ASSOC);
    if ($guncel_user) {
        $_SESSION['rutbe'] = $guncel_user['rutbe']; 
        $_SESSION['kayit_tarihi'] = $guncel_user['kayit_tarihi'];
    }
    $my_stats = getUserStats($db, $_SESSION['user_id']);
}

$aktif_sayfa = isset($_GET['sayfa']) ? $_GET['sayfa'] : 'ana-sayfa';

// --- 4. GENEL İSTATİSTİKLER ---
try {
    $stat_konu = $db->query("SELECT COUNT(*) FROM konular")->fetchColumn();
    $stat_yorum = $db->query("SELECT COUNT(*) FROM yorumlar")->fetchColumn();
    $stat_uye = $db->query("SELECT COUNT(*) FROM kullanicilar")->fetchColumn();
} catch (PDOException $e) { $stat_konu = 0; $stat_yorum = 0; $stat_uye = 0; }

// --- 5. ÜYELERİ ÇEK ---
try {
    $uye_sorgu = $db->query("SELECT * FROM kullanicilar ORDER BY kayit_tarihi DESC LIMIT 8");
    $uyeler = $uye_sorgu->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $uyeler = []; }

// Sidebar İpucu
$ipuclari = [
    "Valorant'ta koşarak ateş etme, sabit dur!",
    "CS2'de smoke içi bombayı çözmek için 'E' tuşuna basılı tut.",
    "Metin2'de dolunay kılıcı için vahşi uzman kesmelisin.",
    "Takım içi iletişim, iyi bir aimden daha önemlidir.",
    "Klavye ve fareni temiz tutmak performansını artırır."
];
$gunun_ipucu = $ipuclari[array_rand($ipuclari)];

// Haberler
$haberler = [
    1 => ['baslik' => 'VALORANT: Yeni Ajan "Cyborg" İncelemesi', 'tarih' => '23.12.2025', 'resim' => 'https://i.hizliresim.com/2mkt5ib.jpg', 'icerik' => 'Riot Games yeni ajanı tanıttı... Detaylar siber ağda hızla yayılıyor.'],
    2 => ['baslik' => 'CS2 Major Turnuvası Başlıyor', 'tarih' => '22.12.2025', 'resim' => 'https://i.hizliresim.com/o1ch9st.jpg', 'icerik' => 'Dünyanın en iyi takımları kupa için karşı karşıya geliyor...'],
    3 => ['baslik' => 'GTA VI İçin Yeni Sızıntılar', 'tarih' => '20.12.2025', 'resim' => 'https://i.hizliresim.com/kfz5sap.jpg', 'icerik' => 'Harita boyutunun devasa olacağı iddia ediliyor...']
];
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OyunForum | Ultimate Platform</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .text-bright { color: #fff !important; text-shadow: 0 0 5px rgba(255,255,255,0.5); }
        .hover-glow:hover { text-shadow: 0 0 10px #00f2ff; color: #00f2ff !important; }
        .sidebar-widget { background: rgba(0,0,0,0.6); border: 1px solid var(--primary-neon); }
        .stat-box { transition: transform 0.3s; }
        .stat-box:hover { transform: translateY(-5px); border-color: var(--secondary-neon) !important; }
        .member-link { text-decoration: none; display: block; transition: transform 0.3s; }
        .member-link:hover { transform: translateY(-5px); }
        .nav-pills .nav-link.active { background-color: var(--primary-neon); color: #000; font-weight: bold; box-shadow: 0 0 15px var(--primary-neon); }
        .nav-pills .nav-link { color: #fff; border: 1px solid rgba(255,255,255,0.1); margin-right: 5px; }
        <?php if(!$loader_goster): ?> #preloader { display: none !important; } <?php endif; ?>
    </style>
</head>
<body>

    <div id="preloader">
        <div class="loader-text">OYUN<span style="color:var(--primary-neon);">FORUM</span></div>
        <div class="loader-bar"></div>
        <small class="text-white mt-2 fw-bold" style="font-family: 'Rajdhani'; letter-spacing: 3px; text-shadow: 0 0 10px white;">SİSTEM YÜKLENİYOR...</small>
    </div>

    <?php
    $toast_mesaj = ""; $toast_tur = "success";
    if(isset($_GET['durum'])) {
        if($_GET['durum'] == 'konu_acildi') $toast_mesaj = "Konu başarıyla yayınlandı!";
        elseif($_GET['durum'] == 'yorum_gonderildi') $toast_mesaj = "Mesajın iletildi Ajan!";
        elseif($_GET['durum'] == 'giris_basarili') $toast_mesaj = "Sisteme hoş geldin!";
        elseif($_GET['durum'] == 'cikis_yapildi') { $toast_mesaj = "Oturum kapatıldı."; $toast_tur="warning"; }
        elseif($_GET['durum'] == 'bos_alan') { $toast_mesaj = "Tüm alanları doldurmalısın!"; $toast_tur="danger"; }
    }
    ?>
    <?php if($toast_mesaj): ?>
    <div id="customToast" class="<?php echo $toast_tur; ?>" style="display:flex;">
        <i class="bi bi-bell-fill"></i> <span id="toastMessage"><?php echo $toast_mesaj; ?></span>
    </div>
    <script>setTimeout(() => { document.getElementById('customToast').style.display='none'; }, 4000);</script>
    <?php endif; ?>

    <nav class="navbar navbar-expand-lg navbar-dark sticky-top p-3 shadow-lg" style="border-bottom: 2px solid var(--primary-neon);">
        <div class="container-fluid px-5">
            <a class="navbar-brand fs-3 fw-bold" href="index.php?sayfa=ana-sayfa" style="font-family: 'Rajdhani'; letter-spacing: 2px;">
                <i class="bi bi-cpu-fill text-primary-neon me-2"></i> //OyunForum
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar"><span class="navbar-toggler-icon"></span></button>
            <div class="collapse navbar-collapse" id="mainNavbar">
                <ul class="navbar-nav me-auto ms-4">
                    <li class="nav-item"><a class="nav-link <?php echo $aktif_sayfa=='ana-sayfa'?'active':''; ?> px-3" href="index.php?sayfa=ana-sayfa"><i class="bi bi-house-fill me-1"></i> ANA ÜS</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo $aktif_sayfa=='forum'?'active':''; ?> px-3" href="index.php?sayfa=forum"><i class="bi bi-chat-dots-fill me-1"></i> FORUM</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo $aktif_sayfa=='uyeler'?'active':''; ?> px-3" href="index.php?sayfa=uyeler"><i class="bi bi-people-fill me-1"></i> AJANLAR</a></li>
                </ul>
                <div class="text-white me-4 d-none d-lg-block" style="font-family:'Rajdhani'; font-weight:bold; letter-spacing:1px;">
                    <i class="bi bi-clock text-secondary-neon me-1"></i> <span id="liveClock">00:00:00</span>
                </div>
                <?php if(isset($_SESSION['oturum'])): ?>
                    <div class="dropdown"> 
                        <a class="nav-link dropdown-toggle text-white border border-primary px-3 py-2" href="#" data-bs-toggle="dropdown" style="background: rgba(0, 242, 255, 0.1);">
                            <img src="https://ui-avatars.com/api/?name=<?php echo $_SESSION['kullanici_adi']; ?>&background=random&color=fff" class="rounded-circle me-2 border border-light" width="25">
                            <span class="fw-bold text-primary-neon"><?php echo $_SESSION['kullanici_adi']; ?></span>
                            <span class="badge bg-warning text-dark ms-2">LVL <?php echo $my_stats['lvl']; ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow-lg bg-dark border-secondary">
                            <li><a class="dropdown-item text-white hover-glow" href="index.php?sayfa=profil"><i class="bi bi-person-badge me-2"></i> Profilim</a></li>
                            <li><a class="dropdown-item text-white hover-glow" href="index.php?sayfa=ayarlar"><i class="bi bi-sliders me-2"></i> Ayarlar</a></li>
                            <li><hr class="dropdown-divider bg-secondary"></li>
                            <li><a class="dropdown-item text-danger fw-bold" href="islem.php?cikis=1"><i class="bi bi-power me-2"></i> OTURUMU KAPAT</a></li>
                        </ul>
                    </div>
                <?php else: ?>
                    <div class="d-flex gap-2 ms-3">
                        <a href="index.php?sayfa=giris-yap" class="btn btn-outline-light btn-sm px-4 fw-bold"><i class="bi bi-box-arrow-in-right me-1"></i> GİRİŞ</a>
                        <a href="index.php?sayfa=uye-ol" class="btn btn-premium btn-sm px-4"><i class="bi bi-person-plus-fill me-1"></i> KAYIT OL</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <div class="fade-in-up">
        
        <?php if ($aktif_sayfa == 'ana-sayfa'): ?>
        <section id="ana-sayfa">
            <div class="forum-banner mb-5">
                <div class="text-center p-4" style="background: rgba(0,0,0,0.7); width:100%;">
                    <h1 class="display-3 fw-bold text-white" style="text-shadow: 0 0 20px red;">OYUN DÜNYASININ MERKEZİ</h1>
                    <p class="lead text-white fw-bold mb-0">STRATEJİ. TAKTİK. TOPLULUK.</p>
                </div>
            </div>
            
            <div class="container my-5">
                <div class="row">
                    <div class="col-lg-9">
                        <div class="d-flex align-items-center justify-content-between mb-4 border-bottom border-primary pb-2">
                            <h2 class="text-secondary-neon m-0"><i class="bi bi-broadcast me-2"></i> SON İSTİHBARAT</h2>
                            <span class="badge bg-danger animate-pulse">CANLI AKIŞ</span>
                        </div>
                        <div class="card shadow-lg mb-5 border-secondary">
                            <div class="card-body p-0">
                                <?php foreach($haberler as $id => $h): ?>
                                    <div class="news-item p-4 border-bottom border-secondary d-flex align-items-center justify-content-between hover-glow-bg">
                                        <div>
                                            <a href="index.php?sayfa=haber-detay&haber_id=<?php echo $id; ?>" class="text-white text-decoration-none fs-5 fw-bold d-block mb-1 hover-glow">
                                                <i class="bi bi-caret-right-fill text-primary-neon me-1"></i> <?php echo $h['baslik']; ?>
                                            </a>
                                            <span class="text-light opacity-100 small fw-bold" style="color: #ccc !important;"><i class="bi bi-clock me-1"></i> <?php echo $h['tarih']; ?> tarihinde yayınlandı.</span>
                                        </div>
                                        <a href="index.php?sayfa=haber-detay&haber_id=<?php echo $id; ?>" class="btn btn-sm btn-outline-info rounded-pill px-3">OKU</a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <h2 class="text-primary-neon mb-4 border-bottom border-secondary pb-2"><i class="bi bi-grid-3x3-gap-fill me-2"></i> OPERASYON BÖLGELERİ</h2>
                        <div class="row g-4">
                            <div class="col-md-4">
                                <div class="card h-100 member-card overflow-hidden border-danger">
                                    <div class="position-relative">
                                        <img src="https://i.hizliresim.com/2mkt5ib.jpg" class="card-img-top game-card-img">
                                        <div class="position-absolute top-0 end-0 bg-danger text-white px-2 py-1 fw-bold">FPS</div>
                                    </div>
                                    <div class="card-body text-center bg-dark">
                                        <h4 class="card-title text-white">VALORANT</h4>
                                        <p class="small text-white opacity-75">Taktiksel Nişancı</p>
                                        <a href="index.php?sayfa=forum&kategori=Valorant" class="btn btn-outline-danger w-100 fw-bold">GÖREVE GİT</a>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4"><div class="card h-100 member-card overflow-hidden border-warning"><div class="position-relative"><img src="https://i.hizliresim.com/o1ch9st.jpg" class="card-img-top game-card-img"><div class="position-absolute top-0 end-0 bg-warning text-dark px-2 py-1 fw-bold">FPS</div></div><div class="card-body text-center bg-dark"><h4 class="card-title text-white">CS2</h4><p class="small text-white opacity-75">Efsanevi Rekabet</p><a href="index.php?sayfa=forum&kategori=CS2" class="btn btn-outline-warning w-100 fw-bold">GÖREVE GİT</a></div></div></div>
                            <div class="col-md-4"><div class="card h-100 member-card overflow-hidden border-info"><div class="position-relative"><img src="https://i.hizliresim.com/r5su7dq.jpg" class="card-img-top game-card-img"><div class="position-absolute top-0 end-0 bg-info text-dark px-2 py-1 fw-bold">MMO</div></div><div class="card-body text-center bg-dark"><h4 class="card-title text-white">METİN 2</h4><p class="small text-white opacity-75">Doğunun Macerası</p><a href="index.php?sayfa=forum&kategori=Metin2" class="btn btn-outline-info w-100 fw-bold">GÖREVE GİT</a></div></div></div>
                        </div>
                    </div>
                    <div class="col-lg-3">
                        <div class="card mb-4 p-3 shadow-lg border-secondary bg-dark sidebar-widget">
                            <h5 class="text-secondary-neon mb-3 fw-bold"><i class="bi bi-hdd-network me-2"></i> SUNUCULAR</h5>
                            <div class="mb-3"><div class="d-flex justify-content-between small mb-1 text-white"><span>VALORANT</span><span class="text-success fw-bold">AKTİF</span></div><div class="progress" style="height: 6px; background: #333;"><div class="progress-bar bg-success" style="width: 100%"></div></div></div>
                            <div class="mb-3"><div class="d-flex justify-content-between small mb-1 text-white"><span>CS2</span><span class="text-warning fw-bold">YOĞUN</span></div><div class="progress" style="height: 6px; background: #333;"><div class="progress-bar bg-warning" style="width: 75%"></div></div></div>
                        </div>
                        
                        <div class="card mb-4 p-0 shadow-lg border-primary text-center bg-dark sidebar-widget overflow-hidden">
                            <div class="card-header bg-danger text-white fw-bold py-2">🏆 HAFTANIN MVP'Sİ</div>
                            <div class="card-body">
                                <a href="#" class="text-decoration-none">
                                    <img src="https://ui-avatars.com/api/?name=berats&background=random&color=fff" class="rounded-circle mb-2 border border-3 border-info p-1" width="80">
                                    <h5 class="text-white fw-bold hover-glow">berats</h5>
                                </a>
                                <span class="badge bg-info text-dark mb-2 shadow-sm"><i class="bi bi-hammer"></i> MODERATÖR</span>
                                <div class="d-flex justify-content-center gap-2 mt-2">
                                    <span class="badge bg-danger border border-danger">LVL 5</span>
                                    <span class="badge bg-dark border border-secondary">KDA: 4.2</span>
                                </div>
                            </div>
                        </div>

                        <div class="card mb-4 p-3 shadow-lg border-info bg-dark"><h5 class="text-info mb-2 fw-bold"><i class="bi bi-lightbulb-fill me-2"></i> İPUCU</h5><p class="text-white small fst-italic mb-0">"<?php echo $gunun_ipucu; ?>"</p></div>
                    </div>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <?php if ($aktif_sayfa == 'profil'): 
            if(!isset($_SESSION['oturum'])) { header("Location: index.php?sayfa=giris-yap"); exit; }
            $hedef_id = isset($_GET['kullanici_id']) ? $_GET['kullanici_id'] : $_SESSION['user_id'];
            $sorgu = $db->prepare("SELECT * FROM kullanicilar WHERE id = ?");
            $sorgu->execute([$hedef_id]);
            $profil_user = $sorgu->fetch(PDO::FETCH_ASSOC);
            if(!$profil_user) { echo "<div class='container my-5 alert alert-danger'>Ajan bulunamadı.</div>"; } 
            else {
                $profil_stats = getUserStats($db, $hedef_id);
                $is_me = ($hedef_id == $_SESSION['user_id']);
        ?>
        <section id="profil-sayfasi">
            <div class="container my-5">
                <div class="row">
                    <div class="col-lg-4">
                        <div class="card p-4 text-center border-primary shadow-lg mb-4 bg-dark">
                            <div class="position-relative d-inline-block">
                                <img src="https://ui-avatars.com/api/?name=<?php echo $profil_user['kullanici_adi']; ?>&background=00f2ff&color=000&size=150" class="rounded-circle border border-4 border-primary p-1 mb-3">
                                <span class="position-absolute bottom-0 end-0 badge bg-danger rounded-pill p-2 border border-dark fs-6">Lvl <?php echo $profil_stats['lvl']; ?></span>
                            </div>
                            <h2 class="text-white fw-bold mb-1"><?php echo htmlspecialchars($profil_user['kullanici_adi']); ?></h2>
                            <div class="mb-3"><?php echo rutbeRozeti($profil_user['rutbe']); ?></div>
                            <div class="progress mb-2" style="height: 12px; background: #222; border-radius:10px;">
                                <div class="progress-bar bg-gradient-warning progress-bar-striped progress-bar-animated" role="progressbar" style="width: <?php echo $profil_stats['xp']; ?>%"></div>
                            </div>
                            <small class="text-light d-block mb-4 fw-bold">%<?php echo $profil_stats['xp']; ?> XP</small>
                            <div class="mb-4">
                                <span class="badge bg-dark border border-secondary text-white-50">Sniper</span>
                                <span class="badge bg-dark border border-secondary text-white-50">IGL</span>
                            </div>
                            <ul class="list-group list-group-flush bg-transparent">
                                <li class="list-group-item bg-transparent text-light d-flex justify-content-between border-bottom border-secondary"><span><i class="bi bi-calendar-check me-2"></i>Katılım:</span> <span><?php echo date("d.m.Y", strtotime($profil_user['kayit_tarihi'])); ?></span></li>
                                <li class="list-group-item bg-transparent text-light d-flex justify-content-between border-bottom border-secondary"><span><i class="bi bi-chat-left-dots me-2"></i>Mesajlar:</span> <span class="text-primary-neon fw-bold"><?php echo $profil_stats['msg']; ?></span></li>
                                <li class="list-group-item bg-transparent text-light d-flex justify-content-between border-bottom border-secondary"><span><i class="bi bi-pencil-square me-2"></i>Konular:</span> <span class="text-primary-neon fw-bold"><?php echo $profil_stats['konu']; ?></span></li>
                            </ul>
                            <?php if($is_me): ?>
                                <a href="index.php?sayfa=ayarlar" class="btn btn-secondary-neon w-100 mt-4 fw-bold"><i class="bi bi-gear-fill me-2"></i> PROFİLİ DÜZENLE</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-lg-8">
                        <div class="card p-4 border-secondary shadow-lg h-100 bg-dark">
                            <h3 class="text-primary-neon mb-4 border-bottom border-secondary pb-3"><i class="bi bi-activity me-2"></i> SON OPERASYONLAR</h3>
                            <div class="activity-feed">
                                <?php
                                $aktivite_sorgu = $db->prepare("SELECT y.*, k.baslik as konu_baslik FROM yorumlar y LEFT JOIN konular k ON y.konu_id = k.id WHERE y.kullanici_id = ? ORDER BY y.tarih DESC LIMIT 5");
                                $aktivite_sorgu->execute([$hedef_id]);
                                $aktiviteler = $aktivite_sorgu->fetchAll(PDO::FETCH_ASSOC);
                                if ($aktiviteler) {
                                    foreach($aktiviteler as $akt) {
                                        echo '<div class="d-flex align-items-start mb-3 p-3 rounded border border-secondary" style="background: rgba(255,255,255,0.02);">';
                                        echo '<div class="me-3"><i class="bi bi-reply-fill text-warning fs-3"></i></div>';
                                        echo '<div><h6 class="text-bright mb-1">"' . htmlspecialchars($akt['konu_baslik']) . '" konusuna cevap verdi.</h6><p class="text-white opacity-75 small mb-1 fst-italic">"' . substr(htmlspecialchars($akt['icerik']), 0, 80) . '..."</p><small class="text-primary-neon">' . date("d.m.Y H:i", strtotime($akt['tarih'])) . '</small></div></div>';
                                    }
                                } else { echo '<div class="alert alert-dark text-white border-secondary">Henüz bir aktivite kaydı yok.</div>'; }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <?php } endif; ?>

        <?php if ($aktif_sayfa == 'haber-detay'): 
            $id = isset($_GET['haber_id']) ? (int)$_GET['haber_id'] : 1;
            $haber = isset($haberler[$id]) ? $haberler[$id] : $haberler[1];
        ?>
        <section id="haber-detay"><div class="container my-5"><nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="index.php" class="text-decoration-none text-info fw-bold">Ana Sistem</a></li><li class="breadcrumb-item active text-white" aria-current="page">Haber Detayı</li></ol></nav><div class="card shadow-lg p-0 border-primary overflow-hidden bg-dark"><div style="width: 100%; height: 450px; overflow: hidden; position: relative;"><img src="<?php echo $haber['resim']; ?>" class="w-100 h-100" style="object-fit: cover;"><div class="position-absolute top-0 start-0 w-100 h-100" style="background: linear-gradient(to top, #000, transparent);"></div><div style="position: absolute; bottom: 30px; left: 30px;"><span class="badge bg-danger mb-2 px-3 py-2">SICAK GELİŞME</span><h1 class="text-white display-4 fw-bold" style="text-shadow: 0 0 15px black;"><?php echo $haber['baslik']; ?></h1></div></div><div class="card-body p-5"><div class="d-flex align-items-center gap-4 mb-4 text-white border-bottom border-secondary pb-3"><span class="text-info"><i class="bi bi-calendar3 me-1"></i> <?php echo $haber['tarih']; ?></span><span class="badge bg-success border border-light">DOĞRULANMIŞ İÇERİK</span></div><div class="text-white lead" style="line-height: 1.9; font-size: 1.1rem;"><?php echo nl2br(htmlspecialchars($haber['icerik'])); ?></div><div class="mt-5"><a href="index.php" class="btn btn-secondary-neon px-4 py-2"><i class="bi bi-arrow-left me-2"></i> ANA SİSTEME DÖN</a></div></div></div></div></section>
        <?php endif; ?>

        <?php if ($aktif_sayfa == 'forum'): 
            $kategori = isset($_GET['kategori']) ? $_GET['kategori'] : 'Hepsi';
            if ($kategori == 'Hepsi') { $konu_sorgu = $db->query("SELECT * FROM konular ORDER BY tarih DESC"); $baslik_metni = "GLOBAL VERİ AKIŞI"; } 
            else { $konu_sorgu = $db->prepare("SELECT * FROM konular WHERE kategori = ? ORDER BY tarih DESC"); $konu_sorgu->execute([$kategori]); $baslik_metni = htmlspecialchars($kategori) . " AĞI"; }
            $gelen_konular = $konu_sorgu->fetchAll(PDO::FETCH_ASSOC);
        ?>
        <section id="forum">
            <div class="container my-5">
                <div class="row mb-4">
                    <div class="col-md-4"><div class="card p-3 bg-dark border-secondary text-center stat-box"><h3 class="text-primary-neon fw-bold mb-0"><?php echo $stat_konu; ?></h3><span class="text-white small ls-1">TOPLAM KONU</span></div></div>
                    <div class="col-md-4"><div class="card p-3 bg-dark border-secondary text-center stat-box"><h3 class="text-secondary-neon fw-bold mb-0"><?php echo $stat_yorum; ?></h3><span class="text-white small ls-1">TOPLAM MESAJ</span></div></div>
                    <div class="col-md-4"><div class="card p-3 bg-dark border-secondary text-center stat-box"><h3 class="text-danger fw-bold mb-0"><?php echo $stat_uye; ?></h3><span class="text-white small ls-1">KAYITLI AJAN</span></div></div>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3 border-bottom border-primary pb-3">
                    <h2 class="text-white m-0"><i class="bi bi-hdd-stack-fill me-2 text-primary-neon"></i> <?php echo $baslik_metni; ?></h2>
                    <a href="index.php?sayfa=konu-ac&kat=<?php echo urlencode($kategori); ?>" class="btn btn-premium px-4"><i class="bi bi-plus-lg me-1"></i> YENİ KONU</a>
                </div>
                <div class="card shadow-lg border-secondary">
                    <div class="table-responsive">
                        <table class="table table-dark thread-table mb-0 table-hover">
                            <thead><tr class="border-bottom border-secondary"><th class="ps-4 py-3 text-secondary-neon">KONU BAŞLIĞI</th><th class="text-center text-secondary-neon">KATEGORİ</th><th class="text-center text-secondary-neon">YAZAN</th><th class="text-center text-secondary-neon">TARİH</th></tr></thead>
                            <tbody>
                                <?php if ($gelen_konular): foreach ($gelen_konular as $k): ?>
                                    <tr class="align-middle" style="cursor: pointer;" onclick="window.location='index.php?sayfa=konu-detay&id=<?php echo $k['id']; ?>'">
                                        <td class="ps-4 py-3"><a href="index.php?sayfa=konu-detay&id=<?php echo $k['id']; ?>" class="text-white text-decoration-none fw-bold fs-5 hover-glow"><?php echo htmlspecialchars($k['baslik']); ?></a></td>
                                        <td class="text-center"><span class="badge <?php echo kategoriRozeti($k['kategori']); ?>"><?php echo htmlspecialchars($k['kategori']); ?></span></td>
                                        <td class="text-center text-info fw-bold"><?php echo htmlspecialchars($k['yazan_adi']); ?></td>
                                        <td class="text-center text-white-50 small fw-bold"><?php echo date("d.m H:i", strtotime($k['tarih'])); ?></td>
                                    </tr>
                                <?php endforeach; else: ?><tr><td colspan="4" class="text-center p-5 text-white-50 fs-5">Bu frekansta henüz sinyal yok.</td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <?php if ($aktif_sayfa == 'konu-detay'): 
            $konu_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            $konu = $db->query("SELECT * FROM konular WHERE id = $konu_id")->fetch(PDO::FETCH_ASSOC);
            $yorumlar = $db->query("SELECT * FROM yorumlar WHERE konu_id = $konu_id ORDER BY tarih DESC")->fetchAll(PDO::FETCH_ASSOC);
        ?>
        <section id="konu-detay">
            <div class="container my-5">
                <?php if($konu): ?>
                    <div class="card p-4 mb-4 border-danger shadow-lg bg-dark">
                        <div class="d-flex justify-content-between align-items-start">
                            <h2 class="text-primary-neon fw-bold"><?php echo htmlspecialchars($konu['baslik']); ?></h2>
                            <span class="badge bg-secondary"><?php echo $konu['kategori']; ?></span>
                        </div>
                        <div class="mb-4 text-white small border-bottom border-secondary pb-2 d-flex gap-3">
                            <a href="index.php?sayfa=profil&kullanici_id=<?php echo $konu['yazan_id']; ?>" class="text-decoration-none"><span class="hover-glow"><i class="bi bi-person-fill text-warning"></i> <?php echo htmlspecialchars($konu['yazan_adi']); ?></span></a>
                            <span><i class="bi bi-clock-fill text-info"></i> <?php echo date("d.m.Y H:i", strtotime($konu['tarih'])); ?></span>
                        </div>
                        <div class="lead text-white" style="font-size: 1.1rem; line-height: 1.7;"><?php echo nl2br(htmlspecialchars($konu['icerik'])); ?></div>
                    </div>
                    <h4 class="text-white mb-4 ps-3 border-start border-5 border-warning">CEVAPLAR (<?php echo count($yorumlar); ?>)</h4>
                    <div id="yorum-alani">
                        <?php if (count($yorumlar) > 0): ?>
                            <?php foreach ($yorumlar as $y): ?>
                                <div class="card p-3 mb-3 border-secondary bg-dark shadow-sm">
                                    <div class="d-flex justify-content-between">
                                        <div class="d-flex align-items-center gap-2">
                                            <img src="https://ui-avatars.com/api/?name=<?php echo $y['kullanici_adi']; ?>&background=random" class="rounded-circle" width="30">
                                            <a href="index.php?sayfa=profil&kullanici_id=<?php echo $y['kullanici_id']; ?>" class="text-decoration-none"><strong class="text-success hover-glow"><?php echo htmlspecialchars($y['kullanici_adi']); ?></strong></a>
                                            <?php echo rutbeRozeti($y['rutbe']); ?>
                                        </div>
                                        <small class="text-white-50"><?php echo date("d.m H:i", strtotime($y['tarih'])); ?></small>
                                    </div>
                                    <hr class="border-secondary opacity-25">
                                    <p class="mb-0 text-white mt-2 ps-2"><?php echo nl2br(htmlspecialchars($y['icerik'])); ?></p>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?><div class="alert alert-dark border-secondary text-center text-white-50 py-4">Henüz cevap yok.</div><?php endif; ?>
                    </div>
                    <?php if(isset($_SESSION['oturum'])): ?>
                        <div class="card p-4 mt-4 shadow-lg border-primary bg-dark">
                            <h5 class="text-white mb-3"><i class="bi bi-reply-fill"></i> Yanıt Yaz</h5>
                            <form action="islem.php" method="POST">
                                <input type="hidden" name="konu_id" value="<?php echo $konu_id; ?>">
                                <textarea name="yorum" class="form-control mb-3 bg-dark text-white border-secondary" rows="4" placeholder="Fikrini belirt..." required></textarea>
                                <button type="submit" name="yorum_yap" class="btn btn-secondary-neon px-4 fw-bold">GÖNDER</button>
                            </form>
                        </div>
                    <?php endif; ?>
                <?php else: ?><div class="alert alert-danger">Konu bulunamadı.</div><?php endif; ?>
            </div>
        </section>
        <?php endif; ?>

        <?php if ($aktif_sayfa == 'giris-yap'): ?>
        <section class="container my-5 d-flex justify-content-center align-items-center" style="min-height: 60vh;">
            <div class="card p-5 mx-auto shadow-lg border-secondary bg-dark" style="width: 450px; background: rgba(10,10,10,0.95);">
                <div class="text-center mb-4">
                    <i class="bi bi-shield-lock-fill text-secondary-neon" style="font-size: 3rem;"></i>
                    <h2 class="text-white mt-2 fw-bold brand-font">ERİŞİM İZNİ</h2>
                    <p class="text-white-50 small mb-0">SİBER AĞ GÜVENLİĞİ AKTİF</p>
                </div>
                <form action="islem.php" method="POST">
                    <div class="mb-3">
                        <label class="text-secondary-neon small mb-1 fw-bold">KULLANICI KİMLİĞİ</label>
                        <div class="input-group">
                            <span class="input-group-text bg-dark border-secondary text-white"><i class="bi bi-person"></i></span>
                            <input type="text" name="kadi" class="form-control" placeholder="Ajan adın..." required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="text-secondary-neon small mb-1 fw-bold">GÜVENLİK ANAHTARI</label>
                        <div class="input-group">
                            <span class="input-group-text bg-dark border-secondary text-white"><i class="bi bi-key"></i></span>
                            <input type="password" name="sifre" class="form-control" placeholder="******" required>
                        </div>
                    </div>
                    <div class="mb-4 form-check"><input type="checkbox" name="beni_hatirla" class="form-check-input" id="rmb"><label class="text-white-50 small" for="rmb">Kimliğimi 30 Gün Sakla</label></div>
                    <button type="submit" name="giris_yap" class="btn btn-secondary-neon w-100 fw-bold py-2">SİSTEME BAĞLAN</button>
                </form>
                <div class="text-center mt-4 pt-3 border-top border-secondary">
                    <a href="index.php?sayfa=uye-ol" class="text-white small text-decoration-none hover-glow">Henüz bir kimliğin yok mu? <span class="text-primary-neon">Kayıt Ol</span></a>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <?php if ($aktif_sayfa == 'uye-ol'): ?>
        <section class="container my-5 d-flex justify-content-center align-items-center" style="min-height: 60vh;">
            <div class="card p-5 mx-auto shadow-lg border-primary bg-dark" style="width: 450px; background: rgba(10,10,10,0.95);">
                <div class="text-center mb-4">
                    <i class="bi bi-person-plus-fill text-primary-neon" style="font-size: 3rem;"></i>
                    <h2 class="text-white mt-2 fw-bold brand-font">AJAN KAYDI</h2>
                    <p class="text-white-50 small mb-0">YENİ PERSONEL ALIMI</p>
                </div>
                <form action="islem.php" method="POST">
                    <div class="mb-3"><label class="text-primary-neon small mb-1 fw-bold">KOD ADI</label><input type="text" name="kadi" class="form-control" required></div>
                    <div class="mb-3"><label class="text-primary-neon small mb-1 fw-bold">İLETİŞİM FREKANSI (E-Posta)</label><input type="email" name="eposta" class="form-control" required></div>
                    <div class="mb-3"><label class="text-primary-neon small mb-1 fw-bold">GÜVENLİK ANAHTARI</label><input type="password" name="sifre" class="form-control" required></div>
                    <div class="mb-4"><label class="text-primary-neon small mb-1 fw-bold">ANAHTAR ONAYI</label><input type="password" name="sifre_tekrar" class="form-control" required></div>
                    <button type="submit" name="kayit_ol" class="btn btn-premium w-100 fw-bold py-2">KAYDI TAMAMLA</button>
                </form>
                <div class="text-center mt-4 pt-3 border-top border-secondary">
                    <a href="index.php?sayfa=giris-yap" class="text-white small text-decoration-none hover-glow">Zaten personelsen <span class="text-secondary-neon">Giriş Yap</span></a>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <?php if ($aktif_sayfa == 'ayarlar' && isset($_SESSION['oturum'])): ?>
        <section class="container my-5">
            <div class="row">
                <div class="col-lg-3 mb-4">
                    <div class="card bg-dark border-secondary">
                        <div class="list-group list-group-flush bg-transparent">
                            <button class="list-group-item list-group-item-action bg-transparent text-white border-secondary active fw-bold" data-bs-toggle="pill" data-bs-target="#hesap"><i class="bi bi-person-gear me-2"></i> Hesap Bilgileri</button>
                            <button class="list-group-item list-group-item-action bg-transparent text-white border-secondary fw-bold" data-bs-toggle="pill" data-bs-target="#guvenlik"><i class="bi bi-shield-lock me-2"></i> Güvenlik</button>
                            <button class="list-group-item list-group-item-action bg-transparent text-white border-secondary fw-bold" data-bs-toggle="pill" data-bs-target="#gizlilik"><i class="bi bi-eye-slash me-2"></i> Gizlilik</button>
                        </div>
                    </div>
                </div>
                <div class="col-lg-9">
                    <div class="card p-5 bg-dark border-primary shadow-lg">
                        <div class="tab-content">
                            <div class="tab-pane fade show active" id="hesap">
                                <h4 class="text-primary-neon mb-4 border-bottom border-secondary pb-2">HESAP BİLGİLERİ</h4>
                                <form action="islem.php" method="POST">
                                    <div class="mb-3"><label class="text-white-50 small mb-1">KOD ADI (Değiştirilemez)</label><input type="text" class="form-control bg-dark text-white-50 border-secondary" value="<?php echo $_SESSION['kullanici_adi']; ?>" disabled></div>
                                    <div class="mb-4"><label class="text-white-50 small mb-1">E-POSTA ADRESİ</label><input type="email" name="eposta" class="form-control" value="<?php echo $_SESSION['eposta']; ?>"></div>
                                    <button type="submit" name="ayarlari_guncelle" class="btn btn-primary px-4 fw-bold">BİLGİLERİ GÜNCELLE</button>
                                </form>
                            </div>
                            <div class="tab-pane fade" id="guvenlik">
                                <h4 class="text-danger mb-4 border-bottom border-secondary pb-2">ŞİFRE DEĞİŞİKLİĞİ</h4>
                                <form action="islem.php" method="POST">
                                    <div class="mb-3"><label class="text-white-50 small mb-1">MEVCUT ŞİFRE</label><input type="password" name="mevcut_sifre" class="form-control"></div>
                                    <div class="mb-4"><label class="text-white-50 small mb-1">YENİ ŞİFRE</label><input type="password" name="yeni_sifre" class="form-control"></div>
                                    <button type="submit" name="ayarlari_guncelle" class="btn btn-danger px-4 fw-bold">ŞİFREYİ YENİLE</button>
                                </form>
                            </div>
                            <div class="tab-pane fade" id="gizlilik">
                                <h4 class="text-success mb-4 border-bottom border-secondary pb-2">GİZLİLİK AYARLARI</h4>
                                <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" id="flexSwitchCheckChecked" checked><label class="form-check-label text-white" for="flexSwitchCheckChecked">Çevrimiçi durumumu göster</label></div>
                                <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" id="mailCheck" checked><label class="form-check-label text-white" for="mailCheck">E-posta bildirimlerini al</label></div>
                                <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" id="profilCheck"><label class="form-check-label text-white" for="profilCheck">Profilimi sadece üyelere göster</label></div>
                                <button class="btn btn-success mt-3 px-4 fw-bold">TERCİHLERİ KAYDET</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <?php if ($aktif_sayfa == 'konu-ac'): ?>
        <section class="container my-5"><div class="card p-5 bg-dark border-secondary"><h3 class="text-primary-neon mb-4">YENİ KONU OLUŞTUR</h3><form action="islem.php" method="POST"><div class="mb-3"><input type="text" name="baslik" class="form-control" placeholder="Başlık" required></div><div class="mb-3"><select name="kategori" class="form-control"><option value="Valorant">Valorant</option><option value="CS2">CS2</option><option value="Metin2">Metin2</option><option value="Genel">Genel</option></select></div><div class="mb-3"><textarea name="icerik" class="form-control" rows="5" placeholder="İçerik..." required></textarea></div><button type="submit" name="konu_ac" class="btn btn-premium px-5 fw-bold">YAYINLA</button></form></div></section>
        <?php endif; ?>

        <?php if ($aktif_sayfa == 'uyeler'): ?>
        <section class="container my-5"><h3 class="text-white mb-4 border-bottom pb-2">TÜM AJANLAR</h3><div class="row g-3">
            <?php foreach($uyeler as $u): ?>
                <div class="col-md-3">
                    <a href="index.php?sayfa=profil&kullanici_id=<?php echo $u['id']; ?>" class="member-link">
                        <div class="card text-center p-3 bg-dark border-secondary shadow-sm h-100">
                            <img src="https://ui-avatars.com/api/?name=<?php echo $u['kullanici_adi']; ?>&background=random" class="rounded-circle mx-auto mb-3" width="60">
                            <h5 class="text-warning fw-bold mb-1"><?php echo $u['kullanici_adi']; ?></h5>
                            <?php echo rutbeRozeti($u['rutbe']); ?>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div></section>
        <?php endif; ?>

    </div>

    <button id="aiChatButton"><i class="bi bi-robot"></i></button>
    <div id="aiChatWindow"><div id="chatHeader"><span>SİBER ASİSTAN</span><i class="bi bi-x-lg" style="cursor:pointer;" onclick="document.getElementById('aiChatWindow').style.display='none'"></i></div><div id="chatBody"><div class="chat-message-ai"><span>Selam Ajan! Nasıl yardımcı olabilirim?</span></div></div><div class="p-2 bg-black d-flex"><input type="text" id="chatInput" class="form-control border-0" placeholder="Mesaj yaz..."><button id="sendBtn" class="btn btn-sm btn-secondary-neon ms-2"><i class="bi bi-send"></i></button></div></div>

    <footer class="bg-dark text-white-50 py-5 mt-5 border-top border-secondary" style="background: #050505 !important;">
        <div class="container">
            <div class="row g-4">
                <div class="col-md-4 text-center text-md-start">
                    <h5 class="text-white mb-3 fw-bold font-monospace"><i class="bi bi-cpu me-2"></i>OyunForum</h5>
                    <p class="small text-secondary">Oyun dünyasının kalbinin attığı yer. En son haberler, stratejiler ve topluluk tartışmaları burada.</p>
                </div>
                <div class="col-md-4 text-center">
                    <h5 class="text-white mb-3 fw-bold">Hızlı Erişim</h5>
                    <ul class="list-unstyled">
                        <li><a href="index.php?sayfa=ana-sayfa" class="text-decoration-none text-white-50 hover-glow">Ana Sayfa</a></li>
                        <li><a href="index.php?sayfa=forum" class="text-decoration-none text-white-50 hover-glow">Forum</a></li>
                        <li><a href="index.php?sayfa=uyeler" class="text-decoration-none text-white-50 hover-glow">Üyeler</a></li>
                    </ul>
                </div>
                <div class="col-md-4 text-center text-md-end">
                    <h5 class="text-white mb-3 fw-bold">Bizi Takip Et</h5>
                    <div class="d-flex justify-content-center justify-content-md-end gap-3">
                        <a href="#" class="text-white fs-4 hover-glow"><i class="bi bi-discord"></i></a>
                        <a href="#" class="text-white fs-4 hover-glow"><i class="bi bi-twitter-x"></i></a>
                        <a href="#" class="text-white fs-4 hover-glow"><i class="bi bi-youtube"></i></a>
                    </div>
                </div>
            </div>
            <hr class="border-secondary my-4 opacity-25">
            <div class="text-center small opacity-50">&copy; 2025 OyunForum - Tüm Hakları Saklıdır.</div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // --- 1. HIZLI INTRO (800ms) ---
        window.addEventListener('load', function() {
            const screen = document.getElementById('preloader');
            if (screen) { setTimeout(() => { screen.classList.add('loader-hide'); }, 800); }
        });

        // --- 2. CANLI SAAT ---
        function updateClock() { document.getElementById('liveClock').textContent = new Date().toLocaleTimeString('tr-TR'); }
        setInterval(updateClock, 1000); updateClock();

        // --- 3. CHAT BOT ---
        const win = document.getElementById('aiChatWindow');
        document.getElementById('aiChatButton').onclick = () => win.style.display = 'flex';
        document.getElementById('sendBtn').onclick = sendMsg;
        document.getElementById('chatInput').onkeypress = (e) => { if(e.key==='Enter') sendMsg(); }
        async function sendMsg() {
            const inp = document.getElementById('chatInput');
            const msg = inp.value.trim();
            if(!msg) return;
            const body = document.getElementById('chatBody');
            body.innerHTML += `<div class="chat-message-user"><span>${msg}</span></div>`;
            inp.value = ''; body.scrollTop = body.scrollHeight;
            const id = "l-" + Date.now();
            body.innerHTML += `<div id="${id}" class="chat-message-ai"><span><i>...</i></span></div>`;
            try {
                const r = await fetch('bot.php', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({message:msg}) });
                const d = await r.json();
                document.getElementById(id).remove(); body.innerHTML += `<div class="chat-message-ai"><span>${d.reply}</span></div>`;
            } catch(e) { document.getElementById(id).remove(); }
            body.scrollTop = body.scrollHeight;
        }
    </script>
</body>
</html>
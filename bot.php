<?php
/**
 * Yapay Zeka Entegrasyon Modülü
 * Bu dosyada, kullanıcının girdiği mesajları alıp Groq API servisine ileten
 * ve dönen yanıtı işleyen arka uç (backend) kodlarını yazdım.
 */

// İstemciye (Frontend) dönecek yanıtın formatını JSON olarak belirledim.
header('Content-Type: application/json');

// React/JS tarafından gönderilen ham POST verisini (raw input) yakaladım ve diziye çevirdim.
$input = file_get_contents('php://input');
$data = json_decode($input, true);
$userMessage = $data['message'] ?? '';

// Eğer kullanıcı boş bir mesaj gönderdiyse API'yi meşgul etmemek için işlemi burada durdurdum.
if (empty($userMessage)) {
    echo json_encode(['reply' => 'Lütfen geçerli bir mesaj giriniz.']);
    exit;
}

// API erişim anahtarımı ve istek yapacağım uç noktayı (endpoint) burada tanımladım.
// Güvenlik Notu: Proje sunucuya taşındığında bu anahtarı ortam değişkenlerine (.env) taşıyacağım.
$apiKey = "BURAYA_API_KEY_GELECEK"; 
$apiUrl = "https://api.groq.com/openai/v1/chat/completions";

// API'ye göndereceğim veri paketini hazırladım.
// Model olarak performanslı olduğu için llama-3.3-70b versiyonunu tercih ettim.
$postData = [
    "model" => "llama-3.3-70b-versatile",
    "messages" => [
        // Botun kimliğini ve davranış biçimini burada sisteme tanıttım.
        ["role" => "system", "content" => "Sen yardımcı bir asistansın."],
        ["role" => "user", "content" => $userMessage]
    ]
];

// Uzak sunucu ile iletişim kurmak için cURL oturumunu başlattım.
$ch = curl_init($apiUrl);

// cURL ayarlarını yapılandırdım:
// 1. Yanıtı doğrudan ekrana basmak yerine değişkene aktarmasını söyledim.
// 2. HTTP POST metodunu kullanacağımı belirttim.
// 3. Hazırladığım JSON verisini isteğe ekledim.
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));

// Kimlik doğrulama (Authorization) ve içerik tipi başlıklarını ekledim.
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "Authorization: Bearer " . $apiKey
]);

// Localhost ortamında çalıştığım için SSL sertifika hatalarını geçici olarak devre dışı bıraktım.
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

// İsteği gerçekleştirdim ve sunucudan dönen yanıtı yakaladım.
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE); // HTTP durum kodunu kontrol amaçlı aldım.
curl_close($ch);

// API'den dönen JSON yanıtını PHP dizisine çevirdim.
$resData = json_decode($response, true);

// Hata Yönetimi:
// Eğer sunucu 200 (Başarılı) kodu döndürmediyse hata mesajını yakalayıp ekrana basıyorum.
if ($httpCode !== 200) {
    $errorMsg = $resData['error']['message'] ?? "Sunucu tabanlı bir hata oluştu. Kod: $httpCode";
    echo json_encode(['reply' => "Hata: " . $errorMsg]);
} else {
    // İşlem başarılıysa, gelen yanıtın içinden botun mesajını ayıkladım ve kullanıcıya gönderdim.
    $botReply = $resData['choices'][0]['message']['content'] ?? "Yanıt oluşturulamadı.";
    echo json_encode(['reply' => $botReply]);
}
?>

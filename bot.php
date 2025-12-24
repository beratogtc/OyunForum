<?php
// bot.php - ARIZA TESPİT VERSİYONU
header('Content-Type: application/json');

$input = file_get_contents('php://input');
$data = json_decode($input, true);
$userMessage = $data['message'] ?? '';

if (empty($userMessage)) {
    echo json_encode(['reply' => 'Sinyal boş geldi Ajan...']);
    exit;
}

$apiKey = "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"; // Boşluk kalmadığından emin ol
$apiUrl = "https://api.groq.com/openai/v1/chat/completions";

$postData = [
    "model" => "llama-3.3-70b-versatile", // Daha güçlü ve güncel bir model deneyelim
    "messages" => [
        ["role" => "system", "content" => "Sen bir siber asistansın. Adın KankaBot."],
        ["role" => "user", "content" => $userMessage]
    ]
];

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "Authorization: Bearer " . $apiKey
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE); // Sunucunun verdiği kodu al (200, 401, 404 vb.)
curl_close($ch);

$resData = json_decode($response, true);

if ($httpCode !== 200) {
    // Eğer hata varsa, Groq'un gönderdiği JSON hatasını yakala
    $errorMsg = $resData['error']['message'] ?? "Bilinmeyen siber hata (Kod: $httpCode)";
    echo json_encode(['reply' => "Sistem hatası: " . $errorMsg]);
} else {
    $botReply = $resData['choices'][0]['message']['content'] ?? "Yanıt ayıklanamadı kanka.";
    echo json_encode(['reply' => $botReply]);
}
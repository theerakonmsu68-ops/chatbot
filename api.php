<?php

session_start();

header('Content-Type: application/json; charset=utf-8');


// =====================================================
// YouTube Search Function
// =====================================================

function searchYoutube($keyword)
{
    $api_key = getenv("YOUTUBE_API_KEY");

    if (empty($api_key)) {
        return null;
    }

    $url = "https://www.googleapis.com/youtube/v3/search?"
        . http_build_query([
            "key" => $api_key,
            "part" => "snippet",
            "q" => $keyword,
            "type" => "video",
            "maxResults" => 1,
            "regionCode" => "TH",
            "relevanceLanguage" => "th"
        ]);

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10
    ]);

    $response = curl_exec($ch);

    curl_close($ch);

    $data = json_decode($response, true);

    if (isset($data['items'][0]['id']['videoId'])) {
        return "https://www.youtube.com/watch?v=" . $data['items'][0]['id']['videoId'];
    }

    return null;
}


// =====================================================
// Database Configuration
// =====================================================

require_once 'db_config.php';


// =====================================================
// รับข้อมูล JSON
// =====================================================

$data = json_decode(
    file_get_contents('php://input'),
    true
);

$user_id = $_SESSION['user_id'] ?? null;
$action = $data['action'] ?? 'chat';


// =====================================================
// ตรวจสอบการเข้าสู่ระบบ
// =====================================================

if (!$user_id) {
    echo json_encode([
        "error" => "Unauthorized. Please login again."
    ], JSON_UNESCAPED_UNICODE);
    exit;
}


// =====================================================
// FETCH CHAT HISTORY (ประวัติการสนทนา)
// =====================================================

if ($action === 'fetch') {
    $chat_id = $data['chat_id'] ?? '';

    if (empty($chat_id)) {
        echo json_encode([
            "history" => []
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmt = $conn->prepare("
        SELECT message, reply
        FROM chat_history
        WHERE chat_id = ?
        AND user_id = ?
        ORDER BY id ASC
    ");

    if (!$stmt) {
        echo json_encode([
            "error" => "Database prepare error"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmt->bind_param("ss", $chat_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $history = [];
    while ($row = $result->fetch_assoc()) {
        $history[] = [
            "message" => $row['message'],
            "reply" => $row['reply']
        ];
    }

    $stmt->close();

    echo json_encode([
        "history" => $history
    ], JSON_UNESCAPED_UNICODE);

    exit;
}


// =====================================================
// CHAT SYSTEM (ระบบแชทหลัก)
// =====================================================

$message = trim($data['message'] ?? '');
$chat_id = $data['chat_id'] ?? null;

// สร้าง Chat ID ชั่วคราวหากเป็นห้องสนทนาใหม่
if (empty($chat_id)) {
    $chat_id = bin2hex(random_bytes(8));
}

if (empty($message)) {
    echo json_encode([
        "reply" => "พี่สารคามไม่ได้รับข้อความครับ"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}


// =====================================================
// SYSTEM PROMPT (คำสั่งควบคุมพฤติกรรม AI)
// =====================================================

$messages = [
    [
        "role" => "system",
        "content" => "
คุณคือ 'พี่สารคาม AI' ผู้ช่วยอัจฉริยะ ประจำคณะวิทยาการสารสนเทศ มหาวิทยาลัยมหาสารคาม

หน้าที่หลัก:
- ตอบคำถามให้ถูกต้อง ชัดเจน และเข้าใจง่าย
- ให้คำตอบอย่างเป็นมืออาชีพ มีความสุภาพ เป็นกันเอง
- ใช้ภาษาไทยเป็นหลักในการสื่อสาร

กฎเหล็กเกี่ยวกับการแนะนำเพลง:
- หากผู้ใช้ขอเพลง ให้ระบุชื่อเพลงและชื่อศิลปินที่มีอยู่จริงให้ถูกต้องแม่นยำ ห้ามสลับชื่อศิลปินหรือเดาชื่อเพลงขึ้นมาเองเด็ดขาด
- แนะนำเพลงหลักเพียง 1 เพลงที่ตรงกับความต้องการของผู้ใช้ที่สุด
- ไม่ต้องพยายามสร้างลิงก์หรือ URL ด้วยตนเอง ระบบค้นหาจะแนบลิงก์ YouTube ที่ถูกต้องให้อัตโนมัติ

รูปแบบการตอบ:
- เรียบเรียงเป็นประโยคที่อ่านง่าย ย่อหน้าเหมาะสม
- ไม่ใช้ Markdown ที่ซับซ้อน ไม่ใช้เครื่องหมายพิเศษ เช่น ###, **, ```
- ไม่อธิบายกระบวนการคิดภายใน ตอบเฉพาะข้อมูลที่เป็นประโยชน์เท่านั้น
"
    ]
];


// =====================================================
// LOAD HISTORY (ดึงประวัติการสนทนาล่าสุด 10 รายการ)
// =====================================================

$stmt_history =$conn->prepare("
    SELECT message, reply
    FROM chat_history
    WHERE chat_id = ?
    AND user_id = ?
    ORDER BY id DESC
    LIMIT 10
");

if ($stmt_history) {
    $stmt_history->bind_param("ss", $chat_id, $user_id);$stmt_history->execute();
    $result_history =$stmt_history->get_result();

    $temp_history = [];
    while ($row =$result_history->fetch_assoc()) {
        $temp_history[] =$row;
    }
    $stmt_history->close();

    // เรียงประวัติจากเก่าไปใหม่ เพื่อส่งให้ AI เข้าใจบริบท
    $temp_history = array_reverse($temp_history);
    foreach ($temp_history as $row) {$messages[] = [
            "role" => "user",
            "content" => $row['message']
        ];
        $messages[] = [
            "role" => "assistant",
            "content" => $row['reply']
        ];
    }
}

// เพิ่มข้อความปัจจุบันของผู้ใช้
$messages[] = [
    "role" => "user",
    "content" => $message
];


// =====================================================
// GROQ CONFIG & API CALL
// =====================================================

$api_key = getenv("GROQ_API_KEY");
$model = getenv("GROQ_MODEL") ?: "openai/gpt-oss-120b";

if (empty($api_key)) {
    echo json_encode([
        "reply" => "ขออภัยครับ ระบบยังไม่ได้ตั้งค่า Groq API Key"
    ], JSON_UNESCAPED_UNICODE);
    $conn->close();
    exit;
}

$api_url = "[https://api.groq.com/openai/v1/chat/completions](https://api.groq.com/openai/v1/chat/completions)";

$ch = curl_init($api_url);

curl_setopt_array($ch, [
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer " . $api_key,
        "Content-Type: application/json"
    ],
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode([
        "model" => $model,
        "messages" => $messages,
        "temperature" => 0.3,
        "top_p" => 0.9
    ], JSON_UNESCAPED_UNICODE),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 60,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2
]);

$response = curl_exec($ch);

if (curl_errno($ch)) {
    $error = curl_error($ch);
    curl_close($ch);
    error_log("Groq CURL Error : " . $error);$ai_reply = "ขออภัยครับ พี่สารคามไม่สามารถเชื่อมต่อระบบ AI ได้";
} else {
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $json = json_decode($response, true);

    if (isset($json['choices'][0]['message']['content'])) {
        $ai_reply =$json['choices'][0]['message']['content'];

        // Clean AI Response
        $ai_reply = preg_replace('/#{1,6}\s*/', '', $ai_reply);$ai_reply = str_replace(['**', '```'], '', $ai_reply);
        $ai_reply = trim($ai_reply);
    } else {
        $api_error = $json['error']['message'] ?? "Unknown Groq Error";
        error_log("Groq API Error HTTP " . $http_code . " : " . $api_error);
        $ai_reply = "ขออภัยครับ พี่สารคามไม่สามารถประมวลผลคำตอบได้";
    }
}


// =====================================================
// MUSIC SEARCH (ค้นหาคลิปวิดีโอ YouTube)
// =====================================================

if (preg_match('/เพลง|ฟัง|music|song|youtube|เปิด/i', $message)) {
    // สกัดคำค้นหา ตัดคำเชื่อมที่ไม่จำเป็นออก
    $keyword = preg_replace('/ขอ|เพลง|ฟัง|เปิด|youtube|หน่อย|ครับ|ค่ะ/i', '', $message);
    $keyword = trim($keyword);

    // หากคำค้นหาสั้นเกินไป ให้ใช้ข้อความการตอบกลับของ AI ช่วยค้นหาเพลงแทน
    if (empty($keyword) && !empty($ai_reply)) {
        $keyword = mb_substr($ai_reply, 0, 50, 'UTF-8');
    }

    if (!empty($keyword)) {
        $youtube = searchYoutube($keyword);

        if ($youtube) {
            $ai_reply .= "\n\n🎧 เปิดฟังเพลง:\n" . $youtube;
        }
    }
}


// =====================================================
// SAVE CHAT HISTORY (บันทึกลงฐานข้อมูล)
// =====================================================

$stmt = $conn->prepare("
    INSERT INTO chat_history (chat_id, user_id, message, reply)
    VALUES (?, ?, ?, ?)
");

if ($stmt) {
    $stmt->bind_param("ssss", $chat_id, $user_id, $message, $ai_reply);
    $stmt->execute();
    $stmt->close();
}


// =====================================================
// CLOSE DATABASE & RESPONSE
// =====================================================

$conn->close();

echo json_encode([
    "reply" => $ai_reply,
    "chat_id" => $chat_id
], JSON_UNESCAPED_UNICODE);

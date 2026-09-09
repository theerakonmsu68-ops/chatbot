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

$data = json_decode(file_get_contents('php://input'), true);

$user_id =$_SESSION['user_id'] ?? null;
$action =$data['action'] ?? 'chat';


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
// FETCH CHAT HISTORY
// =====================================================

if ($action === 'fetch') {
    $chat_id =$data['chat_id'] ?? '';

    if (empty($chat_id)) {
        echo json_encode(["history" => []], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmt =$conn->prepare("
        SELECT message, reply
        FROM chat_history
        WHERE chat_id = ? AND user_id = ?
        ORDER BY id ASC
    ");

    if (!$stmt) {
        echo json_encode(["error" => "Database prepare error"], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmt->bind_param("ss", $chat_id, $user_id);$stmt->execute();
    $result =$stmt->get_result();

    $history = [];
    while ($row = $result->fetch_assoc()) {$history[] = [
            "message" => $row['message'],
            "reply" => $row['reply']
        ];
    }

    $stmt->close();

    echo json_encode(["history" => $history], JSON_UNESCAPED_UNICODE);
    exit;
}


// =====================================================
// CHAT SYSTEM
// =====================================================

$message = trim($data['message'] ?? '');
$chat_id =$data['chat_id'] ?? null;

if (empty($chat_id)) {$chat_id = bin2hex(random_bytes(8));
}

if (empty($message)) {
    echo json_encode([
        "reply" => "พี่สารคามไม่ได้รับข้อความครับ"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}


// =====================================================
// SYSTEM PROMPT
// =====================================================

$messages = [
    [
        "role" => "system",
        "content" => "คุณคือ 'พี่สารคาม AI' ผู้ช่วยอัจฉริยะ คณะวิทยาการสารสนเทศ มหาวิทยาลัยมหาสารคาม
ตอบคำถามสุภาพ เป็นกันเอง ชัดเจน และเข้าใจง่าย ใช้ภาษาไทยเป็นหลัก
หากผู้ใช้ขอเพลง ให้แนะนำชื่อเพลงและศิลปินจริงที่มีอยู่จริงเพียง 1 เพลง ห้ามเดาสุ่มลิงก์"
    ]
];


// =====================================================
// LOAD HISTORY 10 RECORDS
// =====================================================

$stmt_history =$conn->prepare("
    SELECT message, reply
    FROM chat_history
    WHERE chat_id = ? AND user_id = ?
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

    $temp_history = array_reverse($temp_history);
    foreach ($temp_history as$row) {
        $messages[] = ["role" => "user", "content" => $row['message']];
        $messages[] = ["role" => "assistant", "content" => $row['reply']];
    }
}

// เพิ่มข้อความปัจจุบันของผู้ใช้
$messages[] = ["role" => "user", "content" => $message];


// =====================================================
// GROQ CONFIG & API CALL
// =====================================================

$api_key = getenv("GROQ_API_KEY");

// ใช้โมเดลมาตรฐานที่เสถียรของ Groq หากไม่ได้ตั้งค่า Environment Variable ไว้
$model = getenv("GROQ_MODEL") ?: "llama-3.3-70b-versatile";

if (empty($api_key)) {
    echo json_encode([
        "reply" => "ขออภัยครับ ระบบยังไม่ได้ตั้งค่า GROQ_API_KEY"
    ], JSON_UNESCAPED_UNICODE);
    $conn->close();
    exit;
}

$api_url = "https://api.groq.com/openai/v1/chat/completions";

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
        "temperature" => 0.5,
        "max_tokens" => 1024
    ], JSON_UNESCAPED_UNICODE),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2
]);

$response = curl_exec($ch);$is_success = false;

if (curl_errno($ch)) {
    $error = curl_error($ch);
    curl_close($ch);
    error_log("Groq CURL Error : " . $error); //[span_4](start_span)[span_4](end_span)$ai_reply = "ขออภัยครับ พี่สารคามไม่สามารถเชื่อมต่อระบบ AI ได้";[span_5](start_span)[span_5](end_span)
} else {
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $json = json_decode($response, true);

    if ($http_code === 200 && isset($json['choices'][0]['message']['content'])) {
        $ai_reply =$json['choices'][0]['message']['content'];

        // Clean AI Response
        $ai_reply = preg_replace('/#{1,6}\s*/', '', $ai_reply); //[span_6](start_span)[span_6](end_span)$ai_reply = str_replace(['**', '```'], '', $ai_reply); //[span_7](start_span)[span_7](end_span)
        $ai_reply = trim($ai_reply); //[span_8](start_span)[span_8](end_span)
        $is_success = true;
    } else {
        $api_error = $json['error']['message'] ?? "HTTP Status Code: " . $http_code; //[span_9](start_span)[span_9](end_span)
        error_log("Groq API Error: " . $api_error); //[span_10](start_span)[span_10](end_span)
        $ai_reply = "ขออภัยครับ พี่สารคามไม่สามารถเชื่อมต่อระบบ AI ได้";[span_11](start_span)[span_11](end_span)
    }
}


// =====================================================
// MUSIC SEARCH (ดึงคลิปเฉพาะเมื่อ AI ทำงานสำเร็จ)
// =====================================================

if ($is_success && preg_match('/เพลง|ฟัง|music|song|youtube|เปิด/i', $message)) {
    $keyword = preg_replace('/ขอ|เพลง|ฟัง|เปิด|youtube|หน่อย|ครับ|ค่ะ/i', '', $message);
    $keyword = trim($keyword);

    if (!empty($keyword)) {
        $youtube = searchYoutube($keyword);
        if ($youtube) {
            $ai_reply .= "\n\n🎧 เปิดฟังเพลง:\n" . $youtube; //[span_12](start_span)[span_12](end_span)
        }
    }
}


// =====================================================
// SAVE CHAT HISTORY
// =====================================================

$stmt = $conn->prepare("
    INSERT INTO chat_history (chat_id, user_id, message, reply)
    VALUES (?, ?, ?, ?)
");

if ($stmt) {
    $stmt->bind_param("ssss", $chat_id, $user_id, $message, $ai_reply); //[span_13](start_span)[span_13](end_span)
    $stmt->execute();
    $stmt->close();
}


// =====================================================
// CLOSE DATABASE & RESPONSE
// =====================================================

$conn->close(); //[span_14](start_span)[span_14](end_span)

echo json_encode([
    "reply" => $ai_reply,
    "chat_id" => $chat_id
], JSON_UNESCAPED_UNICODE); //[span_15](start_span)[span_15](end_span)

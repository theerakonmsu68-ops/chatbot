<?php

session_start();

header('Content-Type: application/json; charset=utf-8');

require_once 'db_config.php';


// =====================================================
// รับ JSON
// =====================================================

$data = json_decode(
    file_get_contents('php://input'),
    true
);


$user_id = $_SESSION['user_id'] ?? null;

$action = $data['action'] ?? 'chat';


// =====================================================
// ตรวจสอบ Login
// =====================================================

if (!$user_id) {

    echo json_encode([
        'error' => 'Unauthorized. Please login again.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}



// =====================================================
// FETCH CHAT HISTORY
// =====================================================

if ($action === 'fetch') {


    $chat_id = $data['chat_id'] ?? '';


    if (empty($chat_id)) {

        echo json_encode([
            'history' => []
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
            'error' => 'Database prepare error'
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }



    $stmt->bind_param(
        "ss",
        $chat_id,
        $user_id
    );


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
// CHAT SYSTEM
// =====================================================


$message = trim(
    $data['message'] ?? ''
);


$chat_id = $data['chat_id'] ?? null;



// สร้าง chat id ใหม่

if (empty($chat_id)) {

    $chat_id = bin2hex(
        random_bytes(8)
    );
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

        "content" => "คุณคือ พี่สารคาม AI ผู้ช่วยอัจฉริยะ ให้ข้อมูลอย่างสุภาพ เป็นกันเอง ใช้ภาษาไทยเป็นหลัก ตอบให้เข้าใจง่าย กระชับ และช่วยเหลือผู้ใช้ให้ดีที่สุด"

    ]

];





// =====================================================
// LOAD HISTORY 10 RECORD
// =====================================================


$stmt_history = $conn->prepare("

SELECT message, reply

FROM chat_history

WHERE chat_id = ?

AND user_id = ?

ORDER BY id ASC

LIMIT 10

");



if ($stmt_history) {


    $stmt_history->bind_param(

        "ss",

        $chat_id,

        $user_id

    );



    $stmt_history->execute();



    $result_history =
        $stmt_history->get_result();




    while ($row = $result_history->fetch_assoc()) {



        $messages[] = [

            "role" => "user",

            "content" => $row['message']

        ];



        $messages[] = [

            "role" => "assistant",

            "content" => $row['reply']

        ];
    }



    $stmt_history->close();
}





// เพิ่มข้อความใหม่


$messages[] = [

    "role" => "user",

    "content" => $message

];

// =====================================================
// GROQ API CONFIGURATION
// =====================================================


// ดึง API Key จาก Render Environment

$api_key = getenv("GROQ_API_KEY");


// ดึง Model จาก Environment

$model = getenv("GROQ_MODEL")
    ?: "openai/gpt-oss-120b";




// ตรวจสอบ API KEY

if (empty($api_key)) {


    echo json_encode([

        "reply" => "ขออภัยครับ ระบบยังไม่ได้ตั้งค่า Groq API Key"

    ], JSON_UNESCAPED_UNICODE);



    $conn->close();

    exit;
}




// Groq API URL

$api_url =
    "https://api.groq.com/openai/v1/chat/completions";




// =====================================================
// CALL GROQ API
// =====================================================


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


        "temperature" => 0.7



    ], JSON_UNESCAPED_UNICODE),



    CURLOPT_RETURNTRANSFER => true,



    CURLOPT_TIMEOUT => 60,



    CURLOPT_SSL_VERIFYPEER => true,


    CURLOPT_SSL_VERIFYHOST => 2



]);





$response = curl_exec($ch);





// =====================================================
// CURL ERROR
// =====================================================


if (curl_errno($ch)) {



    $error = curl_error($ch);



    curl_close($ch);



    error_log(
        "Groq CURL Error : " . $error
    );



    $ai_reply =
        "ขออภัยครับ พี่สารคามไม่สามารถเชื่อมต่อระบบ AI ได้";
} else {



    $http_code =
        curl_getinfo(
            $ch,
            CURLINFO_HTTP_CODE
        );



    curl_close($ch);



    $json =
        json_decode(
            $response,
            true
        );




    // =====================================================
    // CHECK GROQ RESPONSE
    // =====================================================


    if (
        isset(
            $json['choices'][0]['message']['content']
        )
    ) {


        $ai_reply =
            $json['choices'][0]['message']['content'];
    } else {


        $api_error =
            $json['error']['message']
            ?? "Unknown Groq Error";



        error_log(

            "Groq API Error HTTP "
                . $http_code
                . " : "
                . $api_error

        );



        $ai_reply =
            "ขออภัยครับ พี่สารคามไม่สามารถประมวลผลได้ กรุณาลองใหม่อีกครั้ง";
    }
}





// =====================================================
// SAVE CHAT HISTORY TO TiDB
// =====================================================


$stmt = $conn->prepare("

INSERT INTO chat_history

(
chat_id,
user_id,
message,
reply
)

VALUES (?,?,?,?)

");




if ($stmt) {



    $stmt->bind_param(

        "ssss",

        $chat_id,

        $user_id,

        $message,

        $ai_reply

    );



    $stmt->execute();



    $stmt->close();
}




// =====================================================
// CLOSE DATABASE
// =====================================================


$conn->close();




// =====================================================
// RESPONSE TO FRONTEND
// =====================================================


echo json_encode([


    "reply" => $ai_reply,


    "chat_id" => $chat_id



], JSON_UNESCAPED_UNICODE);

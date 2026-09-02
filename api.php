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



    $url =
        "https://www.googleapis.com/youtube/v3/search?"
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



    $data =
        json_decode(
            $response,
            true
        );



    if (
        isset(
            $data['items'][0]['id']['videoId']
        )
    ) {


        return

            "https://www.youtube.com/watch?v="

            . $data['items'][0]['id']['videoId'];
    }



    return null;
}




// =====================================================
// Database
// =====================================================

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
// ตรวจ Login
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



// สร้าง Chat ID ใหม่

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

        "content" => "

คุณคือ พี่สารคาม AI ผู้ช่วยอัจฉริยะ

หน้าที่:
- ตอบคำถามให้ถูกต้องและเข้าใจง่าย
- วิเคราะห์คำถามก่อนตอบ
- ให้คำตอบเหมือนผู้ช่วยมืออาชีพ
- ใช้ภาษาไทยเป็นหลัก

รูปแบบการตอบ:
- เรียบเรียงเป็นประโยคที่อ่านง่าย
- ใช้ย่อหน้าให้เหมาะสม
- ถ้ามีหลายข้อ ให้ใช้รายการตัวเลขหรือหัวข้อ
- ไม่ใช้ Markdown ที่ซับซ้อน
- ไม่ใช้เครื่องหมายพิเศษ เช่น ###, **, ``` 
- ไม่ใส่โค้ดหรือรูปแบบแปลก ๆ หากผู้ใช้ไม่ได้ร้องขอ

สไตล์:
- สุภาพ
- เป็นกันเอง
- กระชับ แต่ให้ข้อมูลครบ
- อธิบายเหมือนผู้เชี่ยวชาญกำลังให้คำแนะนำ

หากไม่แน่ใจ:
- แจ้งอย่างตรงไปตรงมา
- ไม่แต่งข้อมูลขึ้นเอง

หากผู้ใช้ขอเพลง:
- บอกชื่อเพลงและศิลปิน
- ระบบจะเพิ่มลิงก์ YouTube ให้อัตโนมัติ
- ไม่สร้างลิงก์ปลอม

ก่อนตอบให้วิเคราะห์ความต้องการของผู้ใช้ก่อน

ตอบเฉพาะข้อมูลที่จำเป็น
ไม่อธิบายกระบวนการคิดภายใน
ไม่แสดงเหตุผลการคิดทีละขั้น

ตอบเหมือนผู้ช่วยส่วนตัวที่มีความรู้

"

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

ORDER BY id DESC

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





// เพิ่มข้อความปัจจุบัน

$messages[] = [


    "role" => "user",


    "content" => $message


];





// =====================================================
// GROQ CONFIG
// =====================================================


$api_key = getenv("GROQ_API_KEY");



$model = getenv("GROQ_MODEL")

    ?: "openai/gpt-oss-120b";




if (empty($api_key)) {


    echo json_encode([


        "reply" => "ขออภัยครับ ระบบยังไม่ได้ตั้งค่า Groq API Key"


    ], JSON_UNESCAPED_UNICODE);



    $conn->close();


    exit;
}

// =====================================================
// CALL GROQ API
// =====================================================


$api_url =
    "https://api.groq.com/openai/v1/chat/completions";



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

        "temperature" => 0.4,

        "top_p" => 0.9



    ], JSON_UNESCAPED_UNICODE),



    CURLOPT_RETURNTRANSFER => true,



    CURLOPT_TIMEOUT => 60,



    CURLOPT_SSL_VERIFYPEER => true,


    CURLOPT_SSL_VERIFYHOST => 2



]);




$response = curl_exec($ch);





// =====================================================
// CHECK CURL ERROR
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




    if (
        isset(
            $json['choices'][0]['message']['content']
        )
    ) {



        $ai_reply =

            $json['choices'][0]['message']['content'];
        // =====================================================
        // Clean AI Response
        // =====================================================

        $ai_reply = preg_replace(
            '/#{1,6}\s*/',
            '',
            $ai_reply
        );


        $ai_reply = str_replace(
            ['**', '```'],
            '',
            $ai_reply
        );


        $ai_reply = trim($ai_reply);
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

            "ขออภัยครับ พี่สารคามไม่สามารถประมวลผลได้";
    }
}






// =====================================================
// MUSIC SEARCH
// =====================================================


if (
    preg_match(
        '/เพลง|ฟัง|music|song|youtube/i',
        $message
    )
) {


   $keyword = preg_replace(
    '/ขอ|เพลง|ฟัง|เปิด|youtube/i',
    '',
    $message
);


$youtube = searchYoutube(
    trim($keyword)
);



    if ($youtube) {



        $ai_reply .=


            "\n\n🎧 เปิดฟังเพลง:\n"

            . $youtube;
    }
}






// =====================================================
// SAVE CHAT HISTORY
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
// RESPONSE
// =====================================================


echo json_encode([


    "reply" => $ai_reply,


    "chat_id" => $chat_id



], JSON_UNESCAPED_UNICODE);

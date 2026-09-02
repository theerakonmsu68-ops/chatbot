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



$chat_id = $data['chat_id'] ?? null;

$user_id = $_SESSION['user_id'] ?? null;




// =====================================================
// ตรวจสอบ Login
// =====================================================


if (!$user_id) {


    http_response_code(401);


    echo json_encode([

        "status"=>"unauthorized",

        "message"=>"Please login again"

    ], JSON_UNESCAPED_UNICODE);



    exit;

}




if (!$chat_id) {


    http_response_code(400);


    echo json_encode([

        "status"=>"error",

        "message"=>"Missing chat_id"

    ], JSON_UNESCAPED_UNICODE);



    exit;

}





// =====================================================
// DELETE CHAT
// =====================================================


$stmt = $conn->prepare("

DELETE FROM chat_history

WHERE user_id = ?

AND chat_id = ?

");




if(!$stmt){


    http_response_code(500);


    echo json_encode([

        "status"=>"error",

        "message"=>"Database prepare failed"

    ], JSON_UNESCAPED_UNICODE);



    exit;

}





$stmt->bind_param(

    "ss",

    $user_id,

    $chat_id

);






if($stmt->execute()){



    echo json_encode([

        "status"=>"success"

    ], JSON_UNESCAPED_UNICODE);



}else{



    http_response_code(500);



    echo json_encode([

        "status"=>"error",

        "message"=>"Database execution failed"

    ], JSON_UNESCAPED_UNICODE);



}





$stmt->close();

$conn->close();


?>
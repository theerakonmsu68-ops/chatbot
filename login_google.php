<?php

session_start();

header('Content-Type: text/html; charset=utf-8');


// =====================================================
// Google OAuth Config จาก Environment
// =====================================================

$client_id = getenv("GOOGLE_CLIENT_ID");

$client_secret = getenv("GOOGLE_CLIENT_SECRET");

$redirect_uri = getenv("GOOGLE_REDIRECT_URI");



// ตรวจสอบ Config

if(
    empty($client_id) ||
    empty($client_secret) ||
    empty($redirect_uri)
){

    die("Google OAuth configuration missing");

}



// =====================================================
// ตรวจสอบ Code จาก Google
// =====================================================


if(!isset($_GET['code'])){

    header("Location: index.php");

    exit();

}





// =====================================================
// แลก Authorization Code เป็น Access Token
// =====================================================


$post_fields = [

    "code" => $_GET['code'],

    "client_id" => $client_id,

    "client_secret" => $client_secret,

    "redirect_uri" => $redirect_uri,

    "grant_type" => "authorization_code"

];




$ch = curl_init(
    "https://oauth2.googleapis.com/token"
);



curl_setopt_array($ch,[


    CURLOPT_POST => true,


    CURLOPT_POSTFIELDS =>
        http_build_query($post_fields),


    CURLOPT_RETURNTRANSFER => true,


    CURLOPT_SSL_VERIFYPEER => true,


    CURLOPT_SSL_VERIFYHOST => 2


]);




$response = curl_exec($ch);



if(curl_errno($ch)){


    die(
        "Google OAuth Error : "
        .curl_error($ch)
    );

}


curl_close($ch);



$token_data =
json_decode(
    $response,
    true
);





if(!isset($token_data['access_token'])){


    die(
        "Cannot get Google Access Token"
    );


}





// =====================================================
// ดึงข้อมูล User จาก Google
// =====================================================


$user_url =
"https://www.googleapis.com/oauth2/v2/userinfo?access_token="
.$token_data['access_token'];




$ch_user = curl_init();



curl_setopt_array($ch_user,[


    CURLOPT_URL=>$user_url,


    CURLOPT_RETURNTRANSFER=>true,


    CURLOPT_SSL_VERIFYPEER=>true,


    CURLOPT_SSL_VERIFYHOST=>2


]);



$user_response =
curl_exec($ch_user);



curl_close($ch_user);



$user_info =
json_decode(
    $user_response,
    true
);





// =====================================================
// สร้าง Session
// =====================================================


if(isset($user_info['id'])){


    $_SESSION['user_id'] =
        $user_info['id'];


    $_SESSION['user_name'] =
        $user_info['name'] ?? "";


    $_SESSION['user_picture'] =
        $user_info['picture'] ?? "";


    $_SESSION['user_email'] =
        $user_info['email'] ?? "";



    header(
        "Location: index.php"
    );


    exit();


}




echo "Login Failed: ไม่สามารถดึงข้อมูลผู้ใช้จาก Google ได้";


?>
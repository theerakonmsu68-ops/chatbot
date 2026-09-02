<?php

// ปิด mysqli error แสดงตรงหน้าเว็บ
mysqli_report(MYSQLI_REPORT_OFF);


// =====================================================
// TiDB Cloud Config จาก Render Environment
// =====================================================

$host = getenv("DB_HOST");

$user = getenv("DB_USER");

$pass = getenv("DB_PASSWORD");

$db_name = getenv("DB_NAME");

$port = getenv("DB_PORT") ?: 4000;




// =====================================================
// ตรวจสอบ Environment
// =====================================================

if(
    empty($host) ||
    empty($user) ||
    empty($pass) ||
    empty($db_name)
){

    die("Database environment variables are missing");

}





// =====================================================
// TiDB SSL Connection
// =====================================================

$conn = mysqli_init();



if(!$conn){

    die("Cannot initialize database connection");

}




// SSL สำหรับ TiDB Cloud

mysqli_ssl_set(
    $conn,
    null,
    null,
    null,
    null,
    null
);





$connected = $conn->real_connect(

    $host,

    $user,

    $pass,

    $db_name,

    $port,

    null,

    MYSQLI_CLIENT_SSL

);





if(!$connected){

    error_log(
        "TiDB Connection Error: "
        .$conn->connect_error
    );


    die(
        "Database connection failed"
    );

}





// UTF-8 Support

$conn->set_charset("utf8mb4");



?>
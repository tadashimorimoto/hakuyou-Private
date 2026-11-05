<?php
/*
hakyouCOnfig.php 
version 2026-01-00 by 2026/03/23
 copyright (c) Tadashi Asano All Rights Reserved.
*/
// ===============================
// 構成情報
// hakuyouConfig.php
// DB接続情報
// ===============================
define('DEBUG', false);//true  userlogファイルにlogを記録
define('PRIVATE_PATH', $_SERVER['DOCUMENT_ROOT'] . '/../private/');
define('PUBLIC_PATH', $_SERVER['DOCUMENT_ROOT'] . '/');
define('COMMONLIB_PATH', $_SERVER['DOCUMENT_ROOT'] . '/../commonLib/');
define('PHPMAILER_PATH', $_SERVER['DOCUMENT_ROOT'] . '/../PHPMailer/');
$DbConfig = [
        'host' => 'localhost',
        'dbname' => 'tasano8025_hakuyou',
        'charset' => 'utf8mb4',
        'user' => 'tasano8025_haku',
        'pass' => 'hakuyouteikyukai'
];
define('SMTP_Config',     [
        'host' => 'smtp.gmail.com',
        'charset' => 'utf8mb4',
        'from' => 'hakuyouteikyukai@gmail.com',
        'username' => 'hakuyouteikyukai@gmail.com',
        'password' => 'caxoaoxpnqjgswhi' // Gmailのアプリパスワード を取得したもの
]);


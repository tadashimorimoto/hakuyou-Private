<?php
/*
hakuyou_fetch class="php"
hakuyou/index.html
version 2026-01-00 by 2026/03/23
 copyright (c) Tadashi Asano All Rights Reserved.
*/
/* JSとPHPを繋ぐ */
//ここでPHP環境をすべて設定する
ini_set('display_errors', 0);
error_reporting(E_ALL);
// config for hakuyou
require_once $_SERVER['DOCUMENT_ROOT'] . '/../private/hakuyou/config/hakuyouConfig.php';
// 共通ユーティリティー functions
//  usrlog関数を含む
define('LOGFILE', PUBLIC_PATH . 'hakuyou/logs/userlog.log');// userlogのファイルパスを定義
require_once COMMONLIB_PATH . 'userLib/userFunctions.php';
debuglog('enter');// userlogを書き出す 
//includeing BaseFetch class
require_once COMMONLIB_PATH . 'BaseApi.php';//必須 
require_once COMMONLIB_PATH . 'DbAccessClass.php';//必須 DB関連
require_once PRIVATE_PATH . 'hakuyou/class/HakuyouApi.php';//必須 アプリロジック
require_once PRIVATE_PATH . 'hakuyou/class/UserClass.php';//必須  usrTable アクセスロジック
//require_once COMMONLIB_PATH . 'Response.php';//必須 JSON RESPONSE
//PHPMailer
require_once COMMONLIB_PATH . '/userLib/MailUtil.php';//PHPMailerの機能
// DBインスタンス
//DB接続
debuglog('hakuyou_fetch.php start');
try {
    $db = new DbAccess($DbConfig);
} catch (PDOException $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => 'データベースに接続できません',
        'errorCode' => $e->getCode(),
        'errorMessage' => $e->getMessage()

    ], JSON_UNESCAPED_UNICODE);
    debuglog('DB接続エラー: ' . $e->getCode() . ' - ' . $e->getMessage());
    exit;
}
debuglog('DB接続完了');
//  BaseApi.phpのrun()を通して、
//  HakuyouApi.phpのfunctionを呼び出す
try {
    $api = new HakuyouFetch($db);
    debuglog('hakuyou RUN');
    $api->run();

} catch (Throwable $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    exit;
}

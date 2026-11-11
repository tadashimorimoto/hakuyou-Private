<?php
/*
HakuyouApi.php

version 2026-07-31 by 2026/07/31
 copyright (c) Tadashi Asano All Rights Reserved.
*/
//　jsからhakuyou_fetch.php経由で渡された command　で指定された
//  関数が呼び出される
//
debuglog('required');
//require_once COMMONLIB_PATH . '/BaseApi.php';

//PHP SESSION start 
//session_nameは同じＰＨＰで動くアプリを
// 区別する
if (session_status() === PHP_SESSION_NONE) {
    session_name("hakuyou");
    session_start();
    session_set_cookie_params([
        'path' => '/hakuyou/'
    ]);
}

class HakuyouFetch extends BaseFetch
{    

    protected DbAccess $db;
    public function __construct($db)
    {
    $this->db = $db;
        parent::__construct();
    }
//logut()
// session情報を消す
    protected function logout(): void
    {
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }
        session_destroy();
        $this->response([
            'success' => true
        ]);
    }
// 2段階ログインのパスワード確認フェーズ
    protected function login2phase(): void
    {
        $this->login();
    }
    // メールで受け取った6桁コードを検証し、成功後にログインセッションを作る
    protected function verify2phase(): void
    {
        $challenge = $_SESSION['login2phase'] ?? null;
        $code = trim((string)($this->input['code'] ?? ''));

        if (!is_array($challenge) || !isset($challenge['expiresAt'], $challenge['codeHash'], $challenge['user'])) {
            unset($_SESSION['login2phase']);
            $this->response([
                'success' => false,
                'message' => '認証コードを最初から取得してください'
            ]);
        }

        if (time() > $challenge['expiresAt']) {
            unset($_SESSION['login2phase']);
            $this->response([
                'success' => false,
                'message' => '認証コードの有効期限が切れました。もう一度ログインしてください'
            ]);
        }

        if (($challenge['attempts'] ?? 0) >= 5) {
            unset($_SESSION['login2phase']);
            $this->response([
                'success' => false,
                'message' => '認証コードの入力回数が上限に達しました。もう一度ログインしてください'
            ]);
        }

        if (!preg_match('/^\d{6}$/', $code) || !password_verify($code, $challenge['codeHash'])) {
            $challenge['attempts'] = ($challenge['attempts'] ?? 0) + 1;
            if ($challenge['attempts'] >= 5) {
                unset($_SESSION['login2phase']);
                $this->response([
                    'success' => false,
                    'message' => '認証コードの入力回数が上限に達しました。もう一度ログインしてください'
                ]);
            }
            $_SESSION['login2phase'] = $challenge;
            $this->response([
                'success' => false,
                'message' => '認証コードが正しくありません'
            ]);
        }

        $user = $challenge['user'];
        unset($_SESSION['login2phase']);
        session_regenerate_id(true);
        $this->setSessionInfo($user);

        $this->response([
            'success' => true,
            'data' => [
                'uid' => $user['uid'],
                'email' => $user['email'],
                'name' => $user['name'],
                'userType' => $user['userType']
            ]
        ]);
    }

    protected function login(): void
    //  パラメーターでわたされたuser情報を
    // PHP session　にセットする
    //LOGIN
    // email と　passwordを　dbでチェック
    {
        debuglog('enter HakuyouApi.php login()');
        $email = $this->input['email'] ?? '';
        $pass  = $this->input['password'] ?? '';
        $userModel = new UserClass($this->db);
        //  UserClassのlogin関数で、DBアクセス
        $user = $userModel->login($email, $pass);
        if (!$user) {
            $resp['success'] = false;
            $resp['message'] = 'email または　パスワードがちがいます';
            $this->response($resp);
        }
        unset($_SESSION['login2phase']);
        $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $smtp = SMTP_Config;

        $_SESSION['login2phase'] = [
            'user' => [
                'uid' => $user['uid'],
                'email' => $user['email'],
                'name' => $user['name'],
                'userType' => $user['userType']
            ],
            'codeHash' => password_hash($code, PASSWORD_DEFAULT),
            'expiresAt' => time() + 300,
            'attempts' => 0
        ];

        try {
            $mail = new PHPMailUtil($smtp);
            $sent = $mail->sendMail([
                'to' => $user['email'],
                'subject' => '【白楊庭球会NAVI】ログイン認証コード',
                'body' => "ログイン認証コードは {$code} です。5分以内に入力してください。",
                'from' => $smtp['from'],
                'fromName' => '白楊庭球会NAVI'
            ]);
        } catch (Throwable $e) {
            $sent = false;
        }

        if (!$sent) {
            unset($_SESSION['login2phase']);
            debuglog('2段階ログインの認証コード送信に失敗しました');
            $this->response([
                'success' => false,
                'message' => '認証コードをメールで送信できませんでした。時間をおいて再試行してください'
            ]);
        }

        $this->response([
            'success' => true,
            'requires2phase' => true,
            'message' => '登録メールアドレスに6桁の認証コードを送信しました'
        ]);
    }
    //  パラメーターでわたされたuser情報を
    // PHP session　にセットする
    protected function setSessionInfo($data): void
    {
        debuglog('enter HakuyouApi.php setSessionInfo()');
        //  $data = $this->input;
        $_SESSION['uid'] = $data['uid'] ?? '';
        $_SESSION['email'] = $data['email'] ?? '';
        $_SESSION['name'] = $data['name'] ?? '';
        $_SESSION['userType'] = $data['userType'] ?? '';
        $_SESSION['authCode'] = $data['authCode'] ?? '';
        $_SESSION['authCodeExpire'] = $data['authCodeExpire'] ?? '';
    }

    /// getSessionInfo
    // php session情報にセットされている
    // 情報を返す
    protected function getSessionInfo(): void
    {
        debuglog('getSessionInfo()');
        if (isset($_SESSION['uid'])) {
            $resp['success'] = true;
            $resp['data'] = [
                'uid' => $_SESSION['uid'],
                'email' => $_SESSION['email'],
                'name' => $_SESSION['name'],
                'userType' => $_SESSION['userType'],
                'authCode' => $_SESSION['authCode'],
                'authCodeExpire' => $_SESSION['authCodeExpire']
            ];
        } else {
            $resp['success'] = false;
            $resp['message'] = 'セッションがありません';
        }
        debuglog('getSessionInfo result: ', $resp);
        $this->response($resp);
    }

// 
// updateUser
// 
protected   function updateUser(): void
    {
        debuglog('enter HakuyouApi.php updateUser()');
        $userModel = new UserClass($this->db);
        $data = $this->input;
        // --- 全項目取得（なければ空 or null） ---
        $email     = trim($data['email'] ?? '');
        $ki        = trim($data['ki'] ?? '');
        $name      = trim($data['name'] ?? '');
        $password  = $data['password'] ?? '';
        // --- 必須チェック ---
        if (!$email || !$ki || !$name) {
            throw new Exception('email, 期, 名前は必須項目です');
        }
        debuglog('updateUser input data=', $data);
        $user = $userModel->updateUser($this->input);
        if (!$user) {
            throw new Exception('ユーザーの更新に失敗しました');
        }
        if ($_SESSION['uid'] === $user['uid']) {
            $this->setSessionInfo($user);
        };
        $resp['success'] = true;
        $resp['data'] = $user;
        $this->response($resp);
    }

    protected function createUser(): void
    {
        debuglog('enter HakuyouApi.php createUser()');
        $userModel = new UserClass($this->db);
        $data = $this->input;
        // --- 全項目取得（なければ空 or null） ---
        $email     = trim($data['email'] ?? '');
        $ki        = trim($data['ki'] ?? '');
        $name      = trim($data['name'] ?? '');
        $password  = $data['password'] ?? '';
        // --- 必須チェック ---
        if (!$email || !$ki || !$name || !$password) {
            throw new Exception('email, 期, 名前, パスワードは必須項目です');
        }
        $user = $userModel->createUser($this->input);
        $resp['success'] = true;
        $resp['data'] = $user;
        $this->response($resp);
    }
    //serchUser function
    protected function searchUser(): void
    {
        debuglog('enter HakuyouApi.php searchUser()');
        $userModel = new UserClass($this->db);
        $data = $this->input;

        $email = trim($data['email'] ?? '');
        $ki = trim($data['ki'] ?? '');
        $name = trim($data['name'] ?? '');


        if (!$email && !$ki && !$name) {
            throw new Exception('email, 期, 名前のいずれかは必須項目です');
        }

        $user = $userModel->searchUsers($data);
        if (!$user) {
            $resp['success'] = false;
            $resp['message'] = 'ユーザーが見つかりませんでした';
            $this->response($resp);
        }
        $resp['success'] = true;
        $resp['data'] = $user;
        $this->response($resp);
    }
    protected function getUserByUid(): void
    {
        $userModel = new UserClass($this->db);
        $data = $this->input;

        $uid = trim($data['uid'] ?? '');

        if (!$uid) {
            throw new Exception('uidは必須項目です');
        }
        // debuglog('enter HakuyouApi.php getUserByUid() ---');
        $user = $userModel->getUserByUid($uid);
        // debuglog('enter HakuyouApi.php getUserByUid() 2');
        if (!$user) {
            $resp['success'] = false;
            $resp['message'] = 'ユーザーが見つかりませんでした';
            $this->response($resp);
        }
        debuglog('getUserByUid result:$user ');
        $resp['success'] = true;
        $resp['data'] = $user;
        $this->response($resp);
    }
    protected function changePassword(): void
    {
        debuglog('enter HakuyouApi.php changePassword()');
        $userModel = new UserClass($this->db);
        $data = $this->input;
        // --- 全項目取得（なければ空 or null） ---
        $uid     = trim($data['uid'] ?? '');
        $currentPassword  = $data['currentPassword'] ?? '';
        $newPassword  = $data['newPassword'] ?? '';
        // --- 必須チェック ---
        if (!$uid || !$currentPassword || !$newPassword) {
            throw new Exception('uid, 現在のパスワード, 新しいパスワードは必須項目です');
        }
        $result = $userModel->changePassword($uid, $currentPassword, $newPassword);
        if (!$result) {
            throw new Exception('パスワードの変更に失敗しました');
        }
        $resp['success'] = true;
        $resp['data'] = $result;
        $this->response($resp);
    }
    protected function deleteUser(): void
    {
        debuglog('enter HakuyouApi.php deleteUser()');
        $userModel = new UserClass($this->db);
        $data = $this->input;

        $uid = trim($data['uid'] ?? '');

        if (!$uid) {
            throw new Exception('uidは必須項目です');
        }

        $result = $userModel->deleteUser($uid);
        if (!$result) {
            throw new Exception('ユーザーの削除に失敗しました');
        }
        $resp['success'] = true;
        $this->response($resp);
    }
    /////////////////////////////////
    //  ユーザー登録申請
    ////////////////////////////////       
    protected function requestRegister(): void
    {
        debuglog('enter HakuyouApi.php requestRegister()');
        $userModel = new UserClass($this->db);
        $data = $this->input;
        // --- 全項目取得（なければ空 or null） ---
        $email     = trim($data['email'] ?? '');
        $ki        = trim($data['ki'] ?? '');
        $name      = trim($data['name'] ?? '');
        $data['password'] = bin2hex(random_bytes(4));
        // --- 必須チェック ---
        if (!$email || !$ki || !$name || !$data['password']) {
            throw new Exception('email, 期, 名前, パスワードは必須項目です');
        }

      //PHPMailerで受付メールを送信　
      //smtpでおくる
      $smtpParms=SMTP_Config;
           if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            // 件名

            // 本文
            $message = <<<EOD
{$name} 様

白楊庭球会NAVIへの登録申請を受け付けました。
管理者の承認後,LOGIN用仮パスワードが発行されます。
仮パスワードでログイン後、マイページからパスワードの変更をお願いいたします。

────────────────────
白楊庭球会

なお、このメールに心当たりがない場合は、お手数ですがこのメールを破棄してください。
ご質問などございましたら、下記のメールアドレスまでお問い合わせください。
お問い合わせ:   　hakuyouteikyukai@gmail.com
EOD;
            $mailParam = [
                "to" => [$email],
                "bcc" => $smtpParms['from'],
                "subject" => "【白楊庭球会NAVI】登録申請受付のお知らせ",
                "body" => $message,
                "altBody" => "",
                "from" => $smtpParms['from'],
                "smtp" => [
                    "host" => $smtpParms['host'],
                    "username" => $smtpParms['username'],
                    "password" => $smtpParms['password']
                ],
                "fromName" => "白楊庭球会"
            ];
            try {
                $mail = new PHPMailUtil($smtpParms);
                $result = $mail->sendMail($mailParam);
                if ($result) {
                    debuglog('メール送信成功');
                    $resp['success'] = true;
                } else {
                    debuglog('メール送信失敗');
                    $resp['success'] = false;
                    $resp['message'] = 'メール送信に失敗しました';
        }

               // $result = PHPMailSender::sendMail($mailParam);
               // ($result ? debuglog('メール送信成功') : debuglog('メール送信失敗'));
               // $resp['success'] = true;
            } catch (Exception $e) {
                debuglog('メール送信エラー: ' . $e->getMessage());
                $resp['success'] = false;
                $resp['message'] = "メール送信に失敗しました";
            }
            $this->response($resp);
        }
    }
    protected function listNotApproved(): void
    {
        debuglog('enter HakuyouApi.php liostNotapproved()');
        $userModel = new UserClass($this->db);
        //  $data = $this->input;

        //        $uid = trim($data['uid'] ?? '');

        $result = $userModel->listNotApproved();
        if (!$result) {
            throw new Exception('承認保留ユーザーの取得に失敗しました');
        }
        $resp['success'] = true;
        $this->response($resp);
    }
}

<?php
// 
// HakuyouApi.php classの中で、ユーザー認証やメール送信などの機能を提供するクラスです。 
// 
debuglog('required UserClass.php');

class UserClass
{
    private DbAccess $db;

    public function __construct(DbAccess $db)
    {
        $this->db = $db;
    }
    ////////////////////////////////////////////////////////////////////////  
    // single login     usernameとpasswordをチェックして、ユーザー情報を返す
    ////////////////////////////////////////////////////////////////////////  
    public function login(string $email, string $pass): ?array
    {
        //$sql = "SELECT id, email, passwordHash FROM usrtbl 
        //        WHERE email = :email 
        //        LIMIT 1";
        //   $sql = "SELECT id, email, passwordHash , name, userType ,authoCode,authCodeExpire
        $sql = "SELECT uid, email, passwordHash , name, userType ,authCode,authCodeExpire
                        FROM usrtbl 
                        WHERE email = :email 
                        LIMIT 1";

        $user = $this->db->fetch($sql, [
            ':email' => $email
        ]);

        if (!$user) {
            return null;
        }

        if (!password_verify($pass, $user['passwordHash'])) {
            return null;
        }
        // --- loginTime 更新 ---
        $sql = "UPDATE usrtbl SET loginTime = :loginTime WHERE uid = :uid";
        $this->db->execute($sql, [
            ':loginTime' => date('Y-m-d H:i:s'),
            ':uid' => $user['uid']
        ]);
        return $user;
    }

    ////////////////////////////////////////////////////////////////////////  
    // createUser     新しいユーザーを作成する
    ////////////////////////////////////////////////////////////////////////  
    public function createUser(array $data): void
    {

        debuglog('createUser()');
        //        debuglog($data);
        // --- 全項目取得（なければ空 or null） ---
        $email     = trim($data['email'] ?? '');
        $ki        = trim($data['ki'] ?? '');
        $name      = trim($data['name'] ?? '');
        $password  = $data['password'] ?? '';
        $zip       = trim($data['zip'] ?? '');
        $address   = trim($data['address'] ?? '');
        $tel       = trim($data['tel'] ?? '');
        $birthday  = $data['birthday'] ?? null;
        $userType  = $data['userType'] ?? 'user';
        $ml        = !empty($data['MLTouroku']) ? 1 : 0;
        $approved  = 0; // 登録時は未承認
        $creationDate = date('Y-m-d H:i:s');
        // userType補正
        if (!in_array($userType, ['admin', 'user'])) {
            $userType = 'user';
        }
        // メール重複チェック
        $sql = "SELECT uid, email  FROM usrtbl   WHERE email = :email    LIMIT 1";
        $exist = $this->db->fetch($sql, [
            ':email' => $email
        ]);
        if ($exist) {
            throw new Exception('このメールは既に登録されています');
        }
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        //genereta uid = id 
        $uid = substr(
            rtrim(strtr(base64_encode(random_bytes(20)), '+/', '-_'), '='),
            0,
            26
        );
        // --- 登録（全部渡す） ---
        $sql = "INSERT INTO usrtbl
                    (uid,email,ki, name, approved, passwordHash, userType, MLTouroku,
                     zip, address, tel, birthday,creationDate)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?,?,? ,?)";
        $params = [
            $uid,
            $email,
            $ki,
            $name,
            $approved,
            $hashed,
            $userType,
            $ml,
            $zip,
            $address,
            $tel,
            $birthday,
            $creationDate
        ];
        $stmt = $this->db->execute($sql, $params);
    }
    ////////////////////////////////////////////////////////////////////////  
    // updateUser     既存のユーザー情報を更新する
    ////////////////////////////////////////////////////////////////////////  
  public function updateUser(array $data): ?array
    {
        debuglog('updateUser() data=',$data);
      
        // --- 取得 ---
        $uid      = trim($data['uid'] ?? '');
        $email    = trim($data['email'] ?? '');
        $ki       = trim($data['ki'] ?? '');
        $name     = trim($data['name'] ?? '');
        $password = $data['password'] ?? '';
        $approved = !empty($data['approved']) ? 1 : 0;
        $zip      = trim($data['zip'] ?? '');
        $address  = trim($data['address'] ?? '');
        $tel      = trim($data['tel'] ?? '');
        $birthday = $data['birthday'] ?? null;
        $userType = $data['userType'] ?? 'user';
        $ml       = !empty($data['MLTouroku']) ? 1 : 0;
        $updateDate = date('Y-m-d H:i:s');

        if (!$uid) {
            throw new Exception("uid missing");
        }

        // --- birthday補正 ---
        if (!$birthday || $birthday === '0000-00-00') {
            $birthday = null;
        }

        // --- userType補正 ---
        if (!in_array($userType, ['admin', 'user'])) {
            $userType = 'user';
        }

        // --- SQL ---
        $sql = "UPDATE usrtbl SET
        email=:email,
        ki=:ki,
        name=:name,
        userType=:userType,
        approved=:approved,
        MLTouroku=:MLTouroku,
        zip=:zip,
        address=:address,
        tel=:tel,
        birthday=:birthday,
        updateDate=:updateDate";
        $params = [
            ':email' => $email,
            ':ki' => $ki,
            ':name' => $name,
            ':userType' => $userType,
            ':approved' => $approved,
            ':MLTouroku' => $ml,
            ':zip' => $zip,
            ':address' => $address,
            ':tel' => $tel,
            ':birthday' => $birthday,
            ':updateDate' => $updateDate,
            ':uid' => $uid  // ←追加
        ];

        // --- パスワード更新 
        if (!empty($password)) {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $sql .= ", passwordHash=:passwordHash";
            $params[':passwordHash'] = $hashed;
        }

        $sql .= " WHERE uid=:uid";

        $this->db->execute($sql, $params);

        return $this->getUserByUid($uid);
    }
    ////////////////////////////////////////////////////////////////////////  
    // getUserByUid     uidを指定してユーザー情報を取得する
    ////////////////////////////////////////////////////////////////////////  
    public function getUserByUid(string $uid): ?array
    {
        debuglog('getUserById()');

        $sql = "SELECT  *
            FROM usrtbl
            WHERE uid = :uid
            LIMIT 1";

        $row = $this->db->fetch($sql, [':uid' => $uid]);

        if (!$row) {
            return null;
        }

        // birthday補正
        if ($row['birthday'] === '0000-00-00') {
            $row['birthday'] = null;
        }

        return $row;
    }
    ////////////////////////////////////////////////////////////////////////  
    // searchUsers     ユーザーを検索する
    ////////////////////////////////////////////////////////////////////////    
    public function searchUsers(array $params): ?array
    {
        debuglog('searchUsers()');
        $results = [];
        //検索SQL作成
        $sql = "SELECT * from usrtbl ";
        $wheres = [];
        $parm = [];

        if (!empty($params['email'])) {
            $wheres[] = "email LIKE :email";
            $parm[':email'] = '%' . $params['email'] . '%';
        }
        if (!empty($params['name'])) {
            $wheres[] = "   name LIKE :name";
            $parm[':name'] = '%' . $params['name'] . '%';
        }
        if (!empty($params['ki']) && $params['ki'] > 0) {
            $wheres[] = "ki = :ki";
            // $params[':ki'] = '%' . $parms['ki'] . '%';
            $parm[':ki'] =  $params['ki'];
        }

        if ($wheres) {
            $sql .= " WHERE " . implode(" AND ", $wheres);
        }

        $results = $this->db->fetchAll($sql, $parm);
        return $results;
    }
    ////////////////////////////////////////////////////////////////////////  
    // deleteUser     ユーザーを削除する    
    ////////////////////////////////////////////////////////////////////////
    public function deleteUser(string $uid)
    {

        debuglog('deleteUser()');
        $sql = "DELETE FROM usrtbl WHERE uid = :uid";
        $stmt = $this->db->execute($sql, [':uid' => $uid]);
        return $stmt;
    }
    public function changePassword(string $uid, string $currentPassword, string $newPassword): array
    {
        debuglog('changePassword()');
        // --- ユーザー取得 ---
        $user = $this->getUserByUid($uid);
        if (!$user) {
            throw new Exception('ユーザーが見つかりません');
        }

        // --- 現在のパスワード確認 ---
        if (!password_verify($currentPassword, $user['passwordHash'])) {
            throw new Exception('現在のパスワードが正しくありません');
        }

        // --- パスワード更新 ---
        $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
        $sql = "UPDATE usrtbl SET passwordHash=:passwordHash WHERE uid=:uid";
        $this->db->execute($sql, [
            ':passwordHash' => $hashed,
            ':uid' => $uid
        ]);

        // --- 更新後のユーザー情報を返す ---
        $user = $this->getUserByUid($uid);
        return $user;
    }
    public function requestRegister($data): void
    {
        debuglog('requestRegistor()');
        $email = $data['email'] ?? '';
        $ki = $data['ki'] ?? '';
        $name = $data['name'] ?? '';
        // --- メール重複チェック ---
        $sql = "SELECT *   from usrtbl WHERE email = :email    LIMIT 1";
        $exist = $this->db->fetch($sql, [
            ':email' => $email
        ]);
        if ($exist) {
            throw new Exception('このメールは既に登録されています');
        }
        // --- 登録処理 ---
        $this->createUser([
            'email' => $email,
            'ki' => $ki,
            'name' => $name,
            'approved' => 0
        ]);
    }
}

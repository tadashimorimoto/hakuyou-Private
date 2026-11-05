# userLib ユーザーライブラリ　

## 1 Directry  
```
├─ public/ <---document ROOT 
│  ├─ prject dir 
|     ├─ README.md
│     ├─ index.php
│     ├─ 
│     └─ css/
│         └─ style.css
├─ private     
│  ├─ project dir 
|
|
├─ commonLib  ──
|  ├── << userLib >>
|        ├──readme.md  
|        ├──config.php
|        ├──UserClass.php
|        ├──DbAccessClass.php

```
## 2 コントロール
```
→fetch ----->　2PCommit_fetch ($params);
                     　・config.php から構成情報を取得
                        　//DB 関連情報
                        ・受け取ったパラメーターにより必要な
                           userClass.php のfunctionを呼び出す
                                  -->userClass.php  // 必要関数の集まり    
                        ・ＲＥＳＰＯＮＳＥ　ＪＳＯＮを返す
```



## 3. 使い方
### 3.1 
fetch_api.php を jsから呼び出せる一に配置。
### 3.2
 fetch_api.php から　userLibのPATHを設定 
### 3.3  fetch の呼び出し方
```

FETCH CODE

```

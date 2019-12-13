# TweetHistory2SQLite3
TwitterからDLできる「Twitterデータ」のツイートたちをSQLite3化する

ツイート履歴のDLができなくなったため、従来のものが使えなくなった。  
`retweeted_status`や`user`オブジェクトが含まれていない「Twitterデータ(笑)」だけどこれしかないので使わざるを得ない。

## DBの作成
```
php tweet2sqlite3.php /path/to/TwitterDataDirectory [/path/to/database]
```

## ツイート取得
```
/tweet2sqlite3.php
```

### GET Params
* NONE: 最新の50件を取得
* `page`: ページ
* `count`: 数
* `query`: ツイート本文から検索
* `targetId`: 対象ツイートIDの前後のツイートを取得
    - デフォルトでは前後5件ずつ計11ツイートの取得
    - `count`で件数変更可能
    - `page`は使わないと思うので非対応
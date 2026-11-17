# TweetHistory2SQLite3
Twitter から DL できる「Twitterデータ」のツイートたちを SQLite3 化する。

ツイート履歴の DL ができなくなったため、従来のものが使えなくなった。  
`retweeted_status` や `user` オブジェクトが含まれていない「Twitterデータ(笑)」だがこれしかないので使わざるを得ない。

## DB 作成
```
php tweet2sqlite3.php /path/to/TwitterDataDirectory [/path/to/database]
```

## ツイート取得
```
GET /tweet2sqlite3.php
```

### GET Params
* NONE: 最新の 50 件を取得
* `page`: ページ
* `count`: 数
* `query`: ツイート本文から検索
* `targetId`: 対象のツイート ID の前後のツイートを取得
  - デフォルトでは前後 5 件ずつ、計 11 ツイートの取得
  - `count` で件数変更可能
* `since`: 日時から
* `until`: 日時まで

### 併用
\ | page | count | query | targetId | since | until
--- | :---: | :---: | :---: | :---: | :---: | :---:
page | \ | ✅ | ✅ | | ✅ | ✅
count | ✅ | \ | ✅ | ✅ | ✅ | ✅
query | ✅ | ✅ | \ | | ✅ | ✅
targetId | | ✅ | | \ | |
since | ✅ | ✅ | ✅ | | \ | ✅
until | ✅ | ✅ | ✅ | | ✅ | \

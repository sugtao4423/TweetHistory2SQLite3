# TweetHistory2SQLite3
TwitterからDLできる「Twitterデータ」のツイートたちをSQLite3化する

ツイート履歴のDLができなくなったため、従来のものが使えなくなった。  
`retweeted_status`や`user`オブジェクトが含まれていない「Twitterデータ(笑)」だけどこれしかないので使わざるを得ない。

SQLite3にJSONを解釈してくれる神機能があるのでそれをメインに使っていく。  
1スクリプトでapiとしても使えるようにしていこうと思う。

## DBの作成
```
php tweet2sqlite3.php /path/to/TwitterDataDirectory [/path/to/database]
```

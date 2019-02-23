# TweetHistory2SQLite3
TwitterからDLできるツイート履歴をSQLite3化する

## ファイルツリー
```
├── README.md
├── script
│   ├── dataclass/
│   └── tweet2sqlite3.php
├── tweets.sqlite3
└── twitterdata/
```

### twitterdata/
TwitterからDLしたツイート履歴のzipを解凍した中身

### tweet2sqlite3.php
メインスクリプト

### tweets.sqlite3
```
php tweet2sqlite3.php
```
したら生成される

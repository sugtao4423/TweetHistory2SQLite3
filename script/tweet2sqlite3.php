<?php
declare(strict_types=1);
ini_set('memory_limit', '1024M');

define('DATACLASSDIR', dirname(__FILE__) . '/dataclass');
require_once(DATACLASSDIR . '/UserMention.php');
require_once(DATACLASSDIR . '/User.php');
require_once(DATACLASSDIR . '/Source.php');
require_once(DATACLASSDIR . '/Url.php');
require_once(DATACLASSDIR . '/Media.php');
require_once(DATACLASSDIR . '/Status.php');

/*** config ***/
$jsFilesDir = dirname(__FILE__) . '/../twitterdata/data/js/tweets/';
$dbFile     = dirname(__FILE__) . '/../tweets.sqlite3';
/**************/

$db = new SQLite3($dbFile);
$db->enableExceptions(true);
$db->exec('BEGIN');

$db->exec('CREATE TABLE user_mentions (
    id INTEGER NOT NULL UNIQUE,
    name TEXT NOT NULL,
    screen_name TEXT NOT NULL
)');
$db->exec('CREATE TABLE users (
    id INTEGER NOT NULL UNIQUE,
    name TEXT NOT NULL,
    screen_name TEXT NOT NULL,
    protected INTEGER NOT NULL,
    profile_image_url_https TEXT NOT NULL,
    verified INTEGER NOT NULL
)');
$db->exec('CREATE TABLE sources (
    id INTEGER PRIMARY KEY NOT NULL,
    name TEXT NOT NULL,
    url TEXT NOT NULL,
    UNIQUE(name, url)
)');
$db->exec('CREATE TABLE medias (
    id INTEGER NOT NULL,
    url TEXT NOT NULL,
    media_url_https TEXT NOT NULL,
    display_url TEXT NOT NULL,
    expanded_url TEXT NOT NULL,
    media_alt TEXT
)');
$db->exec('CREATE TABLE urls (
    id INTEGER PRIMARY KEY NOT NULL,
    url TEXT NOT NULL,
    expanded_url TEXT NOT NULL,
    display_url TEXT NOT NULL,
    UNIQUE(url, expanded_url, display_url)
)');
$db->exec('CREATE TABLE statuses (
    id INTEGER NOT NULL UNIQUE,
    text TEXT NOT NULL,
    created_at INTEGER NOT NULL,
    in_reply_to_status_id INTEGER,
    in_reply_to_user_id INTEGER,
    in_reply_to_screen_name TEXT,
    geo TEXT,
    user_id INTEGER NOT NULL,
    user_mention_ids TEXT,
    media_ids TEXT,
    url_ids TEXT,
    source_id INTEGER NOT NULL,
    retweeted_status_id INTEGER
)');
$db->exec('CREATE TABLE retweeted_statuses (
    id INTEGER NOT NULL UNIQUE,
    text TEXT NOT NULL,
    created_at INTEGER NOT NULL,
    in_reply_to_status_id INTEGER,
    in_reply_to_user_id INTEGER,
    in_reply_to_screen_name TEXT,
    geo TEXT,
    user_id INTEGER NOT NULL,
    user_mention_ids TEXT,
    media_ids TEXT,
    url_ids TEXT,
    source_id INTEGER NOT NULL,
    retweeted_status_id INTEGER
)');

$count = 0;
echo '0 tweets done';
foreach(glob("${jsFilesDir}/*.js") as $js){
    $rawJson = file_get_contents($js);
    $rawJson = preg_replace('/Grailbird\.data\.tweets_\d{4}_\d{2}\s*?=/', '', $rawJson);
    $json = array_reverse(json_decode($rawJson, true));
    foreach($json as $j){
        $status = new Status($j);
        statusJson2db($db, $status);
        if($status->getRetweetedStatus() !== null){
            statusJson2db($db, $status->getRetweetedStatus(), true);
        }
        $count++;
        echo "\r${count} tweets done";
    }
}

$db->exec('COMMIT');
$db->close();
echo "\n";

function statusJson2db(SQLite3 $db, Status $status, bool $isRetweet = false){
    /*** Add User ***/
    addUser($db, $status->getUser());

    /*** Add User Mentions ***/
    foreach($status->getUserMentions() as $mention){
        addUserMention($db, $mention);
    }

    /*** Add Source ***/
    addSource($db, $status->getSource());

    /*** Add Medias ***/
    foreach($status->getMedias() as $media){
        addMedia($db, $media);
    }

    /*** Add Urls ***/
    foreach($status->getUrls() as $url){
        addUrl($db, $url);
    }

    /*** Add Status ***/
    addStatus($db, $status, $isRetweet);
}

function bool2int(bool $b): int{
    return $b ? 1 : 0;
}

function addUser(SQLite3 $db, User $user){
    try{
        $stmt = $db->prepare('INSERT OR IGNORE INTO users VALUES (
            :id, :name, :screen_name, :protected, :profile_image_url_https, :verified
        )');
        $stmt->bindValue(':id', $user->getId(), SQLITE3_INTEGER);
        $stmt->bindValue(':name', $user->getName(), SQLITE3_TEXT);
        $stmt->bindValue(':screen_name', $user->getScreenName(), SQLITE3_TEXT);
        $stmt->bindValue(':protected', bool2int($user->getProtected()), SQLITE3_INTEGER);
        $stmt->bindValue(':profile_image_url_https', $user->getProfileImageUrlHttps(), SQLITE3_TEXT);
        $stmt->bindValue(':verified', bool2int($user->getVerified()), SQLITE3_INTEGER);
        $stmt->execute();
    }catch(Exception $e){
        var_dump($e);
    }
}

function addUserMention(SQLite3 $db, UserMention $mention){
    try{
        $stmt = $db->prepare('INSERT OR IGNORE INTO user_mentions VALUES (
            :id, :name, :screen_name
        )');
        $stmt->bindValue(':id', $mention->getId(), SQLITE3_INTEGER);
        $stmt->bindValue(':name', $mention->getName(), SQLITE3_TEXT);
        $stmt->bindValue(':screen_name', $mention->getScreenName(), SQLITE3_TEXT);
        $stmt->execute();
    }catch(Exception $e){
        var_dump($e);
    }
}

function addSource(SQLite3 $db, Source $source){
    try{
        $stmt = $db->prepare('INSERT OR IGNORE INTO sources (name, url) VALUES (
            :name, :url
        )');
        $stmt->bindValue(':name', $source->getName(), SQLITE3_TEXT);
        $stmt->bindValue(':url', $source->getUrl(), SQLITE3_TEXT);
        $stmt->execute();
    }catch(Exception $e){
        var_dump($e);
    }
}

function addMedia(SQLite3 $db, Media $media){
    try{
        $stmt = $db->prepare('INSERT OR IGNORE INTO medias VALUES (
            :id, :url, :media_url_https, :display_url, :expanded_url, :media_alt
        )');
        $stmt->bindValue(':id', $media->getId(), SQLITE3_INTEGER);
        $stmt->bindValue(':url', $media->getUrl(), SQLITE3_TEXT);
        $stmt->bindValue(':media_url_https', $media->getMediaUrlHttps(), SQLITE3_TEXT);
        $stmt->bindValue(':display_url', $media->getDisplayUrl(), SQLITE3_TEXT);
        $stmt->bindValue(':expanded_url', $media->getExpandedUrl(), SQLITE3_TEXT);
        $stmt->bindValue(':media_alt', $media->getMediaAlt(), SQLITE3_TEXT);
        $stmt->execute();
    }catch(Exception $e){
        var_dump($e);
    }
}

function addUrl(SQLite3 $db, Url $url){
    try{
        $stmt = $db->prepare('INSERT OR IGNORE INTO urls (url, expanded_url, display_url) VALUES (
            :url, :expanded_url, :display_url
        )');
        $stmt->bindValue(':url', $url->getUrl(), SQLITE3_TEXT);
        $stmt->bindValue(':expanded_url', $url->getExpandedUrl(), SQLITE3_TEXT);
        $stmt->bindValue(':display_url', $url->getDisplayUrl(), SQLITE3_TEXT);
        $stmt->execute();
    }catch(Exception $e){
        var_dump($e);
    }
}

function addStatus(SQLite3 $db, Status $status, bool $isRetweet = false){
    try{
        $tableName = $isRetweet ? 'retweeted_statuses' : 'statuses';
        $stmt = $db->prepare("INSERT OR IGNORE INTO ${tableName} VALUES (
            :id, :text, :created_at,
            :in_reply_to_status_id, :in_reply_to_user_id, :in_reply_to_screen_name,
            :geo, :user_id, :user_mention_ids, :media_ids, :url_ids,
                (SELECT id FROM sources WHERE name = :source_name AND url = :source_url),
            :retweeted_status_id
        )");
        $stmt->bindValue(':id', $status->getId(), SQLITE3_INTEGER);
        $stmt->bindValue(':text', $status->getText(), SQLITE3_TEXT);
        $stmt->bindValue(':created_at', $status->getCreatedAt(), SQLITE3_INTEGER);

        $inReplyTo = $status->getInReplyTo();
        if($inReplyTo->getInReplyToStatusId() === null){
            $stmt->bindValue(':in_reply_to_status_id', null, SQLITE3_NULL);
        }else{
            $stmt->bindValue(':in_reply_to_status_id', $inReplyTo->getInReplyToStatusId(), SQLITE3_INTEGER);
        }
        if($inReplyTo->getInReplyToUserId() === null){
            $stmt->bindValue(':in_reply_to_user_id', null, SQLITE3_NULL);
        }else{
            $stmt->bindValue(':in_reply_to_user_id', $inReplyTo->getInReplyToUserId(), SQLITE3_INTEGER);
        }
        if($inReplyTo->getInReplyToScreenName() === null){
            $stmt->bindValue(':in_reply_to_screen_name', null, SQLITE3_NULL);
        }else{
            $stmt->bindValue(':in_reply_to_screen_name', $inReplyTo->getInReplyToScreenName(), SQLITE3_TEXT);
        }

        if($status->getGeo() === null){
            $stmt->bindValue(':geo', null, SQLITE3_NULL);
        }else{
            $stmt->bindValue(':geo', $status->getGeo(), SQLITE3_TEXT);
        }

        $stmt->bindValue(':user_id', $status->getUser()->getId(), SQLITE3_INTEGER);

        $userMentionIds = [];
        foreach($status->getUserMentions() as $mention){
            $userMentionIds[] = $mention->getId();
        }
        if(count($userMentionIds) > 0){
            $stmt->bindValue(':user_mention_ids', implode(',', $userMentionIds), SQLITE3_TEXT);
        }else{
            $stmt->bindValue(':user_mention_ids', null, SQLITE3_NULL);
        }

        $mediaIds = [];
        foreach($status->getMedias() as $media){
            $mediaIds[] = $media->getId();
        }
        if(count($mediaIds) > 0){
            $stmt->bindValue(':media_ids', implode(',', $mediaIds), SQLITE3_TEXT);
        }else{
            $stmt->bindValue(':media_ids', null, SQLITE3_NULL);
        }

        $urlIds = [];
        foreach($status->getUrls() as $url){
            $urlStmt = $db->prepare('SELECT id FROM urls WHERE url = ? AND expanded_url = ? AND display_url = ?');
            $urlStmt->bindValue(1, $url->getUrl(), SQLITE3_TEXT);
            $urlStmt->bindValue(2, $url->getExpandedUrl(), SQLITE3_TEXT);
            $urlStmt->bindValue(3, $url->getDisplayUrl(), SQLITE3_TEXT);
            $urlIds[] = $urlStmt->execute()->fetchArray(SQLITE3_NUM)[0];
        }
        if(count($urlIds) > 0){
            $stmt->bindValue(':url_ids', implode(',', $urlIds), SQLITE3_TEXT);
        }else{
            $stmt->bindValue(':url_ids', null, SQLITE3_NULL);
        }

        $stmt->bindValue(':source_name', $status->getSource()->getName(), SQLITE3_TEXT);
        $stmt->bindValue(':source_url', $status->getSource()->getUrl(), SQLITE3_TEXT);

        if($status->getRetweetedStatus() === null){
            $stmt->bindValue(':retweeted_status_id', null, SQLITE3_NULL);
        }else{
            $stmt->bindValue(':retweeted_status_id', $status->getRetweetedStatus()->getId(), SQLITE3_INTEGER);
        }
        $stmt->execute();
    }catch(Exception $e){
        var_dump($e);
    }
}

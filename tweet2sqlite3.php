<?php
declare(strict_types=1);

$isCreateDB = isset($argv[1]);
if($isCreateDB){
    createDB();
    exit(0);
}

// default DB location
define('SQLITE3_DB', __DIR__ . '/tweets.sqlite3');
// default json count
define('DEFAULT_COUNT', 50);
//default before after tweets count
define('DEFAULT_BEFORE_AFTER_COUNT', 5);

$page = intval($_GET['page'] ?? 1);
$count = intval($_GET['count'] ?? DEFAULT_COUNT);
$query = $_GET['query'] ?? null;
$beforeAfterTargetId = $_GET['targetId'] ?? null;

header('Content-Type: application/json');
try{
    $pdo = new PDO('sqlite:' . SQLITE3_DB);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if(isset($query)){
        $tweets = searchTweets($query, $page, $count);
    }else if(isset($beforeAfterTargetId)){
        $count = intval($_GET['count'] ?? DEFAULT_BEFORE_AFTER_COUNT);
        $tweets = getBeforeAfterTweets($beforeAfterTargetId, $count);
    }else{
        $tweets = getLatestTweets($page, $count);
    }
    echo $tweets;

    $pdo = null;
}catch(Exception $e){
    $res = [ 'error' => [
        'code' => $e->getCode(),
        'message' => $e->getMessage()
    ]];
    http_response_code(500);
    echo json_encode($res, JSON_UNESCAPED_UNICODE);
}

function createDB(){
    global $argv;
    $twitterDataDir = $argv[1];
    $dbPath = $argv[2] ?? __DIR__ . '/tweets.sqlite3';

    if(!is_dir($twitterDataDir)){
        echo 'Please set Twitter Data directory.';
        exit(1);
    }

    if(file_exists($dbPath)){
        echo 'Are you sure you want to delete the existing database? ';
        $input = trim(fgets(STDIN));
        if($input === 'y' || $input === 'yes'){
            unlink($dbPath);
        }else{
            echo "Please rename or delete old database.\n";
            exit(1);
        }
    }

    echo "Loading all tweets...\n";
    echo '0 tweets';
    $pdo = new PDO("sqlite:${dbPath}");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->beginTransaction();
    $pdo->exec('CREATE TABLE temp (json JSON, created_at DATE)');

    $insertSql = 'INSERT INTO temp VALUES (?, ?)';
    $tweetCount = 0;
    foreach(glob("${twitterDataDir}/tweet*.js") as $js){
        $rawJson = file_get_contents($js);
        $rawJson = preg_replace('/window\.YTD\.tweet\.part.+ =/', '', $rawJson);
        $json = json_decode($rawJson, true);
        foreach($json as $j){
            if(isset($j['coordinates']['coordinates'])){
                $j['coordinates']['coordinates'] = array_map('floatval', $j['coordinates']['coordinates']);
            }
            if(isset($j['entities'])){
                $j['entities'] = array_filter($j['entities'], function($val){
                    return is_array($val) && !empty($val);
                });
                if(empty($j['entities'])){
                    unset($j['entities']);
                }
            }
            $stmt = $pdo->prepare($insertSql);
            $stmt->bindValue(1, json_encode($j, JSON_UNESCAPED_UNICODE), PDO::PARAM_STR);
            $stmt->bindValue(2, strtotime($j['created_at']), PDO::PARAM_INT);
            $stmt->execute();
            $tweetCount++;
            echo "\r${tweetCount} tweets";
        }
    }

    echo "\nSorting tweets...\n";
    $pdo->exec('CREATE TABLE tweets (json JSON, created_at DATE)');
    $pdo->exec("INSERT INTO tweets SELECT json, created_at FROM temp ORDER BY CAST(JSON_EXTRACT(json, '$.id') AS INTEGER) ASC");
    $pdo->exec('DROP TABLE temp');
    $pdo->commit();
    echo "Optimizing database...\n";
    $pdo->exec('VACUUM');
    $pdo = null;
    echo "Done!\n";
}

function getLatestTweets(int $page, int $count): string{
    $offset = $page * $count;
    $sql = 'WITH targetRange AS (SELECT ROWID, json FROM tweets ' . getTargetRangeWhere() . '), ' .
                'counts AS (SELECT COUNT(json) AS allCount FROM targetRange) ' .
            "SELECT json, allCount FROM targetRange, counts LIMIT (SELECT allCount FROM counts LIMIT 1) - ${offset}, ${count}";
    $dbData = getDBData($sql);
    $procTime = $dbData['procTime'];
    $allCount = intval($dbData['allCount'][0]);
    $rangeStart = $allCount - $offset;
    $rangeEnd = $rangeStart + $count;
    negative0($rangeStart);
    negative0($rangeEnd);
    if($rangeStart === 0 && $rangeEnd === 0){
        $dbData['json'] = [];
    }else if($rangeEnd < $count){
        $dbData['json'] = array_slice($dbData['json'], 0, $rangeEnd);
    }
    return "{\"procTime\":${procTime},\"allCount\":${allCount},\"range\":[${rangeStart},${rangeEnd}],\"data\":[" . implode(',', $dbData['json']) . ']}';
}

function searchTweets(string $searchQuery, int $page, int $count): string{
    $searches = [];
    if(preg_match_all('/"(.+?)"/', $searchQuery, $m) === 1){
        foreach($m[1] as $v){
            $searches[] = $v;
            $searchQuery = str_replace("\"$v\"", '', $searchQuery);
        }
    }

    foreach(preg_split('/( |　)+/', $searchQuery) as $v){
        if(mb_strlen($v) !== 0){
            $searches[] = $v;
        }
    }
    $searches = array_map(function($val){
        $val = htmlspecialchars($val, ENT_NOQUOTES);
        return "%${val}%";
    }, $searches);

    $sql = 'SELECT json FROM tweets WHERE ';
    $sql .= str_repeat("JSON_EXTRACT(json, '$.full_text') LIKE ? AND ", count($searches));
    $sql .= getTargetRangeWhere(false);
    $sql = preg_replace('/AND $/', '', $sql);

    $dbData = getDBData($sql, ...$searches);
    $jsons = $dbData['json'] ?? [];
    $procTime = $dbData['procTime'];
    $allCount = count($jsons);
    $rangeStart = $allCount - $page * $count;
    $rangeEnd = $rangeStart + $count;
    negative0($rangeStart);
    negative0($rangeEnd);
    if($rangeStart === 0 && $rangeEnd === 0){
        $jsons = [];
    }else{
        $count = ($rangeEnd < $count) ? $rangeEnd : $count;
        $jsons = array_slice($jsons, $rangeStart, $count);
    }
    return "{\"procTime\":${procTime},\"allCount\":${allCount},\"range\":[${rangeStart},${rangeEnd}],\"data\":[" . implode(',', $jsons) . ']}';
}

function getTargetRangeWhere(bool $includeWhere = true): string{
    function toUnixTime($str): int{
        if(is_null($str)){
            return -1;
        }
        if(preg_match('/^[0-9]+$/', $str) === 1){
            return intval($str);
        }else{
            $decodeTime = strtotime($str);
            if($decodeTime === false){
                http_response_code(400);
                echo json_encode([ 'error' => [
                    'code' => 400,
                    'message' => 'unsupported date format.'
                ]]);
                exit(1);
            }else{
                return $decodeTime;
            }
        }
    }
    $since = toUnixTime($_GET['since'] ?? null);
    $until = toUnixTime($_GET['until'] ?? null);
    if($until >= 0){
        $untilOnlyDate = date('His', $until) === '000000';
        if($untilOnlyDate){
            $until += 86400 - 1;
        }
    }
    $prefix = $includeWhere ? 'WHERE' : '';
    if($since >= 0 && $until >= 0){
        return "$prefix created_at BETWEEN $since AND $until";
    }else if($since >= 0){
        return "$prefix created_at >= $since";
    }else if($until >= 0){
        return "$prefix created_at <= $until";
    }else{
        return '';
    }
}

function getBeforeAfterTweets(string $targetId, int $count): string{
    $sql = "WITH targetRow AS (SELECT ROWID FROM tweets WHERE JSON_EXTRACT(json, '$.id') = ?) " .
        'SELECT tweets.json FROM tweets, targetRow ' .
        "WHERE tweets.ROWID BETWEEN targetRow.ROWID - ${count} AND targetRow.ROWID + ${count}";
    $dbData = getDBData($sql, $targetId);
    $allCount = count($dbData['json']);
    return '{"procTime":' . $dbData['procTime'] .',"allCount":' . $allCount . ',"data":[' . implode(',', $dbData['json']) . ']}';
}

function getDBData(string $sql, string ...$bindArgs): array{
    global $pdo;
    $startTime = microtime(true);
    $data = [];
    $stmt = $pdo->prepare($sql);
    $stmt->execute($bindArgs);
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if(!empty($result)){
        foreach(array_keys($result[0]) as $k){
            $data[$k] = array_column($result, $k);
        }
    }
    $procTime = microtime(true) - $startTime;
    $data['procTime'] = round($procTime * 1000);
    return $data;
}

function negative0(int &$num){
    if($num < 0){
        $num = 0;
    }
}

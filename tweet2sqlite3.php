<?php
declare(strict_types=1);
// about 450k tweets need 3GB memory
ini_set('memory_limit', '3G');

$isCreateDB = isset($argv[1]);
if($isCreateDB){
    createDB();
    exit(0);
}

// default DB location
define('SQLITE3_DB', __DIR__ . '/tweets.sqlite3');
// default json count
define('DEFAULT_COUNT', 50);

$page = intval($_GET['page'] ?? 1);
$count = intval($_GET['count'] ?? DEFAULT_COUNT);

$db = new SQLite3(SQLITE3_DB);
$tweets = getLatestTweets($page, $count);

header('Content-Type: application/json');
echo $tweets;

$db->close();

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
    $tweets = [];
    foreach(glob("${twitterDataDir}/tweet*.js") as $js){
        $rawJson = file_get_contents($js);
        $rawJson = preg_replace('/window\.YTD\.tweet\.part.+ =/', '', $rawJson);
        $json = json_decode($rawJson, true);
        foreach($json as $j){
            if(isset($j['coordinates']['coordinates'])){
                $coord = &$j['coordinates']['coordinates'];
                $coord[0] = floatval($coord[0]);
                $coord[1] = floatval($coord[1]);
            }
            if(isset($j['entities'])){
                $j['entities'] = array_filter($j['entities'], function($val){
                    return is_array($val) && !empty($val);
                });
                if(empty($j['entities'])){
                    unset($j['entities']);
                }
            }
            $tweets[$j['id']] = json_encode($j, JSON_UNESCAPED_UNICODE);
            $size = count($tweets);
            echo "\r${size} tweets";
        }
    }
    ksort($tweets);

    echo "\nCreating database...\n";
    $db = new SQLite3($dbPath);
    $db->enableExceptions(true);
    $db->exec('BEGIN');
    $db->exec('CREATE TABLE tweets (json JSON)');

    $sql = 'INSERT INTO tweets VALUES (?)';
    foreach($tweets as $t){
        $stmt = $db->prepare($sql);
        $stmt->bindValue(1, $t, SQLITE3_TEXT);
        $stmt->execute();
    }
    $db->exec('COMMIT');
    $db->close();
    echo "Done!\n";
}

function getLatestTweets(int $page, int $count): string{
    global $db;
    $offset = $page * $count;
    $sql = "SELECT json FROM tweets LIMIT (SELECT MAX(ROWID) FROM tweets) - ${offset}, ${count}";
    $jsons = [];
    $query = $db->query($sql);
    while($q = $query->fetchArray(SQLITE3_NUM)){
        $jsons[] = $q[0];
    }
    return '[' . implode(',', $jsons) . ']';
}

<?php

declare(strict_types=1);

// default DB location
define('SQLITE3_DB', __DIR__ . '/tweets.sqlite3');
// default json count
define('DEFAULT_COUNT', 50);
// default before after tweets count
define('DEFAULT_BEFORE_AFTER_COUNT', 5);

define('FTS_MIN_LENGTH', 3);

if (PHP_SAPI === 'cli') {
    if (!isset($argv[1])) {
        fwrite(STDERR, "Usage: php {$argv[0]} <TwitterDataDir> [dbPath]\n");
        exit(1);
    }
    createDB($argv[1], $argv[2] ?? SQLITE3_DB);
    exit(0);
}

handleRequest();

class ApiException extends RuntimeException {}

function handleRequest(): void
{
    header('Content-Type: application/json');

    try {
        if (!is_file(SQLITE3_DB)) {
            throw new ApiException('database not found.', 500);
        }

        $query = getStringParam('query');
        $targetId = getStringParam('targetId');
        $isBeforeAfter = $query === null && $targetId !== null;
        [$since, $until] = parseRange();

        $page = max(1, intval($_GET['page'] ?? 1));
        $defaultCount = $isBeforeAfter ? DEFAULT_BEFORE_AFTER_COUNT : DEFAULT_COUNT;
        $count = max($isBeforeAfter ? 0 : 1, intval($_GET['count'] ?? $defaultCount));

        $pdo = new PDO('sqlite:' . SQLITE3_DB);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('PRAGMA query_only = ON');
        $pdo->exec('PRAGMA mmap_size = 268435456');

        if ($query !== null) {
            echo searchTweets($pdo, $query, $since, $until, $page, $count);
        } else if ($targetId !== null) {
            echo getBeforeAfterTweets($pdo, $targetId, $count);
        } else {
            echo getLatestTweets($pdo, $since, $until, $page, $count);
        }
    } catch (ApiException $e) {
        http_response_code($e->getCode());
        echo json_encode(['error' => ['code' => $e->getCode(), 'message' => $e->getMessage()]], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        error_log((string)$e);
        http_response_code(500);
        echo json_encode(['error' => ['code' => 500, 'message' => 'internal server error.']], JSON_UNESCAPED_UNICODE);
    }
}

function getStringParam(string $key): ?string
{
    if (!isset($_GET[$key])) {
        return null;
    }
    if (!is_string($_GET[$key])) {
        throw new ApiException("invalid parameter: {$key}", 400);
    }
    return $_GET[$key];
}

function parseTime(?string $str): ?int
{
    if ($str === null) {
        return null;
    }
    if (ctype_digit($str)) {
        return intval($str);
    }
    $time = strtotime($str);
    if ($time === false) {
        throw new ApiException('unsupported date format.', 400);
    }
    return $time;
}

/** @return array{0: ?int, 1: ?int} [since, until] */
function parseRange(): array
{
    $since = parseTime(getStringParam('since'));
    $untilRaw = getStringParam('until');
    $until = parseTime($untilRaw);

    if ($until !== null && !ctype_digit((string)$untilRaw) && date('His', $until) === '000000') {
        $until += 86400 - 1;
    }
    return [$since, $until];
}

/** @return array{0: string[], 1: string[]} [include, exclude] */
function parseSearchTerms(string $searchQuery): array
{
    $include = [];
    $exclude = [];

    if (preg_match_all('/(-?)"(.+?)"/u', $searchQuery, $m, PREG_SET_ORDER) > 0) {
        foreach ($m as [, $minus, $phrase]) {
            $phrase = trim($phrase);
            if ($phrase === '') {
                continue;
            }
            if ($minus === '-') {
                $exclude[] = $phrase;
            } else {
                $include[] = $phrase;
            }
        }
        $searchQuery = preg_replace('/-?"(.+?)"/u', ' ', $searchQuery);
    }
    $searchQuery = str_replace('"', ' ', $searchQuery);

    foreach (preg_split('/[\s　]+/u', $searchQuery, -1, PREG_SPLIT_NO_EMPTY) as $word) {
        if ($word[0] === '-') {
            $word = substr($word, 1);
            if ($word !== '') {
                $exclude[] = $word;
            }
        } else {
            $include[] = $word;
        }
    }
    return [$include, $exclude];
}

function runQuery(PDO $pdo, string $sql, array $params = []): PDOStatement
{
    $stmt = $pdo->prepare($sql);
    foreach (array_values($params) as $i => $value) {
        $stmt->bindValue($i + 1, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
    return $stmt;
}

/** @return ?array{0: int, 1: int} [lo, hi] */
function resolveIdRange(PDO $pdo, ?int $since, ?int $until): ?array
{
    $lo = 0;
    $hi = PHP_INT_MAX;
    if ($since !== null) {
        $id = runQuery($pdo, 'SELECT id FROM tweets WHERE created_at >= ? ORDER BY created_at, id LIMIT 1', [$since])->fetchColumn();
        if ($id === false) {
            return null;
        }
        $lo = (int)$id;
    }
    if ($until !== null) {
        $id = runQuery($pdo, 'SELECT id FROM tweets WHERE created_at <= ? ORDER BY created_at DESC, id DESC LIMIT 1', [$until])->fetchColumn();
        if ($id === false) {
            return null;
        }
        $hi = (int)$id;
    }
    return [$lo, $hi];
}

/** @return array{0: ?string, 1: string[], 2: array} [match, conds, condParams] */
function buildSearchSpec(array $include, array $exclude): array
{
    $conds = [];
    $condParams = [];
    $includeFts = [];
    $excludeFts = [];

    foreach ([false, true] as $negate) {
        $not = $negate ? 'NOT ' : '';
        $terms = $negate ? $exclude : $include;
        foreach ($terms as $term) {
            if (mb_strlen($term) >= FTS_MIN_LENGTH) {
                $quoted = '"' . str_replace('"', '""', $term) . '"';
                if ($negate) {
                    $excludeFts[] = $quoted;
                } else {
                    $includeFts[] = $quoted;
                }
            } else {
                $conds[] = "tweets.full_text {$not}LIKE ? ESCAPE '\\'";
                $condParams[] = '%' . escapeLike($term) . '%';
            }
        }
    }

    $match = null;
    if ($includeFts) {
        $match = implode(' ', $includeFts);
        if ($excludeFts) {
            $match = '(' . $match . ') NOT (' . implode(' OR ', $excludeFts) . ')';
        }
    } else if ($excludeFts) {
        $conds[] = 'tweets.id NOT IN (SELECT rowid FROM tweets_fts WHERE tweets_fts MATCH ?)';
        $condParams[] = implode(' OR ', $excludeFts);
    }
    return [$match, $conds, $condParams];
}

function escapeLike(string $s): string
{
    return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $s);
}

function elapsedMs(float $startTime): int
{
    return (int)round((microtime(true) - $startTime) * 1000);
}

function fetchPage(PDO $pdo, ?string $match, array $conds, array $condParams, ?int $since, ?int $until, int $page, int $count): string
{
    $startTime = microtime(true);

    $allCount = 0;
    $offset = ($page - 1) * $count;
    $jsons = [];

    $idRange = resolveIdRange($pdo, $since, $until);
    if ($idRange !== null) {
        [$lo, $hi] = $idRange;

        if ($match !== null) {
            $from = 'tweets_fts JOIN tweets ON tweets.id = tweets_fts.rowid';
            $countFrom = $conds ? $from : 'tweets_fts';
            $idCol = 'tweets_fts.rowid';
            $where = $countWhere = ['tweets_fts MATCH ?', 'tweets_fts.rowid BETWEEN ? AND ?'];
            $params = $countParams = [$match, $lo, $hi];
        } else {
            $from = $countFrom = 'tweets';
            $idCol = 'tweets.id';
            $where = $countWhere = [];
            $params = $countParams = [];
            if ($since !== null || $until !== null) {
                $where[] = 'tweets.id BETWEEN ? AND ?';
                $params = [$lo, $hi];
            }
            if ($since !== null) {
                $countWhere[] = 'tweets.created_at >= ?';
                $countParams[] = $since;
            }
            if ($until !== null) {
                $countWhere[] = 'tweets.created_at <= ?';
                $countParams[] = $until;
            }
        }
        $where = array_merge($where, $conds);
        $countWhere = array_merge($countWhere, $conds);
        $params = array_merge($params, $condParams);
        $countParams = array_merge($countParams, $condParams);
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $countWhereSql = $countWhere ? 'WHERE ' . implode(' AND ', $countWhere) : '';

        $allCount = (int)runQuery($pdo, "SELECT COUNT(*) FROM {$countFrom} {$countWhereSql}", $countParams)->fetchColumn();

        if ($offset < $allCount) {
            $jsons = runQuery(
                $pdo,
                "SELECT tweets.json FROM {$from} {$whereSql} ORDER BY {$idCol} DESC LIMIT ? OFFSET ?",
                array_merge($params, [$count, $offset])
            )->fetchAll(PDO::FETCH_COLUMN);
            $jsons = array_reverse($jsons);
        }
    }

    $rangeStart = max(0, $allCount - $page * $count);
    $rangeEnd = max(0, $allCount - $offset);

    $procTime = elapsedMs($startTime);
    return "{\"procTime\":{$procTime},\"allCount\":{$allCount},\"range\":[{$rangeStart},{$rangeEnd}],\"data\":[" . implode(',', $jsons) . ']}';
}

function getLatestTweets(PDO $pdo, ?int $since, ?int $until, int $page, int $count): string
{
    return fetchPage($pdo, null, [], [], $since, $until, $page, $count);
}

function searchTweets(PDO $pdo, string $searchQuery, ?int $since, ?int $until, int $page, int $count): string
{
    if (!mb_check_encoding($searchQuery, 'UTF-8')) {
        throw new ApiException('query must be valid UTF-8.', 400);
    }

    [$include, $exclude] = parseSearchTerms($searchQuery);
    if (!$include && !$exclude) {
        throw new ApiException('query is empty.', 400);
    }
    [$match, $conds, $condParams] = buildSearchSpec($include, $exclude);
    return fetchPage($pdo, $match, $conds, $condParams, $since, $until, $page, $count);
}

function getBeforeAfterTweets(PDO $pdo, string $targetId, int $count): string
{
    if (!ctype_digit($targetId)) {
        throw new ApiException('invalid targetId.', 400);
    }
    $id = (int)$targetId;

    $startTime = microtime(true);
    $jsons = [];
    if (runQuery($pdo, 'SELECT 1 FROM tweets WHERE id = ?', [$id])->fetchColumn() !== false) {
        $sql = 'SELECT json FROM ('
            . ' SELECT * FROM (SELECT id, json FROM tweets WHERE id < ? ORDER BY id DESC LIMIT ?)'
            . ' UNION ALL'
            . ' SELECT * FROM (SELECT id, json FROM tweets WHERE id >= ? ORDER BY id ASC LIMIT ?)'
            . ') ORDER BY id';
        $jsons = runQuery($pdo, $sql, [$id, $count, $id, $count + 1])->fetchAll(PDO::FETCH_COLUMN);
    }

    $procTime = elapsedMs($startTime);
    $allCount = count($jsons);
    return "{\"procTime\":{$procTime},\"allCount\":{$allCount},\"data\":[" . implode(',', $jsons) . ']}';
}

function createDB(string $twitterDataDir, string $dbPath): void
{
    if (!is_dir($twitterDataDir)) {
        echo "Please set Twitter Data directory.\n";
        exit(1);
    }

    $files = glob("{$twitterDataDir}/tweet*.js") ?: [];
    sort($files);
    if (!$files) {
        echo "No tweet*.js found in {$twitterDataDir}\n";
        exit(1);
    }

    if (file_exists($dbPath)) {
        echo 'Are you sure you want to delete the existing database? ';
        $input = trim((string)fgets(STDIN));
        if ($input === 'y' || $input === 'yes') {
            unlink($dbPath);
        } else {
            echo "Please rename or delete old database.\n";
            exit(1);
        }
    }

    $pdo = new PDO("sqlite:{$dbPath}");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    try {
        $pdo->exec("CREATE VIRTUAL TABLE temp.fts_check USING fts5(x, tokenize='trigram')");
        $pdo->exec('DROP TABLE temp.fts_check');
    } catch (PDOException $e) {
        $version = $pdo->query('SELECT sqlite_version()')->fetchColumn();
        $pdo = null;
        @unlink($dbPath);
        echo "FTS5 with trigram tokenizer is not available (SQLite {$version}, requires 3.34+ with FTS5).\n";
        exit(1);
    }

    try {
        buildDatabase($pdo, $files);
    } catch (Throwable $e) {
        $pdo = null;
        @unlink($dbPath);
        echo "\nFailed: {$e->getMessage()}\n";
        exit(1);
    }
    $pdo = null;
    echo "Done!\n";
}

function buildDatabase(PDO $pdo, array $files): void
{
    $pdo->exec('PRAGMA journal_mode = OFF');
    $pdo->exec('PRAGMA synchronous = OFF');

    echo "Loading all tweets...\n";
    echo '0 tweets';
    $pdo->beginTransaction();
    $pdo->exec(
        'CREATE TABLE tweets ('
            . ' id INTEGER PRIMARY KEY,'
            . ' created_at INTEGER NOT NULL,'
            . ' full_text TEXT NOT NULL,'
            . ' json TEXT NOT NULL'
            . ')'
    );

    $insert = $pdo->prepare('INSERT INTO tweets (id, created_at, full_text, json) VALUES (?, ?, ?, ?)');

    $tweetCount = 0;
    $skipped = 0;
    foreach ($files as $js) {
        $rawJson = file_get_contents($js);
        $start = $rawJson === false ? false : strpos($rawJson, '[');
        if ($start === false) {
            echo "\nSkip (unexpected format): {$js}\n";
            continue;
        }
        $items = json_decode(substr($rawJson, $start), true, 512, JSON_THROW_ON_ERROR);
        unset($rawJson);

        foreach ($items as $item) {
            $tweet = $item['tweet'] ?? null;

            if (!is_array($tweet) || !isset($tweet['id'], $tweet['full_text'], $tweet['created_at'])) {
                $skipped++;
                continue;
            }

            unset($tweet['edit_info']);
            if (isset($tweet['entities'])) {
                $tweet['entities'] = array_filter($tweet['entities'], function ($val) {
                    return is_array($val) && !empty($val);
                });
                if (empty($tweet['entities'])) {
                    unset($tweet['entities']);
                }
            }

            $createdAt = strtotime($tweet['created_at']);
            if ($createdAt === false) {
                throw new RuntimeException("invalid created_at: {$tweet['created_at']} (id {$tweet['id']})");
            }

            $insert->bindValue(1, (int)$tweet['id'], PDO::PARAM_INT);
            $insert->bindValue(2, $createdAt, PDO::PARAM_INT);
            $insert->bindValue(3, normalizeSearchText($tweet), PDO::PARAM_STR);
            $insert->bindValue(4, json_encode($tweet, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), PDO::PARAM_STR);
            $insert->execute();

            $tweetCount++;
            if ($tweetCount % 1000 === 0) {
                echo "\r{$tweetCount} tweets";
            }
        }
        unset($items);
    }
    echo "\r{$tweetCount} tweets";
    if ($skipped > 0) {
        echo " ({$skipped} entries skipped)";
    }
    echo "\n";

    echo "Creating indexes...\n";
    $pdo->exec('CREATE INDEX idx_tweets_created_at ON tweets (created_at)');
    $pdo->exec(
        'CREATE VIRTUAL TABLE tweets_fts USING fts5('
            . "full_text, content='tweets', content_rowid='id', tokenize='trigram')"
    );
    $pdo->exec("INSERT INTO tweets_fts(tweets_fts) VALUES ('rebuild')");
    $pdo->commit();

    echo "Compacting database...\n";
    $pdo->exec('VACUUM');

    $pdo->exec('ANALYZE');
}

function normalizeSearchText(array $tweet): string
{
    $text = $tweet['full_text'];
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    if (isset($tweet['entities']['urls'])) {
        foreach ($tweet['entities']['urls'] as $url) {
            if (isset($url['url'], $url['expanded_url'])) {
                $text = str_replace($url['url'], $url['expanded_url'], $text);
            }
        }
    }
    return $text;
}

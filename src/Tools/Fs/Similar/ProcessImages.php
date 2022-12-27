<?php

ini_set('error_reporting', E_ALL & ~E_NOTICE & ~E_DEPRECATED);

require_once __DIR__ . '/../../../../vendor/autoload.php';

use ToolsCli\Tools\Fs\Similar\Editor;

$level = $argv[1];
$session = $argv[2];
$verbose = $argv[3];
$thread = $argv[4];
$delay = $argv[5];
$redis = null;

try {
    $hashes = [];
    $names = [];
    $done = 0;
    $editor = new Editor();
    $iterations = [];
    $checked = [];
    $redis = new Redis();
    $redis->connect('127.0.0.1', 6378);
    $verboseContent = '';

    usleep($delay * 1000);

    while ($mainPath = $redis->lPop("$session-paths-compare")) {
        $founded = [];
        $hashMain = $redis->hGet("$session-hashes", $mainPath);
        $redis->hDel("$session-hashes", $mainPath);

        foreach ($redis->hgetAll("$session-hashes") as $secondPath => $secondHash) {
            $mainPathHash = "$mainPath;;;$secondPath";
            $secondPathHash = "$secondPath;;;$mainPath";
            $done++;
            $redis->incr("$session-compare-processed");

            if ($verbose === '1') {
                $verboseContent = " - $mainPath - $secondPath";
            }

            if (
                $redis->sIsMember("$session-checked", $secondPathHash)
                || $redis->sIsMember("$session-checked", $mainPathHash)
            ) {
                echo json_encode(['status' => ['done' => $done . $verboseContent]], JSON_THROW_ON_ERROR, 512);
                continue;
            }

            $redis->sAdd("$session-checked", $mainPathHash);
            $redis->sAdd("$session-checked", $secondPathHash);

            try {
                $hammingDistance = $editor->compareHashes($hashMain, $secondHash);
            } catch (Throwable $exception) {
                $redis->sAdd("$session-errors", $exception->getMessage());
                echo json_encode(['status' => ['done' => $done . $verboseContent]], JSON_THROW_ON_ERROR, 512);
                continue;
            }

            if ($hammingDistance > $level) {
                echo json_encode(['status' => ['done' => $done . $verboseContent]], JSON_THROW_ON_ERROR, 512);
                continue;
            }

            $founded[$mainPath][] = [
                'path' => $secondPath,
                'level' => $hammingDistance,
            ];

            echo json_encode(['status' => ['done' => $done . $verboseContent]], JSON_THROW_ON_ERROR, 512);
        }

        if (empty($founded)) {
            echo json_encode(['status' => ['done' => $done . $verboseContent]], JSON_THROW_ON_ERROR, 512);
            continue;
        }

        $foundedMain = $redis->hGet("$session-founded", "thread-$thread");

        if ($foundedMain) {
            $founded = array_merge(json_decode($foundedMain, true), $founded);
        }

        $redis->hSet("$session-founded", "thread-$thread", json_encode($founded, JSON_THROW_ON_ERROR, 512));
    }

    echo '{"status": {"message": "ok ' . $done . $verboseContent . '"}}';
} catch (Throwable $exception) {
    $message = $exception->getMessage();
    $redis?->sAdd("$session-errors", $exception->getMessage());
    echo "{\"status\":\"error\",\"message\": \"$message\"}";
}

usleep($delay * 1000);

<?php

ini_set('error_reporting', E_ALL & ~E_NOTICE & ~E_DEPRECATED);

require_once __DIR__ . '/../../../../vendor/autoload.php';

use ToolsCli\Tools\Fs\Similar\Editor;

$level = $argv[1];
$session = $argv[2];
$thread = $argv[3];
$verbose = $argv[4];
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

    $len = $redis->lLen("$session-base-paths");

    while ($mainPath = $redis->lPop("$session-process-paths")) {
        $founded = [];

        for ($index = 0; $index < $len; $index++) {
            $secondPath = $redis->lIndex("$session-base-paths", $index);
            $mainPathHash = "$mainPath;;;$secondPath";
            $secondPathHash = "$secondPath;;;$mainPath";
            $done++;
            $redis->incr("$session-processed");

            if ($verbose === '1') {
                $verboseContent = " - $mainPath - $secondPath";
            }

            if (
                $redis->sIsMember("$session-checked", $secondPathHash)
                || $redis->sIsMember("$session-checked", $mainPathHash)
                || $mainPath === $secondPath
            ) {
                echo json_encode(['status' => ['done' => $done . $verboseContent]], JSON_THROW_ON_ERROR, 512);
                continue;
            }

            $redis->sAdd("$session-checked", $mainPathHash);
            $redis->sAdd("$session-checked", $secondPathHash);

            try {
                $hammingDistance = $editor->compareNew($mainPath, $secondPath, $redis, $session);
//                $hammingDistance = $editor->compare($mainPath, $secondPath);
            } catch (Throwable $exception) {
                $redis->sAdd("$session-errors", $exception->getMessage());
            }

            if ($hammingDistance > $level) {
                echo json_encode(['status' => ['done' => $done . $verboseContent]], JSON_THROW_ON_ERROR, 512);
                continue;
            }

            $founded[] = [
                'path' => $secondPath,
                'level' => $hammingDistance,
            ];

            echo json_encode(['status' => ['done' => $done . $verboseContent]], JSON_THROW_ON_ERROR, 512);
        }

        if (empty($founded)) {
            echo json_encode(['status' => ['done' => $done . $verboseContent]], JSON_THROW_ON_ERROR, 512);
            continue;
        }

        $iterations[] = [
            'main' => $mainPath,
            'founded' => $founded
        ];
    }

    $res = $redis->hSet($session, "thread-$thread", json_encode($iterations, JSON_THROW_ON_ERROR, 512));

    echo '{"status": {"message": "ok ' . $done . $verboseContent . '"}}';
} catch (Throwable $exception) {
    $message = $exception->getMessage();
    $redis?->sAdd("$session-errors", $exception->getMessage());
    echo "{\"status\":\"error\",\"message\": \"$message\"}";
}

<?php

ini_set('error_reporting', E_ALL & ~E_NOTICE & ~E_DEPRECATED);

require_once __DIR__ . '/../../../../vendor/autoload.php';

use ToolsCli\Tools\Fs\Similar\Editor;

$session = $argv[1];
$thread = $argv[2];
$verbose = $argv[3];
$identical = $argv[4];
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

    while ($path = $redis->lPop("$session-paths-hashes")) {
        $founded = [];
        $done++;

        if ($verbose === '1') {
            $verboseContent = " - $path";
        }

        try {
            $bin = $editor->generateHash($path);
            $decoded = [];

            $redis->hSet("$session-hashes", $path, $bin);
            $redis->rPush("$session-paths-compare", $path);
            $redis->incr("$session-hash-processed");

            if ($identical === '1') {
                $identical = $redis->hGet("$session-hashes-identical", $bin);
                if ($identical) {
                    $decoded = json_decode($identical, true);
                }

                $decoded[] = $path;
                $encoded = json_encode($decoded, JSON_THROW_ON_ERROR, 512);
                $redis->hSet("$session-hashes-identical", $bin, $encoded);
            }

        } catch (Throwable $exception) {
            $redis->sAdd("$session-errors", $exception->getMessage());
        }

        echo json_encode(['status' => ['done' => $done . $verboseContent]], JSON_THROW_ON_ERROR, 512);
    }

    echo '{"status": {"message": "ok ' . $done . $verboseContent . '"}}';
} catch (Throwable $exception) {
    $message = $exception->getMessage();
    $redis?->sAdd("$session-errors", $exception->getMessage());
    echo "{\"status\":\"error\",\"message\": \"$message\"}";
}

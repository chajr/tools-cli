<?php

ini_set('error_reporting', E_ALL & ~E_NOTICE & ~E_DEPRECATED);

require_once __DIR__ . '/../../../../vendor/autoload.php';

use Grafika\Grafika;

$level = $argv[1];
$session = $argv[2];
$thread = $argv[3];

try {
    $hashes = [];
    $names = [];
    $done = 1;
    $editor = Grafika::createEditor();
    $iterations = [];
    $checked = [];
    $redis = new Redis();
    $redis->connect('127.0.0.1', 6379);

    $len = $redis->lLen("$session-base-paths");

    while ($mainPath = $redis->lPop("$session-process-paths")) {
        $founded = [];

        for ($index = 0; $index < $len; $index++) {
            $secondPath = $redis->lIndex("$session-base-paths", $index);
            $mainPathHash = "$mainPath;;;$secondPath";
            $secondPathHash = "$secondPath;;;$mainPath";

            if (
                $redis->sIsMember("$session-checked", $secondPathHash)
                || $redis->sIsMember("$session-checked", $mainPathHash)
                || $mainPath === $secondPath
            ) {
                echo json_encode(['status' => ['done' => $done++]], JSON_THROW_ON_ERROR, 512);
                continue;
            }

            $redis->sAdd("$session-checked", $mainPathHash);
            $redis->sAdd("$session-checked", $secondPathHash);

            $hammingDistance = $editor->compare($mainPath, $secondPath);

            if ($hammingDistance > $level) {
                echo json_encode(['status' => ['done' => $done++]], JSON_THROW_ON_ERROR, 512);
                continue;
            }

            $founded[] = [
                'path' => $secondPath,
                'level' => $hammingDistance,
            ];

            echo json_encode(['status' => ['done' => $done++]], JSON_THROW_ON_ERROR, 512);
        }

        $iterations[] = [
            'main' => $mainPath,
            'founded' => $founded
        ];
    }

    $res = $redis->hSet($session, "thread-$thread", json_encode($iterations, JSON_THROW_ON_ERROR, 512));

    echo '{"status": "ok"}';
} catch (Throwable $exception) {
    $message = $exception->getMessage();
    echo "{\"status\":\"error\",\"message\": \"$message\"}";
}

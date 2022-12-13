<?php

ini_set('error_reporting', E_ALL & ~E_NOTICE & ~E_DEPRECATED);

require_once __DIR__ . '/../../../../vendor/autoload.php';

use Grafika\Grafika;

$hasHListFile = $argv[1];
$level = $argv[2];
$instanceUuid = $argv[3];
$thread = $argv[4];

try {
    if (!file_exists($hasHListFile)) {
        throw new UnexpectedValueException("Missing data file: $hasHListFile");
    }

    $mainFileList = json_decode(
        file_get_contents(__DIR__ . '/../../../../var/tmp/dupimg/main.json'),
        true,
        512,
        JSON_THROW_ON_ERROR
    );

    $fileList = json_decode(file_get_contents($hasHListFile), true, 512, JSON_THROW_ON_ERROR);
    $hashes = [];
    $names = [];
    $count = count($fileList) * count($mainFileList);
    $done = 0;
    $editor = Grafika::createEditor();
    $iterations = [];
    $checked = [];
    $redis = new Redis();
    $redis->connect('127.0.0.1', 6379);

    foreach ($mainFileList as $index => $fileMain) {
        $founded = [];

        foreach ($fileList as $fileSecond) {
            $mainPath = $fileMain['path'] . '/' . $fileMain['name'];
            $secondPath = $fileSecond['path'] . '/' . $fileSecond['name'];

            $mainPathHash = \hash('sha3-256', $mainPath . $secondPath);
            $secondPathHash = \hash('sha3-256', $secondPath . $mainPath);

            if (
                $redis->hGet($instanceUuid, $secondPathHash)
                || $redis->hGet($instanceUuid, $mainPathHash)
                || $mainPath === $secondPath
            ) {
                echo json_encode([
                    'status'  => [
                        'all' => $count,
                        'left' => $done++
                    ],
                ], JSON_THROW_ON_ERROR, 512);
                continue;
            }

            $redis->hSet($instanceUuid, $mainPathHash, true);
            $redis->hSet($instanceUuid, $secondPathHash, true);
            //append to uuid key (-hash), potem explode do kasowania

            $hammingDistance = $editor->compare($mainPath, $secondPath);

            if ($hammingDistance > $level) {
                echo json_encode([
                    'status'  => [
                        'all' => $count,
                        'left' => $done++
                    ],
                ], JSON_THROW_ON_ERROR, 512);
                continue;
            }

            $founded[] = [
                'path' => $fileSecond,
                'level' => $hammingDistance,
            ];

            echo json_encode([
                'status'  => [
                    'all' => $count,
                    'left' => $done++
                ],
            ], JSON_THROW_ON_ERROR, 512);
        }


        if (empty($founded)) {
            echo json_encode([
                'status'  => [
                    'all' => $count,
                    'left' => $done++
                ],
            ], JSON_THROW_ON_ERROR, 512);
            continue;
        }

        $iterations[$index] = [
            'main' => $fileMain,
            'founded' => $founded
        ];
    }

    $res = $redis->hSet($instanceUuid, "thread-$thread", serialize($iterations));

    if (!$res) {
        throw new UnderflowException($redis->getLastError());
    }

    echo '{"status": "ok"}';
} catch (Throwable $exception) {
    $message = $exception->getMessage();
    echo "{\"status\":\"error\",\"message\": \"$message\"}";
}

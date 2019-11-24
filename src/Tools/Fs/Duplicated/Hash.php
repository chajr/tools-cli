<?php

$hasHListFile = $argv[1];

try {
    if (!file_exists($hasHListFile)) {
        throw new RuntimeException("Missing data file: $hasHListFile");
    }

    $fileList = json_decode(file_get_contents($hasHListFile), true, 512, JSON_THROW_ON_ERROR);
    $hashes = [];
    $names = [];
    $count = count($fileList);
    $done = 0;

    foreach ($fileList as $file) {
        //@todo    if ($this->input->getOption('skip-empty') && filesize($file) === 0) {
        //@todo    if ($this->input->getOption('check-by-name')) {
        $hash = hash_file('sha3-256', $file);

        $hashes[$hash][] = $file;
        echo json_encode([
            'status'  => [
                'all' => $count,
                'left' => $done++
            ],
        ], JSON_THROW_ON_ERROR, 512);
    }

    $data = json_encode([
        'names'  => $names,
        'hashes' => $hashes
    ], JSON_THROW_ON_ERROR, 512);

    file_put_contents($hasHListFile, $data);

    echo '{"status": "ok"}';
} catch (Throwable $exception) {
    $message = $exception->getMessage();
    echo "{\"status\":\"error\",\"message\": \"$message\"}";
}

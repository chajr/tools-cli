<?php
$stdin = fopen('php://stdin', 'rb');

$hashes = [];
$names = [];

$contents = stream_get_contents($stdin);

$fileList = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

foreach ($fileList as $file) {
//    if ($this->input->getOption('skip-empty') && filesize($file) === 0) {
//        continue;
//    }

//    if ($this->input->getOption('check-by-name')) {
//        $fileInfo = new \SplFileInfo($file);
//        $name = $fileInfo->getFilename();
//
//        $names[$file] = $name;
//    } else {
        $hash = hash_file('sha3-256', $file);

        $hashes[$hash][] = $file;
//    }
}
//    dump($names, $hashes);
try {
    echo json_encode([
        'names'  => $names,
        'heshes' => $hashes
    ], JSON_THROW_ON_ERROR, 512);
} catch (\JsonException $exception) {
    $message = $exception->getMessage();
    echo "{\"error\": \"$message\"}";
}

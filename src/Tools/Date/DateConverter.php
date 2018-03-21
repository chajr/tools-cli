#!/usr/bin/env php

<?php

$mainDir = rtrim($argv[1], '/');
$destination = rtrim($argv[2], '/');
$list = glob($mainDir . '/*');
$count = 0;
$filenameCollision = [];
$contentCollision = [];

foreach ($list as $item) {
    $file = new SplFileInfo($item);

    if (!$file->isFile()) {
        continue;
    }

    $hash = md5(file_get_contents($item));

    if (in_array($hash, $contentCollision, true)) {
        echo colorizeShell('red', 'content collision detected: ');
        echo colorizeShell('red_label', $mainDir . '/' . $file->getBasename());
        echo "\n";
        continue;
    }

    $contentCollision[] = $hash;

    echo colorizeShell('brown', $file->getBasename());
    echo ' - ';
    echo colorizeShell('brown', date('Y-m-d_H:i:s', $file->getMTime()));
    echo ' - ';
    echo colorizeShell('red', ++$count);

    $newName = date('Y-m-d_H:i:s', $file->getMTime());
    $newPath = $destination . '/' . $newName;

    if (file_exists($newPath . '.' . $file->getExtension())) {
        if (!isset($filenameCollision[$newPath])) {
            $filenameCollision[$newPath] = 0;
        }

        $newPath .= '-' . ++$filenameCollision[$newPath];

        echo "\n";
        echo colorizeShell('red', 'collision detected: ');
        echo colorizeShell('red_label', $newPath);
    }

    echo "\n";
    $status = copy(
        $mainDir . '/' . $file->getBasename(),
        $newPath . '.' . $file->getExtension()
    ) ? colorizeShell('green', 'copy success') : colorizeShell('reed', 'copy fail');

    echo $status;

    echo "\n";
}


/**
 * apply colors for shell
 *
 * @param string $type
 * @param string $string
 * @return string
 */
function colorizeShell($type, $string)
{
    $list = [
        'start' => '',
        'end'   => "\033[0m"
    ];

    switch ($type) {
        case 'red':
            $list['start'] = "\033[0;31m";
            break;

        case 'green':
            $list['start'] = "\033[0;32m";
            break;

        case 'brown':
            $list['start'] = "\033[0;33m";
            break;

        case 'black':
            $list['start'] = "\033[0;30m";
            break;

        case 'blue':
            $list['start'] = "\033[0;34m";
            break;

        case 'magenta':
            $list['start'] = "\033[0;35m";
            break;

        case 'cyan':
            $list['start'] = "\033[0;36m";
            break;

        case 'white':
            $list['start'] = "\033[0;37m";
            break;

        case 'red_label':
            $list['start'] = "\033[41m";
            break;

        case 'brown_label':
            $list['start'] = "\033[43m";
            break;

        case 'black_label':
            $list['start'] = "\033[40m";
            break;

        case 'green_label':
            $list['start'] = "\033[42m";
            break;

        case 'blue_label':
            $list['start'] = "\033[44m";
            break;

        case 'magenta_label':
            $list['start'] = "\033[45m";
            break;

        case 'cyan_label':
            $list['start'] = "\033[46m";
            break;

        case 'white_label':
            $list['start'] = "\033[47m";
            break;

        default:
            $list['end'] = '';
            break;
    }

    return $list['start'] . $string . $list['end'];
}

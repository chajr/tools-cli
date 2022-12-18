<?php

declare(strict_types=1);

namespace ToolsCli\Tools\Fs\Similar;

use Grafika\Imagick\{
    Editor as EditorOrigin,
    Image,
    ImageHash\DifferenceHash
};

class Editor extends EditorOrigin
{
    public function compareNew(string $image1, string $image2, \Redis $redis, string $session): int
    {
        $hash = new DifferenceHash();

        $bin1 = $redis->hGet("$session-hashes", $image1);
        if (!$bin1) {
            try {
                if (\is_string($image1)) {
                    $image1Obj = Image::createFromFile($image1);
                    $this->flatten($image1Obj);
                }
            } catch (\Throwable $exception) {
                throw new \UnexpectedValueException($exception->getMessage() . " - " . $image1);
            }

            $bin1 = $hash->hash($image1Obj, $this);
            $redis->hSet("$session-hashes", $image1, $bin1);
        }

        $bin2 = $redis->hGet("$session-hashes", $image2);
        if (!$bin2) {
            try {
                if (\is_string($image2)) {
                    $image2Obj = Image::createFromFile($image2);
                    $this->flatten($image2Obj);
                }
            } catch (\Throwable $exception) {
                throw new \UnexpectedValueException($exception->getMessage() . " - " . $image2);
            }

            $bin2 = $hash->hash($image2Obj, $this);
            $redis->hSet("$session-hashes", $image2, $bin2);
        }

        $str1 = \str_split($bin1);
        $str2 = \str_split($bin2);
        $distance = 0;

        foreach ($str1 as $i => $char) {
            if ($char !== $str2[$i]) {
                $distance++;
            }
        }

        return $distance;
    }
}

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
    public function compareHashes(string $bin1, string $bin2): int
    {
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

    public function generateHash(string $image): string
    {
        try {
            if (\is_string($image)) {
                $imageObj = Image::createFromFile($image);
                $this->flatten($imageObj);
            }
        } catch (\Throwable $exception) {
            throw new \UnexpectedValueException($exception->getMessage() . " - " . $image);
        }

        return (new DifferenceHash())->hash($imageObj, $this);
    }
}

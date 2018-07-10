<?php

namespace ToolsCli\Tools\Fs\Duplicated;

class Name
{
    protected function checkByName(array $names, array $hashes) : array
    {
        foreach ($names as $path => $fileName) {
            unset($names[$path]);

            foreach ($names as $verifiedPath => $toVerified) {
                $val = 0;
                similar_text($fileName, $toVerified, $val);

                if ($val >= (int)$this->input->getOption('check-by-name')) {
                    if (!($hashes[$fileName] ?? false)) {
                        $hashes[$fileName][] = $path;
                    }

                    $hashes[$fileName][] = $verifiedPath;
                }
            }
        }

        return $hashes;
    }
}

<?php

declare(strict_types=1);

namespace ChangeChampion\Commands\Concerns;

trait ResolvesResourceDir
{
    private function getResourcesDir(): string
    {
        // When installed as a dependency: vendor/change-champion/champ/resources
        // When running from source: ./resources
        $paths = [
            __DIR__.'/../../../resources',
            __DIR__.'/../../../../resources',
        ];

        foreach ($paths as $path) {
            $realPath = realpath($path);
            if ($realPath && is_dir($realPath)) {
                return $realPath;
            }
        }

        return __DIR__.'/../../../resources';
    }
}

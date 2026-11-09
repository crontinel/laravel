<?php

declare(strict_types=1);

// Composer loads crontinel/php's copy of a same-named class in real installs. Here the root package wins, so nothing else catches it.
it('declares no class that crontinel/php also declares', function () {
    $src = realpath(__DIR__.'/../../src');
    $php = realpath(__DIR__.'/../../vendor/crontinel/php/src');
    expect($php)->not->toBeFalse();

    $collisions = collect(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS)))
        ->map(fn (SplFileInfo $file) => substr($file->getPathname(), strlen($src)))
        ->filter(fn (string $path) => file_exists($php.$path))
        ->values()
        ->all();

    expect($collisions)->toBe([]);
});

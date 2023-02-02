<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Composer;

use Composer\Composer;
use Composer\Factory as ComposerFactory;
use Composer\IO\IOInterface;
use Composer\IO\NullIO;

class Factory
{
    public static function createComposer(string $composerJsonDir, ?IOInterface $composerIO = null): Composer
    {
        if ($composerIO === null) {
            $composerIO = new NullIO();
        }

        return (new ComposerFactory())->createComposer(
            $composerIO,
            $composerJsonDir . '/composer.json',
            false,
            $composerJsonDir
        );
    }
}

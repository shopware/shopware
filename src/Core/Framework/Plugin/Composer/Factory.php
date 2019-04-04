<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Composer;

use Composer\Composer;
use Composer\Factory as ComposerFactory;
use Composer\IO\NullIO;

class Factory
{
    public static function createComposer(string $composerJsonDir): Composer
    {
        return (new ComposerFactory())->createComposer(
            new NullIO(),
            $composerJsonDir . '/composer.json',
            false,
            $composerJsonDir
        );
    }
}

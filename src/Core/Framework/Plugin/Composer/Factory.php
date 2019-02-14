<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Composer;

use Composer\Composer;
use Composer\Factory as ComposerFactory;
use Composer\IO\NullIO;

class Factory
{
    public static function createComposer(string $projectDir): Composer
    {
        return (new ComposerFactory())->createComposer(
            new NullIO(),
            $projectDir . '/composer.json',
            false,
            $projectDir
        );
    }
}

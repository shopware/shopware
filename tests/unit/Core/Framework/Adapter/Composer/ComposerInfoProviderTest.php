<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Composer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Composer\ComposerInfoProvider;
use Shopware\Core\Framework\Adapter\Composer\ComposerPackage;

/**
 * @internal
 */
#[CoversClass(ComposerInfoProvider::class)]
class ComposerInfoProviderTest extends TestCase
{
    public function testFakeAndReset(): void
    {
        $packages = [
            new ComposerPackage(
                name: 'shopware/core',
                version: '6.4.0.0',
                prettyVersion: '6.4.0.0',
                path: '/sw/core',
            ),
        ];

        ComposerInfoProvider::fake($packages);

        static::assertSame($packages, ComposerInfoProvider::getComposerPackages('shopware-platform-plugin'));

        ComposerInfoProvider::reset();

        static::assertNotSame($packages, ComposerInfoProvider::getComposerPackages('shopware-platform-plugin'));
    }
}

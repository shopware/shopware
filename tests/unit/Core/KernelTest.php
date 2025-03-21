<?php

declare(strict_types=1);

namespace Shopware\Tests\Unit\Core;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\StaticKernelPluginLoader;
use Shopware\Core\Kernel;

/**
 * @internal
 */
#[CoversClass(Kernel::class)]
class KernelTest extends TestCase
{
    public function testGetCacheDir(): void
    {
        $kernel = new Kernel(
            'fooBar',
            true,
            $this->createMock(StaticKernelPluginLoader::class),
            'cacheId',
            '6.6.6',
            $this->createMock(Connection::class),
            'www/shopware'
        );

        static::assertStringStartsWith('www/shopware/var/cache/fooBar_h', $kernel->getCacheDir());
    }
}

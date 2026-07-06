<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DependencyInjection\Configuration;
use Shopware\Core\Framework\DependencyInjection\FrameworkExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 */
#[CoversClass(FrameworkExtension::class)]
#[CoversClass(Configuration::class)]
class FrameworkExtensionTest extends TestCase
{
    public function testDeprecatedCacheCompressionConfigSetsReplacementParameters(): void
    {
        $container = new ContainerBuilder();

        (new FrameworkExtension())->load([
            [
                'cache' => [
                    'cache_compression' => false,
                    'cache_compression_method' => 'deflate',
                ],
            ],
        ], $container);

        static::assertFalse($container->getParameter('shopware.cache.compress'));
        static::assertSame('deflate', $container->getParameter('shopware.cache.compression_method'));
        static::assertFalse($container->getParameter('shopware.cache.cache_compression'));
        static::assertSame('deflate', $container->getParameter('shopware.cache.cache_compression_method'));
    }

    public function testReplacementCacheCompressionConfigHasPrecedenceOverDeprecatedConfig(): void
    {
        $container = new ContainerBuilder();

        (new FrameworkExtension())->load([
            [
                'cache' => [
                    'cache_compression' => false,
                    'compress' => true,
                    'cache_compression_method' => 'deflate',
                    'compression_method' => 'gzip',
                ],
            ],
        ], $container);

        static::assertTrue($container->getParameter('shopware.cache.compress'));
        static::assertSame('gzip', $container->getParameter('shopware.cache.compression_method'));
        static::assertFalse($container->getParameter('shopware.cache.cache_compression'));
        static::assertSame('deflate', $container->getParameter('shopware.cache.cache_compression_method'));
    }
}

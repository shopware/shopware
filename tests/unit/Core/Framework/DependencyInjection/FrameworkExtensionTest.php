<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DependencyInjection\FrameworkExtension;
use Shopware\Core\Framework\Feature\FeatureException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Symfony\Component\DependencyInjection\Compiler\MergeExtensionConfigurationPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(FrameworkExtension::class)]
class FrameworkExtensionTest extends TestCase
{
    #[DisabledFeatures(['v6.8.0.0'])]
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

    public function testDeprecatedCacheCompressionConfigThrowsException(): void
    {
        $this->expectExceptionObject(FeatureException::error('Tried to access deprecated functionality: Parameter "shopware.cache.cache_compression" is deprecated and will be removed. Please use "shopware.cache.compress" instead.'));
        (new FrameworkExtension())->load([
            [
                'cache' => [
                    'cache_compression' => false,
                    'cache_compression_method' => 'deflate',
                ],
            ],
        ], new ContainerBuilder());
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

    public function testDeprecatedCacheCompressionConfigIsSetForBC(): void
    {
        $container = new ContainerBuilder();

        (new FrameworkExtension())->load([
            [
                'cache' => [
                    'compress' => true,
                    'compression_method' => 'gzip',
                ],
            ],
        ], $container);

        static::assertTrue($container->getParameter('shopware.cache.cache_compression'));
        static::assertSame('gzip', $container->getParameter('shopware.cache.cache_compression_method'));
    }

    public function testPasswordHasherPlaceholderHashesUseTheConfiguredSettings(): void
    {
        $container = new ContainerBuilder();

        (new FrameworkExtension())->load([
            [
                'password_hasher' => [
                    'customer' => ['algorithm' => 'bcrypt', 'options' => ['cost' => 5]],
                ],
            ],
        ], $container);

        $customerPlaceholderHash = $container->getParameter('shopware.password_hasher.customer.placeholder_hash');
        static::assertIsString($customerPlaceholderHash);
        static::assertStringStartsWith('$2y$05$', $customerPlaceholderHash);

        $adminPlaceholderHash = $container->getParameter('shopware.password_hasher.admin.placeholder_hash');
        static::assertIsString($adminPlaceholderHash);
        static::assertSame(\PASSWORD_DEFAULT, password_get_info($adminPlaceholderHash)['algo']);
    }

    public function testPasswordHasherPlaceholderHashIsNotCreatedForOptionsFromEnvironmentVariables(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new FrameworkExtension());
        $container->loadFromExtension('shopware', [
            'password_hasher' => [
                'customer' => ['algorithm' => 'bcrypt', 'options' => ['cost' => '%env(int:PASSWORD_COST)%']],
            ],
        ]);

        (new MergeExtensionConfigurationPass())->process($container);

        static::assertNull($container->getParameter('shopware.password_hasher.customer.placeholder_hash'));
        static::assertIsString($container->getParameter('shopware.password_hasher.admin.placeholder_hash'));
    }
}

<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DependencyInjection\CompilerPass;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\HttpCacheConfigCompilerPass;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(HttpCacheConfigCompilerPass::class)]
class HttpCacheConfigCompilerPassTest extends TestCase
{
    private ContainerBuilder $container;

    protected function setUp(): void
    {
        $this->container = new ContainerBuilder();
        $this->container->setParameter('kernel.debug', false);
        $this->container->setDefinition('http_kernel.cache', new Definition(null, [null, null, null, null]));
        $this->container->setDefinition('http_cache', (new Definition())->setPublic(true));
    }

    public function testPassesTheHttpCacheConfigToShopwareHttpCacheKernel(): void
    {
        $this->container->prependExtensionConfig('framework', ['http_cache' => ['default_ttl' => 7200]]);

        (new HttpCacheConfigCompilerPass())->process($this->container);

        $options = $this->container->getDefinition('http_kernel.cache')->getArgument(3);
        static::assertIsArray($options);
        static::assertSame(7200, $options['default_ttl']);
    }

    public function testSymfonyHttpCacheIsNotPublicInLongrunningEnvironment(): void
    {
        $this->container->prependExtensionConfig('framework', ['http_cache' => ['enabled' => true]]);

        (new HttpCacheConfigCompilerPass())->process($this->container);

        static::assertFalse($this->container->getDefinition('http_cache')->isPublic());
    }

    #[DisabledFeatures(['LONGRUNNING_ENVIRONMENT'])]
    public function testSymfonyHttpCacheStaysPublicWithoutLongrunningEnvironment(): void
    {
        (new HttpCacheConfigCompilerPass())->process($this->container);

        static::assertTrue($this->container->getDefinition('http_cache')->isPublic());
    }
}

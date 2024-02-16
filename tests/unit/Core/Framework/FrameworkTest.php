<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\ExtensionRegistry;
use Shopware\Core\Framework\Feature\FeatureFlagRegistry;
use Shopware\Core\Framework\Framework;
use Shopware\Core\Framework\FrameworkException;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInstanceRegistry;
use Symfony\Component\DependencyInjection\Container;

/**
 * @internal
 */
#[CoversClass(Framework::class)]
class FrameworkTest extends TestCase
{
    public function testTemplatePriority(): void
    {
        $framework = new Framework();

        static::assertEquals(-1, $framework->getTemplatePriority());
    }

    public function testFeatureFlagRegisteredOnBoot(): void
    {
        $container = new Container();
        $registry = $this->createMock(FeatureFlagRegistry::class);
        $registry->expects(static::once())->method('register');

        $container->set(FeatureFlagRegistry::class, $registry);
        $container->set(DefinitionInstanceRegistry::class, $this->createMock(DefinitionInstanceRegistry::class));
        $container->set(SalesChannelDefinitionInstanceRegistry::class, $this->createMock(SalesChannelDefinitionInstanceRegistry::class));
        $container->set(ExtensionRegistry::class, $this->createMock(ExtensionRegistry::class));
        $container->setParameter('kernel.cache_dir', '/tmp');
        $container->setParameter('shopware.cache.cache_compression', true);
        $framework = new Framework();
        $framework->setContainer($container);

        $framework->boot();
    }

    public function testInvalidKernelCacheDir(): void
    {
        static::expectException(FrameworkException::class);
        static::expectExceptionMessage('Container parameter "kernel.cache_dir" needs to be a string');

        $container = new Container();
        $registry = $this->createMock(FeatureFlagRegistry::class);
        $registry->expects(static::once())->method('register');

        $container->set(FeatureFlagRegistry::class, $registry);
        $container->set(DefinitionInstanceRegistry::class, $this->createMock(DefinitionInstanceRegistry::class));
        $container->set(SalesChannelDefinitionInstanceRegistry::class, $this->createMock(SalesChannelDefinitionInstanceRegistry::class));
        $container->set(ExtensionRegistry::class, $this->createMock(ExtensionRegistry::class));
        $container->setParameter('kernel.cache_dir', null);
        $container->setParameter('shopware.cache.cache_compression', true);
        $framework = new Framework();
        $framework->setContainer($container);

        $framework->boot();
    }
}

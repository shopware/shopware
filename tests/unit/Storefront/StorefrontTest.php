<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\DependencyInjection\DisableTemplateCachePass;
use Shopware\Storefront\DependencyInjection\StorefrontMigrationReplacementCompilerPass;
use Shopware\Storefront\Mcp\Tool\ThemeConfigTool;
use Shopware\Storefront\Storefront;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Storefront::class)]
class StorefrontTest extends TestCase
{
    public function testBuildRegistersMcpServicesAndCompilerPasses(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'prod');

        // Storefront::buildDefaultConfig() loads framework.yaml and shopware.php
        // from Resources/config/packages, so register no-op extensions for both
        // namespaces to satisfy the config loader without booting FrameworkBundle.
        $container->registerExtension($this->makeStubExtension('framework'));
        $container->registerExtension($this->makeStubExtension('shopware'));

        $storefront = new Storefront();
        $storefront->build($container);

        // MCP services from mcp.php loaded via PhpFileLoader.
        static::assertTrue(
            $container->hasDefinition(ThemeConfigTool::class),
            'Storefront::build() must load mcp.php so ThemeConfigTool is registered',
        );

        $passClasses = $this->toClassNames($container->getCompilerPassConfig()->getPasses());
        static::assertContains(DisableTemplateCachePass::class, $passClasses);
        static::assertContains(StorefrontMigrationReplacementCompilerPass::class, $passClasses);
    }

    private function makeStubExtension(string $alias): Extension
    {
        return new class($alias) extends Extension {
            public function __construct(private readonly string $alias)
            {
            }

            public function getAlias(): string
            {
                return $this->alias;
            }

            public function load(array $configs, ContainerBuilder $container): void
            {
            }
        };
    }

    /**
     * @param CompilerPassInterface[] $passes
     *
     * @return array<int, string>
     */
    private function toClassNames(array $passes): array
    {
        return array_map(static fn (CompilerPassInterface $pass): string => $pass::class, $passes);
    }
}

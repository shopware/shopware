<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Checkout\Cart\SalesChannel\StorefrontCartFacade;
use Shopware\Storefront\Controller\NavigationController;
use Shopware\Storefront\DependencyInjection\DisableTemplateCachePass;
use Shopware\Storefront\DependencyInjection\StorefrontMigrationReplacementCompilerPass;
use Shopware\Storefront\DependencyInjection\TwigComponentBundlePass;
use Shopware\Storefront\Framework\Captcha\HoneypotCaptcha;
use Shopware\Storefront\Framework\Seo\SeoUrlRoute\ProductPageSeoUrlRoute;
use Shopware\Storefront\Mcp\Tool\ThemeConfigTool;
use Shopware\Storefront\Storefront;
use Shopware\Storefront\System\SalesChannel\SalesChannelAnalyticsLoader;
use Shopware\Storefront\Theme\ThemeService;
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
    public function testTemplatePriority(): void
    {
        static::assertSame(0, (new Storefront())->getTemplatePriority());
    }

    public function testBuildRegistersCompilerPasses(): void
    {
        $container = $this->buildContainer();
        $storefront = new Storefront();
        $storefront->build($container);

        $passClasses = array_map(
            static fn (CompilerPassInterface $pass): string => $pass::class,
            $container->getCompilerPassConfig()->getPasses()
        );

        static::assertContains(DisableTemplateCachePass::class, $passClasses);
        static::assertContains(StorefrontMigrationReplacementCompilerPass::class, $passClasses);
        static::assertContains(TwigComponentBundlePass::class, $passClasses);
    }

    public function testBuildSetsStorefrontRootParameter(): void
    {
        $container = $this->buildContainer();
        $storefront = new Storefront();
        $storefront->build($container);

        static::assertTrue($container->hasParameter('storefrontRoot'));
        static::assertSame($storefront->getPath(), $container->getParameter('storefrontRoot'));
    }

    public function testBuildLoadsServiceDefinitions(): void
    {
        $container = $this->buildContainer();
        (new Storefront())->build($container);

        static::assertTrue($container->has(StorefrontCartFacade::class), 'services.xml');
        static::assertTrue($container->has(HoneypotCaptcha::class), 'captcha.xml');
        static::assertTrue($container->has(ProductPageSeoUrlRoute::class), 'seo.xml');
        static::assertTrue($container->has(NavigationController::class), 'controller.xml');
        static::assertTrue($container->has(ThemeService::class), 'theme.xml');
        static::assertTrue($container->has(SalesChannelAnalyticsLoader::class), 'system.xml');
        static::assertTrue($container->has(ThemeConfigTool::class), 'mcp.php');
    }

    private function buildContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'prod');

        foreach ($this->stubExtensions() as $extension) {
            $container->registerExtension($extension);
        }

        return $container;
    }

    /**
     * @return list<Extension>
     */
    private function stubExtensions(): array
    {
        $stub = static fn (string $alias): Extension => new class($alias) extends Extension {
            public function __construct(private readonly string $alias)
            {
            }

            /**
             * @throws void
             */
            public function load(array $configs, ContainerBuilder $container): void
            {
            }

            /**
             * @throws void
             */
            public function getAlias(): string
            {
                return $this->alias;
            }
        };

        return [
            $stub('framework'),
            $stub('twig'),
            $stub('twig_component'),
        ];
    }
}

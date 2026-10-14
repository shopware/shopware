<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Seo\SeoUrlRoute;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Seo\SeoUrlRoute\EntityRouteResolver;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteRegistry;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Framework\Seo\SeoUrlRoute\LandingPageSeoUrlRoute;
use Shopware\Storefront\Framework\Seo\SeoUrlRoute\NavigationPageSeoUrlRoute;
use Shopware\Storefront\Framework\Seo\SeoUrlRoute\ProductPageSeoUrlRoute;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\DecoratorServicePass;
use Symfony\Component\DependencyInjection\Compiler\ResolveTaggedIteratorArgumentPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Guards that the storefront page routes are the first `shopware.seo_url.route` of their entity.
 * {@see EntityRouteResolver} resolves the canonical route of an entity as the first route
 * the {@see SeoUrlRouteRegistry} returns for it, e.g. for sitemap URLs.
 *
 * @internal
 */
#[Package('inventory')]
#[CoversNothing]
class SeoUrlRouteServiceConfigTest extends TestCase
{
    public function testStorefrontPageRoutesPrecedeExtensionRoutesOfTheSameEntity(): void
    {
        $container = new ContainerBuilder();
        $loader = new PhpFileLoader($container, new FileLocator());
        $loader->load(__DIR__ . '/../../../../../../src/Storefront/DependencyInjection/seo.php');

        // the core services the storefront decorates are not part of this container
        foreach ($container->getDefinitions() as $definition) {
            $definition->setDecoratedService(null);
        }

        // an extension registers another route for `product` and decorates the product page route afterwards,
        // so the decorator carrying the route tag is defined after the extension route
        $container->register('extension.bundle_page_route', ProductPageSeoUrlRoute::class)
            ->addTag('shopware.seo_url.route');
        $container->register('extension.product_page_route_decorator', ProductPageSeoUrlRoute::class)
            ->setDecoratedService(ProductPageSeoUrlRoute::class);

        $container->register('seo_url_routes', \ArrayIterator::class)
            ->setPublic(true)
            ->addArgument(new TaggedIteratorArgument('shopware.seo_url.route'));

        (new DecoratorServicePass())->process($container);
        (new ResolveTaggedIteratorArgumentPass())->process($container);

        $argument = $container->getDefinition('seo_url_routes')->getArgument(0);
        static::assertInstanceOf(TaggedIteratorArgument::class, $argument);

        $routeIds = array_map(
            static fn (Reference $reference): string => (string) $reference,
            $argument->getValues(),
        );

        static::assertSame(
            [
                NavigationPageSeoUrlRoute::class,
                LandingPageSeoUrlRoute::class,
                'extension.product_page_route_decorator',
                'extension.bundle_page_route',
            ],
            $routeIds,
        );
    }
}

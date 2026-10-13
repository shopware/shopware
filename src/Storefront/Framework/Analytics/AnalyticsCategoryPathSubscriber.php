<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Analytics;

use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\Cms\SalesChannel\Struct\ProductBoxStruct;
use Shopware\Core\Content\Cms\SalesChannel\Struct\ProductSliderStruct;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelAnalytics\SalesChannelAnalyticsEntity;
use Shopware\Storefront\Event\StorefrontRenderEvent;
use Shopware\Storefront\Page\LandingPage\LandingPage;
use Shopware\Storefront\Page\Navigation\NavigationPage;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Gives the product boxes of the homepage and of landing pages the category path analytics reports,
 * because their breadcrumb describes no category. A category page keeps using its breadcrumb.
 *
 * It only runs when analytics is active, after the analytics settings were loaded for the render, and
 * resolves all boxes of the page together, see {@see AnalyticsCategoryPathLoader}.
 *
 * @internal
 */
#[Package('discovery')]
class AnalyticsCategoryPathSubscriber implements EventSubscriberInterface
{
    final public const EXTENSION = 'analyticsCategoryPath';

    private const ROUTES = ['frontend.home.page', 'frontend.landing.page'];

    public function __construct(private readonly AnalyticsCategoryPathLoader $loader)
    {
    }

    public static function getSubscribedEvents(): array
    {
        // after SalesChannelAnalyticsLoader, which provides `storefrontAnalytics` with priority 2000
        return [StorefrontRenderEvent::class => ['addCategoryPaths', 1000]];
    }

    public function addCategoryPaths(StorefrontRenderEvent $event): void
    {
        $parameters = $event->getParameters();
        $analytics = $parameters['storefrontAnalytics'] ?? null;

        if (!$analytics instanceof SalesChannelAnalyticsEntity || !$analytics->isActive()) {
            return;
        }

        if (!\in_array($event->getRequest()->attributes->get('_route'), self::ROUTES, true)) {
            return;
        }

        $page = $parameters['page'] ?? null;
        $cmsPage = match (true) {
            $page instanceof NavigationPage => $page->getCmsPage(),
            $page instanceof LandingPage => $page->getLandingPage()?->getCmsPage(),
            default => null,
        };

        $products = $this->collectProducts($cmsPage);
        if ($products === []) {
            return;
        }

        $paths = $this->loader->load(array_values($products), $event->getSalesChannelContext());

        foreach ($products as $product) {
            $names = $paths[$product->getId()] ?? [];

            if ($names !== []) {
                $product->addExtension(self::EXTENSION, new ArrayStruct($names));
            }
        }
    }

    /**
     * @return array<string, SalesChannelProductEntity>
     */
    private function collectProducts(?CmsPageEntity $cmsPage): array
    {
        $products = [];

        foreach ($cmsPage?->getSections()?->getBlocks()->getSlots() ?? [] as $slot) {
            $data = $slot->getData();

            $found = match (true) {
                $data instanceof ProductSliderStruct => $data->getProducts()?->getElements() ?? [],
                $data instanceof ProductBoxStruct => [$data->getProduct()],
                default => [],
            };

            foreach ($found as $product) {
                if ($product instanceof SalesChannelProductEntity) {
                    $products[$product->getId()] = $product;
                }
            }
        }

        return $products;
    }
}

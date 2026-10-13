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
 * The page decides, not the route: the homepage is the page of the navigation root, which can also be
 * reached through the navigation route of that category.
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

        $page = $parameters['page'] ?? null;
        $navigationId = $event->getSalesChannelContext()->getSalesChannel()->getNavigationCategoryId();

        $cmsPage = match (true) {
            $page instanceof LandingPage => $page->getLandingPage()?->getCmsPage(),
            // a category page reports its own category through the breadcrumb
            $page instanceof NavigationPage && $page->getNavigationId() === $navigationId => $page->getCmsPage(),
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

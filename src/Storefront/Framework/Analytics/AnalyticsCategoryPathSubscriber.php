<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Analytics;

use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Struct\Collection;
use Shopware\Core\Framework\Struct\Struct;
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

    /**
     * Slot data, a struct or collection in it, and the products of that: deep enough for the
     * elements of Shopware and of extensions, shallow enough to never walk large object graphs.
     */
    private const MAX_DEPTH = 4;

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
        $request = $event->getRequest();

        $cmsPage = match (true) {
            $page instanceof LandingPage => $page->getLandingPage()?->getCmsPage(),
            // a category page reports its own category through the breadcrumb
            $page instanceof NavigationPage && $page->getNavigationId() === $navigationId => $page->getCmsPage(),
            // Listing pagination and filters render the CMS page without a NavigationPage wrapper.
            $request->attributes->get('_route') === 'frontend.cms.navigation.page'
                && $request->attributes->get('navigationId') === $navigationId => $parameters['cmsPage'] ?? null,
            default => null,
        };

        $products = $this->collectProducts($cmsPage instanceof CmsPageEntity ? $cmsPage : null);
        if ($products === []) {
            return;
        }

        $uniqueProducts = [];
        foreach ($products as $product) {
            $uniqueProducts[$product->getId()] = $product;
        }
        $paths = $this->loader->load(array_values($uniqueProducts), $event->getSalesChannelContext());

        foreach ($products as $product) {
            $names = $paths[$product->getId()] ?? [];

            if ($names !== []) {
                $product->addExtension(self::EXTENSION, new ArrayStruct($names));
            }
        }
    }

    /**
     * The products the slots of the page show, whatever element shows them: the Shopware sliders,
     * boxes and listings, and the elements of extensions, which carry their products in their own
     * data structs. A product is not walked into, and neither is any other entity, so the walk stays
     * at the few objects an element keeps in its data.
     *
     * @return array<int, SalesChannelProductEntity>
     */
    private function collectProducts(?CmsPageEntity $cmsPage): array
    {
        $products = [];

        foreach ($cmsPage?->getSections()?->getBlocks()->getSlots() ?? [] as $slot) {
            $this->collect($slot->getData(), $products, 0);
        }

        return $products;
    }

    /**
     * @param array<int, SalesChannelProductEntity> $products
     */
    private function collect(mixed $value, array &$products, int $depth): void
    {
        if ($value instanceof SalesChannelProductEntity) {
            // Different slots can contain distinct objects for the same product. Enrich all of them.
            $products[spl_object_id($value)] = $value;

            return;
        }

        if ($depth > self::MAX_DEPTH || $value instanceof Entity || $value instanceof Context || $value instanceof Criteria) {
            return;
        }

        $children = match (true) {
            $value instanceof EntitySearchResult => $value->getEntities()->getElements(),
            $value instanceof Collection => $value->getElements(),
            $value instanceof Struct => $value->getVars(),
            \is_array($value) => $value,
            default => [],
        };

        foreach ($children as $child) {
            if (\is_object($child) || \is_array($child)) {
                $this->collect($child, $products, $depth + 1);
            }
        }
    }
}

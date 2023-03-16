<?php declare(strict_types=1);

namespace Shopware\Core\Content\LandingPage\SalesChannel;

use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\SalesChannel\Struct\ProductBoxStruct;
use Shopware\Core\Content\Cms\SalesChannel\Struct\ProductSliderStruct;
use Shopware\Core\Content\LandingPage\Event\LandingPageRouteCacheKeyEvent;
use Shopware\Core\Content\LandingPage\Event\LandingPageRouteCacheTagsEvent;
use Shopware\Core\Framework\Adapter\Cache\AbstractCacheTracer;
use Shopware\Core\Framework\Adapter\Cache\CacheValueCompressor;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RuleAreas;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Json;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Route(defaults: ['_routeScope' => ['store-api']])]
#[Package('content')]
class CachedLandingPageRoute extends AbstractLandingPageRoute
{
    /**
     * @internal
     *
     * @param AbstractCacheTracer<LandingPageRouteResponse> $tracer
     * @param array<string> $states
     */
    public function __construct(
        private readonly AbstractLandingPageRoute $decorated,
        private readonly CacheInterface $cache,
        private readonly EntityCacheKeyGenerator $generator,
        private readonly AbstractCacheTracer $tracer,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly array $states
    ) {
    }

    public static function buildName(string $id): string
    {
        return 'landing-page-route-' . $id;
    }

    public function getDecorated(): AbstractLandingPageRoute
    {
        return $this->decorated;
    }

    #[Route(path: '/store-api/landing-page/{landingPageId}', name: 'store-api.landing-page.detail', methods: ['POST'])]
    public function load(string $landingPageId, Request $request, SalesChannelContext $context): LandingPageRouteResponse
    {
        if ($context->hasState(...$this->states)) {
            return $this->getDecorated()->load($landingPageId, $request, $context);
        }

        $key = $this->generateKey($landingPageId, $request, $context);

        if ($key === null) {
            return $this->getDecorated()->load($landingPageId, $request, $context);
        }

        $value = $this->cache->get($key, function (ItemInterface $item) use ($request, $context, $landingPageId) {
            $name = self::buildName($landingPageId);
            $response = $this->tracer->trace($name, fn () => $this->getDecorated()->load($landingPageId, $request, $context));

            $item->tag($this->generateTags($landingPageId, $response, $request, $context));

            return CacheValueCompressor::compress($response);
        });

        return CacheValueCompressor::uncompress($value);
    }

    private function generateKey(string $landingPageId, Request $request, SalesChannelContext $context): ?string
    {
        $parts = [...$request->query->all(), ...$request->request->all(), ...[$this->generator->getSalesChannelContextHash($context, [RuleAreas::LANDING_PAGE_AREA, RuleAreas::PRODUCT_AREA, RuleAreas::CATEGORY_AREA])]];

        $event = new LandingPageRouteCacheKeyEvent($landingPageId, $parts, $request, $context, null);
        $this->dispatcher->dispatch($event);

        if (!$event->shouldCache()) {
            return null;
        }

        return self::buildName($landingPageId) . '-' . md5(Json::encode($event->getParts()));
    }

    /**
     * @return array<string>
     */
    private function generateTags(string $landingPageId, LandingPageRouteResponse $response, Request $request, SalesChannelContext $context): array
    {
        $tags = array_merge(
            $this->tracer->get(self::buildName($landingPageId)),
            $this->extractIds($response),
            [self::buildName($landingPageId)]
        );

        $event = new LandingPageRouteCacheTagsEvent($landingPageId, $tags, $request, $response, $context, null);
        $this->dispatcher->dispatch($event);

        return array_unique(array_filter($event->getTags()));
    }

    /**
     * @return array<string>
     */
    private function extractIds(LandingPageRouteResponse $response): array
    {
        $page = $response->getLandingPage()->getCmsPage();

        if ($page === null) {
            return [];
        }

        $ids = [];
        $streamIds = [];

        $slots = $page->getElementsOfType('product-slider');
        /** @var CmsSlotEntity $slot */
        foreach ($slots as $slot) {
            $slider = $slot->getData();

            if (!$slider instanceof ProductSliderStruct) {
                continue;
            }

            if ($slider->getStreamId() !== null) {
                $streamIds[] = $slider->getStreamId();
            }

            if ($slider->getProducts() === null) {
                continue;
            }
            foreach ($slider->getProducts() as $product) {
                $ids[] = $product->getId();
                $ids[] = $product->getParentId();
            }
        }

        $slots = $page->getElementsOfType('product-box');
        /** @var CmsSlotEntity $slot */
        foreach ($slots as $slot) {
            $box = $slot->getData();

            if (!$box instanceof ProductBoxStruct) {
                continue;
            }
            if ($box->getProduct() === null) {
                continue;
            }

            $ids[] = $box->getProduct()->getId();
            $ids[] = $box->getProduct()->getParentId();
        }

        $ids = array_values(array_unique(array_filter($ids)));

        return [...array_map(EntityCacheKeyGenerator::buildProductTag(...), $ids), ...array_map(EntityCacheKeyGenerator::buildStreamTag(...), $streamIds), ...[EntityCacheKeyGenerator::buildCmsTag($page->getId())]];
    }
}

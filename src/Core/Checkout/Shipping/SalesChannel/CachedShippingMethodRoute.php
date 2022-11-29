<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Shipping\SalesChannel;

use Shopware\Core\Checkout\Shipping\Event\ShippingMethodRouteCacheKeyEvent;
use Shopware\Core\Checkout\Shipping\Event\ShippingMethodRouteCacheTagsEvent;
use Shopware\Core\Framework\Adapter\Cache\AbstractCacheTracer;
use Shopware\Core\Framework\Adapter\Cache\CacheValueCompressor;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RuleAreas;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\JsonFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Routing\Annotation\Entity;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\StoreApiResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @package checkout
 *
 * @Route(defaults={"_routeScope"={"store-api"}})
 */
class CachedShippingMethodRoute extends AbstractShippingMethodRoute
{
    public const ALL_TAG = 'shipping-method-route';

    private AbstractShippingMethodRoute $decorated;

    private CacheInterface $cache;

    private EntityCacheKeyGenerator $generator;

    /**
     * @var AbstractCacheTracer<ShippingMethodRouteResponse>
     */
    private AbstractCacheTracer $tracer;

    /**
     * @var array<string>
     */
    private array $states;

    private EventDispatcherInterface $dispatcher;

    /**
     * @internal
     *
     * @param AbstractCacheTracer<ShippingMethodRouteResponse> $tracer
     * @param array<string> $states
     */
    public function __construct(
        AbstractShippingMethodRoute $decorated,
        CacheInterface $cache,
        EntityCacheKeyGenerator $generator,
        AbstractCacheTracer $tracer,
        EventDispatcherInterface $dispatcher,
        array $states
    ) {
        $this->decorated = $decorated;
        $this->cache = $cache;
        $this->generator = $generator;
        $this->tracer = $tracer;
        $this->states = $states;
        $this->dispatcher = $dispatcher;
    }

    public function getDecorated(): AbstractShippingMethodRoute
    {
        return $this->decorated;
    }

    /**
     * @Since("6.2.0.0")
     * @Entity("shipping_method")
     * @Route("/store-api/shipping-method", name="store-api.shipping.method", methods={"GET", "POST"})
     */
    public function load(Request $request, SalesChannelContext $context, Criteria $criteria): ShippingMethodRouteResponse
    {
        if ($context->hasState(...$this->states)) {
            return $this->getDecorated()->load($request, $context, $criteria);
        }

        $key = $this->generateKey($request, $context, $criteria);

        if ($key === null) {
            return $this->getDecorated()->load($request, $context, $criteria);
        }

        $value = $this->cache->get($key, function (ItemInterface $item) use ($request, $context, $criteria) {
            $name = self::buildName($context->getSalesChannelId());

            $response = $this->tracer->trace($name, function () use ($request, $context, $criteria) {
                return $this->getDecorated()->load($request, $context, $criteria);
            });

            $item->tag($this->generateTags($request, $response, $context, $criteria));

            return CacheValueCompressor::compress($response);
        });

        return CacheValueCompressor::uncompress($value);
    }

    public static function buildName(string $salesChannelId): string
    {
        return 'shipping-method-route-' . $salesChannelId;
    }

    private function generateKey(Request $request, SalesChannelContext $context, Criteria $criteria): ?string
    {
        $parts = [
            $this->generator->getCriteriaHash($criteria),
            $this->generator->getSalesChannelContextHash($context, [RuleAreas::SHIPPING_AREA]),
            $request->query->getBoolean('onlyAvailable', false),
        ];

        $event = new ShippingMethodRouteCacheKeyEvent($parts, $request, $context, $criteria);
        $this->dispatcher->dispatch($event);

        if (!$event->shouldCache()) {
            return null;
        }

        return self::buildName($context->getSalesChannelId()) . '-' . md5(JsonFieldSerializer::encodeJson($event->getParts()));
    }

    /**
     * @return array<string>
     */
    private function generateTags(Request $request, StoreApiResponse $response, SalesChannelContext $context, Criteria $criteria): array
    {
        $tags = array_merge(
            $this->tracer->get(self::buildName($context->getSalesChannelId())),
            [self::buildName($context->getSalesChannelId()), self::ALL_TAG]
        );

        $event = new ShippingMethodRouteCacheTagsEvent($tags, $request, $response, $context, $criteria);
        $this->dispatcher->dispatch($event);

        return array_unique(array_filter($event->getTags()));
    }
}

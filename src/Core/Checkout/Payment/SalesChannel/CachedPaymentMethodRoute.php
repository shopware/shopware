<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\SalesChannel;

use Shopware\Core\Checkout\Payment\Event\PaymentMethodRouteCacheKeyEvent;
use Shopware\Core\Checkout\Payment\Event\PaymentMethodRouteCacheTagsEvent;
use Shopware\Core\Framework\Adapter\Cache\AbstractCacheTracer;
use Shopware\Core\Framework\Adapter\Cache\CacheValueCompressor;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RuleAreas;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Json;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\StoreApiResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Route(defaults: ['_routeScope' => ['store-api']])]
#[Package('checkout')]
class CachedPaymentMethodRoute extends AbstractPaymentMethodRoute
{
    final public const ALL_TAG = 'payment-method-route';

    /**
     * @internal
     *
     * @param AbstractCacheTracer<PaymentMethodRouteResponse> $tracer
     * @param array<string> $states
     */
    public function __construct(
        private readonly AbstractPaymentMethodRoute $decorated,
        private readonly CacheInterface $cache,
        private readonly EntityCacheKeyGenerator $generator,
        private readonly AbstractCacheTracer $tracer,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly array $states
    ) {
    }

    public function getDecorated(): AbstractPaymentMethodRoute
    {
        return $this->decorated;
    }

    #[Route(path: '/store-api/payment-method', name: 'store-api.payment.method', methods: ['GET', 'POST'], defaults: ['_entity' => 'payment_method'])]
    public function load(Request $request, SalesChannelContext $context, Criteria $criteria): PaymentMethodRouteResponse
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

            $response = $this->tracer->trace($name, fn () => $this->getDecorated()->load($request, $context, $criteria));

            $item->tag($this->generateTags($request, $response, $context, $criteria));

            return CacheValueCompressor::compress($response);
        });

        return CacheValueCompressor::uncompress($value);
    }

    public static function buildName(string $salesChannelId): string
    {
        return 'payment-method-route-' . $salesChannelId;
    }

    private function generateKey(Request $request, SalesChannelContext $context, Criteria $criteria): ?string
    {
        $parts = [
            $this->generator->getCriteriaHash($criteria),
            $this->generator->getSalesChannelContextHash($context, [RuleAreas::PAYMENT_AREA]),
            $request->query->getBoolean('onlyAvailable', false),
        ];

        $event = new PaymentMethodRouteCacheKeyEvent($parts, $request, $context, $criteria);
        $this->dispatcher->dispatch($event);

        if (!$event->shouldCache()) {
            return null;
        }

        return self::buildName($context->getSalesChannelId()) . '-' . md5(Json::encode($event->getParts()));
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

        $event = new PaymentMethodRouteCacheTagsEvent($tags, $request, $response, $context, $criteria);
        $this->dispatcher->dispatch($event);

        return array_unique(array_filter($event->getTags()));
    }
}

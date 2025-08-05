<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Shipping\SalesChannel;

use Shopware\Core\Checkout\Shipping\Hook\ShippingMethodRouteHook;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\Framework\Rule\RuleIdMatcher;
use Shopware\Core\Framework\Script\Execution\ScriptExecutor;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
#[Package('checkout')]
class ShippingMethodRoute extends AbstractShippingMethodRoute
{
    final public const ALL_TAG = 'shipping-method-route';

    /**
     * @param SalesChannelRepository<ShippingMethodCollection> $shippingMethodRepository
     *
     * @internal
     */
    public function __construct(
        private readonly SalesChannelRepository $shippingMethodRepository,
        private readonly CacheTagCollector $cacheTagCollector,
        private readonly ScriptExecutor $scriptExecutor,
        private readonly RuleIdMatcher $ruleIdMatcher,
    ) {
    }

    public function getDecorated(): AbstractShippingMethodRoute
    {
        throw new DecorationPatternException(self::class);
    }

    public static function buildName(string $salesChannelId): string
    {
        return 'shipping-method-route-' . $salesChannelId;
    }

    #[Route(
        path: '/store-api/shipping-method',
        name: 'store-api.shipping.method',
        defaults: ['_entity' => 'shipping_method'],
        methods: ['GET', 'POST']
    )]
    public function load(Request $request, SalesChannelContext $context, Criteria $criteria): ShippingMethodRouteResponse
    {
        $this->cacheTagCollector->addTag(self::buildName($context->getSalesChannelId()));

        $criteria
            ->addFilter(new EqualsFilter('active', true))
            ->addAssociation('media');

        if (empty($criteria->getSorting())) {
            $criteria->addSorting(new FieldSorting('position'), new FieldSorting('name', FieldSorting::ASCENDING));
        }

        $result = $this->shippingMethodRepository->search($criteria, $context);

        $shippingMethods = $result->getEntities();
        $shippingMethods->sortShippingMethodsByPreference($context);

        if ($request->query->getBoolean('onlyAvailable') || $request->request->getBoolean('onlyAvailable')) {
            $shippingMethods = $this->ruleIdMatcher->filterCollection($shippingMethods, $context->getRuleIds());
        }

        $result->assign(['entities' => $shippingMethods, 'elements' => $shippingMethods->getElements(), 'total' => $shippingMethods->count()]);

        $this->scriptExecutor->execute(new ShippingMethodRouteHook(
            $shippingMethods,
            $request->query->getBoolean('onlyAvailable') || $request->request->getBoolean('onlyAvailable'),
            $context,
        ));

        return new ShippingMethodRouteResponse($result);
    }
}

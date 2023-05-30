<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Shipping\SalesChannel;

use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['store-api']])]
#[Package('checkout')]
class ShippingMethodRoute extends AbstractShippingMethodRoute
{
    /**
     * @internal
     */
    public function __construct(private readonly SalesChannelRepository $shippingMethodRepository)
    {
    }

    public function getDecorated(): AbstractShippingMethodRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/shipping-method', name: 'store-api.shipping.method', methods: ['GET', 'POST'], defaults: ['_entity' => 'shipping_method'])]
    public function load(Request $request, SalesChannelContext $context, Criteria $criteria): ShippingMethodRouteResponse
    {
        $criteria
            ->addFilter(new EqualsFilter('active', true))
            ->addAssociation('media');

        if (empty($criteria->getSorting())) {
            $criteria->addSorting(new FieldSorting('position'), new FieldSorting('name', FieldSorting::ASCENDING));
        }

        $result = $this->shippingMethodRepository->search($criteria, $context);

        /** @var ShippingMethodCollection $shippingMethods */
        $shippingMethods = $result->getEntities();

        if ($request->query->getBoolean('onlyAvailable') || $request->request->getBoolean('onlyAvailable')) {
            $shippingMethods = $shippingMethods->filterByActiveRules($context);
        }

        $result->assign(['entities' => $shippingMethods, 'elements' => $shippingMethods, 'total' => $shippingMethods->count()]);

        return new ShippingMethodRouteResponse($result);
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Shipping\SalesChannel;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Routing\Annotation\Entity;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @package checkout
 *
 * @Route(defaults={"_routeScope"={"store-api"}})
 */
class SortedShippingMethodRoute extends AbstractShippingMethodRoute
{
    private AbstractShippingMethodRoute $decorated;

    /**
     * @internal
     */
    public function __construct(AbstractShippingMethodRoute $decorated)
    {
        $this->decorated = $decorated;
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
        $response = $this->getDecorated()->load($request, $context, $criteria);

        $response->getShippingMethods()->sortShippingMethodsByPreference($context);

        return $response;
    }
}

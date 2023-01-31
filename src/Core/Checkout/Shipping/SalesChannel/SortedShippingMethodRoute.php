<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Shipping\SalesChannel;

use Shopware\Core\Checkout\Shipping\Hook\ShippingMethodRouteHook;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Execution\ScriptExecutor;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['store-api']])]
#[Package('checkout')]
class SortedShippingMethodRoute extends AbstractShippingMethodRoute
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractShippingMethodRoute $decorated,
        private readonly ScriptExecutor $scriptExecutor
    ) {
    }

    public function getDecorated(): AbstractShippingMethodRoute
    {
        return $this->decorated;
    }

    #[Route(path: '/store-api/shipping-method', name: 'store-api.shipping.method', methods: ['GET', 'POST'], defaults: ['_entity' => 'shipping_method'])]
    public function load(Request $request, SalesChannelContext $context, Criteria $criteria): ShippingMethodRouteResponse
    {
        $response = $this->getDecorated()->load($request, $context, $criteria);

        $response->getShippingMethods()->sortShippingMethodsByPreference($context);

        $this->scriptExecutor->execute(new ShippingMethodRouteHook(
            $response->getShippingMethods(),
            $request->query->getBoolean('onlyAvailable') || $request->request->getBoolean('onlyAvailable'),
            $context
        ));

        return $response;
    }
}

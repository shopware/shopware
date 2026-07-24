<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\SalesChannel;

use Shopware\Core\Checkout\Payment\Hook\PaymentMethodRouteHook;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Execution\ScriptExecutor;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @deprecated tag:v6.7.0 - reason:decoration-will-be-removed - Will be removed
 */
#[Package('checkout')]
class SortedPaymentMethodRoute extends AbstractPaymentMethodRoute
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractPaymentMethodRoute $decorated,
        private readonly ScriptExecutor $scriptExecutor
    ) {
    }

    public function getDecorated(): AbstractPaymentMethodRoute
    {
        return $this->decorated;
    }

    public function load(Request $request, SalesChannelContext $context, Criteria $criteria): PaymentMethodRouteResponse
    {
        if (Feature::isActive('cache_rework')) {
            return $this->getDecorated()->load($request, $context, $criteria);
        }

        $response = $this->getDecorated()->load($request, $context, $criteria);

        $response->getPaymentMethods()->sortPaymentMethodsByPreference($context);

        $this->scriptExecutor->execute(new PaymentMethodRouteHook(
            $response->getPaymentMethods(),
            $request->query->getBoolean('onlyAvailable') || $request->request->getBoolean('onlyAvailable'),
            $context
        ));

        return $response;
    }
}

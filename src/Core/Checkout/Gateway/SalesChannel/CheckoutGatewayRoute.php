<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Gateway\SalesChannel;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Gateway\CheckoutGatewayInterface;
use Shopware\Core\Checkout\Gateway\CheckoutGatewayResponse;
use Shopware\Core\Checkout\Gateway\Command\Struct\CheckoutGatewayPayloadStruct;
use Shopware\Core\Checkout\Payment\Cart\Error\PaymentMethodBlockedError;
use Shopware\Core\Checkout\Payment\SalesChannel\AbstractPaymentMethodRoute;
use Shopware\Core\Checkout\Shipping\Cart\Error\ShippingMethodBlockedError;
use Shopware\Core\Checkout\Shipping\SalesChannel\AbstractShippingMethodRoute;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Rule\RuleIdMatcher;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['store-api']])]
#[Package('checkout')]
class CheckoutGatewayRoute extends AbstractCheckoutGatewayRoute
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractPaymentMethodRoute $paymentMethodRoute,
        private readonly AbstractShippingMethodRoute $shippingMethodRoute,
        private readonly CheckoutGatewayInterface $checkoutGateway,
        private readonly RuleIdMatcher $ruleIdMatcher,
    ) {
    }

    public function getDecorated(): AbstractCheckoutGatewayRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/checkout/gateway', name: 'store-api.checkout.gateway', methods: ['GET', 'POST'])]
    public function load(Request $request, Cart $cart, SalesChannelContext $context): CheckoutGatewayRouteResponse
    {
        $paymentCriteria = new Criteria();
        $shippingCriteria = new Criteria();

        $paymentCriteria->addAssociation('appPaymentMethod.app');
        $shippingCriteria->addAssociation('appShippingMethod.app');

        if ($request->query->has('onlyAvailable') || $request->request->has('onlyAvailable')) {
            Feature::triggerDeprecationOrThrow(
                'v6.7.0.0',
                'The "onlyAvailable" parameter is deprecated. The checkout gateway route will always filter payment and shipping methods based on active rules'
            );
        }

        if (!Feature::isActive('v6.7.0.0')) {
            $request->query->set('onlyAvailable', '1');
        }

        $result = $this->paymentMethodRoute->load($request, $context, $paymentCriteria);
        $paymentMethods = $this->ruleIdMatcher->filterCollection($result->getPaymentMethods(), $context->getRuleIds());

        $result = $this->shippingMethodRoute->load($request, $context, $shippingCriteria);
        $shippingMethods = $this->ruleIdMatcher->filterCollection($result->getShippingMethods(), $context->getRuleIds());

        $payload = new CheckoutGatewayPayloadStruct($cart, $context, $paymentMethods, $shippingMethods);
        $response = $this->checkoutGateway->process($payload);

        $this->addBlockedMethodsCartErrors($response, $cart, $context);

        return new CheckoutGatewayRouteResponse($response->getAvailablePaymentMethods(), $response->getAvailableShippingMethods(), $response->getCartErrors());
    }

    private function addBlockedMethodsCartErrors(CheckoutGatewayResponse $response, Cart $cart, SalesChannelContext $context): void
    {
        $paymentMethod = $context->getPaymentMethod();

        if (!\in_array($paymentMethod->getId(), $response->getAvailablePaymentMethods()->getIds(), true)) {
            $response->getCartErrors()->add(
                new PaymentMethodBlockedError((string) $paymentMethod->getTranslation('name'), 'not allowed')
            );
        }

        foreach ($cart->getDeliveries() as $delivery) {
            $deliveryMethod = $delivery->getShippingMethod();

            if (!\in_array($deliveryMethod->getId(), $response->getAvailableShippingMethods()->getIds(), true)) {
                $response->getCartErrors()->add(
                    new ShippingMethodBlockedError((string) $deliveryMethod->getTranslation('name'))
                );
            }
        }
    }
}

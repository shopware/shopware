<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Gateway\SalesChannel;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Transaction\Struct\Transaction;
use Shopware\Core\Checkout\Gateway\CheckoutGatewayInterface;
use Shopware\Core\Checkout\Gateway\CheckoutGatewayResponse;
use Shopware\Core\Checkout\Gateway\Command\Struct\CheckoutGatewayPayloadStruct;
use Shopware\Core\Checkout\Gateway\Extension\CheckoutGatewayRouteExtension;
use Shopware\Core\Checkout\Payment\Cart\Error\PaymentMethodBlockedError;
use Shopware\Core\Checkout\Payment\SalesChannel\AbstractPaymentMethodRoute;
use Shopware\Core\Checkout\Shipping\Cart\Error\ShippingMethodBlockedError;
use Shopware\Core\Checkout\Shipping\SalesChannel\AbstractShippingMethodRoute;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Package('checkout')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class CheckoutGatewayRoute extends AbstractCheckoutGatewayRoute
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractPaymentMethodRoute $paymentMethodRoute,
        private readonly AbstractShippingMethodRoute $shippingMethodRoute,
        private readonly CheckoutGatewayInterface $checkoutGateway,
        private readonly ExtensionDispatcher $extensions,
    ) {
    }

    public function getDecorated(): AbstractCheckoutGatewayRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/checkout/gateway',
        name: 'store-api.checkout.gateway',
        defaults: [PlatformRequest::ATTRIBUTE_ALLOW_ORDER_RESTORATION => true],
        methods: ['GET', 'POST']
    )]
    public function load(Request $request, Cart $cart, SalesChannelContext $context): CheckoutGatewayRouteResponse
    {
        return $this->extensions->publish(
            name: CheckoutGatewayRouteExtension::NAME,
            extension: new CheckoutGatewayRouteExtension($request, $cart, $context),
            function: $this->_load(...),
        );
    }

    private function _load(Request $request, Cart $cart, SalesChannelContext $context): CheckoutGatewayRouteResponse
    {
        $paymentCriteria = new Criteria();
        $shippingCriteria = new Criteria();

        $paymentCriteria->addAssociation('appPaymentMethod.app');
        $shippingCriteria->addAssociation('appShippingMethod.app');

        // Only load available payment and shipping methods from the routes
        $request->query->set('onlyAvailable', '1');

        $paymentMethods = $this->paymentMethodRoute->load($request, $context, $paymentCriteria)->getPaymentMethods();
        $shippingMethods = $this->shippingMethodRoute->load($request, $context, $shippingCriteria)->getShippingMethods();

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
                new PaymentMethodBlockedError(
                    id: $paymentMethod->getId(),
                    name: (string) $paymentMethod->getTranslation('name'),
                    reason: 'not allowed',
                    transactionIndexes: $this->findTransactionIndexes($cart, $paymentMethod->getId()),
                )
            );
        }

        $blockedShippingMethods = [];
        $blockedDeliveryIndexes = [];

        foreach (array_values($cart->getDeliveries()->getElements()) as $deliveryIndex => $delivery) {
            $shippingMethod = $delivery->getShippingMethod();

            if (\in_array($shippingMethod->getId(), $response->getAvailableShippingMethods()->getIds(), true)) {
                continue;
            }

            $blockedShippingMethods[$shippingMethod->getId()] = $shippingMethod;

            if ($delivery->getShippingCosts()->getTotalPrice() >= 0) {
                $blockedDeliveryIndexes[$shippingMethod->getId()][] = $deliveryIndex;
            }
        }

        foreach ($blockedShippingMethods as $shippingMethod) {
            $response->getCartErrors()->add(
                new ShippingMethodBlockedError(
                    id: $shippingMethod->getId(),
                    name: (string) $shippingMethod->getTranslation('name'),
                    reason: 'not allowed',
                    deliveryIndexes: $blockedDeliveryIndexes[$shippingMethod->getId()] ?? [],
                )
            );
        }
    }

    /**
     * @return list<int>
     */
    private function findTransactionIndexes(Cart $cart, string $paymentMethodId): array
    {
        $transactions = array_values($cart->getTransactions()->getElements());

        return array_keys(array_filter(
            $transactions,
            static fn (Transaction $transaction) => $transaction->getPaymentMethodId() === $paymentMethodId,
        ));
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\SalesChannel;

use Shopware\Core\Checkout\Cart\AbstractCartPersister;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartCalculator;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\CartLocker;
use Shopware\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopware\Core\Checkout\Cart\Delivery\Struct\SelectedDeliveryShippingMethods;
use Shopware\Core\Checkout\Cart\Event\CartChangedEvent;
use Shopware\Core\Checkout\Cart\Extension\CartDeliveryUpdateRouteExtension;
use Shopware\Core\Checkout\Shipping\ShippingMethodDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Validation\EntityExists;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('checkout')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class CartDeliveryUpdateRoute
{
    public function __construct(
        private readonly AbstractCartPersister $cartPersister,
        private readonly CartCalculator $cartCalculator,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly CartLocker $cartLocker,
        private readonly DataValidator $validator,
        private readonly ExtensionDispatcher $extensions,
    ) {
    }

    #[Route(path: '/store-api/checkout/cart/delivery', name: 'store-api.checkout.cart.delivery.update', methods: [Request::METHOD_PATCH])]
    public function update(Request $request, Cart $cart, SalesChannelContext $context): CartResponse
    {
        return $this->extensions->publish(
            name: CartDeliveryUpdateRouteExtension::NAME,
            extension: new CartDeliveryUpdateRouteExtension($request, $cart, $context),
            function: $this->_update(...),
        );
    }

    private function _update(Request $request, Cart $cart, SalesChannelContext $context): CartResponse
    {
        return $this->cartLocker->locked($context, function () use ($request, $cart, $context) {
            /** @var list<array{identifier: string, shippingMethodId?: string|null}> $deliveries */
            $deliveries = $request->request->all('deliveries');
            $this->validator->validate(['deliveries' => $deliveries], $this->createValidationDefinition($context));

            $shipmentIdentifiers = $cart->getDeliveries()
                ->filter(static fn (Delivery $delivery) => $delivery->getShippingCosts()->getTotalPrice() >= 0)
                ->map(static fn (Delivery $delivery) => $delivery->getIdentifier());

            foreach ($deliveries as $delivery) {
                if (!\in_array($delivery['identifier'], $shipmentIdentifiers, true)) {
                    throw CartException::deliveryNotFound($delivery['identifier']);
                }
            }

            $selection = $cart->getExtensionOfType(SelectedDeliveryShippingMethods::KEY, SelectedDeliveryShippingMethods::class)
                ?? new SelectedDeliveryShippingMethods();

            foreach ($deliveries as $delivery) {
                $identifier = $delivery['identifier'];
                $shippingMethodId = $delivery['shippingMethodId'] ?? null;
                if ($shippingMethodId === null) {
                    $selection->removeShippingMethodId($identifier);

                    continue;
                }

                $selection->setShippingMethodId($identifier, $shippingMethodId);
            }

            $cart->addExtension(SelectedDeliveryShippingMethods::KEY, $selection);
            $cart->markModified();

            $cart = $this->cartCalculator->calculate($cart, $context);
            $this->cartPersister->save($cart, $context);

            $this->eventDispatcher->dispatch(new CartChangedEvent($cart, $context));

            return new CartResponse($cart);
        });
    }

    private function createValidationDefinition(SalesChannelContext $context): DataValidationDefinition
    {
        $shippingMethodCriteria = (new Criteria())
            ->addFilter(new EqualsFilter('shipping_method.salesChannels.id', $context->getSalesChannelId()));

        return (new DataValidationDefinition('cart.delivery.update'))
            ->add('deliveries', new NotBlank())
            ->addList(
                'deliveries',
                (new DataValidationDefinition())
                    ->add('identifier', new NotBlank(), new Type('string'))
                    ->add('shippingMethodId', new Type('string'), new EntityExists(
                        entity: ShippingMethodDefinition::ENTITY_NAME,
                        context: $context->getContext(),
                        criteria: $shippingMethodCriteria,
                    ))
            );
    }
}

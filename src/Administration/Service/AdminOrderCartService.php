<?php declare(strict_types=1);

namespace Shopware\Administration\Service;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Delivery\DeliveryProcessor;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class AdminOrderCartService
{
    /**
     * @var CartService
     */
    protected $cartService;

    /**
     * @var SalesChannelContextPersister
     */
    protected $contextPersister;

    public function __construct(CartService $cartService, SalesChannelContextPersister $contextPersister)
    {
        $this->cartService = $cartService;
        $this->contextPersister = $contextPersister;
    }

    public function updateShippingCosts(CalculatedPrice $calculatedPrice, SalesChannelContext $context): Cart
    {
        $cart = $this->cartService->getCart($context->getToken(), $context);

        $cart->addExtension(DeliveryProcessor::MANUAL_SHIPPING_COSTS, $calculatedPrice);

        return $this->cartService->recalculate($cart, $context);
    }

    public function addPermission(string $token, string $permission): void
    {
        $payload = $this->contextPersister->load($token);
        if (!array_key_exists(SalesChannelContextService::PERMISSIONS, $payload)) {
            $payload[SalesChannelContextService::PERMISSIONS] = [];
        }

        $payload[SalesChannelContextService::PERMISSIONS][$permission] = true;
        $this->contextPersister->save($token, $payload);
    }

    public function deletePermission(string $token, string $permission): void
    {
        $payload = $this->contextPersister->load($token);
        $payload[SalesChannelContextService::PERMISSIONS][$permission] = false;
        $this->contextPersister->save($token, $payload);
    }
}

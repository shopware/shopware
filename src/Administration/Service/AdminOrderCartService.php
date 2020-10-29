<?php declare(strict_types=1);

namespace Shopware\Administration\Service;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Delivery\DeliveryProcessor;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Framework\Feature;
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

    /**
     * @feature-deprecated (flag:FEATURE_NEXT_10058) tag:v6.4.0 - $salesChannelId will be required
     */
    public function addPermission(string $token, string $permission, ?string $salesChannelId = null): void
    {
        $payload = $this->contextPersister->load($token, $salesChannelId);

        if (!array_key_exists(SalesChannelContextService::PERMISSIONS, $payload)) {
            $payload[SalesChannelContextService::PERMISSIONS] = [];
        }

        $payload[SalesChannelContextService::PERMISSIONS][$permission] = true;
        $this->contextPersister->save($token, $payload, $salesChannelId);
    }

    /**
     * @feature-deprecated (flag:FEATURE_NEXT_10058) tag:v6.4.0 - $salesChannelId will be required
     */
    public function deletePermission(string $token, string $permission, ?string $salesChannelId = null): void
    {
        $payload = $this->contextPersister->load($token, Feature::isActive('FEATURE_NEXT_10058') ? $salesChannelId : null);
        $payload[SalesChannelContextService::PERMISSIONS][$permission] = false;

        $this->contextPersister->save($token, $payload, $salesChannelId);
    }
}

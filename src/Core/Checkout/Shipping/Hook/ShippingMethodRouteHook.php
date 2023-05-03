<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Shipping\Hook;

use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Execution\Awareness\SalesChannelContextAwareTrait;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\StoreApiRequestHook;

/**
 * Triggered when ShippingMethodRoute is requested
 *
 * @hook-use-case data_loading
 *
 * @since 6.5.0.0
 *
 * @final
 */
#[Package('checkout')]
class ShippingMethodRouteHook extends StoreApiRequestHook
{
    use SalesChannelContextAwareTrait;

    final public const HOOK_NAME = 'shipping-method-route-request';

    /**
     * @internal
     */
    public function __construct(
        private readonly ShippingMethodCollection $collection,
        private readonly bool $onlyAvailable,
        protected SalesChannelContext $salesChannelContext
    ) {
        parent::__construct($salesChannelContext->getContext());
    }

    public function getName(): string
    {
        return self::HOOK_NAME;
    }

    public function getCollection(): ShippingMethodCollection
    {
        return $this->collection;
    }

    public function isOnlyAvailable(): bool
    {
        return $this->onlyAvailable;
    }
}

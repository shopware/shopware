<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account\Order;

use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Framework\Page\StorefrontSearchResult;
use Shopware\Storefront\Page\Page;

#[Package('checkout')]
class AccountOrderPage extends Page
{
    /**
     * @deprecated tag:v6.7.0 - Type will change to EntitySearchResult<OrderCollection>
     *
     * @var StorefrontSearchResult<OrderCollection>
     */
    protected $orders;

    /**
     * @var string|null
     */
    protected $deepLinkCode;

    /**
     * @deprecated tag:v6.7.0 - Return type will change to EntitySearchResult<OrderCollection>
     *
     * @return StorefrontSearchResult<OrderCollection>
     */
    public function getOrders(): StorefrontSearchResult
    {
        return $this->orders;
    }

    /**
     * @deprecated tag:v6.7.0 - Type will change to EntitySearchResult<OrderCollection>
     *
     * @param StorefrontSearchResult<OrderCollection> $orders
     */
    public function setOrders(StorefrontSearchResult $orders): void
    {
        $this->orders = $orders;
    }

    public function getDeepLinkCode(): ?string
    {
        return $this->deepLinkCode;
    }

    public function setDeepLinkCode(?string $deepLinkCode): void
    {
        $this->deepLinkCode = $deepLinkCode;
    }
}

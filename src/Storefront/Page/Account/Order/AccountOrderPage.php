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
     * @var StorefrontSearchResult<OrderCollection>
     */
    protected $orders;

    /**
     * @var string|null
     */
    protected $deepLinkCode;

    /**
     * @return StorefrontSearchResult<OrderCollection>
     */
    public function getOrders(): StorefrontSearchResult
    {
        return $this->orders;
    }

    /**
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

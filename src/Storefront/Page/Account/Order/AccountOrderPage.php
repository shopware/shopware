<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account\Order;

use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Page\Page;

#[Package('checkout')]
class AccountOrderPage extends Page
{
    /**
     * @var EntitySearchResult<OrderCollection>
     */
    protected EntitySearchResult $orders;

    protected ?string $deepLinkCode;

    /**
     * @return EntitySearchResult<OrderCollection>
     */
    public function getOrders(): EntitySearchResult
    {
        return $this->orders;
    }

    /**
     * @param EntitySearchResult<OrderCollection> $orders
     */
    public function setOrders(EntitySearchResult $orders): void
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

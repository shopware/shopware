<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account\Order;

use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Framework\Page\StorefrontSearchResult;
use Shopware\Storefront\Page\Page;

#[Package('customer-order')]
class AccountOrderPage extends Page
{
    /**
     * @var StorefrontSearchResult
     */
    protected $orders;

    /**
     * @var string|null
     */
    protected $deepLinkCode;

    public function getOrders(): StorefrontSearchResult
    {
        return $this->orders;
    }

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

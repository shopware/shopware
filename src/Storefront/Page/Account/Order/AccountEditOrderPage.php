<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account\Order;

use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Promotion\PromotionCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Page\Page;

#[Package('customer-order')]
class AccountEditOrderPage extends Page
{
    /**
     * @var OrderEntity
     */
    protected $order;

    /**
     * @var PaymentMethodCollection
     */
    protected $paymentMethods;

    /**
     * @var PromotionCollection
     */
    protected $activePromotions;

    /**
     * @var string|null
     */
    protected $deepLinkCode;

    /**
     * @var bool
     */
    protected $paymentChangeable = true;

    /**
     * @var string|null
     */
    protected $errorCode;

    public function getOrder(): OrderEntity
    {
        return $this->order;
    }

    public function setOrder(OrderEntity $order): void
    {
        $this->order = $order;
    }

    public function getPaymentMethods(): PaymentMethodCollection
    {
        return $this->paymentMethods;
    }

    public function setPaymentMethods(PaymentMethodCollection $paymentMethods): void
    {
        $this->paymentMethods = $paymentMethods;
    }

    public function getDeepLinkCode(): ?string
    {
        return $this->deepLinkCode;
    }

    public function setDeepLinkCode(?string $deepLinkCode): void
    {
        $this->deepLinkCode = $deepLinkCode;
    }

    public function getActivePromotions(): PromotionCollection
    {
        return $this->activePromotions;
    }

    public function setActivePromotions(PromotionCollection $activePromotions): void
    {
        $this->activePromotions = $activePromotions;
    }

    public function isPaymentChangeable(): bool
    {
        return $this->paymentChangeable;
    }

    public function setPaymentChangeable(bool $paymentChangeable): void
    {
        $this->paymentChangeable = $paymentChangeable;
    }

    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    public function setErrorCode(?string $errorCode): void
    {
        $this->errorCode = $errorCode;
    }
}

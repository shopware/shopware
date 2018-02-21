<?php declare(strict_types=1);

namespace Shopware\Api\Payment\Event\PaymentMethod;

use Shopware\Api\Customer\Event\Customer\CustomerBasicLoadedEvent;
use Shopware\Api\Order\Event\Order\OrderBasicLoadedEvent;
use Shopware\Api\Payment\Collection\PaymentMethodDetailCollection;
use Shopware\Api\Payment\Event\PaymentMethodTranslation\PaymentMethodTranslationBasicLoadedEvent;
use Shopware\Api\Plugin\Event\Plugin\PluginBasicLoadedEvent;
use Shopware\Api\Shop\Event\Shop\ShopBasicLoadedEvent;
use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class PaymentMethodDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'payment_method.detail.loaded';

    /**
     * @var ShopContext
     */
    protected $context;

    /**
     * @var PaymentMethodDetailCollection
     */
    protected $paymentMethods;

    public function __construct(PaymentMethodDetailCollection $paymentMethods, ShopContext $context)
    {
        $this->context = $context;
        $this->paymentMethods = $paymentMethods;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ShopContext
    {
        return $this->context;
    }

    public function getPaymentMethods(): PaymentMethodDetailCollection
    {
        return $this->paymentMethods;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->paymentMethods->getPlugins()->count() > 0) {
            $events[] = new PluginBasicLoadedEvent($this->paymentMethods->getPlugins(), $this->context);
        }
        if ($this->paymentMethods->getCustomers()->count() > 0) {
            $events[] = new CustomerBasicLoadedEvent($this->paymentMethods->getCustomers(), $this->context);
        }
        if ($this->paymentMethods->getCustomers()->count() > 0) {
            $events[] = new CustomerBasicLoadedEvent($this->paymentMethods->getCustomers(), $this->context);
        }
        if ($this->paymentMethods->getOrders()->count() > 0) {
            $events[] = new OrderBasicLoadedEvent($this->paymentMethods->getOrders(), $this->context);
        }
        if ($this->paymentMethods->getTranslations()->count() > 0) {
            $events[] = new PaymentMethodTranslationBasicLoadedEvent($this->paymentMethods->getTranslations(), $this->context);
        }
        if ($this->paymentMethods->getShops()->count() > 0) {
            $events[] = new ShopBasicLoadedEvent($this->paymentMethods->getShops(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}

<?php declare(strict_types=1);

namespace Shopware\Payment\Event\PaymentMethod;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Customer\Event\Customer\CustomerBasicLoadedEvent;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\Order\Event\Order\OrderBasicLoadedEvent;
use Shopware\Payment\Collection\PaymentMethodDetailCollection;
use Shopware\Payment\Event\PaymentMethodTranslation\PaymentMethodTranslationBasicLoadedEvent;
use Shopware\Plugin\Event\Plugin\PluginBasicLoadedEvent;
use Shopware\Shop\Event\Shop\ShopBasicLoadedEvent;

class PaymentMethodDetailLoadedEvent extends NestedEvent
{
    const NAME = 'payment_method.detail.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var PaymentMethodDetailCollection
     */
    protected $paymentMethods;

    public function __construct(PaymentMethodDetailCollection $paymentMethods, TranslationContext $context)
    {
        $this->context = $context;
        $this->paymentMethods = $paymentMethods;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
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

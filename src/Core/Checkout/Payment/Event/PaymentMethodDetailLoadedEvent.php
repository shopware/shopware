<?php declare(strict_types=1);

namespace Shopware\Checkout\Payment\Event;

use Shopware\Framework\Context;
use Shopware\Checkout\Payment\Aggregate\PaymentMethodTranslation\Event\PaymentMethodTranslationBasicLoadedEvent;
use Shopware\Checkout\Payment\Collection\PaymentMethodDetailCollection;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\Framework\Plugin\Event\PluginBasicLoadedEvent;

class PaymentMethodDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'payment_method.detail.loaded';

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var PaymentMethodDetailCollection
     */
    protected $paymentMethods;

    public function __construct(PaymentMethodDetailCollection $paymentMethods, Context $context)
    {
        $this->context = $context;
        $this->paymentMethods = $paymentMethods;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
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
        if ($this->paymentMethods->getTranslations()->count() > 0) {
            $events[] = new PaymentMethodTranslationBasicLoadedEvent($this->paymentMethods->getTranslations(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}

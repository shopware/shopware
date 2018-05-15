<?php declare(strict_types=1);

namespace Shopware\Checkout\Payment\Event\PaymentMethod;

use Shopware\Checkout\Payment\Collection\PaymentMethodDetailCollection;
use Shopware\Checkout\Payment\Event\PaymentMethodTranslation\PaymentMethodTranslationBasicLoadedEvent;
use Shopware\Framework\Plugin\Event\Plugin\PluginBasicLoadedEvent;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class PaymentMethodDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'payment_method.detail.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var PaymentMethodDetailCollection
     */
    protected $paymentMethods;

    public function __construct(PaymentMethodDetailCollection $paymentMethods, ApplicationContext $context)
    {
        $this->context = $context;
        $this->paymentMethods = $paymentMethods;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
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

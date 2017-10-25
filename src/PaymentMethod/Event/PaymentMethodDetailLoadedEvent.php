<?php declare(strict_types=1);

namespace Shopware\PaymentMethod\Event;

use Shopware\AreaCountry\Event\AreaCountryBasicLoadedEvent;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\PaymentMethod\Struct\PaymentMethodDetailCollection;
use Shopware\Shop\Event\ShopBasicLoadedEvent;

class PaymentMethodDetailLoadedEvent extends NestedEvent
{
    const NAME = 'payment_method.detail.loaded';

    /**
     * @var PaymentMethodDetailCollection
     */
    protected $paymentMethods;

    /**
     * @var TranslationContext
     */
    protected $context;

    public function __construct(PaymentMethodDetailCollection $paymentMethods, TranslationContext $context)
    {
        $this->paymentMethods = $paymentMethods;
        $this->context = $context;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getPaymentMethods(): PaymentMethodDetailCollection
    {
        return $this->paymentMethods;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [
            new PaymentMethodBasicLoadedEvent($this->paymentMethods, $this->context),
        ];

        if ($this->paymentMethods->getShops()->count() > 0) {
            $events[] = new ShopBasicLoadedEvent($this->paymentMethods->getShops(), $this->context);
        }
        if ($this->paymentMethods->getCountries()->count() > 0) {
            $events[] = new AreaCountryBasicLoadedEvent($this->paymentMethods->getCountries(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}

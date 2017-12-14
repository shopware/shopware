<?php declare(strict_types=1);

namespace Shopware\Api\Payment\Event\PaymentMethodTranslation;

use Shopware\Api\Payment\Collection\PaymentMethodTranslationDetailCollection;
use Shopware\Api\Payment\Event\PaymentMethod\PaymentMethodBasicLoadedEvent;
use Shopware\Api\Shop\Event\Shop\ShopBasicLoadedEvent;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class PaymentMethodTranslationDetailLoadedEvent extends NestedEvent
{
    const NAME = 'payment_method_translation.detail.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var PaymentMethodTranslationDetailCollection
     */
    protected $paymentMethodTranslations;

    public function __construct(PaymentMethodTranslationDetailCollection $paymentMethodTranslations, TranslationContext $context)
    {
        $this->context = $context;
        $this->paymentMethodTranslations = $paymentMethodTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getPaymentMethodTranslations(): PaymentMethodTranslationDetailCollection
    {
        return $this->paymentMethodTranslations;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->paymentMethodTranslations->getPaymentMethods()->count() > 0) {
            $events[] = new PaymentMethodBasicLoadedEvent($this->paymentMethodTranslations->getPaymentMethods(), $this->context);
        }
        if ($this->paymentMethodTranslations->getLanguages()->count() > 0) {
            $events[] = new ShopBasicLoadedEvent($this->paymentMethodTranslations->getLanguages(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}

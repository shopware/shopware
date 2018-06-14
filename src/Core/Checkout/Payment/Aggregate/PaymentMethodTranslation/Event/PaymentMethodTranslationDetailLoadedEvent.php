<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Aggregate\PaymentMethodTranslation\Event;

use Shopware\Core\Checkout\Payment\Aggregate\PaymentMethodTranslation\Collection\PaymentMethodTranslationDetailCollection;
use Shopware\Core\Checkout\Payment\Event\PaymentMethodBasicLoadedEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\System\Language\Event\LanguageBasicLoadedEvent;

class PaymentMethodTranslationDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'payment_method_translation.detail.loaded';

    /**
     * @var \Shopware\Core\Framework\Context
     */
    protected $context;

    /**
     * @var PaymentMethodTranslationDetailCollection
     */
    protected $paymentMethodTranslations;

    public function __construct(PaymentMethodTranslationDetailCollection $paymentMethodTranslations, Context $context)
    {
        $this->context = $context;
        $this->paymentMethodTranslations = $paymentMethodTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
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
            $events[] = new LanguageBasicLoadedEvent($this->paymentMethodTranslations->getLanguages(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}

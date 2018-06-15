<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Aggregate\PaymentMethodTranslation\Event;

use Shopware\Core\Checkout\Payment\Aggregate\PaymentMethodTranslation\Collection\PaymentMethodTranslationBasicCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;

class PaymentMethodTranslationBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'payment_method_translation.basic.loaded';

    /**
     * @var \Shopware\Core\Framework\Context
     */
    protected $context;

    /**
     * @var \Shopware\Core\Checkout\Payment\Aggregate\PaymentMethodTranslation\Collection\PaymentMethodTranslationBasicCollection
     */
    protected $paymentMethodTranslations;

    public function __construct(PaymentMethodTranslationBasicCollection $paymentMethodTranslations, Context $context)
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

    public function getPaymentMethodTranslations(): PaymentMethodTranslationBasicCollection
    {
        return $this->paymentMethodTranslations;
    }
}

<?php declare(strict_types=1);

namespace Shopware\Checkout\Payment\Event\PaymentMethodTranslation;

use Shopware\Checkout\Payment\Collection\PaymentMethodTranslationBasicCollection;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

class PaymentMethodTranslationBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'payment_method_translation.basic.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var PaymentMethodTranslationBasicCollection
     */
    protected $paymentMethodTranslations;

    public function __construct(PaymentMethodTranslationBasicCollection $paymentMethodTranslations, ApplicationContext $context)
    {
        $this->context = $context;
        $this->paymentMethodTranslations = $paymentMethodTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
    {
        return $this->context;
    }

    public function getPaymentMethodTranslations(): PaymentMethodTranslationBasicCollection
    {
        return $this->paymentMethodTranslations;
    }
}

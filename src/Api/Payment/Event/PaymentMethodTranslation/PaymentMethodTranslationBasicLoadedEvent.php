<?php declare(strict_types=1);

namespace Shopware\Api\Payment\Event\PaymentMethodTranslation;

use Shopware\Api\Payment\Collection\PaymentMethodTranslationBasicCollection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;

class PaymentMethodTranslationBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'payment_method_translation.basic.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var PaymentMethodTranslationBasicCollection
     */
    protected $paymentMethodTranslations;

    public function __construct(PaymentMethodTranslationBasicCollection $paymentMethodTranslations, TranslationContext $context)
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

    public function getPaymentMethodTranslations(): PaymentMethodTranslationBasicCollection
    {
        return $this->paymentMethodTranslations;
    }
}

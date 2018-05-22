<?php declare(strict_types=1);

namespace Shopware\Checkout\Payment\Aggregate\PaymentMethodTranslation\Event;

use Shopware\Checkout\Payment\Aggregate\PaymentMethodTranslation\PaymentMethodTranslationDefinition;
use Shopware\Framework\ORM\Write\WrittenEvent;

class PaymentMethodTranslationWrittenEvent extends WrittenEvent
{
    public const NAME = 'payment_method_translation.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return PaymentMethodTranslationDefinition::class;
    }
}

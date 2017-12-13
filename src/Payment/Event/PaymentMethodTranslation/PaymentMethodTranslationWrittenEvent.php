<?php declare(strict_types=1);

namespace Shopware\Payment\Event\PaymentMethodTranslation;

use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Payment\Definition\PaymentMethodTranslationDefinition;

class PaymentMethodTranslationWrittenEvent extends WrittenEvent
{
    const NAME = 'payment_method_translation.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return PaymentMethodTranslationDefinition::class;
    }
}

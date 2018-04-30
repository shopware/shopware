<?php declare(strict_types=1);

namespace Shopware\Api\Payment\Event\PaymentMethodTranslation;

use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Api\Payment\Definition\PaymentMethodTranslationDefinition;

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

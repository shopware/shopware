<?php declare(strict_types=1);

namespace Shopware\Api\Payment\Event\PaymentMethodTranslation;

use Shopware\Api\Entity\Write\DeletedEvent;
use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Api\Payment\Definition\PaymentMethodTranslationDefinition;

class PaymentMethodTranslationDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'payment_method_translation.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return PaymentMethodTranslationDefinition::class;
    }
}

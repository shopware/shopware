<?php declare(strict_types=1);

namespace Shopware\Checkout\Payment\Aggregate\PaymentMethodTranslation\Event;

use Shopware\Framework\ORM\Write\DeletedEvent;
use Shopware\Framework\ORM\Write\WrittenEvent;
use Shopware\Checkout\Payment\Aggregate\PaymentMethodTranslation\PaymentMethodTranslationDefinition;

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

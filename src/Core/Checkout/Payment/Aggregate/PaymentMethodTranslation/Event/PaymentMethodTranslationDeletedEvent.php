<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Aggregate\PaymentMethodTranslation\Event;

use Shopware\Core\Checkout\Payment\Aggregate\PaymentMethodTranslation\PaymentMethodTranslationDefinition;
use Shopware\Core\Framework\ORM\Event\DeletedEvent;
use Shopware\Core\Framework\ORM\Event\WrittenEvent;

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

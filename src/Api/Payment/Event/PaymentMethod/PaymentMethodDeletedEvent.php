<?php declare(strict_types=1);

namespace Shopware\Api\Payment\Event\PaymentMethod;

use Shopware\Api\Entity\Write\DeletedEvent;
use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Api\Payment\Definition\PaymentMethodDefinition;

class PaymentMethodDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'payment_method.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return PaymentMethodDefinition::class;
    }
}

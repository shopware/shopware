<?php declare(strict_types=1);

namespace Shopware\Payment\Event\PaymentMethod;

use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Payment\Definition\PaymentMethodDefinition;

class PaymentMethodWrittenEvent extends WrittenEvent
{
    const NAME = 'payment_method.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return PaymentMethodDefinition::class;
    }
}

<?php declare(strict_types=1);

namespace Shopware\Checkout\Payment\Event\PaymentMethod;

use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Checkout\Payment\Definition\PaymentMethodDefinition;

class PaymentMethodWrittenEvent extends WrittenEvent
{
    public const NAME = 'payment_method.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return PaymentMethodDefinition::class;
    }
}

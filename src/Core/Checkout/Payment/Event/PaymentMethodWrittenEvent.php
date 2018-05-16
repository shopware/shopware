<?php declare(strict_types=1);

namespace Shopware\Checkout\Payment\Event;

use Shopware\Framework\ORM\Write\WrittenEvent;
use Shopware\Checkout\Payment\PaymentMethodDefinition;

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

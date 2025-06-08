<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Gateway\Command;

use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class RemovePaymentMethodCommand extends AbstractCheckoutGatewayCommand
{
    public const COMMAND_KEY = 'remove-payment-method';

    public function __construct(
        public readonly string $paymentMethodTechnicalName
    ) {
    }

    public static function getDefaultKeyName(): string
    {
        return self::COMMAND_KEY;
    }
}

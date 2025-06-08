<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Gateway\Command;

use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class AddShippingMethodCommand extends AbstractCheckoutGatewayCommand
{
    public const COMMAND_KEY = 'add-shipping-method';

    public function __construct(
        public readonly string $shippingMethodTechnicalName
    ) {
    }

    public static function getDefaultKeyName(): string
    {
        return self::COMMAND_KEY;
    }
}

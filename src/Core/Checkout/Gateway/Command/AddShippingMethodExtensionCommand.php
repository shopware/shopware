<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Gateway\Command;

use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class AddShippingMethodExtensionCommand extends AbstractCheckoutGatewayCommand
{
    public const COMMAND_KEY = 'add-shipping-method-extension';

    /**
     * @param array<array-key, mixed> $extensionsPayload
     */
    public function __construct(
        public readonly string $shippingMethodTechnicalName,
        public readonly string $extensionKey,
        public readonly array $extensionsPayload,
    ) {
    }

    public static function getDefaultKeyName(): string
    {
        return self::COMMAND_KEY;
    }
}

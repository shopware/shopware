<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Gateway\Command;

use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class AddCartErrorCommand extends AbstractCheckoutGatewayCommand
{
    public const COMMAND_KEY = 'add-cart-error';

    public function __construct(
        public readonly string $message,
        public readonly bool $blocking = false,
        public readonly int $level = Error::LEVEL_WARNING,
    ) {
    }

    public static function getDefaultKeyName(): string
    {
        return self::COMMAND_KEY;
    }
}

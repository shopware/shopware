<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Gateway\Context\Command;

use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
class ChangeShippingMethodCommand extends AbstractContextGatewayCommand
{
    public const COMMAND_KEY = 'context_change-shipping-method';

    public function __construct(
        public readonly string $technicalName,
    ) {
    }

    public static function getDefaultKeyName(): string
    {
        return self::COMMAND_KEY;
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Gateway\Context\Command;

use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
class ChangeBillingAddressCommand extends AbstractContextGatewayCommand
{
    public const COMMAND_KEY = 'context_change-billing-address';

    public function __construct(
        public readonly string $addressId,
    ) {
    }

    public static function getDefaultKeyName(): string
    {
        return self::COMMAND_KEY;
    }
}

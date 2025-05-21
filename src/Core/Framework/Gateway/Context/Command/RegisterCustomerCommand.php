<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Gateway\Context\Command;

use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
class RegisterCustomerCommand extends AbstractContextGatewayCommand implements TokenCommandInterface
{
    public const COMMAND_KEY = 'context_register-customer';

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public readonly array $data
    ) {
    }

    public static function getDefaultKeyName(): string
    {
        return self::COMMAND_KEY;
    }
}

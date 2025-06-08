<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Gateway\Command\_fixture;

use PHPUnit\Framework\Attributes\CoversNothing;
use Shopware\Core\Checkout\Gateway\Command\AbstractCheckoutGatewayCommand;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[CoversNothing]
#[Package('checkout')]
class TestCheckoutGatewayCommand extends AbstractCheckoutGatewayCommand
{
    public const COMMAND_KEY = 'test';

    /**
     * @param string[] $paymentMethodTechnicalNames
     */
    public function __construct(
        public readonly array $paymentMethodTechnicalNames
    ) {
    }

    public static function getDefaultKeyName(): string
    {
        return self::COMMAND_KEY;
    }
}

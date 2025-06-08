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
class TestCheckoutGatewayFooCommand extends AbstractCheckoutGatewayCommand
{
    public const COMMAND_KEY = 'test-foo';

    public static function getDefaultKeyName(): string
    {
        return self::COMMAND_KEY;
    }
}

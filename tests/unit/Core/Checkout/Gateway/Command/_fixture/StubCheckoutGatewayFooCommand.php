<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Gateway\Command\_fixture;

use Shopware\Core\Checkout\Gateway\Command\AbstractCheckoutGatewayCommand;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
class StubCheckoutGatewayFooCommand extends AbstractCheckoutGatewayCommand
{
    public const COMMAND_KEY = 'test-foo';

    public static function getDefaultKeyName(): string
    {
        return self::COMMAND_KEY;
    }
}

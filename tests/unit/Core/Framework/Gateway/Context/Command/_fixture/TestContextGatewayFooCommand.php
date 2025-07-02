<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Gateway\Context\Command\_fixture;

use PHPUnit\Framework\Attributes\CoversNothing;
use Shopware\Core\Framework\Gateway\Context\Command\AbstractContextGatewayCommand;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[CoversNothing]
#[Package('framework')]
class TestContextGatewayFooCommand extends AbstractContextGatewayCommand
{
    public const COMMAND_KEY = 'test_foo';

    public static function getDefaultKeyName(): string
    {
        return self::COMMAND_KEY;
    }
}

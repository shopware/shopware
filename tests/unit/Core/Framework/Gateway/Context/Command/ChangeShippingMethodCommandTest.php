<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Gateway\Context\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Gateway\Context\Command\ChangeShippingMethodCommand;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ChangeShippingMethodCommand::class)]
class ChangeShippingMethodCommandTest extends TestCase
{
    public function testCommand(): void
    {
        $command = ChangeShippingMethodCommand::createFromPayload(['technicalName' => 'app_test_shipping']);

        static::assertSame('context_change-shipping-method', $command::getDefaultKeyName());
        static::assertSame('app_test_shipping', $command->technicalName);
    }
}

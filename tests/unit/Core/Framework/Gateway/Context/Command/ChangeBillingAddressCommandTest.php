<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Gateway\Context\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Gateway\Context\Command\ChangeBillingAddressCommand;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ChangeBillingAddressCommand::class)]
class ChangeBillingAddressCommandTest extends TestCase
{
    public function testCommand(): void
    {
        $command = ChangeBillingAddressCommand::createFromPayload(['addressId' => '1234']);

        static::assertSame('context_change-billing-address', $command::getDefaultKeyName());
        static::assertSame('1234', $command->addressId);
    }
}

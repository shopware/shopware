<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Gateway\Context\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Gateway\Context\Command\RegisterCustomerCommand;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(RegisterCustomerCommand::class)]
class RegisterCustomerCommandTest extends TestCase
{
    public function testCommand(): void
    {
        $command = RegisterCustomerCommand::createFromPayload(['data' => ['foo' => 'bar']]);

        static::assertSame('context_register-customer', $command::getDefaultKeyName());
        static::assertSame(['foo' => 'bar'], $command->data);
    }
}

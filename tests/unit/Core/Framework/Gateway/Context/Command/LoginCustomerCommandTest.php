<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Gateway\Context\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Gateway\Context\Command\LoginCustomerCommand;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(LoginCustomerCommand::class)]
class LoginCustomerCommandTest extends TestCase
{
    public function testCommand(): void
    {
        $command = LoginCustomerCommand::createFromPayload(['customerEmail' => 'foo@bar.com']);

        static::assertSame('context_login-customer', $command::getDefaultKeyName());
        static::assertSame('foo@bar.com', $command->customerEmail);
    }
}

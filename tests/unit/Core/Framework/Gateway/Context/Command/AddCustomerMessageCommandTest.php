<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Gateway\Context\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Gateway\Context\Command\AddCustomerMessageCommand;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[CoversClass(AddCustomerMessageCommand::class)]
#[Package('framework')]
class AddCustomerMessageCommandTest extends TestCase
{
    public function testCommand(): void
    {
        $command = AddCustomerMessageCommand::createFromPayload(['message' => 'Foo Bar']);

        static::assertSame('context_add-customer-message', $command::getDefaultKeyName());
        static::assertSame('Foo Bar', $command->message);
    }
}

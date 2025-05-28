<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Gateway\Context\Command\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Gateway\Context\Command\AddCustomerMessageCommand;
use Shopware\Core\Framework\Gateway\Context\Command\Handler\AddCustomerMessageCommandHandler;
use Shopware\Core\Framework\Gateway\GatewayException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Generator;

/**
 * @internal
 */
#[CoversClass(AddCustomerMessageCommandHandler::class)]
#[Package('framework')]
class AddCustomerMessageCommandHandlerTest extends TestCase
{
    public function testAddCustomerMessage(): void
    {
        $command = AddCustomerMessageCommand::createFromPayload(['message' => 'Foo Bar']);
        $context = Generator::generateSalesChannelContext();
        $parameters = [];

        $this->expectExceptionObject(GatewayException::customerMessage('Foo Bar'));

        $handler = new AddCustomerMessageCommandHandler();
        $handler->handle($command, $context, $parameters);
    }

    public function testGetSupportedCommands(): void
    {
        static::assertSame([AddCustomerMessageCommand::class], AddCustomerMessageCommandHandler::supportedCommands());
    }
}

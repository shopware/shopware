<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Command\DeleteUnusedGuestCustomersCommand;
use Shopware\Core\Checkout\Customer\DeleteUnusedGuestCustomerService;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(DeleteUnusedGuestCustomersCommand::class)]
class DeleteUnusedGuestCustomersCommandTest extends TestCase
{
    private MockObject&DeleteUnusedGuestCustomerService $service;

    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->service = $this->createMock(DeleteUnusedGuestCustomerService::class);

        $this->commandTester = new CommandTester(new DeleteUnusedGuestCustomersCommand($this->service));
    }

    #[TestDox('Nothing is deleted when no unused guest customers exist')]
    public function testSucceedsEarlyWithoutUnusedGuestCustomers(): void
    {
        $this->service->method('countUnusedCustomers')->willReturn(0);
        $this->service->expects($this->never())->method('deleteUnusedCustomers');

        static::assertSame(Command::SUCCESS, $this->commandTester->execute([]));
        static::assertStringContainsString('No unused guest customers found.', $this->commandTester->getDisplay());
    }

    #[TestDox('Nothing is deleted when the confirmation is declined')]
    public function testAbortsWithoutConfirmation(): void
    {
        $this->service->method('countUnusedCustomers')->willReturn(3);
        $this->service->expects($this->never())->method('deleteUnusedCustomers');

        static::assertSame(Command::SUCCESS, $this->commandTester->execute([], ['interactive' => false]));
        static::assertStringContainsString('Aborting due to user input.', $this->commandTester->getDisplay());
    }

    #[TestDox('Unused guest customers are deleted in batches after confirmation')]
    public function testDeletesUnusedGuestCustomersAfterConfirmation(): void
    {
        $this->service->method('countUnusedCustomers')->willReturn(2);
        $this->service
            ->expects($this->exactly(2))
            ->method('deleteUnusedCustomers')
            ->willReturnOnConsecutiveCalls(
                [Uuid::randomHex(), Uuid::randomHex()],
                []
            );

        $this->commandTester->setInputs(['yes']);

        static::assertSame(Command::SUCCESS, $this->commandTester->execute([]));
        static::assertStringContainsString('Successfully deleted 2 guest customers.', $this->commandTester->getDisplay());
    }
}

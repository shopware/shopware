<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\NumberRange\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\NumberRange\Command\MigrateIncrementStorageCommand;
use Shopware\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage\IncrementStorageRegistry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(MigrateIncrementStorageCommand::class)]
class MigrateIncrementStorageCommandTest extends TestCase
{
    private MockObject&IncrementStorageRegistry $registry;

    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->registry = $this->createMock(IncrementStorageRegistry::class);

        $this->commandTester = new CommandTester(new MigrateIncrementStorageCommand($this->registry));
    }

    #[TestDox('The increment storage is migrated after confirming the duplicate number warning')]
    public function testMigratesAfterConfirmation(): void
    {
        $this->registry->expects($this->once())->method('migrate')->with('mysql', 'redis');

        $this->commandTester->setInputs(['yes']);

        static::assertSame(Command::SUCCESS, $this->commandTester->execute([
            'from' => 'mysql',
            'to' => 'redis',
        ]));

        $display = $this->commandTester->getDisplay();
        static::assertStringContainsString('duplicate numbers', $display);
        static::assertStringContainsString('Successfully migrated number range increments from "mysql" to "redis"', $display);
    }

    #[TestDox('Nothing is migrated when the confirmation is declined')]
    public function testAbortsWithoutConfirmation(): void
    {
        $this->registry->expects($this->never())->method('migrate');

        $this->commandTester->setInputs(['no']);

        static::assertSame(Command::FAILURE, $this->commandTester->execute([
            'from' => 'mysql',
            'to' => 'redis',
        ]));
        static::assertStringContainsString('Aborting due to user input.', $this->commandTester->getDisplay());
    }
}

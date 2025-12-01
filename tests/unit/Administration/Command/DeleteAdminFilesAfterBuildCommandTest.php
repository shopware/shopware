<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Administration\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Command\DeleteAdminFilesAfterBuildCommand;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[CoversClass(DeleteAdminFilesAfterBuildCommand::class)]
class DeleteAdminFilesAfterBuildCommandTest extends TestCase
{
    private DeleteAdminFilesAfterBuildCommand&MockObject $command;

    protected function setUp(): void
    {
        $this->command = $this->getMockBuilder(DeleteAdminFilesAfterBuildCommand::class)
            ->onlyMethods(['removeDirectory', 'deleteEmptyDirectories', 'deleteModuleFiles', 'deletePackageLockFile'])
            ->getMock();
    }

    public function testCommandAbortsOnNegativeConfirmation(): void
    {
        $this->command->expects($this->never())->method('removeDirectory');
        $this->command->expects($this->never())->method('deleteEmptyDirectories');
        $this->command->expects($this->never())->method('deleteModuleFiles');
        $this->command->expects($this->never())->method('deletePackageLockFile');

        $commandTester = new CommandTester($this->command);
        $commandTester->setInputs(['no']);
        $commandTester->execute([]);

        static::assertStringContainsString('Command aborted!', $commandTester->getDisplay());
    }

    public function testCommandDeletesFilesOnConfirmation(): void
    {
        $this->command->expects($this->any())->method('removeDirectory');
        $this->command->expects($this->any())->method('deleteEmptyDirectories');
        $this->command->expects($this->any())->method('deleteModuleFiles');
        $this->command->expects($this->once())->method('deletePackageLockFile');

        $commandTester = new CommandTester($this->command);
        $commandTester->setInputs(['yes']);
        $commandTester->execute([]);

        static::assertStringContainsString('All unnecessary files of the administration after the build process have been deleted.', $commandTester->getDisplay());
    }
}

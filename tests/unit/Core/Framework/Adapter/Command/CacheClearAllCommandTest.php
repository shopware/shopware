<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\CacheClearer;
use Shopware\Core\Framework\Adapter\Command\CacheClearAllCommand;
use Shopware\Core\Framework\Adapter\Console\TtyDetector;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[CoversClass(CacheClearAllCommand::class)]
class CacheClearAllCommandTest extends TestCase
{
    public function testExecuteDisplaysEnvironmentInfo(): void
    {
        $command = $this->createCommand(env: 'prod', debug: false);
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $output = $commandTester->getDisplay();
        static::assertStringContainsString('prod', $output);
        static::assertStringContainsString('false', $output);
    }

    public function testExecuteHandlesException(): void
    {
        $cacheClearer = $this->createMock(CacheClearer::class);
        $cacheClearer->expects($this->once())
            ->method('clear')
            ->willThrowException(new \RuntimeException('Cache directory not writable'));

        $command = $this->createCommand($cacheClearer);
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        static::assertSame(1, $commandTester->getStatusCode());
        static::assertStringContainsString('Cache directory not writable', $commandTester->getDisplay());
    }

    public function testExecuteInNonTtySkipsConfirmation(): void
    {
        $cacheClearer = $this->createMock(CacheClearer::class);
        $cacheClearer->expects($this->once())->method('clear');

        $command = $this->createCommand($cacheClearer, isTty: false);
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $commandTester->assertCommandIsSuccessful();
        static::assertStringContainsString('was successfully', $commandTester->getDisplay());
    }

    public function testExecuteInTtyAbortsOnDecline(): void
    {
        $cacheClearer = $this->createMock(CacheClearer::class);
        $cacheClearer->expects($this->never())->method('clear');

        $command = $this->createCommand($cacheClearer, isTty: true);
        $commandTester = new CommandTester($command);
        $commandTester->setInputs(['no']);
        $commandTester->execute([]);

        $commandTester->assertCommandIsSuccessful();
        static::assertStringContainsString('Do you want to continue?', $commandTester->getDisplay());
        static::assertStringContainsString('Aborting due to user input', $commandTester->getDisplay());
        static::assertStringNotContainsString('was successfully', $commandTester->getDisplay());
    }

    public function testExecuteInTtyAsksForConfirmationAndProceeds(): void
    {
        $cacheClearer = $this->createMock(CacheClearer::class);
        $cacheClearer->expects($this->once())->method('clear');

        $command = $this->createCommand($cacheClearer, isTty: true);
        $commandTester = new CommandTester($command);
        $commandTester->setInputs(['yes']);
        $commandTester->execute([]);

        $commandTester->assertCommandIsSuccessful();
        static::assertStringContainsString('Do you want to continue?', $commandTester->getDisplay());
        static::assertStringContainsString('was successfully', $commandTester->getDisplay());
    }

    public function testExecuteShowsWarning(): void
    {
        $command = $this->createCommand();
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $output = $commandTester->getDisplay();
        static::assertSame(1, substr_count($output, '[WARNING]'));
    }

    public function testExecuteWithForceSkipsConfirmation(): void
    {
        $cacheClearer = $this->createMock(CacheClearer::class);
        $cacheClearer->expects($this->once())->method('clear');

        $command = $this->createCommand($cacheClearer, isTty: true);
        $commandTester = new CommandTester($command);
        $commandTester->execute(['--force' => true]);

        $commandTester->assertCommandIsSuccessful();
        static::assertStringContainsString('was successfully', $commandTester->getDisplay());
        static::assertStringNotContainsString('Do you want to continue?', $commandTester->getDisplay());
    }

    public function testExecuteWithNoInteractionInTtySkipsConfirmation(): void
    {
        $cacheClearer = $this->createMock(CacheClearer::class);
        $cacheClearer->expects($this->once())->method('clear');

        $command = $this->createCommand($cacheClearer, isTty: true);
        $commandTester = new CommandTester($command);
        $commandTester->execute([], ['interactive' => false]);

        $commandTester->assertCommandIsSuccessful();
        static::assertStringContainsString('was successfully', $commandTester->getDisplay());
        static::assertStringNotContainsString('Do you want to continue?', $commandTester->getDisplay());
    }

    private function createCommand(
        ?CacheClearer $cacheClearer = null,
        bool $isTty = false,
        string $env = 'dev',
        bool $debug = true,
    ): CacheClearAllCommand {
        $ttyDetector = $this->createMock(TtyDetector::class);
        $ttyDetector->method('isStdinTty')->willReturn($isTty);

        return new CacheClearAllCommand(
            $cacheClearer ?? $this->createMock(CacheClearer::class),
            $env,
            $debug,
            $ttyDetector,
        );
    }
}

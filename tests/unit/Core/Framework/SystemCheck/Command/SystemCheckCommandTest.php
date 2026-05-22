<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\SystemCheck\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\SystemCheck\Check\Result;
use Shopware\Core\Framework\SystemCheck\Check\Status;
use Shopware\Core\Framework\SystemCheck\Command\SystemCheckCommand;
use Shopware\Core\Framework\SystemCheck\SystemChecker;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SystemCheckCommand::class)]
class SystemCheckCommandTest extends TestCase
{
    public function testInvalidContextReturnsInvalid(): void
    {
        $tester = $this->makeTester($this->createMock(SystemChecker::class));

        $exit = $tester->execute(['--context' => 'bogus']);

        static::assertSame(Command::INVALID, $exit);
        static::assertStringContainsString('Invalid context provided', $tester->getDisplay());
    }

    public function testInvalidFormatReturnsInvalid(): void
    {
        $checker = $this->createMock(SystemChecker::class);
        $checker->method('check')->willReturn([]);
        $tester = $this->makeTester($checker);

        $exit = $tester->execute(['--format' => 'bogus']);

        static::assertSame(Command::INVALID, $exit);
        static::assertStringContainsString('Invalid format provided', $tester->getDisplay());
    }

    public function testHealthyChecksReturnSuccess(): void
    {
        $checker = $this->createMock(SystemChecker::class);
        $checker->method('check')->willReturn([
            new Result('storage', Status::OK, 'ok', healthy: true),
        ]);
        $tester = $this->makeTester($checker);

        $exit = $tester->execute([]);

        static::assertSame(Command::SUCCESS, $exit);
        static::assertStringContainsString('storage', $tester->getDisplay());
    }

    public function testUnhealthyChecksReturnFailure(): void
    {
        $checker = $this->createMock(SystemChecker::class);
        $checker->method('check')->willReturn([
            new Result('storage', Status::OK, 'ok', healthy: true),
            new Result('cache', Status::ERROR, 'broken', healthy: false),
        ]);
        $tester = $this->makeTester($checker);

        $exit = $tester->execute([]);

        static::assertSame(Command::FAILURE, $exit);
    }

    public function testJsonFormatEmitsJsonDocument(): void
    {
        $checker = $this->createMock(SystemChecker::class);
        $checker->method('check')->willReturn([
            new Result('storage', Status::OK, 'ok', healthy: true),
        ]);
        $tester = $this->makeTester($checker);

        $exit = $tester->execute(['--format' => 'json']);

        static::assertSame(Command::SUCCESS, $exit);
        $display = $tester->getDisplay();
        $payload = json_decode($display, true, flags: \JSON_THROW_ON_ERROR);
        static::assertIsArray($payload);
        static::assertArrayHasKey('checks', $payload);
        static::assertSame('storage', $payload['checks'][0]['name']);
    }

    private function makeTester(SystemChecker $checker): CommandTester
    {
        $command = new SystemCheckCommand($checker);
        $command->setApplication(new Application());

        return new CommandTester($command);
    }
}

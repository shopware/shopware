<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\SystemCheck\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
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
#[CoversClass(SystemCheckCommand::class)]
class SystemCheckCommandTest extends TestCase
{
    #[TestDox('an unknown context returns INVALID')]
    public function testInvalidContext(): void
    {
        $tester = $this->createTester($this->checker());
        $exitCode = $tester->execute(['--context' => 'not-a-context']);

        static::assertSame(Command::INVALID, $exitCode);
        static::assertStringContainsString('Invalid context provided', $tester->getDisplay());
    }

    #[TestDox('an unknown format returns INVALID')]
    public function testInvalidFormat(): void
    {
        $tester = $this->createTester($this->checker());
        $exitCode = $tester->execute(['--format' => 'xml']);

        static::assertSame(Command::INVALID, $exitCode);
        static::assertStringContainsString('Invalid format provided', $tester->getDisplay());
    }

    #[TestDox('all healthy checks return SUCCESS and render the table')]
    public function testHealthyRunSucceeds(): void
    {
        $tester = $this->createTester($this->checker(new Result('cache', Status::OK, 'fine', true)));
        $exitCode = $tester->execute([]);

        static::assertSame(Command::SUCCESS, $exitCode);
        static::assertStringContainsString('cache', $tester->getDisplay());
    }

    #[TestDox('an unhealthy check returns FAILURE')]
    public function testUnhealthyRunFails(): void
    {
        $tester = $this->createTester($this->checker(
            new Result('cache', Status::OK, 'fine', true),
            new Result('db', Status::ERROR, 'down', false),
        ));

        static::assertSame(Command::FAILURE, $tester->execute([]));
    }

    #[TestDox('the json format emits a checks payload')]
    public function testJsonFormat(): void
    {
        $tester = $this->createTester($this->checker(new Result('cache', Status::OK, 'fine', true)));
        $tester->execute(['--format' => 'json', '-v' => true]);

        static::assertStringContainsString('"checks"', $tester->getDisplay());
    }

    private function checker(Result ...$results): SystemChecker
    {
        $checker = static::createStub(SystemChecker::class);
        $checker->method('check')->willReturn($results);

        return $checker;
    }

    private function createTester(SystemChecker $checker): CommandTester
    {
        $application = new Application();
        $application->addCommand(new SystemCheckCommand($checker));

        return new CommandTester($application->find('system:check'));
    }
}

<?php

declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\Docs\Script;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\Docs\Script\ScriptReferenceGeneratorCommand;
use Shopware\Core\DevOps\Docs\Script\ServiceReferenceGenerator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[CoversClass(ScriptReferenceGeneratorCommand::class)]
class ScriptReferenceGeneratorCommandTest extends TestCase
{
    private string $testFile;

    protected function setUp(): void
    {
        parent::setUp();
        $fixtureDir = __DIR__ . '/../../../../_fixtures';
        if (!is_dir($fixtureDir)) {
            mkdir($fixtureDir, 0777, true);
        }
        $this->testFile = $fixtureDir . '/script-reference-test.md';
        if (is_file($this->testFile)) {
            unlink($this->testFile);
        }
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        if (is_file($this->testFile)) {
            unlink($this->testFile);
        }
    }

    public function testExecuteWritesFilesAndReturnsSuccess(): void
    {
        $generator = $this->createMock(ServiceReferenceGenerator::class);
        $generator->expects($this->once())
            ->method('generate')
            ->willReturn([
                $this->testFile => 'test content',
            ]);

        $command = new ScriptReferenceGeneratorCommand([$generator]);
        $tester = new CommandTester($command);

        $exitCode = $tester->execute([]);

        static::assertSame(Command::SUCCESS, $exitCode);
        static::assertFileExists($this->testFile);
        static::assertSame('test content', file_get_contents($this->testFile));
        static::assertStringContainsString('Reference documentation was generated successfully', $tester->getDisplay());
    }
}

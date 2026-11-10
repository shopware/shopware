<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\DevOps\Docs\Script;

use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\Docs\Script\ScriptReferenceGeneratorCommand;
use Shopware\Core\DevOps\Docs\Script\ServiceReferenceGenerator;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('framework')]
class ScriptReferenceGeneratorCommandTest extends TestCase
{
    private Filesystem $filesystem;

    private string $outputDir;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->outputDir = sys_get_temp_dir() . '/' . uniqid('shopware-script-reference-test', true);
        $this->filesystem->mkdir($this->outputDir);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->outputDir);
    }

    public function testExecuteWritesFilesAndReturnsSuccess(): void
    {
        $testFile = $this->outputDir . '/script-reference-test.md';

        $generator = $this->createMock(ServiceReferenceGenerator::class);
        $generator->expects($this->once())
            ->method('generate')
            ->willReturn([
                $testFile => 'test content',
            ]);

        $command = new ScriptReferenceGeneratorCommand([$generator]);
        $tester = new CommandTester($command);

        $exitCode = $tester->execute([]);

        static::assertSame(Command::SUCCESS, $exitCode);
        static::assertFileExists($testFile);
        static::assertSame('test content', $this->filesystem->readFile($testFile));
        static::assertStringContainsString('Reference documentation was generated successfully', $tester->getDisplay());
    }
}

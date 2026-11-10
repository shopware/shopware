<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\Docs\Script;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\Docs\Script\ScriptReferenceGeneratorCommand;
use Shopware\Core\DevOps\Docs\Script\ServiceReferenceGenerator;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 *
 * @see \Shopware\Tests\Integration\Core\DevOps\Docs\Script\ScriptReferenceGeneratorCommandTest for the generated files
 */
#[Package('framework')]
#[CoversClass(ScriptReferenceGeneratorCommand::class)]
class ScriptReferenceGeneratorCommandTest extends TestCase
{
    public function testExecuteRunsGeneratorsAndReturnsSuccess(): void
    {
        $generator = $this->createMock(ServiceReferenceGenerator::class);
        $generator->expects($this->once())
            ->method('generate')
            ->willReturn([]);

        $command = new ScriptReferenceGeneratorCommand([$generator]);
        $tester = new CommandTester($command);

        $exitCode = $tester->execute([]);

        static::assertSame(Command::SUCCESS, $exitCode);
        static::assertStringContainsString('Generating reference documentation for the app scripts feature.', $tester->getDisplay());
        static::assertStringContainsString('Reference documentation was generated successfully', $tester->getDisplay());
    }
}

<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Administration\Command;

use PHPUnit\Framework\TestCase;
use Shopware\Administration\Command\CheckExtensionToolingCommand;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * The bin/console twin of `composer admin:extension:check`. Both entry points run
 * the same ts-node script, so what has to hold here is the bridge itself: the
 * command is wired into the console, and the entry script it spawns really exists
 * at the resolved Administration root.
 *
 * @internal
 */
#[Package('framework')]
class CheckExtensionToolingCommandTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testTheCommandIsWiredIntoTheConsole(): void
    {
        $command = static::getContainer()->get(CheckExtensionToolingCommand::class);

        static::assertInstanceOf(CheckExtensionToolingCommand::class, $command);
        static::assertSame('administration:extension:check', $command->getName());

        $definition = $command->getDefinition();

        static::assertTrue($definition->getArgument('names')->isArray());

        foreach ([
            'types',
            'lint',
            'fix',
            'include-platform',
        ] as $option) {
            static::assertTrue($definition->hasOption($option), \sprintf('Option --%s is missing.', $option));
            static::assertFalse($definition->getOption($option)->acceptValue());
        }
    }

    public function testTheEntryScriptItSpawnsExists(): void
    {
        $projectDir = static::getContainer()->getParameter('kernel.project_dir');
        static::assertIsString($projectDir);

        $administrationRoot = $projectDir . '/src/Administration/Resources/app/administration';

        static::assertFileExists($administrationRoot . '/scripts/extensionTooling/cli.ts');
        static::assertFileExists($administrationRoot . '/extension-tooling/tsconfig.base.json');
        static::assertFileExists($administrationRoot . '/extension-tooling/eslint.mjs');
        static::assertFileExists($administrationRoot . '/extension-tooling/admin-types.d.ts');
    }

    public function testItReportsAUsageErrorForAnUnknownExtension(): void
    {
        $projectDir = static::getContainer()->getParameter('kernel.project_dir');
        static::assertIsString($projectDir);

        if (!is_file($projectDir . '/src/Administration/Resources/app/administration/node_modules/.bin/ts-node')) {
            static::markTestSkipped('The Administration\'s Node dependencies are not installed.');
        }

        if (!is_file($projectDir . '/var/plugins.json')) {
            static::markTestSkipped('No bundle configuration dumped; run "bin/console bundle:dump".');
        }

        $command = static::getContainer()->get(CheckExtensionToolingCommand::class);
        static::assertInstanceOf(CheckExtensionToolingCommand::class, $command);

        $tester = new CommandTester($command);

        static::assertSame(2, $tester->execute(['names' => ['ThisExtensionDoesNotExist']]));
        static::assertStringContainsString('Unknown extension: ThisExtensionDoesNotExist.', $tester->getDisplay(true));
    }
}

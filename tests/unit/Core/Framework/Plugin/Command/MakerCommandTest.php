<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Plugin\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Command\MakerCommand;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\Generator\ScaffoldingGenerator;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\PluginScaffoldConfiguration;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\ScaffoldingCollector;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\ScaffoldingWriter;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\StubCollection;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Plugin\PluginService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[CoversClass(MakerCommand::class)]
class MakerCommandTest extends TestCase
{
    public function testExecute(): void
    {
        $scaffoldingWriter = $this->createMock(ScaffoldingWriter::class);
        $scaffoldingWriter->expects(static::once())
            ->method('write')
            ->with(static::callback(static function (StubCollection $stubCollection) {
                $stub = $stubCollection->get('src/Resources/config/services.xml');

                return $stub !== null && str_contains($stub->getContent() ?? '', 'Dummy content');
            }), static::callback(static function (PluginScaffoldConfiguration $configuration) {
                return $configuration->hasOption('foo') && $configuration->getOption('foo');
            }));

        $pluginService = $this->createMock(PluginService::class);
        $pluginService->expects(static::once())
            ->method('getPluginByName')
            ->with('ExamplePlugin')
            ->willReturn($this->getPluginEntity());

        $generator = new DummyScaffoldingGenerator();

        $command = new MakerCommand($generator, $scaffoldingWriter, $pluginService);
        $command->setName('make:foo');

        $tester = new CommandTester($command);
        $tester->setInputs(['ExamplePlugin']);
        $res = $tester->execute([]);

        static::assertEquals(Command::SUCCESS, $res);
    }

    public function testExecuteWithNoNameErrors(): void
    {
        $scaffoldingWriter = $this->createMock(ScaffoldingWriter::class);

        $pluginService = $this->createMock(PluginService::class);

        $generator = new DummyScaffoldingGenerator();

        $command = new MakerCommand($generator, $scaffoldingWriter, $pluginService);
        $command->setName('make:foo');

        $tester = new CommandTester($command);
        $res = $tester->execute([], ['interactive' => false]);

        static::assertEquals(Command::FAILURE, $res);
        static::assertStringContainsString('Plugin name is required', $tester->getDisplay());
    }

    public function testExecuteWithoutPluginPathErrors(): void
    {
        $scaffoldingWriter = $this->createMock(ScaffoldingWriter::class);

        $pluginService = $this->createMock(PluginService::class);
        $pluginService->expects(static::once())
            ->method('getPluginByName')
            ->with('ExamplePlugin')
            ->willReturn(new PluginEntity());

        $generator = new DummyScaffoldingGenerator();

        $command = new MakerCommand($generator, $scaffoldingWriter, $pluginService);
        $command->setName('make:foo');

        $tester = new CommandTester($command);
        $tester->setInputs(['ExamplePlugin']);
        $res = $tester->execute([]);

        static::assertEquals(Command::FAILURE, $res);
        static::assertStringContainsString('Plugin base path is null', $tester->getDisplay());
    }

    private function getPluginEntity(): PluginEntity
    {
        $plugin = new PluginEntity();
        $plugin->setActive(true);
        $plugin->setBaseClass(ExamplePlugin::class);
        $plugin->setPath(__DIR__);

        return $plugin;
    }
}

/**
 * @internal
 */
class ExamplePlugin extends Plugin
{
}

/**
 * @internal
 */
class DummyScaffoldingGenerator implements ScaffoldingGenerator
{
    public function hasCommandOption(): bool
    {
        return true;
    }

    public function getCommandOptionName(): string
    {
        return 'plugin-name';
    }

    public function getCommandOptionDescription(): string
    {
        return 'Plugin Name';
    }

    public function addScaffoldConfig(PluginScaffoldConfiguration $config, InputInterface $input, SymfonyStyle $io): void
    {
        $config->addOption('foo', true);
    }

    public function generateStubs(PluginScaffoldConfiguration $configuration, StubCollection $stubCollection): void
    {
        if (!$configuration->hasOption('foo') || !$configuration->getOption('foo')) {
            return;
        }

        $stubCollection->append('src/Resources/config/services.xml', 'Dummy content');
    }
}

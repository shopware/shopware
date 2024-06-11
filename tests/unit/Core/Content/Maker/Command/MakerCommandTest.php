<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Maker\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Maker\Command\MakerCommand;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\Generator\ScaffoldingGenerator;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\ScaffoldingCollector;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\ScaffoldingWriter;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\StubCollection;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Plugin\PluginService;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[CoversClass(MakerCommand::class)]
class MakerCommandTest extends TestCase
{
    public function testExecute(): void
    {
        $maker = $this->createMock(ScaffoldingGenerator::class);
        $maker->expects(static::once())
            ->method('hasCommandOption')
            ->willReturn(true);
        $maker->expects(static::once())
            ->method('getCommandOptionName')
            ->willReturn('plugin-name');
        $maker->expects(static::once())
            ->method('getCommandOptionDescription')
            ->willReturn('Plugin Name');
        $maker->expects(static::once())
            ->method('addScaffoldConfig');

        $scafoldingCollection = $this->createMock(ScaffoldingCollector::class);
        $scafoldingCollection->expects(static::once())
            ->method('collect')
            ->willReturn(new StubCollection());

        $scaffoldingWriter = $this->createMock(ScaffoldingWriter::class);
        $scaffoldingWriter->expects(static::once())
            ->method('write');

        $pluginService = $this->createMock(PluginService::class);
        $pluginService->expects(static::once())
            ->method('getPluginByName')
            ->willReturn($this->getPluginEntity());

        $command = new MakerCommand($maker, $scafoldingCollection, $scaffoldingWriter, $pluginService);
        $command->setName('make:foo');

        $tester = new CommandTester($command);
        $tester->setInputs(['ExamplePlugin']);
        $tester->execute([]);
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

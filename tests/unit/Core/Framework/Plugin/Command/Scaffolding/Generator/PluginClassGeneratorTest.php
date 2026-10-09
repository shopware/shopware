<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Plugin\Command\Scaffolding\Generator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\Generator\PluginClassGenerator;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\PluginScaffoldConfiguration;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\StubCollection;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PluginClassGenerator::class)]
class PluginClassGeneratorTest extends TestCase
{
    public function testCommandOptions(): void
    {
        $generator = new PluginClassGenerator();

        static::assertFalse($generator->hasCommandOption());
        static::assertEmpty($generator->getCommandOptionName());
        static::assertEmpty($generator->getCommandOptionDescription());
        static::assertSame('', $generator->getCommandOptionTitle());
        static::assertSame('', $generator->getCommandOptionDescriptionLong());
    }

    public function testAddScaffoldConfigDoesNothing(): void
    {
        $configuration = new PluginScaffoldConfiguration('TestPlugin', 'MyNamespace', '/path/to/directory');

        (new PluginClassGenerator())->addScaffoldConfig(
            $configuration,
            static::createStub(InputInterface::class),
            static::createStub(OutputInterface::class),
        );

        static::assertSame([], $configuration->options);
    }

    public function testGenerateStubs(): void
    {
        $generator = new PluginClassGenerator();
        $configuration = new PluginScaffoldConfiguration('TestPlugin', 'MyNamespace', '/path/to/directory');
        $stubCollection = new StubCollection();

        $generator->generateStubs($configuration, $stubCollection);

        static::assertCount(1, $stubCollection);

        static::assertTrue($stubCollection->has('src/TestPlugin.php'));
    }
}

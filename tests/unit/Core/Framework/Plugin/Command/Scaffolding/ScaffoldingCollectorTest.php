<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Plugin\Command\Scaffolding;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\Generator\ScaffoldingGenerator;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\PluginScaffoldConfiguration;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\ScaffoldingCollector;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\Stub;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\StubCollection;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ScaffoldingCollector::class)]
class ScaffoldingCollectorTest extends TestCase
{
    public function testWithoutGeneratorsAndRoutes(): void
    {
        $configuration = new PluginScaffoldConfiguration(
            'TestPlugin',
            'Test',
            'custom/plugins/TestPlugin'
        );

        $collector = new ScaffoldingCollector([]);

        $stubCollection = $collector->collect($configuration);

        static::assertCount(1, $stubCollection);

        $servicesStub = $stubCollection->get('src/Resources/config/services.php');
        static::assertInstanceOf(Stub::class, $servicesStub);
        static::assertSame('src/Resources/config/services.php', $servicesStub->getPath());
        static::assertNotNull($servicesStub->getContent());
        static::assertStringContainsString('use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;', $servicesStub->getContent());
        static::assertStringContainsString('return static function (ContainerConfigurator $containerConfigurator): void {', $servicesStub->getContent());
        static::assertStringContainsString('};', $servicesStub->getContent());
    }

    public function testWithGenerators(): void
    {
        $configuration = new PluginScaffoldConfiguration(
            'TestPlugin',
            'Test',
            'custom/plugins/TestPlugin'
        );

        $generator1 = $this->createMock(ScaffoldingGenerator::class);

        $generator1
            ->expects($this->once())
            ->method('generateStubs')
            ->willReturnCallback(static function (PluginScaffoldConfiguration $configuration, StubCollection $stubCollection): void {
                $stubCollection->add(Stub::raw(
                    'src/Resources/config/config.xml',
                    '<config>',
                ));
            });

        $generator2 = $this->createMock(ScaffoldingGenerator::class);

        $generator2
            ->expects($this->once())
            ->method('generateStubs')
            ->willReturnCallback(static function (PluginScaffoldConfiguration $configuration, StubCollection $stubCollection): void {
                $stubCollection->add(Stub::raw(
                    'src/TestPlugin.php',
                    'class TestPlugin',
                ));
            });

        $collector = new ScaffoldingCollector([
            $generator1,
            $generator2,
        ]);

        $stubCollection = $collector->collect($configuration);

        static::assertCount(3, $stubCollection);

        $servicesStub = $stubCollection->get('src/Resources/config/services.php');
        static::assertInstanceOf(Stub::class, $servicesStub);
        static::assertSame('src/Resources/config/services.php', $servicesStub->getPath());
        static::assertNotNull($servicesStub->getContent());
        static::assertStringContainsString('$services = $containerConfigurator->services();', $servicesStub->getContent());
        static::assertStringContainsString('};', $servicesStub->getContent());

        $configXmlStub = $stubCollection->get('src/Resources/config/config.xml');
        static::assertInstanceOf(Stub::class, $configXmlStub);
        static::assertSame('src/Resources/config/config.xml', $configXmlStub->getPath());
        static::assertNotNull($configXmlStub->getContent());
        static::assertStringContainsString('<config>', $configXmlStub->getContent());

        $pluginStub = $stubCollection->get('src/TestPlugin.php');
        static::assertInstanceOf(Stub::class, $pluginStub);
        static::assertSame('src/TestPlugin.php', $pluginStub->getPath());
        static::assertNotNull($pluginStub->getContent());
        static::assertStringContainsString('class TestPlugin', $pluginStub->getContent());
    }

    public function testWithRoutes(): void
    {
        $configuration = new PluginScaffoldConfiguration(
            'TestPlugin',
            'Test',
            'custom/plugins/TestPlugin',
            [
                PluginScaffoldConfiguration::ROUTE_XML_OPTION_NAME => true,
            ],
        );

        $collector = new ScaffoldingCollector([]);

        $stubCollection = $collector->collect($configuration);

        static::assertCount(2, $stubCollection);

        $servicesStub = $stubCollection->get('src/Resources/config/services.php');
        static::assertInstanceOf(Stub::class, $servicesStub);
        static::assertSame('src/Resources/config/services.php', $servicesStub->getPath());
        static::assertNotNull($servicesStub->getContent());
        static::assertStringContainsString('return static function (ContainerConfigurator $containerConfigurator): void {', $servicesStub->getContent());

        $routesStub = $stubCollection->get('src/Resources/config/routes.php');
        static::assertInstanceOf(Stub::class, $routesStub);
        static::assertSame('src/Resources/config/routes.php', $routesStub->getPath());
        static::assertNotNull($routesStub->getContent());
        static::assertStringContainsString('use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;', $routesStub->getContent());
        static::assertStringContainsString('return static function (RoutingConfigurator $routes): void {', $routesStub->getContent());
        static::assertStringContainsString('};', $routesStub->getContent());
    }

    public function testCollectsOnlyRequestedGenerator(): void
    {
        $configuration = new PluginScaffoldConfiguration(
            'TestPlugin',
            'Test',
            __DIR__ . '/_fixtures/non-existing-plugin'
        );

        $selectedGenerator = $this->createMock(ScaffoldingGenerator::class);
        $selectedGenerator
            ->expects($this->once())
            ->method('generateStubs')
            ->willReturnCallback(static function (PluginScaffoldConfiguration $configuration, StubCollection $stubCollection): void {
                $stubCollection->add(Stub::raw('src/TestPlugin.php', 'class TestPlugin'));
            });

        $otherGenerator = $this->createMock(ScaffoldingGenerator::class);
        $otherGenerator->expects($this->never())->method('generateStubs');

        $stubCollection = (new ScaffoldingCollector([$selectedGenerator, $otherGenerator]))
            ->collect($configuration, $selectedGenerator);

        static::assertTrue($stubCollection->has('src/TestPlugin.php'));
        static::assertCount(1, $stubCollection);
    }

    public function testIncrementalCollectionPreservesExistingAggregateAndWrapsNewAggregate(): void
    {
        $directory = __DIR__ . '/_fixtures/plugin-with-services';

        $configuration = new PluginScaffoldConfiguration(
            'TestPlugin',
            'Test',
            $directory,
            [PluginScaffoldConfiguration::ROUTE_XML_OPTION_NAME => true],
        );

        $generator = $this->createMock(ScaffoldingGenerator::class);
        $generator
            ->expects($this->once())
            ->method('generateStubs')
            ->willReturnCallback(static function (PluginScaffoldConfiguration $configuration, StubCollection $stubCollection): void {
                $stubCollection->append('src/Resources/config/services.php', "\n    generatedService();\n");
                $stubCollection->append('src/Resources/config/routes.php', "\n    generatedRoute();\n");
            });

        $stubCollection = (new ScaffoldingCollector([$generator]))->collect($configuration, $generator);

        $servicesStub = $stubCollection->get('src/Resources/config/services.php');
        static::assertInstanceOf(Stub::class, $servicesStub);
        static::assertSame(Stub::TYPE_APPEND, $servicesStub->getType());
        static::assertSame("\n    generatedService();\n", $servicesStub->getContent());

        $routesStub = $stubCollection->get('src/Resources/config/routes.php');
        static::assertInstanceOf(Stub::class, $routesStub);
        static::assertSame(Stub::TYPE_RAW, $routesStub->getType());
        $routesContent = $routesStub->getContent();
        static::assertIsString($routesContent);
        static::assertStringContainsString('return static function (RoutingConfigurator $routes): void {', $routesContent);
        static::assertStringContainsString('generatedRoute();', $routesContent);
    }
}

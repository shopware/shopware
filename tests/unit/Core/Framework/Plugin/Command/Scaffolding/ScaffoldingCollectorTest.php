<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Plugin\Command\Scaffolding;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\Generator\ScaffoldingGenerator;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\PluginScaffoldConfiguration;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\ScaffoldingCollector;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\Stub;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\StubCollection;

/**
 * @internal
 */
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

        $servicesXmlStub = $stubCollection->get('src/Resources/config/services.xml');
        static::assertInstanceOf(Stub::class, $servicesXmlStub);
        static::assertSame('src/Resources/config/services.xml', $servicesXmlStub->getPath());
        static::assertNotNull($servicesXmlStub->getContent());
        static::assertStringContainsString('<container xmlns="http://symfony.com/schema/dic/services"', $servicesXmlStub->getContent());
        static::assertStringContainsString('</services>', $servicesXmlStub->getContent());
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
            ->expects(static::once())
            ->method('generateStubs')
            ->willReturnCallback(function (PluginScaffoldConfiguration $configuration, StubCollection $stubCollection): void {
                $stubCollection->add(Stub::raw(
                    'src/Resources/config/config.xml',
                    '<config>',
                ));
            });

        $generator2 = $this->createMock(ScaffoldingGenerator::class);

        $generator2
            ->expects(static::once())
            ->method('generateStubs')
            ->willReturnCallback(function (PluginScaffoldConfiguration $configuration, StubCollection $stubCollection): void {
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

        $servicesXmlStub = $stubCollection->get('src/Resources/config/services.xml');
        static::assertInstanceOf(Stub::class, $servicesXmlStub);
        static::assertSame('src/Resources/config/services.xml', $servicesXmlStub->getPath());
        static::assertNotNull($servicesXmlStub->getContent());
        static::assertStringContainsString('<services>', $servicesXmlStub->getContent());
        static::assertStringContainsString('</services>', $servicesXmlStub->getContent());

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

    public function testWithRoutesXml(): void
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

        $servicesXmlStub = $stubCollection->get('src/Resources/config/services.xml');
        static::assertInstanceOf(Stub::class, $servicesXmlStub);
        static::assertSame('src/Resources/config/services.xml', $servicesXmlStub->getPath());
        static::assertNotNull($servicesXmlStub->getContent());
        static::assertStringContainsString('<container xmlns="http://symfony.com/schema/dic/services"', $servicesXmlStub->getContent());
        static::assertStringContainsString('</services>', $servicesXmlStub->getContent());

        $routesXmlStub = $stubCollection->get('src/Resources/config/routes.xml');
        static::assertInstanceOf(Stub::class, $routesXmlStub);
        static::assertSame('src/Resources/config/routes.xml', $routesXmlStub->getPath());
        static::assertNotNull($routesXmlStub->getContent());
        static::assertStringContainsString('<routes xmlns="http://symfony.com/schema/routing"', $routesXmlStub->getContent());
        static::assertStringContainsString('</routes>', $routesXmlStub->getContent());
    }
}

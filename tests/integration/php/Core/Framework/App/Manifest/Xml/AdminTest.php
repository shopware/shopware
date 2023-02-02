<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Manifest\Xml;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\System\SystemConfig\Exception\XmlParsingException;

/**
 * @internal
 */
class AdminTest extends TestCase
{
    public function testFromXml(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../_fixtures/test/manifest.xml');

        static::assertNotNull($manifest->getAdmin());
        static::assertCount(2, $manifest->getAdmin()->getActionButtons());
        static::assertCount(2, $manifest->getAdmin()->getModules());

        $firstActionButton = $manifest->getAdmin()->getActionButtons()[0];
        static::assertEquals('viewOrder', $firstActionButton->getAction());
        static::assertEquals('order', $firstActionButton->getEntity());
        static::assertEquals('detail', $firstActionButton->getView());
        static::assertEquals('https://swag-test.com/your-order', $firstActionButton->getUrl());

        static::assertEquals([
            'en-GB' => 'View Order',
            'de-DE' => 'Zeige Bestellung',
        ], $firstActionButton->getLabel());

        $secondActionButton = $manifest->getAdmin()->getActionButtons()[1];
        static::assertEquals('doStuffWithProducts', $secondActionButton->getAction());
        static::assertEquals('product', $secondActionButton->getEntity());
        static::assertEquals('list', $secondActionButton->getView());
        static::assertEquals('https://swag-test.com/do-stuff', $secondActionButton->getUrl());

        static::assertEquals([
            'en-GB' => 'Do Stuff',
            'de-DE' => 'Mache Dinge',
        ], $secondActionButton->getLabel());

        $firstModule = $manifest->getAdmin()->getModules()[0];
        static::assertEquals('https://test.com', $firstModule->getSource());
        static::assertEquals('first-module', $firstModule->getName());
        static::assertEquals([
            'en-GB' => 'My first own module',
            'de-DE' => 'Mein erstes eigenes Modul',
        ], $firstModule->getLabel());
        static::assertEquals('sw-test-structure-module', $firstModule->getParent());
        static::assertEquals(10, $firstModule->getPosition());

        $secondModule = $manifest->getAdmin()->getModules()[1];
        static::assertNull($secondModule->getSource());
        static::assertEquals('structure-module', $secondModule->getName());
        static::assertEquals([
            'en-GB' => 'My menu entry for modules',
            'de-DE' => 'Mein Menüeintrag für Module',
        ], $secondModule->getLabel());
        static::assertEquals('sw-catalogue', $secondModule->getParent());
        static::assertEquals(50, $secondModule->getPosition());

        $mainModule = $manifest->getAdmin()->getMainModule();
        static::assertNotNull($mainModule);
        static::assertEquals('https://main-module', $mainModule->getSource());
    }

    public function testModulesWithStructureElements(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/manifestWithStructureElement.xml');
        $admin = $manifest->getAdmin();
        static::assertNotNull($admin);

        $moduleWithStructureElement = $admin->getModules()[0];

        static::assertNull($moduleWithStructureElement->getSource());
        static::assertEquals('sw-catalogue', $moduleWithStructureElement->getParent());
        static::assertEquals(50, $moduleWithStructureElement->getPosition());
    }

    public function testMainModuleIsOptional(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/manifestWithoutMainModule.xml');

        static::assertNotNull($manifest->getAdmin());
        static::assertNull($manifest->getAdmin()->getMainModule());
    }

    public function testManifestWithMultipleMainmodulesIsInvalid(): void
    {
        static::expectException(XmlParsingException::class);
        Manifest::createFromXmlFile(__DIR__ . '/_fixtures/manifestWithTwoMainModules.xml');
    }
}

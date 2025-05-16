<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Manifest\Xml\Administration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\Xml\Administration\Admin;

/**
 * @internal
 */
#[CoversClass(Admin::class)]
class AdminTest extends TestCase
{
    public function testFromXml(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../../_fixtures/test/manifest.xml');

        $admin = $manifest->getAdmin();
        static::assertNotNull($admin);
        static::assertCount(2, $admin->getActionButtons());
        static::assertCount(2, $admin->getModules());

        $firstActionButton = $admin->getActionButtons()[0];
        static::assertSame('viewOrder', $firstActionButton->getAction());
        static::assertSame('order', $firstActionButton->getEntity());
        static::assertSame('detail', $firstActionButton->getView());
        static::assertSame('https://swag-test.com/your-order', $firstActionButton->getUrl());
        static::assertSame([
            'en-GB' => 'View Order',
            'de-DE' => 'Zeige Bestellung',
        ], $firstActionButton->getLabel());

        $secondActionButton = $admin->getActionButtons()[1];
        static::assertSame('doStuffWithProducts', $secondActionButton->getAction());
        static::assertSame('product', $secondActionButton->getEntity());
        static::assertSame('list', $secondActionButton->getView());
        static::assertSame('https://swag-test.com/do-stuff', $secondActionButton->getUrl());
        static::assertSame([
            'en-GB' => 'Do Stuff',
            'de-DE' => 'Mache Dinge',
        ], $secondActionButton->getLabel());

        $firstModule = $admin->getModules()[0];
        static::assertSame('https://test.com', $firstModule->getSource());
        static::assertSame('first-module', $firstModule->getName());
        static::assertSame([
            'en-GB' => 'My first own module',
            'de-DE' => 'Mein erstes eigenes Modul',
        ], $firstModule->getLabel());
        static::assertSame('sw-test-structure-module', $firstModule->getParent());
        static::assertSame(10, $firstModule->getPosition());

        $secondModule = $admin->getModules()[1];
        static::assertNull($secondModule->getSource());
        static::assertSame('structure-module', $secondModule->getName());
        static::assertSame([
            'en-GB' => 'My menu entry for modules',
            'de-DE' => 'Mein Menüeintrag für Module',
        ], $secondModule->getLabel());
        static::assertSame('sw-catalogue', $secondModule->getParent());
        static::assertSame(50, $secondModule->getPosition());

        $mainModule = $admin->getMainModule();

        static::assertNotNull($mainModule);
        static::assertSame('https://main-module', $mainModule->getSource());
    }

    public function testModulesWithStructureElements(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/manifestWithStructureElement.xml');

        $admin = $manifest->getAdmin();
        static::assertNotNull($admin);

        $moduleWithStructureElement = $admin->getModules()[0];

        static::assertNull($moduleWithStructureElement->getSource());
        static::assertSame('sw-catalogue', $moduleWithStructureElement->getParent());
        static::assertSame(50, $moduleWithStructureElement->getPosition());
    }

    public function testMainModuleIsOptional(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/manifestWithoutMainModule.xml');

        $admin = $manifest->getAdmin();
        static::assertNotNull($admin);

        static::assertNull($admin->getMainModule());
    }

    public function testManifestWithMultipleMainModulesIsInvalid(): void
    {
        $this->expectException(AppException::class);

        Manifest::createFromXmlFile(__DIR__ . '/_fixtures/manifestWithTwoMainModules.xml');
    }
}

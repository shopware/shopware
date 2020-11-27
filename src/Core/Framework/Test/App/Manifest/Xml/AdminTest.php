<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App\Manifest\Xml\CustomFieldTypes;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Manifest\Manifest;

class AdminTest extends TestCase
{
    public function testFromXml(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../_fixtures/test/manifest.xml');

        static::assertNotNull($manifest->getAdmin());
        static::assertCount(2, $manifest->getAdmin()->getActionButtons());
        static::assertCount(1, $manifest->getAdmin()->getModules());

        $firstActionButton = $manifest->getAdmin()->getActionButtons()[0];
        static::assertEquals('viewOrder', $firstActionButton->getAction());
        static::assertEquals('order', $firstActionButton->getEntity());
        static::assertEquals('detail', $firstActionButton->getView());
        static::assertEquals('https://swag-test.com/your-order', $firstActionButton->getUrl());
        static::assertTrue($firstActionButton->isOpenNewTab());
        static::assertEquals([
            'en-GB' => 'View Order',
            'de-DE' => 'Zeige Bestellung',
        ], $firstActionButton->getLabel());

        $secondActionButton = $manifest->getAdmin()->getActionButtons()[1];
        static::assertEquals('doStuffWithProducts', $secondActionButton->getAction());
        static::assertEquals('product', $secondActionButton->getEntity());
        static::assertEquals('list', $secondActionButton->getView());
        static::assertEquals('https://swag-test.com/do-stuff', $secondActionButton->getUrl());
        static::assertFalse($secondActionButton->isOpenNewTab());
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
    }
}

<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Manifest;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\System\SystemConfig\Exception\XmlParsingException;

/**
 * @internal
 *
 * @covers \Shopware\Core\Framework\App\Manifest\Manifest
 */
class ManifestTest extends TestCase
{
    public function testCreateFromXml(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/test-manifest.xml');

        static::assertEquals(__DIR__ . '/_fixtures', $manifest->getPath());
    }

    public function testSetPath(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/test-manifest.xml');

        $manifest->setPath('test');
        static::assertEquals('test', $manifest->getPath());
    }

    public function testThrowsXmlParsingExceptionIfInvalidWebhookEventNames(): void
    {
        $this->expectException(XmlParsingException::class);
        $this->expectExceptionMessage('attribute \'event\': \'test event\' is not a valid value');
        $this->expectExceptionMessage('attribute \'event\': \'\' is not a valid value');
        $this->expectExceptionMessage('Duplicate key-sequence [\'hook2\'] in unique identity-constraint \'uniqueWebhookName\'');

        Manifest::createFromXmlFile(__DIR__ . '/_fixtures/invalid-webhook-event-names-manifest.xml');
    }

    public function testXSChoice(): void
    {
        $fixedOrderManifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/fixed-order-manifest.xml');
        $randomOrderManifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/random-order-manifest.xml');

        static::assertEquals($fixedOrderManifest->getMetadata(), $randomOrderManifest->getMetadata());
    }

    public function testGetAllHosts(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/test-manifest.xml');

        static::assertEquals([
            'my.app.com',
            'test.com',
            'base-url.com',
            'main-module',
            'swag-test.com',
            'payment.app',
        ], $manifest->getAllHosts());
    }
}

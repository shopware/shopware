<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App\Manifest;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\System\SystemConfig\Exception\XmlParsingException;

class ManifestTest extends TestCase
{
    public function testCreateFromXml(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/test/manifest.xml');

        static::assertEquals(__DIR__ . '/_fixtures/test', $manifest->getPath());
    }

    public function testSetPath(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/test/manifest.xml');

        $manifest->setPath('test');
        static::assertEquals('test', $manifest->getPath());
    }

    public function testThrowsXmlParsingExceptionIfInvalidWebhookEventNames(): void
    {
        static::expectException(XmlParsingException::class);
        static::expectExceptionMessage("attribute 'event': 'test event' is not a valid value");
        static::expectExceptionMessage("attribute 'event': '' is not a valid value");
        static::expectExceptionMessage("Duplicate key-sequence ['hook2'] in unique identity-constraint 'uniqueWebhookName'");

        Manifest::createFromXmlFile(__DIR__ . '/_fixtures/invalidWebhookEventNames/manifest.xml');
    }
}

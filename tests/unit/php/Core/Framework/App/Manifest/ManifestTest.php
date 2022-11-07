<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Manifest;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\System\SystemConfig\Exception\XmlParsingException;
use Shopware\Core\Test\Annotation\DisabledFeatures;

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

    public function testCreateFromXmlFileThrowsExceptionIfAdminModuleHasNoParentSet(): void
    {
        $this->expectException(XmlParsingException::class);
        $this->expectExceptionMessage('Element \'module\': The attribute \'parent\' is required but missing.');

        Manifest::createFromXmlFile(
            __DIR__ . '/_fixtures/manifest-without-parent-module.xml'
        );
    }

    /**
     * @DisabledFeatures(features={"v6.5.0.0"})
     */
    public function testCreateFromXmlFileUsesDeprecatedSchemaIfAdminModuleHasNoParentSetBeforeMajor(): void
    {
        $manifest = Manifest::createFromXmlFile(
            __DIR__ . '/_fixtures/manifest-without-parent-module.xml'
        );

        $admin = $manifest->getAdmin();
        static::assertNotNull($admin);

        $modules = $admin->getModules();
        static::assertNotNull($modules);
        static::assertCount(1, $modules);

        static::assertNull($modules[0]->getParent());
    }

    public function testCreateFromXmlFileThrowsExceptionIfActionButtonHasOpenNewTabAttribute(): void
    {
        $this->expectException(XmlParsingException::class);
        $this->expectExceptionMessage('Element \'action-button\', attribute \'openNewTab\': The attribute \'openNewTab\' is not allowed.');

        Manifest::createFromXmlFile(
            __DIR__ . '/_fixtures/manifest-with-deprecated-open-new-tab.xml'
        );
    }

    /**
     * @DisabledFeatures(features={"v6.5.0.0", "FEATURE_NEXT_14360"})
     */
    public function testCreateFromXmlFileUsesDeprecatedSchemaIfActionButtonHasOpenNewTabAttributeBeforeMajor(): void
    {
        $manifest = Manifest::createFromXmlFile(
            __DIR__ . '/_fixtures/manifest-with-deprecated-open-new-tab.xml'
        );

        $admin = $manifest->getAdmin();

        static::assertNotNull($admin);

        $appActions = $admin->getActionButtons();

        static::assertNotNull($appActions);
        static::assertCount(1, $appActions);

        static::assertTrue($appActions[0]->isOpenNewTab());
    }
}

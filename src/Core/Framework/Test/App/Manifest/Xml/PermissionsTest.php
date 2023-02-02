<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App\Manifest\Xml\CustomFieldTypes;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Manifest\Manifest;

/**
 * @internal
 */
class PermissionsTest extends TestCase
{
    public function testFromXml(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../_fixtures/test/manifest.xml');

        static::assertNotNull($manifest->getPermissions());
        static::assertCount(7, $manifest->getPermissions()->getPermissions());
        static::assertEquals([
            'product' => ['create', 'update', 'delete'],
            'category' => ['delete'],
            'product_manufacturer' => ['create', 'delete'],
            'tax' => ['create'],
            'language' => ['read'],
            'custom_field_set' => ['update'],
            'order' => ['read'],
        ], $manifest->getPermissions()->getPermissions());
    }

    public function testAsParsedPrivileges(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../_fixtures/test/manifest.xml');

        static::assertNotNull($manifest->getPermissions());
        static::assertCount(16, $manifest->getPermissions()->asParsedPrivileges());
        static::assertEquals([
            'product:create',
            'product:read',
            'product:update',
            'product:delete',
            'category:delete',
            'category:read',
            'product_manufacturer:create',
            'product_manufacturer:read',
            'product_manufacturer:delete',
            'tax:create',
            'tax:read',
            'language:read',
            'custom_field_set:update',
            'custom_field_set:read',
            'order:read',
            'user_change_me',
        ], $manifest->getPermissions()->asParsedPrivileges());
    }
}

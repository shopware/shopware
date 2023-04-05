<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\ApiDefinition\DefinitionService;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\BundleSchemaPathCollection;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * @covers \Shopware\Core\Framework\Api\ApiDefinition\Generator\BundleSchemaPathCollection
 *
 * @internal
 */
class BundleSchemaPathCollectionTest extends TestCase
{
    private Bundle $bundleWithSchemas;

    private Bundle $bundleWithoutSchemas;

    protected function setUp(): void
    {
        $this->bundleWithSchemas = $this->createMock(Bundle::class);
        $this->bundleWithSchemas->method('getPath')->willReturn(__DIR__ . '/_fixtures/BundleWithApiSchema');
        $this->bundleWithoutSchemas = $this->createMock(Bundle::class);
        $this->bundleWithoutSchemas->method('getPath')->willReturn(__DIR__ . '/_fixtures/BundleWithoutApiSchema');
    }

    public function testGetPathsForStoreApi(): void
    {
        $factory = new BundleSchemaPathCollection([$this->bundleWithSchemas, $this->bundleWithoutSchemas]);

        $paths = $factory->getSchemaPaths(DefinitionService::STORE_API);
        static::assertContains(__DIR__ . '/_fixtures/BundleWithApiSchema/Resources/Schema/StoreApi', $paths);
        static::assertNotContains(__DIR__ . '/_fixtures/BundleWithoutApiSchema/Resources/Schema/StoreApi', $paths);
    }

    public function testGetPathsForAdminApi(): void
    {
        $factory = new BundleSchemaPathCollection([$this->bundleWithSchemas, $this->bundleWithoutSchemas]);

        $paths = $factory->getSchemaPaths(DefinitionService::API);
        static::assertContains(__DIR__ . '/_fixtures/BundleWithApiSchema/Resources/Schema/AdminApi', $paths);
        static::assertNotContains(__DIR__ . '/_fixtures/BundleWithoutApiSchema/Resources/Schema/AdminApi', $paths);
    }
}

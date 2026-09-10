<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\AllStoreApiSchemaMigrationScopeProvider;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\BundleSchemaPathCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator\_fixtures\CustomBundleWithApiSchema\ShopwareBundleWithName;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AllStoreApiSchemaMigrationScopeProvider::class)]
class AllStoreApiSchemaMigrationScopeProviderTest extends TestCase
{
    public function testProvidesAllScopeConfiguration(): void
    {
        $provider = new AllStoreApiSchemaMigrationScopeProvider(
            new BundleSchemaPathCollection([new ShopwareBundleWithName()]),
            '/schema',
        );

        static::assertSame('all', $provider->getScope());
        static::assertSame([], $provider->getDefinitionClassPrefixes());
        static::assertSame([
            '/schema',
            __DIR__ . '/_fixtures/CustomBundleWithApiSchema/Resources/Schema/StoreApi',
        ], $provider->getSchemaPaths());
        static::assertTrue($provider->includesAllDefinitions());
    }
}

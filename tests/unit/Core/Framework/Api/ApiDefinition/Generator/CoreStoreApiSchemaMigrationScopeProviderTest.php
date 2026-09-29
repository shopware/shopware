<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\CoreStoreApiSchemaMigrationScopeProvider;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CoreStoreApiSchemaMigrationScopeProvider::class)]
class CoreStoreApiSchemaMigrationScopeProviderTest extends TestCase
{
    public function testProvidesCoreScopeConfiguration(): void
    {
        $provider = new CoreStoreApiSchemaMigrationScopeProvider('/schema');

        static::assertSame('core', $provider->getScope());
        static::assertSame([
            'Shopware\\Administration\\',
            'Shopware\\Core\\',
            'Shopware\\Elasticsearch\\',
            'Shopware\\Storefront\\',
        ], $provider->getDefinitionClassPrefixes());
        static::assertSame(['/schema'], $provider->getSchemaPaths());
        static::assertFalse($provider->includesAllDefinitions());
    }

    public function testUsesCoreDefaults(): void
    {
        $provider = new CoreStoreApiSchemaMigrationScopeProvider();
        $projectDirectory = \dirname(__DIR__, 7);

        static::assertSame('core', $provider->getScope());
        static::assertSame([
            $projectDirectory . '/src/Core/Framework/Api/ApiDefinition/Generator/Schema/StoreApi',
        ], $provider->getSchemaPaths());
    }
}

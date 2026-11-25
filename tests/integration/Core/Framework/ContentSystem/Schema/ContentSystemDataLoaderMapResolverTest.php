<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\ContentSystem\Schema;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductCollection;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\EntityCollectionLoader\EntityCollectionLoader;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\EntityLoader\EntityLoader;
use Shopware\Core\Framework\ContentSystem\Schema\ContentSystemDataLoaderMap;
use Shopware\Core\Framework\ContentSystem\Schema\ContentSystemDataLoaderMapResolver;
use Shopware\Core\Framework\ContentSystem\Schema\ContentSystemDataLoaderSchemaGenerator;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
#[Package('framework')]
class ContentSystemDataLoaderMapResolverTest extends TestCase
{
    use IntegrationTestBehaviour;

    #[TestDox('the entity source can produce the sales-channel product entity')]
    public function testGetSourcesForSalesChannelProductIncludesEntity(): void
    {
        $sources = $this->resolveMap()->getSourcesFor(SalesChannelProductEntity::class);

        static::assertContains(EntityLoader::SOURCE, $sources);
    }

    #[TestDox('a base property type resolves to the entity source via the sales-channel subclass producer')]
    public function testGetSourcesForBaseProductEntityIsSubtypeMatched(): void
    {
        // The entity loader declares SalesChannelProductEntity for "product"; a property typed with the
        // base ProductEntity must still match because the declared type is a subclass of it.
        $sources = $this->resolveMap()->getSourcesFor(ProductEntity::class);

        static::assertContains(EntityLoader::SOURCE, $sources);
    }

    #[TestDox('the entity_collection source can produce the sales-channel product collection')]
    public function testGetSourcesForSalesChannelProductCollectionIncludesEntityCollection(): void
    {
        $sources = $this->resolveMap()->getSourcesFor(SalesChannelProductCollection::class);

        static::assertContains(EntityCollectionLoader::SOURCE, $sources);
    }

    #[TestDox('the entity capability for the product carries its config seed')]
    public function testCapabilityForEntityProductCarriesConfigSeed(): void
    {
        $map = $this->resolveMap();
        $capability = $map->capabilityFor(EntityLoader::SOURCE, SalesChannelProductEntity::class);

        static::assertNotNull($capability);
        static::assertSame(['entity' => 'product'], $capability->configTemplate);
        static::assertSame(['property'], $map->residualConfigKeysFor(EntityLoader::SOURCE, $capability));
    }

    #[TestDox('the schema endpoint exposes the capability shape per source')]
    public function testSchemaExposesCapabilityShape(): void
    {
        $generator = static::getContainer()->get(ContentSystemDataLoaderSchemaGenerator::class);
        static::assertInstanceOf(ContentSystemDataLoaderSchemaGenerator::class, $generator);

        $schema = $generator->getSchema();

        static::assertArrayHasKey(EntityLoader::SOURCE, $schema['sources']);
        $first = $schema['sources'][EntityLoader::SOURCE]['types'][0];

        // The entity source produces one capability per registered entity; each carries a concrete
        // produced class and the config seed needed to produce it.
        static::assertArrayHasKey('producedType', $first);
        static::assertTrue(class_exists($first['producedType']), $first['producedType']);
        static::assertArrayHasKey('entity', $first['configTemplate']);
        static::assertArrayHasKey('genericParameters', $first);
    }

    private function resolveMap(): ContentSystemDataLoaderMap
    {
        $resolver = static::getContainer()->get(ContentSystemDataLoaderMapResolver::class);
        static::assertInstanceOf(ContentSystemDataLoaderMapResolver::class, $resolver);

        return $resolver->resolve();
    }
}

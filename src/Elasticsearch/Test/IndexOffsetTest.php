<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\CashRounding;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\PriceFieldSerializer;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Elasticsearch\Framework\AbstractElasticsearchDefinition;
use Shopware\Elasticsearch\Framework\Indexing\EntityMapper;
use Shopware\Elasticsearch\Framework\Indexing\IndexerOffset;
use Shopware\Elasticsearch\Product\ElasticsearchProductDefinition;

class IndexOffsetTest extends TestCase
{
    use KernelTestBehaviour;

    public function testItConvertsDefinitionsToSerilizeableNamesAndCanDoAnDefinitionRoudTrip(): void
    {
        $languageOne = new LanguageEntity();
        $languageTwo = new LanguageEntity();

        $languageOne->setId('foo');
        $languageTwo->setId('bar');

        $languageCollection = new LanguageCollection([
            $languageOne,
            $languageTwo,
        ]);

        $definitions = [
            new ElasticsearchProductDefinition(new ProductDefinition(), new EntityMapper(), $this->createMock(Connection::class), new CashRounding(), $this->createMock(PriceFieldSerializer::class)),
            new MockElasticsearchDefinition(new EntityMapper()),
        ];

        $offset = new IndexerOffset(
            $languageCollection,
            $definitions,
            (new \DateTime())->getTimestamp()
        );

        static::assertEquals(ProductDefinition::ENTITY_NAME, $offset->getDefinition());
        static::assertTrue($offset->hasNextDefinition());

        $offset->setNextDefinition();

        static::assertEquals(ProductManufacturerDefinition::ENTITY_NAME, $offset->getDefinition());
        static::assertEmpty($offset->getDefinitions());
        static::assertFalse($offset->hasNextDefinition());

        $offset->resetDefinitions();

        static::assertEquals(ProductDefinition::ENTITY_NAME, $offset->getDefinition());
        static::assertEquals(
            [
                ProductManufacturerDefinition::ENTITY_NAME,
            ],
            $offset->getDefinitions()
        );
    }

    public function testItConvertsLanguagesToSerilizeableIdsAndCanDoAnLanguageRoudTrip(): void
    {
        $languageOne = new LanguageEntity();
        $languageTwo = new LanguageEntity();

        $languageOne->setId('foo');
        $languageTwo->setId('bar');

        $languageCollection = new LanguageCollection([
            $languageOne,
            $languageTwo,
        ]);

        $definitions = [];

        $offset = new IndexerOffset(
            $languageCollection,
            $definitions,
            (new \DateTime())->getTimestamp()
        );

        static::assertEquals('foo', $offset->getLanguageId());
        static::assertEquals(['bar'], $offset->getLanguages());
        static::assertTrue($offset->hasNextLanguage());
        $offset->setNextLanguage();
        static::assertEquals('bar', $offset->getLanguageId());
        static::assertFalse($offset->hasNextLanguage());
    }
}

class MockElasticsearchDefinition extends AbstractElasticsearchDefinition
{
    public function getEntityDefinition(): EntityDefinition
    {
        return new ProductManufacturerDefinition();
    }
}

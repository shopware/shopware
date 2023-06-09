<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Framework\Indexing;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\Feature;
use Shopware\Elasticsearch\Framework\AbstractElasticsearchDefinition;
use Shopware\Elasticsearch\Framework\Indexing\IndexerOffset;
use Shopware\Elasticsearch\Product\AbstractProductSearchQueryBuilder;
use Shopware\Elasticsearch\Product\ElasticsearchProductDefinition;
use Shopware\Elasticsearch\Product\EsProductDefinition;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 *
 * @covers \Shopware\Elasticsearch\Framework\Indexing\IndexerOffset
 */
class IndexerOffsetTest extends TestCase
{
    public function testItConvertsDefinitionsToSerializableNamesAndCanDoAnDefinitionRoundTrip(): void
    {
        $definitions = [
            new ElasticsearchProductDefinition(
                new ProductDefinition(),
                $this->createMock(Connection::class),
                [],
                new EventDispatcher(),
                $this->createMock(AbstractProductSearchQueryBuilder::class)
                . $this->createMock(EsProductDefinition::class)
            ),
            new MockElasticsearchDefinition(),
        ];

        $timestamp = (new \DateTime())->getTimestamp();
        $offset = new IndexerOffset(
            ['foo', 'bar'],
            $definitions,
            $timestamp
        );

        static::assertEquals(ProductDefinition::ENTITY_NAME, $offset->getDefinition());
        static::assertTrue($offset->hasNextDefinition());
        static::assertSame($timestamp, $offset->getTimestamp());
        static::assertNull($offset->getLastId());

        $offset->selectNextDefinition();

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

        $offset->setLastId(['offset' => 42]);
        static::assertEquals(['offset' => 42], $offset->getLastId());
    }

    public function testItConvertsLanguagesToSerializableIdsAndCanDoAnLanguageRoundTrip(): void
    {
        Feature::skipTestIfActive('ES_MULTILINGUAL_INDEX', $this);
        $definitions = [];

        $offset = new IndexerOffset(
            ['foo', 'bar'],
            $definitions,
            (new \DateTime())->getTimestamp()
        );

        static::assertEquals('foo', $offset->getLanguageId());
        static::assertEquals(['bar'], $offset->getLanguages());
        static::assertTrue($offset->hasNextLanguage());
        $offset->selectNextLanguage();
        static::assertEquals('bar', $offset->getLanguageId());
        static::assertFalse($offset->hasNextLanguage());
    }
}

/**
 * @internal
 */
class MockElasticsearchDefinition extends AbstractElasticsearchDefinition
{
    public function getEntityDefinition(): EntityDefinition
    {
        return new ProductManufacturerDefinition();
    }

    public function getMapping(Context $context): array
    {
        return [];
    }
}

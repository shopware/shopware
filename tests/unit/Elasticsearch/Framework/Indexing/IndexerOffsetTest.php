<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Framework\Indexing;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Feature;
use Shopware\Elasticsearch\Framework\Indexing\IndexerOffset;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

/**
 * @internal
 *
 * @covers \Shopware\Elasticsearch\Framework\Indexing\IndexerOffset
 */
class IndexerOffsetTest extends TestCase
{
    public function testItConvertsDefinitionsToSerializableNamesAndCanDoAnDefinitionRoundTrip(): void
    {
        $timestamp = (new \DateTime())->getTimestamp();
        $offset = new IndexerOffset(
            ['foo', 'bar'],
            ['product', 'product_manufacturer'],
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

        $offset = new IndexerOffset(
            ['foo', 'bar'],
            [],
            (new \DateTime())->getTimestamp()
        );

        static::assertEquals('foo', $offset->getLanguageId());
        static::assertEquals(['bar'], $offset->getLanguages());
        static::assertTrue($offset->hasNextLanguage());
        $offset->selectNextLanguage();
        static::assertEquals('bar', $offset->getLanguageId());
        static::assertFalse($offset->hasNextLanguage());
    }

    public function testSerialize(): void
    {
        $serialize = new Serializer(
            [
                new ObjectNormalizer(),
            ],
            [
                new JsonEncoder(null),
            ]
        );

        $before = new IndexerOffset(
            ['foo', 'bar'],
            ['product', 'product_manufacturer'],
            (new \DateTime())->getTimestamp()
        );
        $data = $serialize->serialize(
            $before,
            'json'
        );

        $after = $serialize->deserialize($data, IndexerOffset::class, 'json', [
            AbstractNormalizer::DEFAULT_CONSTRUCTOR_ARGUMENTS => [
                IndexerOffset::class => ['mappingDefinitions' => []],
            ],
        ]);

        static::assertEquals($before, $after);
    }
}

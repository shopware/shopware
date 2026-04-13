<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Framework\Indexing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Elasticsearch\Framework\Indexing\ElasticsearchIndexingMessage;
use Shopware\Elasticsearch\Framework\Indexing\IndexerOffset;
use Shopware\Elasticsearch\Framework\Indexing\IndexingDto;

/**
 * @phpstan-type MessageData array{
 *     IndexingDto,
 *     IndexerOffset|null,
 *     Context
 * }
 *
 * @internal
 */
#[CoversClass(ElasticsearchIndexingMessage::class)]
class ElasticsearchIndexingMessageTest extends TestCase
{
    /**
     * @param MessageData $message1Data
     * @param MessageData $message2Data
     */
    #[DataProvider('provideDeduplicationData')]
    public function testDeduplicationId(array $message1Data, array $message2Data, bool $shouldBeEqual): void
    {
        $message1 = new ElasticsearchIndexingMessage(...$message1Data);
        $message2 = new ElasticsearchIndexingMessage(...$message2Data);

        $deduplicationId1 = $message1->deduplicationId();
        $deduplicationId2 = $message2->deduplicationId();

        static::assertIsString($deduplicationId1);
        static::assertIsString($deduplicationId2);
        static::assertLessThan(64, \strlen($deduplicationId1), 'Deduplication ID should be under 64 characters');

        if ($shouldBeEqual) {
            static::assertSame($deduplicationId1, $deduplicationId2);
        } else {
            static::assertNotSame($deduplicationId1, $deduplicationId2);
        }
    }

    /**
     * @return iterable<string, array{MessageData, MessageData, bool}>
     */
    public static function provideDeduplicationData(): iterable
    {
        $context = Context::createDefaultContext();

        yield 'same data' => [
            [new IndexingDto(['id1', 'id2'], 'test-index', 'product'), null, $context],
            [new IndexingDto(['id1', 'id2'], 'test-index', 'product'), null, $context],
            true,
        ];

        yield 'different order same ids' => [
            [new IndexingDto(['id2', 'id1'], 'test-index', 'product'), null, $context],
            [new IndexingDto(['id1', 'id2'], 'test-index', 'product'), null, $context],
            true,
        ];

        yield 'different ids' => [
            [new IndexingDto(['id1', 'id2'], 'test-index', 'product'), null, $context],
            [new IndexingDto(['id1', 'id3'], 'test-index', 'product'), null, $context],
            false,
        ];

        yield 'different index' => [
            [new IndexingDto(['id1', 'id2'], 'test-index-1', 'product'), null, $context],
            [new IndexingDto(['id1', 'id2'], 'test-index-2', 'product'), null, $context],
            false,
        ];

        yield 'different entity' => [
            [new IndexingDto(['id1', 'id2'], 'test-index', 'product'), null, $context],
            [new IndexingDto(['id1', 'id2'], 'test-index', 'category'), null, $context],
            false,
        ];

        yield 'different offset' => [
            [new IndexingDto(['id1', 'id2'], 'test-index', 'product'), new IndexerOffset(['product'], 1751556420, ['offset' => 1]), $context],
            [new IndexingDto(['id1', 'id2'], 'test-index', 'product'), new IndexerOffset(['product'], 1751556421, ['offset' => 2]), $context],
            false,
        ];

        yield 'same offset' => [
            [new IndexingDto(['id1', 'id2'], 'test-index', 'product'), new IndexerOffset(['product'], 1751556420, ['offset' => 1]), $context],
            [new IndexingDto(['id1', 'id2'], 'test-index', 'product'), new IndexerOffset(['product'], 1751556420, ['offset' => 1]), $context],
            true,
        ];

        $context2 = Context::createDefaultContext();
        $context2->setRuleIds(['id1', 'id2']);
        yield 'different context' => [
            [new IndexingDto(['id1', 'id2'], 'test-index', 'product'), new IndexerOffset(['product'], 1751556420, ['offset' => 1]), $context],
            [new IndexingDto(['id1', 'id2'], 'test-index', 'product'), new IndexerOffset(['product'], 1751556420, ['offset' => 1]), $context2],
            false,
        ];
    }
}

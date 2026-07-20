<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Indexing\MessageQueue;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\MessageQueue\IterateEntityIndexerMessage;

/**
 * @phpstan-type MessageData array{
 *     string,
 *     array{offset: int|null}|null,
 *     array<string>
 * }
 *
 * @internal
 */
#[CoversClass(IterateEntityIndexerMessage::class)]
class IterateEntityIndexerMessageTest extends TestCase
{
    /**
     * @param MessageData $message1Data
     * @param MessageData $message2Data
     */
    #[DataProvider('provideDeduplicationData')]
    public function testDeduplicationId(array $message1Data, array $message2Data, bool $shouldBeEqual): void
    {
        $message1 = new IterateEntityIndexerMessage(...$message1Data);
        $message2 = new IterateEntityIndexerMessage(...$message2Data);

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
        yield 'same data' => [
            ['test.indexer', null, []],
            ['test.indexer', null, []],
            true,
        ];

        yield 'different indexer' => [
            ['test.indexer', null, []],
            ['other.indexer', null, []],
            false,
        ];

        yield 'different offset' => [
            ['test.indexer', ['offset' => 10], []],
            ['test.indexer', ['offset' => 20], []],
            false,
        ];

        yield 'same offset' => [
            ['test.indexer', ['offset' => 10], []],
            ['test.indexer', ['offset' => 10], []],
            true,
        ];

        yield 'different skip arrays' => [
            ['test.indexer', null, ['skip1']],
            ['test.indexer', null, ['skip2']],
            false,
        ];

        yield 'different order same skip arrays' => [
            ['test.indexer', null, ['skip1', 'skip2']],
            ['test.indexer', null, ['skip2', 'skip1']],
            true,
        ];
    }
}

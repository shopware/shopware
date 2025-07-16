<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Indexing\MessageQueue;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\MessageQueue\FullEntityIndexerMessage;

/**
 * @phpstan-type MessageData array{
 *     list<string>,
 *     list<string>
 * }
 *
 * @internal
 */
#[CoversClass(FullEntityIndexerMessage::class)]
class FullEntityIndexerMessageTest extends TestCase
{
    /**
     * @param MessageData $message1Data
     * @param MessageData $message2Data
     */
    #[DataProvider('provideDeduplicationData')]
    public function testDeduplicationId(array $message1Data, array $message2Data, bool $shouldBeEqual): void
    {
        $message1 = new FullEntityIndexerMessage(...$message1Data);
        $message2 = new FullEntityIndexerMessage(...$message2Data);

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
            [[], []],
            [[], []],
            true,
        ];

        yield 'different skip arrays' => [
            [['skip1'], []],
            [['skip2'], []],
            false,
        ];

        yield 'different order same skip arrays' => [
            [['skip1', 'skip2'], []],
            [['skip2', 'skip1'], []],
            true,
        ];

        yield 'different only arrays' => [
            [[], ['only1']],
            [[], ['only2']],
            false,
        ];

        yield 'different order same only arrays' => [
            [[], ['only1', 'only2']],
            [[], ['only2', 'only1']],
            true,
        ];

        yield 'both arrays same but different order' => [
            [['skip1', 'skip2'], ['only1', 'only2']],
            [['skip2', 'skip1'], ['only2', 'only1']],
            true,
        ];
    }
}

<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Indexing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;

/**
 * @phpstan-type MessageData array{
 *     array<string>|string,
 *     array{offset: int|null}|null,
 *     Context|null,
 *     bool,
 *     bool
 * }
 *
 * @internal
 */
#[CoversClass(EntityIndexingMessage::class)]
class EntityIndexingMessageTest extends TestCase
{
    /**
     * @param MessageData $message1Data
     * @param MessageData $message2Data
     */
    #[DataProvider('provideDeduplicationData')]
    public function testDeduplicationId(array $message1Data, array $message2Data, bool $shouldBeEqual): void
    {
        $message1 = new EntityIndexingMessage(...$message1Data);
        $message2 = new EntityIndexingMessage(...$message2Data);

        // Set indexer names for both messages
        $message1->setIndexer('test.indexer');
        $message2->setIndexer('test.indexer');

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
            [['id1', 'id2'], null, $context, false, false],
            [['id1', 'id2'], null, $context, false, false],
            true,
        ];

        yield 'different order same data' => [
            [['id2', 'id1'], null, $context, false, false],
            [['id1', 'id2'], null, $context, false, false],
            true,
        ];

        yield 'different data' => [
            [['id1', 'id2'], null, $context, false, false],
            [['id1', 'id3'], null, $context, false, false],
            false,
        ];

        yield 'different offset' => [
            [['id1', 'id2'], ['offset' => 10], $context, false, false],
            [['id1', 'id2'], ['offset' => 20], $context, false, false],
            false,
        ];

        yield 'different skip arrays' => [
            [['id1', 'id2'], null, $context, false, false],
            [['id1', 'id2'], null, $context, false, false],
            true,
        ];

        yield 'different forceQueue' => [
            [['id1', 'id2'], null, $context, true, false],
            [['id1', 'id2'], null, $context, false, false],
            false,
        ];

        yield 'different isFullIndexing' => [
            [['id1', 'id2'], null, $context, false, true],
            [['id1', 'id2'], null, $context, false, false],
            false,
        ];

        yield 'string data' => [
            ['single-id', null, $context, false, false],
            ['single-id', null, $context, false, false],
            true,
        ];

        yield 'different string data' => [
            ['single-id-1', null, $context, false, false],
            ['single-id-2', null, $context, false, false],
            false,
        ];

        $otherContext = Context::createDefaultContext();
        $otherContext->setRuleIds(['rule-id-1']);
        yield 'different context' => [
            ['single-id-1', null, $context, false, false],
            ['single-id-2', null, $otherContext, false, false],
            false,
        ];
    }
}

<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Event\UnusedMediaSearchEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;

/**
 * @internal
 */
#[CoversClass(UnusedMediaSearchEvent::class)]
class UnusedMediaSearchEventTest extends TestCase
{
    /**
     * @param array<string> $idsToRemove
     * @param array<string> $expectedIds
     */
    #[DataProvider('removeIdsProvider')]
    public function testRemoveIds(array $idsToRemove, array $expectedIds): void
    {
        $event = new UnusedMediaSearchEvent(['1', '2', '3'], Context::createDefaultContext());
        $event->markAsUsed($idsToRemove);
        static::assertSame($expectedIds, $event->getUnusedIds());
    }

    public function testGetContextReturnsNullWithoutContext(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $event = new UnusedMediaSearchEvent(['1', '2', '3']);

        static::assertNull($event->getContext());
    }

    /**
     * @return iterable<string, array{idsToRemove: array<string>, expectedIds: array<string>}>
     */
    public static function removeIdsProvider(): iterable
    {
        yield 'remove-last-id' => ['idsToRemove' => ['3'], 'expectedIds' => ['1', '2']];
        yield 'remove-middle-id' => ['idsToRemove' => ['2'], 'expectedIds' => ['1', '3']];
        yield 'remove-multiple' => ['idsToRemove' => ['1', '2'], 'expectedIds' => ['3']];
        yield 'remove-all' => ['idsToRemove' => ['1', '2', '3'], 'expectedIds' => []];
        yield 'remove-non-existing-elem' => ['idsToRemove' => ['4'], 'expectedIds' => ['1', '2', '3']];
    }
}

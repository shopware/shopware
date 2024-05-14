<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Event\UnusedMediaSearchEvent;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('buyers-experience')]
#[CoversClass(UnusedMediaSearchEvent::class)]
class UnusedMediaSearchEventTest extends TestCase
{
    public function testGetIds(): void
    {
        $event = new UnusedMediaSearchEvent(['1', '2', '3']);
        static::assertEquals(['1', '2', '3'], $event->getUnusedIds());
    }

    /**
     * @param array<string> $idsToRemove
     * @param array<string> $expectedIds
     */
    #[DataProvider('removeIdsProvider')]
    public function testRemoveIds(array $idsToRemove, array $expectedIds): void
    {
        $event = new UnusedMediaSearchEvent(['1', '2', '3']);
        $event->markAsUsed($idsToRemove);
        static::assertEquals($expectedIds, $event->getUnusedIds());
    }

    /**
     * @return array<string, array{idsToRemove: array<string>, expectedIds: array<string>}>
     */
    public static function removeIdsProvider(): array
    {
        return [
            'remove-last-id' => ['idsToRemove' => ['3'], 'expectedIds' => ['1', '2']],
            'remove-middle-id' => ['idsToRemove' => ['2'], 'expectedIds' => ['1', '3']],
            'remove-multiple' => ['idsToRemove' => ['1', '2'], 'expectedIds' => ['3']],
            'remove-all' => ['idsToRemove' => ['1', '2', '3'], 'expectedIds' => []],
            'remove-non-existing-elem' => ['idsToRemove' => ['4'], 'expectedIds' => ['1', '2', '3']],
        ];
    }
}

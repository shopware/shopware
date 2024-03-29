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
     * @return array<string, array{remove: array<string>, expected: array<string>}>
     */
    public static function removeIdsProvider(): array
    {
        return [
            'remove-last-id' => ['remove' => ['3'], 'expected' => ['1', '2']],
            'remove-middle-id' => ['remove' => ['2'], 'expected' => ['1', '3']],
            'remove-multiple' => ['remove' => ['1', '2'], 'expected' => ['3']],
            'remove-all' => ['remove' => ['1', '2', '3'], 'expected' => []],
            'remove-non-existing-elem' => ['remove' => ['4'], 'expected' => ['1', '2', '3']],
        ];
    }
}

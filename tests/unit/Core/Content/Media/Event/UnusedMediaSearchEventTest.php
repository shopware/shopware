<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Event\UnusedMediaSearchEvent;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Feature\FeatureException;

/**
 * @internal
 */
#[CoversClass(UnusedMediaSearchEvent::class)]
class UnusedMediaSearchEventTest extends TestCase
{
    public function testConstructorRequiresContextWhenFeatureActive(): void
    {
        Feature::skipTestIfInActive('v6.8.0.0', $this);

        $this->expectException(FeatureException::class);
        new UnusedMediaSearchEvent(['1', '2', '3']);
    }

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

    public function testGetContextThrowsWithoutContext(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $event = new UnusedMediaSearchEvent(['1', '2', '3']);

        $this->expectExceptionObject(MediaException::invalidEventData('No context provided. Pass $context to the constructor of ' . UnusedMediaSearchEvent::class));
        $event->getContext();
    }

    public function testGetNullableContextReturnsNullWithoutContext(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $event = new UnusedMediaSearchEvent(['1', '2', '3']);

        static::assertNull($event->getNullableContext());
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

<?php

declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Core\Framework\DataAbstractionLayer\Write\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\ChangeSet;

/**
 * @internal
 */
#[CoversClass(ChangeSet::class)]
final class ChangeSetTest extends TestCase
{
    /**
     * @param array<string, mixed> $state
     * @param array<string, mixed> $payload
     */
    #[DataProvider('changeSetConstructProvider')]
    public function testChangeSetConstruct(array $state, array $payload, bool $expectChanges): void
    {
        $changeSet = new ChangeSet($state, $payload, false);

        if ($expectChanges) {
            static::assertNotCount(0, $changeSet->getAfter(null));
        } else {
            static::assertCount(0, $changeSet->getAfter(null));
        }
    }

    public static function changeSetConstructProvider(): \Generator
    {
        yield 'Do not detect changes when both are zero' => [
            ['foo' => 0],
            ['foo' => 0],
            false,
        ];
        yield 'Do not detect changes when both are null' => [
            ['foo' => null],
            ['foo' => null],
            false,
        ];
        yield 'Detect changes when there is a difference' => [
            ['foo' => 0],
            ['foo' => 1],
            true,
        ];
    }

    public function testChangeSetCanMerge(): void
    {
        $changeSet = new ChangeSet(['foo' => 0], ['foo' => 1], false);
        $changeSet->merge(new ChangeSet(['bar' => 0], ['bar' => 1], false));

        static::assertCount(2, $changeSet->getAfter(null));
        static::assertEquals(1, $changeSet->getAfter('foo'));
        static::assertEquals(1, $changeSet->getAfter('bar'));
    }
}

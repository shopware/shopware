<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Revision;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Layout\Revision\LayoutDraft;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(LayoutDraft::class)]
class LayoutDraftTest extends TestCase
{
    #[TestDox('moving the head keeps every other pointer and the creation, and leaves the original draft untouched')]
    public function testWithHeadMovesOnlyTheHead(): void
    {
        $createdAt = new \DateTimeImmutable('2026-11-01 10:00:00');
        $updatedAt = new \DateTimeImmutable('2026-11-02 10:00:00');
        $draft = new LayoutDraft('draft-1', 'Summer campaign', 'base', 'head', 'live', $createdAt, 'user-1', $createdAt);

        $moved = $draft->withHead('next-head', $updatedAt);

        static::assertEquals(
            new LayoutDraft('draft-1', 'Summer campaign', 'base', 'next-head', 'live', $createdAt, 'user-1', $updatedAt),
            $moved,
        );
        static::assertSame('head', $draft->head);
        static::assertSame($createdAt, $draft->updatedAt);
    }
}

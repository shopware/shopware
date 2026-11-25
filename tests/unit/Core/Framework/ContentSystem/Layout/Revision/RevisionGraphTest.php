<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Revision;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Layout\Revision\LayoutDraft;
use Shopware\Core\Framework\ContentSystem\Layout\Revision\LayoutRevision;
use Shopware\Core\Framework\ContentSystem\Layout\Revision\RevisionGraph;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(RevisionGraph::class)]
class RevisionGraphTest extends TestCase
{
    #[TestDox('isAncestorOrSelf follows the parent chain of the descendant only')]
    public function testIsAncestorOrSelf(): void
    {
        $graph = $this->graph(
            [$this->revision('root', null), $this->revision('a1', 'root'), $this->revision('a2', 'a1'), $this->revision('b1', 'root')],
            published: 'root',
        );

        static::assertTrue($graph->isAncestorOrSelf('root', 'a2'));
        static::assertTrue($graph->isAncestorOrSelf('a1', 'a2'));
        static::assertTrue($graph->isAncestorOrSelf('a2', 'a2'));
        static::assertFalse($graph->isAncestorOrSelf('a2', 'a1'));
        static::assertFalse($graph->isAncestorOrSelf('b1', 'a2'));
        static::assertFalse($graph->isAncestorOrSelf('root', 'unknown'));
    }

    #[TestDox('unreachableIds keeps the chains of draft heads, of the live state a draft was opened from and of every ever-published revision')]
    public function testUnreachableIdsKeepsEveryTip(): void
    {
        $graph = $this->graph(
            [
                $this->revision('root', null, published: true),
                $this->revision('old-live', 'root', published: true),
                $this->revision('live', 'root', published: true),
                $this->revision('kept-1', 'root'),
                $this->revision('kept-2', 'kept-1'),
                $this->revision('opened-from', 'root'),
                $this->revision('orphan-1', 'root'),
                $this->revision('orphan-2', 'orphan-1'),
            ],
            published: 'live',
            drafts: [$this->draft('draft', head: 'kept-2', live: 'opened-from')],
        );

        $unreachable = $graph->unreachableIds();
        sort($unreachable);

        static::assertSame(['orphan-1', 'orphan-2'], $unreachable);
    }

    #[TestDox('newestFirst orders revisions saved in the same instant child before parent')]
    public function testNewestFirstBreaksTimestampTiesByDepth(): void
    {
        $graph = $this->graph(
            [
                $this->revision('child', 'parent', at: '2026-10-27 10:00:00.000'),
                $this->revision('root', null, at: '2026-10-27 09:00:00.000'),
                $this->revision('grandchild', 'child', at: '2026-10-27 10:00:00.000'),
                $this->revision('parent', 'root', at: '2026-10-27 10:00:00.000'),
                $this->revision('later', 'root', at: '2026-10-27 11:00:00.000'),
            ],
            published: 'root',
        );

        static::assertSame(
            ['later', 'grandchild', 'child', 'parent', 'root'],
            array_map(static fn (LayoutRevision $revision): string => $revision->id, $graph->newestFirst()),
        );
    }

    #[TestDox('newestFirst treats a revision whose parent was pruned as a root')]
    public function testNewestFirstToleratesAPrunedParent(): void
    {
        $graph = $this->graph(
            [
                $this->revision('child', 'pruned', at: '2026-10-27 10:00:00.000'),
                $this->revision('grandchild', 'child', at: '2026-10-27 10:00:00.000'),
            ],
            published: 'child',
        );

        static::assertSame(
            ['grandchild', 'child'],
            array_map(static fn (LayoutRevision $revision): string => $revision->id, $graph->newestFirst()),
        );
    }

    #[TestDox('draftIdsAt lists the drafts whose head is the revision')]
    public function testDraftIdsAt(): void
    {
        $graph = $this->graph(
            [$this->revision('root', null), $this->revision('a1', 'root')],
            published: 'root',
            drafts: [$this->draft('one', head: 'a1', live: 'root'), $this->draft('two', head: 'a1', live: 'root'), $this->draft('three', head: 'root', live: 'root')],
        );

        static::assertSame(['one', 'two'], $graph->draftIdsAt('a1'));
        static::assertSame([], $graph->draftIdsAt('unknown'));
    }

    /**
     * @param list<LayoutRevision> $revisions
     * @param list<LayoutDraft> $drafts
     */
    private function graph(array $revisions, string $published, array $drafts = []): RevisionGraph
    {
        $byId = [];
        foreach ($revisions as $revision) {
            $byId[$revision->id] = $revision;
        }

        $draftsById = [];
        foreach ($drafts as $draft) {
            $draftsById[$draft->id] = $draft;
        }

        return new RevisionGraph($byId, $draftsById, $published);
    }

    private function revision(string $id, ?string $parent, bool $published = false, string $at = '2026-10-27 10:00:00.000'): LayoutRevision
    {
        $createdAt = new \DateTimeImmutable($at);

        return new LayoutRevision($id, $parent, $createdAt, null, $published ? $createdAt : null);
    }

    private function draft(string $id, string $head, string $live): LayoutDraft
    {
        $now = new \DateTimeImmutable('2026-10-27 10:00:00.000');

        return new LayoutDraft($id, $id, $head, $head, $live, $now, $now);
    }
}

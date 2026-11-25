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
            live: 'live',
            drafts: [$this->draft('draft', head: 'kept-2', liveAtOpen: 'opened-from')],
        );

        $unreachable = $graph->unreachableIds();
        sort($unreachable);

        static::assertSame(['orphan-1', 'orphan-2'], $unreachable);
    }

    #[TestDox('isAncestorOrSelf follows the parent chain of the descendant only')]
    public function testIsAncestorOrSelf(): void
    {
        $graph = $this->graph(
            [$this->revision('root', null), $this->revision('a1', 'root'), $this->revision('a2', 'a1'), $this->revision('b1', 'root')],
            live: 'root',
        );

        static::assertTrue($graph->isAncestorOrSelf('root', 'a2'));
        static::assertTrue($graph->isAncestorOrSelf('a1', 'a2'));
        static::assertTrue($graph->isAncestorOrSelf('a2', 'a2'));
        static::assertFalse($graph->isAncestorOrSelf('a2', 'a1'));
        static::assertFalse($graph->isAncestorOrSelf('b1', 'a2'));
        static::assertFalse($graph->isAncestorOrSelf('root', 'unknown'));
    }

    /**
     * @param list<LayoutRevision> $revisions
     * @param list<LayoutDraft> $drafts
     */
    private function graph(array $revisions, string $live, array $drafts = []): RevisionGraph
    {
        $byId = [];
        foreach ($revisions as $revision) {
            $byId[$revision->id] = $revision;
        }

        $draftsById = [];
        foreach ($drafts as $draft) {
            $draftsById[$draft->id] = $draft;
        }

        return new RevisionGraph($byId, $draftsById, $live);
    }

    private function revision(string $id, ?string $parent, bool $published = false): LayoutRevision
    {
        $createdAt = new \DateTimeImmutable('2026-10-27 10:00:00.000');

        return new LayoutRevision($id, $parent, $createdAt, null, $published ? $createdAt : null);
    }

    private function draft(string $id, string $head, string $liveAtOpen): LayoutDraft
    {
        $now = new \DateTimeImmutable('2026-10-27 10:00:00.000');

        return new LayoutDraft($id, $id, $head, $head, $liveAtOpen, $now, $now);
    }
}

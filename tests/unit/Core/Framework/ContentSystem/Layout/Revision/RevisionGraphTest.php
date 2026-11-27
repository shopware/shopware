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
    #[TestDox('compaction keeps live, the most recently published revisions and what each draft points at, and deletes the rest')]
    public function testCompactionKeepsLiveRecentlyPublishedAndDraftRevisions(): void
    {
        $graph = $this->graph(
            [
                $this->revision('p1', null, publishedAt: 1),
                $this->revision('p2', 'p1', publishedAt: 2),
                $this->revision('p3', 'p2', publishedAt: 3),
                $this->revision('p4', 'p3', publishedAt: 4),
                $this->revision('edit-1', 'p4'),
                $this->revision('edit-2', 'edit-1'),
                $this->revision('discarded', 'p4'),
            ],
            live: 'p1',
            drafts: [$this->draft('draft', base: 'p4', head: 'edit-2', liveAtOpen: 'p2')],
        );

        $delete = $graph->compaction(1)['delete'];
        sort($delete);

        static::assertSame(['discarded', 'edit-1', 'p3'], $delete);
    }

    #[TestDox('compaction ranks published revisions by the last time they were live, so a revision restored by a rollback outranks one published after it first went live')]
    public function testCompactionRanksByTheLastTimeLive(): void
    {
        $graph = $this->graph(
            [
                $this->revision('restored', null, publishedAt: 1, lastLiveAt: 4),
                $this->revision('p2', 'restored', publishedAt: 2),
                $this->revision('p3', 'p2', publishedAt: 3),
                $this->revision('p5', 'p3', publishedAt: 5),
            ],
            live: 'p5',
        );

        $delete = $graph->compaction(2)['delete'];
        sort($delete);

        static::assertSame(['p2', 'p3'], $delete);
    }

    #[TestDox('compaction re-parents each survivor onto its nearest surviving ancestor, so ancestry among survivors is unchanged')]
    public function testCompactionPreservesAncestryAmongSurvivors(): void
    {
        $revisions = [
            $this->revision('p1', null, publishedAt: 1),
            $this->revision('p2', 'p1', publishedAt: 2),
            $this->revision('p3', 'p2', publishedAt: 3),
            $this->revision('a1', 'p3'),
            $this->revision('a2', 'a1'),
            $this->revision('b1', 'p1'),
        ];
        $drafts = [
            $this->draft('a', base: 'p3', head: 'a2', liveAtOpen: 'p1'),
            $this->draft('b', base: 'b1', head: 'b1', liveAtOpen: 'p1'),
        ];
        $before = $this->graph($revisions, live: 'p3', drafts: $drafts);

        $compaction = $before->compaction(1);
        $survivors = array_values(array_filter($revisions, static fn (LayoutRevision $revision): bool => !\in_array($revision->id, $compaction['delete'], true)));
        $after = $this->graph(
            array_map(
                static fn (LayoutRevision $revision): LayoutRevision => \array_key_exists($revision->id, $compaction['reparent'])
                    ? new LayoutRevision($revision->id, $compaction['reparent'][$revision->id], $revision->createdAt, null, $revision->publishedAt, lastLiveAt: $revision->lastLiveAt)
                    : $revision,
                $survivors,
            ),
            live: 'p3',
            drafts: $drafts,
        );

        static::assertSame(['p2', 'a1'], $compaction['delete']);
        static::assertSame(['p3' => 'p1', 'a2' => 'p3'], $compaction['reparent']);
        foreach ($survivors as $ancestor) {
            foreach ($survivors as $descendant) {
                static::assertSame(
                    $before->isAncestorOrSelf($ancestor->id, $descendant->id),
                    $after->isAncestorOrSelf($ancestor->id, $descendant->id),
                    $ancestor->id . ' → ' . $descendant->id,
                );
            }
        }
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

    private function revision(string $id, ?string $parent, ?int $publishedAt = null, ?int $lastLiveAt = null): LayoutRevision
    {
        $createdAt = new \DateTimeImmutable('2026-10-27 10:00:00.000');
        $minutesLater = static fn (?int $minutes): ?\DateTimeImmutable => $minutes !== null ? $createdAt->add(new \DateInterval('PT' . $minutes . 'M')) : null;

        return new LayoutRevision($id, $parent, $createdAt, null, $minutesLater($publishedAt), lastLiveAt: $minutesLater($lastLiveAt ?? $publishedAt));
    }

    private function draft(string $id, string $base, string $head, string $liveAtOpen): LayoutDraft
    {
        $now = new \DateTimeImmutable('2026-10-27 10:00:00.000');

        return new LayoutDraft($id, $id, $base, $head, $liveAtOpen, $now, null, $now);
    }
}

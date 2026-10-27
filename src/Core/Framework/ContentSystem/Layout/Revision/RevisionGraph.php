<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Revision;

use Shopware\Core\Framework\Log\Package;

/**
 * The metadata of a layout's revision history, without any tree: every revision points at its parent, branches
 * are movable pointers to a head revision, and `published` names the revision the layout column projects.
 *
 * @internal
 */
#[Package('framework')]
final readonly class RevisionGraph
{
    /**
     * @param array<string, LayoutRevision> $revisions keyed by revision id
     * @param array<string, LayoutBranch> $branches keyed by branch id
     */
    public function __construct(
        public array $revisions,
        public array $branches,
        public string $published,
    ) {
    }

    public function revision(string $id): ?LayoutRevision
    {
        return $this->revisions[$id] ?? null;
    }

    public function branch(string $id): ?LayoutBranch
    {
        return $this->branches[$id] ?? null;
    }

    /**
     * @return list<string>
     */
    public function branchIdsAt(string $revisionId): array
    {
        $ids = [];

        foreach ($this->branches as $branch) {
            if ($branch->head === $revisionId) {
                $ids[] = $branch->id;
            }
        }

        return $ids;
    }

    public function isAncestorOrSelf(string $ancestorId, string $revisionId): bool
    {
        for ($id = $revisionId; $id !== null && isset($this->revisions[$id]); $id = $this->revisions[$id]->parent) {
            if ($id === $ancestorId) {
                return true;
            }
        }

        return false;
    }

    /**
     * The revisions that no branch, no ever-published revision and the published one reach through their
     * parent chain. A rollback therefore never makes a newer live state prunable.
     *
     * @return list<string>
     */
    public function unreachableIds(): array
    {
        $tips = [$this->published];
        foreach ($this->revisions as $revision) {
            if ($revision->publishedAt !== null) {
                $tips[] = $revision->id;
            }
        }
        foreach ($this->branches as $branch) {
            $tips[] = $branch->head;
            $tips[] = $branch->live;
        }

        $reachable = [];
        foreach ($tips as $id) {
            while ($id !== null && !isset($reachable[$id]) && isset($this->revisions[$id])) {
                $reachable[$id] = true;
                $id = $this->revisions[$id]->parent;
            }
        }

        return array_map('strval', array_keys(array_diff_key($this->revisions, $reachable)));
    }

    /**
     * `createdAt` only has millisecond precision, so revisions saved within the same instant are ordered by
     * their distance from the root instead: a child always sorts before its parent.
     *
     * @return list<LayoutRevision>
     */
    public function newestFirst(): array
    {
        $depths = [];
        foreach ($this->revisions as $id => $revision) {
            $this->depth((string) $id, $depths);
        }

        $revisions = array_values($this->revisions);

        usort(
            $revisions,
            static fn (LayoutRevision $a, LayoutRevision $b): int => [$b->createdAt, $depths[$b->id]] <=> [$a->createdAt, $depths[$a->id]],
        );

        return $revisions;
    }

    /**
     * A revision whose parent was pruned counts as a root.
     *
     * @param array<string, int> $known memoized depths, so one pass over all revisions stays linear
     */
    private function depth(string $id, array &$known): void
    {
        $chain = [];
        $cursor = $id;

        while ($cursor !== null && isset($this->revisions[$cursor]) && !isset($known[$cursor])) {
            $chain[] = $cursor;
            $cursor = $this->revisions[$cursor]->parent;
        }

        $depth = $cursor !== null && isset($known[$cursor]) ? $known[$cursor] : -1;

        foreach (array_reverse($chain) as $chainId) {
            $known[$chainId] = ++$depth;
        }
    }
}

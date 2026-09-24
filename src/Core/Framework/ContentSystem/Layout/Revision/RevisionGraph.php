<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Revision;

use Shopware\Core\Framework\Log\Package;

/**
 * A layout's revision history: every revision is immutable and points at its parent, branches are movable
 * pointers to a head revision, and `published` names the revision the layout column projects. `publishHistory`
 * lists every revision that was ever published, so a rollback never makes the newer live states prunable.
 *
 * @internal
 */
#[Package('framework')]
final readonly class RevisionGraph
{
    /**
     * @param array<string, LayoutRevision> $revisions keyed by revision id
     * @param array<string, LayoutBranch> $branches keyed by branch id
     * @param list<string> $publishHistory
     */
    public function __construct(
        public array $revisions,
        public array $branches,
        public string $published,
        public array $publishHistory = [],
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

    public function withRevision(LayoutRevision $revision): self
    {
        return new self([...$this->revisions, $revision->id => $revision], $this->branches, $this->published, $this->publishHistory);
    }

    public function withBranch(LayoutBranch $branch): self
    {
        return new self($this->revisions, [...$this->branches, $branch->id => $branch], $this->published, $this->publishHistory);
    }

    public function withoutBranch(string $branchId): self
    {
        $branches = $this->branches;
        unset($branches[$branchId]);

        return new self($this->revisions, $branches, $this->published, $this->publishHistory);
    }

    public function withPublished(string $revisionId): self
    {
        $history = \in_array($revisionId, $this->publishHistory, true) ? $this->publishHistory : [...$this->publishHistory, $revisionId];

        return new self($this->revisions, $this->branches, $revisionId, $history);
    }

    /**
     * Drops every revision that no branch head and no ever-published revision reaches through its parent chain.
     */
    public function reachableOnly(): self
    {
        $tips = [$this->published, ...$this->publishHistory];
        foreach ($this->branches as $branch) {
            $tips[] = $branch->head;
        }

        $reachable = [];
        foreach ($tips as $id) {
            while ($id !== null && !isset($reachable[$id]) && isset($this->revisions[$id])) {
                $reachable[$id] = true;
                $id = $this->revisions[$id]->parent;
            }
        }

        return new self(array_intersect_key($this->revisions, $reachable), $this->branches, $this->published, $this->publishHistory);
    }

    /**
     * `createdAt` only has second precision, so revisions saved within the same second are ordered by their
     * distance from the root instead: a child always sorts before its parent.
     *
     * @return list<LayoutRevision>
     */
    public function newestFirst(): array
    {
        $revisions = array_values($this->revisions);

        usort(
            $revisions,
            fn (LayoutRevision $a, LayoutRevision $b): int => [$b->createdAt, $this->depth($b)] <=> [$a->createdAt, $this->depth($a)],
        );

        return $revisions;
    }

    private function depth(LayoutRevision $revision): int
    {
        $depth = 0;
        $parent = $revision->parent;

        while ($parent !== null && isset($this->revisions[$parent])) {
            ++$depth;
            $parent = $this->revisions[$parent]->parent;
        }

        return $depth;
    }
}

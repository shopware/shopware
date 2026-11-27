<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Revision;

use Shopware\Core\Framework\Log\Package;

/**
 * The metadata of a layout's revision history, without any tree: every revision points at its parent, drafts
 * are movable pointers to a head revision, and `live` names the revision the layout column projects.
 *
 * @internal
 */
#[Package('framework')]
final readonly class RevisionGraph
{
    /**
     * @param array<string, LayoutRevision> $revisions keyed by revision id
     * @param array<string, LayoutDraft> $drafts keyed by draft id
     */
    public function __construct(
        public array $revisions,
        public array $drafts,
        public string $live,
    ) {
    }

    public function revision(string $id): ?LayoutRevision
    {
        return $this->revisions[$id] ?? null;
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
     * Keeps the live revision, the `$publishedKept` most recently live ones and each draft's base, head and
     * live-at-open revision, and deletes every other. Ranking by the last time live rather than the first keeps a
     * revision restored by a rollback, so the publish after it can be undone. Each survivor is re-parented onto its
     * nearest surviving ancestor, which keeps ancestry among the survivors exactly as it was: the publish rule reads
     * only survivors.
     *
     * @return array{delete: list<string>, reparent: array<string, string|null>}
     */
    public function compaction(int $publishedKept): array
    {
        $published = array_values(array_filter($this->revisions, static fn (LayoutRevision $revision): bool => $revision->lastLiveAt !== null));
        usort($published, static fn (LayoutRevision $a, LayoutRevision $b): int => [$b->lastLiveAt, $b->id] <=> [$a->lastLiveAt, $a->id]);

        $kept = [$this->live => true];
        foreach (\array_slice($published, 0, $publishedKept) as $revision) {
            $kept[$revision->id] = true;
        }
        foreach ($this->drafts as $draft) {
            $kept[$draft->base] = $kept[$draft->head] = $kept[$draft->liveAtOpen] = true;
        }
        $kept = array_intersect_key($kept, $this->revisions);

        $reparent = [];
        foreach (array_keys($kept) as $id) {
            $parent = $this->revisions[$id]->parent;
            $survivor = $parent;
            while ($survivor !== null && !isset($kept[$survivor])) {
                $survivor = ($this->revisions[$survivor] ?? null)?->parent;
            }

            if ($survivor !== $parent) {
                $reparent[(string) $id] = $survivor;
            }
        }

        return [
            'delete' => array_map('strval', array_keys(array_diff_key($this->revisions, $kept))),
            'reparent' => $reparent,
        ];
    }
}

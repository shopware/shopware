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
     * The revisions that no draft, no ever-published revision and the live one reach through their
     * parent chain. A rollback therefore never makes a newer live state prunable.
     *
     * @return list<string>
     */
    public function unreachableIds(): array
    {
        $tips = [$this->live];
        foreach ($this->revisions as $revision) {
            if ($revision->publishedAt !== null) {
                $tips[] = $revision->id;
            }
        }
        foreach ($this->drafts as $draft) {
            $tips[] = $draft->head;
            $tips[] = $draft->liveAtOpen;
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
}

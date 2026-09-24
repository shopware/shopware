<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Revision;

use Shopware\Core\Framework\ContentSystem\Layout\Codec\StoredTreeCodec;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTree;
use Shopware\Core\Framework\Log\Package;

/**
 * Both directions of the `content_layout.revisions` column. Every revision tree goes through
 * {@see StoredTreeCodec}, so a revision holds exactly the wire shape the `layout` column holds.
 *
 * @phpstan-type StoredRevision array{parent: string|null, createdAt: string, createdBy: string|null, tree: array<array-key, mixed>}
 * @phpstan-type StoredBranch array{name: string, base: string, head: string, createdAt: string, updatedAt: string}
 * @phpstan-type StoredGraph array{revisions: array<string, StoredRevision>, branches: array<string, StoredBranch>, published: string, publishHistory?: list<string>}
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
class RevisionGraphCodec
{
    public function __construct(
        private readonly StoredTreeCodec $treeCodec,
    ) {
    }

    /**
     * @param StoredGraph $data
     */
    public function decode(array $data): RevisionGraph
    {
        $revisions = [];
        foreach ($data['revisions'] as $id => $revision) {
            $id = (string) $id;
            $revisions[$id] = new LayoutRevision(
                $id,
                $revision['parent'],
                new \DateTimeImmutable($revision['createdAt']),
                $revision['createdBy'],
                $this->treeCodec->decode($revision['tree'])->roots,
            );
        }

        $branches = [];
        foreach ($data['branches'] as $id => $branch) {
            $id = (string) $id;
            $branches[$id] = new LayoutBranch(
                $id,
                $branch['name'],
                $branch['base'],
                $branch['head'],
                new \DateTimeImmutable($branch['createdAt']),
                new \DateTimeImmutable($branch['updatedAt']),
            );
        }

        return new RevisionGraph($revisions, $branches, $data['published'], $data['publishHistory'] ?? [$data['published']]);
    }

    /**
     * Both maps are cast to objects so an empty one is stored as `{}`, never as `[]`.
     *
     * @return array{revisions: object, branches: object, published: string, publishHistory: list<string>}
     */
    public function encode(RevisionGraph $graph): array
    {
        $revisions = [];
        foreach ($graph->revisions as $id => $revision) {
            $revisions[$id] = [
                'parent' => $revision->parent,
                'createdAt' => $revision->createdAt->format(\DATE_ATOM),
                'createdBy' => $revision->createdBy,
                'tree' => $this->treeCodec->encode(new StoredTree($revision->tree)),
            ];
        }

        $branches = [];
        foreach ($graph->branches as $id => $branch) {
            $branches[$id] = [
                'name' => $branch->name,
                'base' => $branch->base,
                'head' => $branch->head,
                'createdAt' => $branch->createdAt->format(\DATE_ATOM),
                'updatedAt' => $branch->updatedAt->format(\DATE_ATOM),
            ];
        }

        return [
            'revisions' => (object) $revisions,
            'branches' => (object) $branches,
            'published' => $graph->published,
            'publishHistory' => $graph->publishHistory,
        ];
    }
}

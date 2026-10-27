<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Revision;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\ContentSystem\Layout\Codec\StoredTreeCodec;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTree;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Json;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Reads and writes the revision tables through the connection rather than the DAL: they are no entities, so no
 * layout read joins them and writing them fires no entity event and invalidates no cache. Every tree goes
 * through {@see StoredTreeCodec}, so a revision holds exactly the wire shape the `layout` column holds.
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
class LayoutRevisionStore
{
    public function __construct(
        private readonly Connection $connection,
        private readonly StoredTreeCodec $treeCodec,
    ) {
    }

    /**
     * Locks the layout row for the caller's transaction. `current` is whether the `layout` column still holds
     * what the last publish wrote to it; `null` when no layout has this id.
     *
     * @return array{revisionId: string|null, current: bool, createdAt: \DateTimeImmutable, checksum: string}|null
     */
    public function lockPublication(string $layoutId): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT LOWER(HEX(`publication`.`revision_id`)) AS `revision_id`,
                    `publication`.`layout_checksum` = SHA2(`layout`.`layout`, 256) AS `current`,
                    SHA2(`layout`.`layout`, 256) AS `checksum`,
                    `layout`.`created_at`
             FROM `content_layout` `layout`
             LEFT JOIN `content_layout_publication` `publication` ON `publication`.`content_layout_id` = `layout`.`id`
             WHERE `layout`.`id` = :id
             FOR UPDATE',
            ['id' => Uuid::fromHexToBytes($layoutId)],
        );

        if ($row === false) {
            return null;
        }

        return [
            'revisionId' => $row['revision_id'] !== null ? (string) $row['revision_id'] : null,
            'current' => (bool) $row['current'],
            'createdAt' => $this->date((string) $row['created_at']),
            'checksum' => (string) $row['checksum'],
        ];
    }

    /**
     * Copies the current `layout` column into a revision. An id that already exists keeps its row: the id is
     * derived from the content, so it already holds this tree.
     */
    public function adoptLayoutColumn(string $layoutId, string $revisionId, ?string $parentId, \DateTimeImmutable $createdAt): void
    {
        $this->connection->executeStatement(
            'INSERT INTO `content_layout_revision` (`id`, `content_layout_id`, `parent_id`, `layout`, `created_by_id`, `created_at`)
             SELECT :revisionId, `id`, :parentId, `layout`, NULL, :createdAt FROM `content_layout` WHERE `id` = :layoutId
             ON DUPLICATE KEY UPDATE `content_layout_revision`.`id` = `content_layout_revision`.`id`',
            [
                'revisionId' => Uuid::fromHexToBytes($revisionId),
                'parentId' => $parentId !== null ? Uuid::fromHexToBytes($parentId) : null,
                'createdAt' => $createdAt->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'layoutId' => Uuid::fromHexToBytes($layoutId),
            ],
        );
    }

    /**
     * @param list<StoredElement> $tree
     */
    public function insertRevision(string $layoutId, LayoutRevision $revision, array $tree): void
    {
        $this->connection->insert('content_layout_revision', [
            'id' => Uuid::fromHexToBytes($revision->id),
            'content_layout_id' => Uuid::fromHexToBytes($layoutId),
            'parent_id' => $revision->parent !== null ? Uuid::fromHexToBytes($revision->parent) : null,
            'layout' => Json::encode($this->treeCodec->encode(new StoredTree($tree))),
            'created_by_id' => $revision->createdBy !== null ? Uuid::fromHexToBytes($revision->createdBy) : null,
            'created_at' => $revision->createdAt->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    /**
     * Points the layout at the revision and records the checksum of the `layout` column as it is now, so a
     * later write that bypasses publishing is detected by {@see lockPublication()}.
     */
    public function markPublished(string $layoutId, string $revisionId, \DateTimeImmutable $publishedAt): void
    {
        $this->connection->executeStatement(
            'UPDATE `content_layout_revision` SET `published_at` = COALESCE(`published_at`, :publishedAt) WHERE `id` = :revisionId',
            [
                'publishedAt' => $publishedAt->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'revisionId' => Uuid::fromHexToBytes($revisionId),
            ],
        );

        $this->connection->executeStatement(
            'INSERT INTO `content_layout_publication` (`content_layout_id`, `revision_id`, `layout_checksum`)
             SELECT `id`, :revisionId, SHA2(`layout`, 256) FROM `content_layout` WHERE `id` = :layoutId
             ON DUPLICATE KEY UPDATE `revision_id` = VALUES(`revision_id`), `layout_checksum` = VALUES(`layout_checksum`)',
            ['revisionId' => Uuid::fromHexToBytes($revisionId), 'layoutId' => Uuid::fromHexToBytes($layoutId)],
        );
    }

    public function graph(string $layoutId, string $published): RevisionGraph
    {
        $revisions = [];
        foreach ($this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(`id`)) AS `id`, LOWER(HEX(`parent_id`)) AS `parent_id`, LOWER(HEX(`created_by_id`)) AS `created_by_id`, `created_at`, `published_at`
             FROM `content_layout_revision` WHERE `content_layout_id` = :layoutId',
            ['layoutId' => Uuid::fromHexToBytes($layoutId)],
        ) as $row) {
            $id = (string) $row['id'];
            $revisions[$id] = new LayoutRevision(
                $id,
                $row['parent_id'] !== null ? (string) $row['parent_id'] : null,
                $this->date((string) $row['created_at']),
                $row['created_by_id'] !== null ? (string) $row['created_by_id'] : null,
                $row['published_at'] !== null ? $this->date((string) $row['published_at']) : null,
            );
        }

        $branches = [];
        foreach ($this->fetchBranches('WHERE `content_layout_id` = :layoutId', ['layoutId' => Uuid::fromHexToBytes($layoutId)]) as $branch) {
            $branches[$branch->id] = $branch;
        }

        return new RevisionGraph($revisions, $branches, $published);
    }

    /**
     * @return list<StoredElement>|null
     */
    public function tree(string $layoutId, string $revisionId): ?array
    {
        $layout = $this->connection->fetchOne(
            'SELECT `layout` FROM `content_layout_revision` WHERE `id` = :revisionId AND `content_layout_id` = :layoutId',
            ['revisionId' => Uuid::fromHexToBytes($revisionId), 'layoutId' => Uuid::fromHexToBytes($layoutId)],
        );

        if ($layout === false) {
            return null;
        }

        return $this->treeCodec->decode(Json::decodeToList((string) $layout))->roots;
    }

    public function branch(string $layoutId, string $branchId): ?LayoutBranch
    {
        return $this->fetchBranches(
            'WHERE `id` = :branchId AND `content_layout_id` = :layoutId',
            ['branchId' => Uuid::fromHexToBytes($branchId), 'layoutId' => Uuid::fromHexToBytes($layoutId)],
        )[0] ?? null;
    }

    public function saveBranch(string $layoutId, LayoutBranch $branch): void
    {
        $this->connection->executeStatement(
            'INSERT INTO `content_layout_branch` (`id`, `content_layout_id`, `name`, `base_revision_id`, `head_revision_id`, `live_revision_id`, `created_at`, `updated_at`)
             VALUES (:id, :layoutId, :name, :base, :head, :live, :createdAt, :updatedAt)
             ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `head_revision_id` = VALUES(`head_revision_id`), `updated_at` = VALUES(`updated_at`)',
            [
                'id' => Uuid::fromHexToBytes($branch->id),
                'layoutId' => Uuid::fromHexToBytes($layoutId),
                'name' => $branch->name,
                'base' => Uuid::fromHexToBytes($branch->base),
                'head' => Uuid::fromHexToBytes($branch->head),
                'live' => Uuid::fromHexToBytes($branch->live),
                'createdAt' => $branch->createdAt->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'updatedAt' => $branch->updatedAt->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ],
        );
    }

    public function deleteBranch(string $layoutId, string $branchId): void
    {
        $this->connection->executeStatement(
            'DELETE FROM `content_layout_branch` WHERE `id` = :branchId AND `content_layout_id` = :layoutId',
            ['branchId' => Uuid::fromHexToBytes($branchId), 'layoutId' => Uuid::fromHexToBytes($layoutId)],
        );
    }

    /**
     * @param list<string> $revisionIds
     */
    public function deleteRevisions(string $layoutId, array $revisionIds): void
    {
        if ($revisionIds === []) {
            return;
        }

        $this->connection->executeStatement(
            'DELETE FROM `content_layout_revision` WHERE `id` IN (:revisionIds) AND `content_layout_id` = :layoutId',
            ['revisionIds' => Uuid::fromHexToBytesList($revisionIds), 'layoutId' => Uuid::fromHexToBytes($layoutId)],
            ['revisionIds' => ArrayParameterType::BINARY],
        );
    }

    /**
     * @param array<string, string> $parameters
     *
     * @return list<LayoutBranch>
     */
    private function fetchBranches(string $where, array $parameters): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(`id`)) AS `id`, `name`, LOWER(HEX(`base_revision_id`)) AS `base`, LOWER(HEX(`head_revision_id`)) AS `head`,
                    LOWER(HEX(`live_revision_id`)) AS `live`, `created_at`, `updated_at`
             FROM `content_layout_branch` ' . $where,
            $parameters,
        );

        return array_map(fn (array $row): LayoutBranch => new LayoutBranch(
            (string) $row['id'],
            (string) $row['name'],
            (string) $row['base'],
            (string) $row['head'],
            (string) $row['live'],
            $this->date((string) $row['created_at']),
            $this->date((string) $row['updated_at']),
        ), $rows);
    }

    private function date(string $value): \DateTimeImmutable
    {
        return new \DateTimeImmutable($value, new \DateTimeZone('UTC'));
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Revision;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
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
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Framework\ContentSystem\Layout\Revision\LayoutRevisionStoreTest
 */
#[Package('framework')]
readonly class LayoutRevisionStore
{
    private const DRAFT_SELECT = <<<'SQL'
        SELECT LOWER(HEX(`draft`.`id`)) AS `id`, `draft`.`name`, LOWER(HEX(`draft`.`base_revision_id`)) AS `base`,
            LOWER(HEX(`draft`.`head_revision_id`)) AS `head`, LOWER(HEX(`draft`.`live_at_open_revision_id`)) AS `live_at_open`,
            `draft`.`created_at`, LOWER(HEX(`draft`.`created_by_id`)) AS `created_by_id`, `draft`.`updated_at`
        SQL;

    private const REVISION_SELECT = <<<'SQL'
        SELECT LOWER(HEX(`id`)) AS `id`, LOWER(HEX(`parent_id`)) AS `parent_id`, LOWER(HEX(`created_by_id`)) AS `created_by_id`, `created_at`,
            `published_at`, LOWER(HEX(`published_by_id`)) AS `published_by_id`, `last_live_at`, `live_content_layout_id` IS NOT NULL AS `live`
        SQL;

    public function __construct(
        private Connection $connection,
        private StoredTreeCodec $treeCodec,
    ) {
    }

    /**
     * The live revision and whether the `layout` column still holds its tree; `null` when no layout has this id.
     * Revisions are encoded like the column, so the column holds the tree published to it byte for byte, and a
     * different checksum means a write that bypassed publishing.
     *
     * @return array{revisionId: string|null, current: bool, createdAt: \DateTimeImmutable, checksum: string}|null
     */
    public function publication(string $layoutId): ?array
    {
        $row = $this->connection->fetchAssociative(
            <<<'SQL'
                SELECT LOWER(HEX(`live`.`id`)) AS `revision_id`,
                    SHA2(`live`.`layout`, 256) = SHA2(`layout`.`layout`, 256) AS `current`,
                    SHA2(`layout`.`layout`, 256) AS `checksum`,
                    `layout`.`created_at`
                FROM `content_layout` `layout`
                LEFT JOIN `content_layout_revision` `live` ON `live`.`live_content_layout_id` = `layout`.`id`
                WHERE `layout`.`id` = :id
                SQL,
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
     * The checksum of the `layout` column when it does not hold the tree of `$revisionId` byte for byte, `null`
     * when it does.
     */
    public function divergentLayoutChecksum(string $layoutId, string $revisionId): ?string
    {
        $row = $this->connection->fetchAssociative(
            <<<'SQL'
                SELECT SHA2(`layout`.`layout`, 256) AS `checksum`,
                    SHA2(`revision`.`layout`, 256) = SHA2(`layout`.`layout`, 256) AS `current`
                FROM `content_layout` `layout`
                INNER JOIN `content_layout_revision` `revision`
                    ON `revision`.`content_layout_id` = `layout`.`id` AND `revision`.`id` = :revisionId
                WHERE `layout`.`id` = :layoutId
                SQL,
            [
                'revisionId' => Uuid::fromHexToBytes($revisionId),
                'layoutId' => Uuid::fromHexToBytes($layoutId),
            ],
        );

        if ($row === false) {
            throw ContentSystemException::contentLayoutRevisionNotFound($layoutId, $revisionId);
        }

        return (bool) $row['current'] ? null : (string) $row['checksum'];
    }

    /**
     * Copies the current `layout` column into a revision. An id that already exists keeps its row: the id is
     * derived from the content, so it already holds this tree.
     */
    public function adoptLayoutColumn(string $layoutId, string $revisionId, ?string $parentId, \DateTimeImmutable $createdAt, ?string $createdBy): void
    {
        $this->connection->executeStatement(
            <<<'SQL'
                INSERT INTO `content_layout_revision` (`id`, `content_layout_id`, `parent_id`, `layout`, `created_by_id`, `created_at`)
                SELECT :revisionId, `id`, :parentId, `layout`, :createdBy, :createdAt FROM `content_layout` WHERE `id` = :layoutId
                ON DUPLICATE KEY UPDATE `content_layout_revision`.`id` = `content_layout_revision`.`id`
                SQL,
            [
                'revisionId' => Uuid::fromHexToBytes($revisionId),
                'parentId' => $parentId !== null ? Uuid::fromHexToBytes($parentId) : null,
                'createdBy' => $createdBy !== null ? Uuid::fromHexToBytes($createdBy) : null,
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
     * Moves the layout's live pointer onto the revision. `published_at` and `published_by_id` keep the first time it
     * went live, `last_live_at` moves with every time, so a rollback renews a revision's place in the history.
     */
    public function markPublished(string $layoutId, string $revisionId, \DateTimeImmutable $publishedAt, ?string $publishedBy): void
    {
        $this->connection->executeStatement(
            <<<'SQL'
                UPDATE `content_layout_revision` SET `live_content_layout_id` = NULL WHERE `live_content_layout_id` = :layoutId
                SQL,
            ['layoutId' => Uuid::fromHexToBytes($layoutId)],
        );

        $this->connection->executeStatement(
            <<<'SQL'
                UPDATE `content_layout_revision`
                SET `live_content_layout_id` = `content_layout_id`,
                    `published_by_id` = IF(`published_at` IS NULL, :publishedBy, `published_by_id`),
                    `published_at` = COALESCE(`published_at`, :publishedAt),
                    `last_live_at` = :publishedAt
                WHERE `id` = :revisionId AND `content_layout_id` = :layoutId
                SQL,
            [
                'publishedAt' => $publishedAt->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'publishedBy' => $publishedBy !== null ? Uuid::fromHexToBytes($publishedBy) : null,
                'revisionId' => Uuid::fromHexToBytes($revisionId),
                'layoutId' => Uuid::fromHexToBytes($layoutId),
            ],
        );
    }

    public function graph(string $layoutId, string $live): RevisionGraph
    {
        $revisions = [];
        foreach ($this->connection->fetchAllAssociative(
            self::REVISION_SELECT . <<<'SQL'
                 FROM `content_layout_revision` WHERE `content_layout_id` = :layoutId
                SQL,
            ['layoutId' => Uuid::fromHexToBytes($layoutId)],
        ) as $row) {
            $revision = $this->hydrateRevision($row);
            $revisions[$revision->id] = $revision;
        }

        $drafts = [];
        foreach ($this->drafts($layoutId) as $draft) {
            $drafts[$draft->id] = $draft;
        }

        return new RevisionGraph($revisions, $drafts, $live);
    }

    /**
     * The revisions that have been live, most recently live first, each with whether the live pointer is on it.
     *
     * @return list<array{revision: LayoutRevision, live: bool}>
     */
    public function publishedRevisions(string $layoutId): array
    {
        return array_map(
            fn (array $row): array => ['revision' => $this->hydrateRevision($row), 'live' => (bool) $row['live']],
            $this->connection->fetchAllAssociative(
                self::REVISION_SELECT . <<<'SQL'
                     FROM `content_layout_revision`
                    WHERE `content_layout_id` = :layoutId AND `last_live_at` IS NOT NULL
                    ORDER BY `last_live_at` DESC, `id` DESC
                    SQL,
                ['layoutId' => Uuid::fromHexToBytes($layoutId)],
            ),
        );
    }

    /**
     * @return list<StoredElement>|null
     */
    public function tree(string $layoutId, string $revisionId): ?array
    {
        $layout = $this->connection->fetchOne(
            <<<'SQL'
                SELECT `layout` FROM `content_layout_revision` WHERE `id` = :revisionId AND `content_layout_id` = :layoutId
                SQL,
            ['revisionId' => Uuid::fromHexToBytes($revisionId), 'layoutId' => Uuid::fromHexToBytes($layoutId)],
        );

        if ($layout === false) {
            return null;
        }

        return $this->treeCodec->decode(Json::decodeToList((string) $layout))->roots;
    }

    public function layoutExists(string $layoutId): bool
    {
        return $this->connection->fetchOne(
            <<<'SQL'
                SELECT 1 FROM `content_layout` WHERE `id` = :id
                SQL,
            ['id' => Uuid::fromHexToBytes($layoutId)],
        ) !== false;
    }

    /**
     * @return list<LayoutDraft> most recently updated first
     */
    public function drafts(string $layoutId): array
    {
        return array_map($this->hydrateDraft(...), $this->connection->fetchAllAssociative(
            self::DRAFT_SELECT . <<<'SQL'
                 FROM `content_layout_draft` `draft`
                WHERE `draft`.`content_layout_id` = :layoutId
                ORDER BY `draft`.`updated_at` DESC
                SQL,
            ['layoutId' => Uuid::fromHexToBytes($layoutId)],
        ));
    }

    public function draft(string $layoutId, string $draftId): ?LayoutDraft
    {
        $row = $this->connection->fetchAssociative(
            self::DRAFT_SELECT . <<<'SQL'
                 FROM `content_layout_draft` `draft`
                WHERE `draft`.`id` = :draftId AND `draft`.`content_layout_id` = :layoutId
                SQL,
            ['draftId' => Uuid::fromHexToBytes($draftId), 'layoutId' => Uuid::fromHexToBytes($layoutId)],
        );

        return $row === false ? null : $this->hydrateDraft($row);
    }

    /**
     * The draft and the tree of its head in one statement, so a concurrent discard that prunes the head cannot
     * fall between reading the one and the other.
     *
     * @return array{draft: LayoutDraft, layout: list<StoredElement>}|null
     */
    public function draftWithHead(string $layoutId, string $draftId): ?array
    {
        $row = $this->connection->fetchAssociative(
            self::DRAFT_SELECT . <<<'SQL'
                , `head`.`layout` AS `head_layout`
                FROM `content_layout_draft` `draft`
                INNER JOIN `content_layout_revision` `head` ON `head`.`id` = `draft`.`head_revision_id`
                WHERE `draft`.`id` = :draftId AND `draft`.`content_layout_id` = :layoutId
                SQL,
            ['draftId' => Uuid::fromHexToBytes($draftId), 'layoutId' => Uuid::fromHexToBytes($layoutId)],
        );

        if ($row === false) {
            return null;
        }

        return [
            'draft' => $this->hydrateDraft($row),
            'layout' => $this->treeCodec->decode(Json::decodeToList((string) $row['head_layout']))->roots,
        ];
    }

    public function saveDraft(string $layoutId, LayoutDraft $draft): void
    {
        $this->connection->executeStatement(
            <<<'SQL'
                INSERT INTO `content_layout_draft` (`id`, `content_layout_id`, `name`, `created_by_id`, `base_revision_id`, `head_revision_id`, `live_at_open_revision_id`, `created_at`, `updated_at`)
                VALUES (:id, :layoutId, :name, :createdBy, :base, :head, :liveAtOpen, :createdAt, :updatedAt)
                ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `head_revision_id` = VALUES(`head_revision_id`), `updated_at` = VALUES(`updated_at`)
                SQL,
            [
                'id' => Uuid::fromHexToBytes($draft->id),
                'layoutId' => Uuid::fromHexToBytes($layoutId),
                'name' => $draft->name,
                'createdBy' => $draft->createdBy !== null ? Uuid::fromHexToBytes($draft->createdBy) : null,
                'base' => Uuid::fromHexToBytes($draft->base),
                'head' => Uuid::fromHexToBytes($draft->head),
                'liveAtOpen' => Uuid::fromHexToBytes($draft->liveAtOpen),
                'createdAt' => $draft->createdAt->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'updatedAt' => $draft->updatedAt->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ],
        );
    }

    public function deleteDraft(string $layoutId, string $draftId): void
    {
        $this->connection->executeStatement(
            <<<'SQL'
                DELETE FROM `content_layout_draft` WHERE `id` = :draftId AND `content_layout_id` = :layoutId
                SQL,
            ['draftId' => Uuid::fromHexToBytes($draftId), 'layoutId' => Uuid::fromHexToBytes($layoutId)],
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
            <<<'SQL'
                DELETE FROM `content_layout_revision` WHERE `id` IN (:revisionIds) AND `content_layout_id` = :layoutId
                SQL,
            ['revisionIds' => Uuid::fromHexToBytesList($revisionIds), 'layoutId' => Uuid::fromHexToBytes($layoutId)],
            ['revisionIds' => ArrayParameterType::BINARY],
        );
    }

    /**
     * @param array<string, string|null> $parents the new parent, keyed by revision id
     */
    public function reparent(string $layoutId, array $parents): void
    {
        foreach ($parents as $revisionId => $parentId) {
            $this->connection->executeStatement(
                <<<'SQL'
                    UPDATE `content_layout_revision` SET `parent_id` = :parentId WHERE `id` = :revisionId AND `content_layout_id` = :layoutId
                    SQL,
                [
                    'parentId' => $parentId !== null ? Uuid::fromHexToBytes($parentId) : null,
                    'revisionId' => Uuid::fromHexToBytes((string) $revisionId),
                    'layoutId' => Uuid::fromHexToBytes($layoutId),
                ],
            );
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrateRevision(array $row): LayoutRevision
    {
        return new LayoutRevision(
            (string) $row['id'],
            $row['parent_id'] !== null ? (string) $row['parent_id'] : null,
            $this->date((string) $row['created_at']),
            $row['created_by_id'] !== null ? (string) $row['created_by_id'] : null,
            $row['published_at'] !== null ? $this->date((string) $row['published_at']) : null,
            $row['published_by_id'] !== null ? (string) $row['published_by_id'] : null,
            $row['last_live_at'] !== null ? $this->date((string) $row['last_live_at']) : null,
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrateDraft(array $row): LayoutDraft
    {
        return new LayoutDraft(
            (string) $row['id'],
            (string) $row['name'],
            (string) $row['base'],
            (string) $row['head'],
            (string) $row['live_at_open'],
            $this->date((string) $row['created_at']),
            $row['created_by_id'] !== null ? (string) $row['created_by_id'] : null,
            $this->date((string) $row['updated_at']),
        );
    }

    private function date(string $value): \DateTimeImmutable
    {
        return new \DateTimeImmutable($value, new \DateTimeZone('UTC'));
    }
}

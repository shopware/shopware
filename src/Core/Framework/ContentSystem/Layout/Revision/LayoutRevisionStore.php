<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Revision;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Json;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Reads and writes `content_layout.revisions` through the connection rather than the DAL: as a DAL field the
 * whole graph would be selected on every layout read, the storefront's included. Saving therefore fires no
 * entity event and invalidates no cache.
 *
 * @phpstan-import-type StoredGraph from RevisionGraphCodec
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
        private readonly RevisionGraphCodec $codec,
    ) {
    }

    /**
     * `null` when no layout has this id. A row without a graph yet — one written through the DAL after the
     * column was added — is read as a single published revision holding its `layout`.
     */
    public function load(string $layoutId, bool $forUpdate = false): ?RevisionGraph
    {
        $row = $this->connection->fetchAssociative(
            'SELECT `revisions`, `layout`, `created_at` FROM `content_layout` WHERE `id` = :id' . ($forUpdate ? ' FOR UPDATE' : ''),
            ['id' => Uuid::fromHexToBytes($layoutId)],
        );

        if ($row === false) {
            return null;
        }

        if ($row['revisions'] !== null) {
            /** @var StoredGraph $data */
            $data = json_decode((string) $row['revisions'], true, 512, \JSON_THROW_ON_ERROR);

            return $this->codec->decode($data);
        }

        return $this->codec->decode($this->initialGraph($layoutId, (string) $row['layout'], (string) $row['created_at']));
    }

    public function save(string $layoutId, RevisionGraph $graph): void
    {
        $this->connection->executeStatement(
            'UPDATE `content_layout` SET `revisions` = :revisions WHERE `id` = :id',
            ['revisions' => Json::encode($this->codec->encode($graph)), 'id' => Uuid::fromHexToBytes($layoutId)],
        );
    }

    /**
     * The initial revision id is derived from the layout id, so reads before the first save all see the same
     * revision and a client can branch from the id it was shown.
     *
     * @return StoredGraph
     */
    private function initialGraph(string $layoutId, string $layout, string $createdAt): array
    {
        $revisionId = Uuid::fromStringToHex('content-layout-revision:' . $layoutId);

        return [
            'revisions' => [
                $revisionId => [
                    'parent' => null,
                    'createdAt' => (new \DateTimeImmutable($createdAt, new \DateTimeZone('UTC')))->format(\DATE_ATOM),
                    'createdBy' => null,
                    'tree' => Json::decodeToList($layout),
                ],
            ],
            'branches' => [],
            'published' => $revisionId,
        ];
    }
}

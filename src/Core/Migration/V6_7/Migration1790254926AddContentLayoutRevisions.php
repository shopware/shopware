<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Util\Json;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('discovery')]
class Migration1790254926AddContentLayoutRevisions extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1790254926;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn($connection, 'content_layout', 'revisions', 'JSON');

        $rows = $connection->fetchAllAssociative('SELECT `id`, `layout`, `created_at` FROM `content_layout` WHERE `revisions` IS NULL');

        foreach ($rows as $row) {
            $revisionId = Uuid::randomHex();
            $createdAt = new \DateTimeImmutable((string) $row['created_at'], new \DateTimeZone('UTC'));

            // Decoded to objects so an empty JSON map in the tree stays `{}` instead of turning into `[]`.
            $graph = [
                'revisions' => [
                    $revisionId => [
                        'parent' => null,
                        'createdAt' => $createdAt->format(\DATE_ATOM),
                        'createdBy' => null,
                        'tree' => json_decode((string) $row['layout'], false, 512, \JSON_THROW_ON_ERROR),
                    ],
                ],
                'branches' => new \stdClass(),
                'published' => $revisionId,
            ];

            $connection->executeStatement(
                'UPDATE `content_layout` SET `revisions` = :revisions WHERE `id` = :id',
                ['revisions' => Json::encode($graph), 'id' => $row['id']],
            );
        }
    }
}

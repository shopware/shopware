<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1756296414UpdateSnippetSetBaseFiles extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1756296414;
    }

    public function update(Connection $connection): void
    {
        $sql = <<<'SQL'
            UPDATE `snippet_set`
            SET base_file = REPLACE(REPLACE(`base_file`, 'de-DE', 'de'), 'en-GB', 'en')
            WHERE `base_file` IN ('messages.de-DE', 'messages.en-GB')
        SQL;

        $connection->executeStatement($sql);
    }
}

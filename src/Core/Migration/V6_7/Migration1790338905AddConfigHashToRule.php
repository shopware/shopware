<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Rule\RuleDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Util\Database\TableHelper;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class Migration1790338905AddConfigHashToRule extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1790338905;
    }

    public function update(Connection $connection): void
    {
        $hashAdded = $this->addColumn($connection, RuleDefinition::ENTITY_NAME, 'config_hash', 'VARCHAR(32)');

        if (!TableHelper::indexExists($connection, RuleDefinition::ENTITY_NAME, 'idx.rule.config_hash')) {
            $this->executeDdlStatement($connection, 'CREATE INDEX `idx.rule.config_hash` ON `rule` (`config_hash`)');
        }

        if ($hashAdded) {
            $this->registerIndexer($connection, 'rule.indexer');
        }
    }
}

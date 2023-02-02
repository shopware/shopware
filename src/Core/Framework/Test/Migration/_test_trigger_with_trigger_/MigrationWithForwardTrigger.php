<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Migration\_test_trigger_with_trigger_;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 *
 * @deprecated tag:v6.5.0 - Will be removed as the old trigger logic will be removed
 */
class MigrationWithForwardTrigger extends MigrationStep
{
    public const TRIGGER_NAME = 'testForwardTrigger';

    /**
     * get creation timestamp
     */
    public function getCreationTimestamp(): int
    {
        return 1;
    }

    /**
     * update non-destructive changes
     */
    public function update(Connection $connection): void
    {
        $this->addForwardTrigger(
            $connection,
            self::TRIGGER_NAME,
            'migration',
            'BEFORE',
            'INSERT',
            'SET NEW.`creation_timestamp` = NEW.`creation_timestamp` + 1'
        );
    }

    /**
     * update destructive changes
     */
    public function updateDestructive(Connection $connection): void
    {
        $this->removeTrigger($connection, self::TRIGGER_NAME);
    }
}

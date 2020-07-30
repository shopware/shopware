<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Migration;

use Doctrine\DBAL\Connection;

abstract class MigrationStep
{
    public const MIGRATION_VARIABLE_FORMAT = '@MIGRATION_%s_IS_ACTIVE';

    /**
     * get creation timestamp
     */
    abstract public function getCreationTimestamp(): int;

    /**
     * update non-destructive changes
     */
    abstract public function update(Connection $connection): void;

    /**
     * update destructive changes
     */
    abstract public function updateDestructive(Connection $connection): void;

    public function removeTrigger(Connection $connection, string $name): void
    {
        $blueGreenDeployment = (int) getenv('BLUE_GREEN_DEPLOYMENT');
        if ($blueGreenDeployment === 0) {
            return;
        }

        $connection->executeUpdate(sprintf('DROP TRIGGER %s', $name));
    }

    /**
     * FORWARD triggers are executed when an old application has to work with a newer Database
     * and has to keep it update-safe
     */
    protected function addForwardTrigger(Connection $connection, string $name, string $table, string $time, string $event, string $statements): void
    {
        $this->addTrigger($connection, $name, $table, $time, $event, $statements, 'IS NULL');
    }

    /**
     * BACKWARD triggers are executed when the new application works with the new Database
     * and has to keep it rollback-safe
     *
     * @deprecated tag:v6.4.0 use createTrigger instead
     */
    protected function addBackwardTrigger(Connection $connection, string $name, string $table, string $time, string $event, string $statements): void
    {
        $this->addTrigger($connection, $name, $table, $time, $event, $statements, '');
    }

    protected function addTrigger(Connection $connection, string $name, string $table, string $time, string $event, string $statements, string $condition): void
    {
        $query = sprintf(
            'CREATE TRIGGER %s
            %s %s ON `%s` FOR EACH ROW
            thisTrigger: BEGIN
                IF (%s %s)
                THEN
                    LEAVE thisTrigger;
                END IF;

                %s;
            END;
            ',
            $name,
            $time,
            $event,
            $table,
            sprintf(self::MIGRATION_VARIABLE_FORMAT, $this->getCreationTimestamp()),
            $condition,
            $statements
        );
        $connection->exec($query);
    }

    /**
     * @param mixed[] $params
     *
     * @throws \Doctrine\DBAL\DBALException
     */
    protected function createTrigger(Connection $connection, string $query, array $params = []): void
    {
        $blueGreenDeployment = (int) getenv('BLUE_GREEN_DEPLOYMENT');
        if ($blueGreenDeployment === 0) {
            return;
        }

        $connection->executeUpdate($query, $params);
    }

    protected function registerIndexer(Connection $connection, string $name): void
    {
        IndexerQueuer::registerIndexer($connection, $name, static::class);
    }
}

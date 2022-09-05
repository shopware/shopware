<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Shopware\Core\Defaults;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;

abstract class MigrationStep
{
    public const MIGRATION_VARIABLE_FORMAT = '@MIGRATION_%s_IS_ACTIVE';
    public const INSTALL_ENVIRONMENT_VARIABLE = 'SHOPWARE_INSTALL';

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
        try {
            $connection->executeUpdate(sprintf('DROP TRIGGER IF EXISTS %s', $name));
        } catch (DBALException $e) {
        }
    }

    public function isInstallation(): bool
    {
        return (bool) EnvironmentHelper::getVariable(self::INSTALL_ENVIRONMENT_VARIABLE, false);
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
     * @internal this method is kept because we don't want hard breaks in old migrations
     *
     * @deprecated tag:v6.4.0 - reason:becomes-internal - use createTrigger instead
     */
    protected function addBackwardTrigger(Connection $connection, string $name, string $table, string $time, string $event, string $statements): void
    {
        @trigger_error('addBackwardTrigger is not supported anymore. Use createTrigger instead', \E_USER_DEPRECATED);
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
        $blueGreenDeployment = EnvironmentHelper::getVariable('BLUE_GREEN_DEPLOYMENT', false);
        if ((int) $blueGreenDeployment === 0) {
            return;
        }

        $connection->executeUpdate($query, $params);
    }

    /**
     * @param array<string> $indexerToRun
     */
    protected function registerIndexer(Connection $connection, string $name, array $indexerToRun = []): void
    {
        IndexerQueuer::registerIndexer($connection, $name, $indexerToRun);
    }

    protected function columnExists(Connection $connection, string $table, string $column): bool
    {
        $exists = $connection->fetchOne(
            'SHOW COLUMNS FROM ' . $table . ' WHERE `Field` LIKE :column',
            ['column' => $column]
        );

        return !empty($exists);
    }

    /**
     * @param array<string, array<string>> $privileges
     *
     * @throws \Doctrine\DBAL\ConnectionException
     * @throws \Doctrine\DBAL\Exception
     * @throws \JsonException
     */
    protected function addAdditionalPrivileges(Connection $connection, array $privileges): void
    {
        $roles = $connection->iterateAssociative('SELECT * from `acl_role`');

        try {
            $connection->beginTransaction();

            /** @var array<string, mixed> $role */
            foreach ($roles as $role) {
                $currentPrivileges = \json_decode($role['privileges'], true, 512, \JSON_THROW_ON_ERROR);
                $newPrivileges = $this->fixRolePrivileges($privileges, $currentPrivileges);

                if ($currentPrivileges === $newPrivileges) {
                    continue;
                }

                $role['privileges'] = \json_encode($newPrivileges);
                $role['updated_at'] = (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_FORMAT);

                $connection->update('acl_role', $role, ['id' => $role['id']]);
            }

            $connection->commit();
        } catch (\Exception $e) {
            $connection->rollBack();

            throw $e;
        }
    }

    /**
     * @param array<string, array<string>> $privilegeChange
     * @param array<string> $rolePrivileges
     *
     * @return array<string>
     */
    private function fixRolePrivileges(array $privilegeChange, array $rolePrivileges): array
    {
        foreach ($privilegeChange as $existingPrivilege => $newPrivileges) {
            if (\in_array($existingPrivilege, $rolePrivileges, true)) {
                $rolePrivileges = \array_merge($rolePrivileges, $newPrivileges);
            }
        }

        return \array_values(\array_unique($rolePrivileges));
    }
}

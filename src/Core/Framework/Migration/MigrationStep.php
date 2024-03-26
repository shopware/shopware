<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ConnectionException;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Shopware\Core\Defaults;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Log\Package;

#[Package('core')]
abstract class MigrationStep
{
    use AddColumnTrait;

    final public const INSTALL_ENVIRONMENT_VARIABLE = 'SHOPWARE_INSTALL';

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
    public function updateDestructive(Connection $connection): void
    {
    }

    public function removeTrigger(Connection $connection, string $name): void
    {
        try {
            $connection->executeStatement(sprintf('DROP TRIGGER IF EXISTS %s', $name));
        } catch (Exception) {
        }
    }

    public function isInstallation(): bool
    {
        return (bool) EnvironmentHelper::getVariable(self::INSTALL_ENVIRONMENT_VARIABLE, false);
    }

    /**
     * @param mixed[] $params
     */
    protected function createTrigger(Connection $connection, string $query, array $params = []): void
    {
        $blueGreenDeployment = EnvironmentHelper::getVariable('BLUE_GREEN_DEPLOYMENT', false);
        if ((int) $blueGreenDeployment === 0) {
            return;
        }

        $connection->executeStatement($query, $params);
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
            'SHOW COLUMNS FROM `' . $table . '` WHERE `Field` LIKE :column',
            ['column' => $column]
        );

        return !empty($exists);
    }

    protected function indexExists(Connection $connection, string $table, string $index): bool
    {
        $exists = $connection->fetchOne(
            'SHOW INDEXES FROM `' . $table . '` WHERE `key_name` LIKE :index',
            ['index' => $index]
        );

        return !empty($exists);
    }

    protected function dropTableIfExists(Connection $connection, string $table): void
    {
        $sql = sprintf('DROP TABLE IF EXISTS `%s`', $table);
        $connection->executeStatement($sql);
    }

    /**
     * @return bool - Returns true when the foreign key has been really deleted
     */
    protected function dropForeignKeyIfExists(Connection $connection, string $table, string $column): bool
    {
        $sql = sprintf('ALTER TABLE `%s` DROP FOREIGN KEY `%s`', $table, $column);

        try {
            $connection->executeStatement($sql);
        } catch (\Throwable $e) {
            if ($e instanceof TableNotFoundException) {
                return false;
            }

            // fk does not exists
            if (str_contains($e->getMessage(), 'SQLSTATE[42000]')) {
                return false;
            }

            throw $e;
        }

        return true;
    }

    /**
     * @return bool - Returns true when the index has been really deleted
     */
    protected function dropIndexIfExists(Connection $connection, string $table, string $index): bool
    {
        $sql = sprintf('ALTER TABLE `%s` DROP INDEX `%s`', $table, $index);

        try {
            $connection->executeStatement($sql);
        } catch (\Throwable $e) {
            if ($e instanceof TableNotFoundException) {
                return false;
            }

            // index does not exists
            if (str_contains($e->getMessage(), 'SQLSTATE[42000]')) {
                return false;
            }

            throw $e;
        }

        return true;
    }

    /**
     * @param array<string, array<string>> $privileges
     *
     * @throws ConnectionException
     * @throws Exception
     * @throws \JsonException
     */
    protected function addAdditionalPrivileges(Connection $connection, array $privileges): void
    {
        $roles = $connection->iterateAssociative('SELECT * from `acl_role`');

        try {
            $connection->beginTransaction();

            foreach ($roles as $role) {
                $currentPrivileges = \json_decode((string) $role['privileges'], true, 512, \JSON_THROW_ON_ERROR);
                $newPrivileges = $this->fixRolePrivileges($privileges, $currentPrivileges);

                if ($currentPrivileges === $newPrivileges) {
                    continue;
                }

                $role['privileges'] = \json_encode($newPrivileges, \JSON_THROW_ON_ERROR);
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

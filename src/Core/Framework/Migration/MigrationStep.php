<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ConnectionException;
use Doctrine\DBAL\Exception;
use Shopware\Core\Defaults;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Log\Package;

#[Package('core')]
abstract class MigrationStep
{
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
    abstract public function updateDestructive(Connection $connection): void;

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

    /**
     * @param array<string, array<string>> $privileges
     *
     * @throws ConnectionException
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

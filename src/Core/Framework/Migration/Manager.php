<?php declare(strict_types=1);
/**
 * Shopware 5
 * Copyright (c) shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

namespace Shopware\Core\Framework\Migration;

use Shopware\Core\Defaults;

/**
 * Shopware\Core migration manager
 *
 * <code>
 * $migrationManager = new Manager($conn, '/path/to/migrations');
 * $migrationManager->run();
 * </code>
 *
 * @category  Shopware\Core
 *
 * @copyright Copyright (c) shopware AG (http://www.shopware.de)
 */
class Manager
{
    /**
     * @var \PDO
     */
    protected $connection;

    /**
     * @var string
     */
    protected $migrationPath;

    /**
     * @param \PDO   $connection
     * @param string $migrationPath
     */
    public function __construct(\PDO $connection, $migrationPath)
    {
        $this->migrationPath = $migrationPath;

        $this->connection = $connection;
    }

    /**
     * @param \PDO $connection
     *
     * @return Manager
     */
    public function setConnection(\PDO $connection)
    {
        $this->connection = $connection;

        return $this;
    }

    /**
     * @return \PDO
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * @param string $migrationPath
     *
     * @return Manager
     */
    public function setMigrationPath($migrationPath)
    {
        $this->migrationPath = $migrationPath;

        return $this;
    }

    public function getMigrationPath(): string
    {
        return $this->migrationPath;
    }

    public function log(string $str)
    {
        if (PHP_SAPI === 'cli') {
            echo $str . "\n";
        }
    }

    /**
     * Creates schama version table if not exists
     */
    public function createSchemaTable()
    {
        $sql = '
            CREATE TABLE IF NOT EXISTS `schema_version` (
            `version` int(11) NOT NULL,
            `start_date` datetime NOT NULL,
            `complete_date` datetime DEFAULT NULL,
            `name` VARCHAR( 255 ) NOT NULL,
            `error_msg` LONGTEXT DEFAULT NULL,
            PRIMARY KEY (`version`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ';
        $this->connection->exec($sql);
    }

    /**
     * Returns current schma version found in database
     *
     * @return int
     */
    public function getCurrentVersion()
    {
        $sql = 'SELECT version FROM schema_version WHERE complete_date IS NOT NULL ORDER BY version DESC';
        $currentVersion = (int) $this->connection->query($sql)->fetchColumn();

        return $currentVersion;
    }

    /**
     * Return an array of Migrations that have a higher version than $currentVersion
     * The array is indexed by Version
     *
     * @param int $currentVersion
     * @param int $limit
     *
     * @throws \Exception
     *
     * @return array
     */
    public function getMigrationsForVersion($currentVersion, $limit = null)
    {
        $regexPattern = '/^([0-9]*)-.+\.php$/i';

        $migrationPath = $this->getMigrationPath();

        $directoryIterator = new \DirectoryIterator($migrationPath);
        $regex = new \RegexIterator($directoryIterator, $regexPattern, \RecursiveRegexIterator::GET_MATCH);

        $migrations = [];

        foreach ($regex as $result) {
            $migrationVersion = $result['1'];
            if ($migrationVersion <= $currentVersion) {
                continue;
            }

            $migrationClassName = 'Migrations_Migration' . $result['1'];
            if (!class_exists($migrationClassName, false)) {
                $file = $migrationPath . '/' . $result['0'];
                require $file;
            }

            try {
                /** @var AbstractMigration $migrationClass */
                $migrationClass = new $migrationClassName($this->getConnection());
            } catch (\Exception $e) {
                throw new \RuntimeException('Could not instantiate Object');
            }

            if (!($migrationClass instanceof AbstractMigration)) {
                throw new \RuntimeException("$migrationClassName is not instanceof AbstractMigration");
            }

            if ($migrationClass->getVersion() != $result['0']) {
                throw new \RuntimeException(
                    sprintf('Version mismatch. Version in filename: %s, Version in Class: %s', $result['1'], $migrationClass->getVersion())
                );
            }

            $migrations[$migrationClass->getVersion()] = $migrationClass;
        }

        ksort($migrations);

        if ($limit !== null) {
            return array_slice($migrations, 0, $limit, true);
        }

        return $migrations;
    }

    /**
     * Applies given $migration to database
     *
     * @param AbstractMigration $migration
     * @param string            $modus
     *
     * @throws \Exception
     */
    public function apply(AbstractMigration $migration, $modus = AbstractMigration::MODUS_INSTALL)
    {
        $sql = 'REPLACE schema_version (version, start_date, name) VALUES (:version, :date, :name)';
        $stmt = $this->connection->prepare($sql);
        $stmt->execute([
            ':version' => $migration->getVersion(),
            ':date' => date(Defaults::DATE_FORMAT),
            ':name' => $migration->getLabel(),
        ]);

        try {
            $migration->up($modus);
            $sqls = $migration->getSql();

            foreach ($sqls as $sql) {
                $this->connection->exec($sql);
            }
        } catch (\Exception $e) {
            $updateVersionSql = 'UPDATE schema_version SET error_msg = :msg WHERE version = :version';
            $stmt = $this->connection->prepare($updateVersionSql);
            $stmt->execute([
                ':version' => $migration->getVersion(),
                ':msg' => $e->getMessage(),
            ]);

            throw new \RuntimeException(sprintf(
                'Could not apply migration (%s). Error: %s ', get_class($migration), $e->getMessage()
            ));
        }

        $sql = 'UPDATE schema_version SET complete_date = :date WHERE version = :version';
        $stmt = $this->connection->prepare($sql);
        $stmt->execute([
            ':version' => $migration->getVersion(),
            ':date' => date(Defaults::DATE_FORMAT),
        ]);
    }

    /**
     * Composite Method to apply all migrations
     *
     * @param string $modus
     */
    public function run($modus = AbstractMigration::MODUS_INSTALL)
    {
        $this->createSchemaTable();

        $currentVersion = $this->getCurrentVersion();
        $this->log(sprintf('Current MigrationNumber: %s', $currentVersion));

        $migrations = $this->getMigrationsForVersion($currentVersion);

        $this->log(sprintf('Found %s migrations to apply', count($migrations)));

        foreach ($migrations as $migration) {
            $this->log(sprintf('Apply MigrationNumber: %s - %s', $migration->getVersion(), $migration->getLabel()));
            $this->apply($migration, $modus);
        }
    }
}

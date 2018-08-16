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

use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Container;

class MigrationRuntime
{
    /**
     * @var Container
     */
    private $container;

    /**
     * @var string
     */
    private $migrationTableName;

    /**
     * @var Connection
     */
    private $connection;

    public function __construct(string $migrationTableName, Container $container)
    {
        $this->migrationTableName = $migrationTableName;
        $this->connection = $container->get('Doctrine\DBAL\Connection');
        $this->container = $container;
    }

    /**
     * @return MigrationRuntime
     */
    public static function create(string $migrationTableName, Container $container): self
    {
        return new self(
            $migrationTableName,
            $container
        );
    }

    /**
     * @param MigrationStepInterface[] $migrationSteps
     */
    public function migrate(array $migrationSteps, bool $destructive = false)
    {
        $this->ensureMigrationTableExists();

        $migrationSteps = $this->filterExecuted($migrationSteps, $destructive);

        foreach ($migrationSteps as $migrationStep) {
            if ($destructive) {
                $migrationStep->updateDestructive($this->container);
            } else {
                $migrationStep->update($this->container);
            }

            $this->setExecuted($migrationStep, $destructive);
        }
    }

    /**
     * @param MigrationStepInterface[] $migrationSteps
     *
     * @return MigrationStepInterface[]
     */
    private function filterExecuted(array $migrationSteps, bool $destructive = false): array
    {
        $executedMigrations = $this->connection->createQueryBuilder()
            ->select('identifier, 1')
            ->from($this->migrationTableName)
            ->where('identifier IN (:identifiers)')
            ->andWhere('destructive = :destructive')
            ->setParameter('identifiers', array_keys($migrationSteps), $this->connection::PARAM_INT_ARRAY)
            ->setParameter('destructive', ($destructive ? 1 : 0))
            ->execute()
            ->fetchAll(\PDO::FETCH_KEY_PAIR);

        return array_diff_key($migrationSteps, $executedMigrations);
    }

    private function setExecuted(MigrationStepInterface $migrationStep, bool $destructive = false)
    {
        $this->connection->insert(
            $this->migrationTableName,
            [
                'identifier' => $migrationStep::getIdentifier(),
                'destructive' => $destructive ? 1 : 0,
            ]
        );
    }

    private function ensureMigrationTableExists()
    {
        $this->connection->exec('
                CREATE TABLE IF NOT EXISTS `' . $this->migrationTableName . '` (
                    `identifier` VARCHAR(255) NOT NULL,
                    `destructive` TINYINT(1) NOT NULL,
                    `migrationTimestamp` TIMESTAMP(6) NOT NULL DEFAULT NOW(6),
                    PRIMARY KEY (`identifier`, `destructive`)
                )
                COLLATE=\'utf8_unicode_ci\'
                ENGINE=InnoDB;
        ');
    }
}

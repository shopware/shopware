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

class MigrationCollectionLoader
{
    /**
     * @var string[]
     */
    private $directories = [];

    /**
     * @return MigrationCollectionLoader
     */
    public static function create(): self
    {
        return new self();
    }

    /**
     * @return MigrationCollectionLoader
     */
    public function addDirectory(string $directory, string $namespace): self
    {
        $this->directories[$directory] = $namespace;

        return $this;
    }

    /**
     * @return MigrationStepInterface[]
     */
    public function getMigrationCollection(): array
    {
        $migrations = [];

        foreach ($this->directories as $directory => $namespace) {
            foreach (scandir($directory) as $classFileName) {
                $path = $directory . '/' . $classFileName;
                $className = $namespace . '\\' . pathinfo($classFileName, PATHINFO_FILENAME);

                if (pathinfo($path, PATHINFO_EXTENSION) !== 'php') {
                    continue;
                }

                if (!class_exists($className)) {
                    throw new \RuntimeException('Unable to load "' . $className . '" at "' . $path . '"');
                }

                if (!is_a($className, MigrationStepInterface::class, true)) {
                    continue;
                }

                /** @var MigrationStepInterface $migration */
                $migration = new $className();

                if (isset($migrations[$migration::getIdentifier()])) {
                    throw new \DomainException('Can not handle two migrations with identical identifiers');
                }

                $migrations[$migration::getIdentifier()] = $migration;
            }
        }

        return $this->sortMigrations($migrations);
    }

    /**
     * @param MigrationStepInterface[] $migrations
     *
     * @return MigrationStepInterface[]
     */
    private function sortMigrations(array $migrations): array
    {
        uasort($migrations, function (MigrationStepInterface $a, MigrationStepInterface $b) {
            return $a->getCreationTimeStamp() >= $b->getCreationTimeStamp() ? 1 : -1;
        });

        $onHold = [];
        $sortedMigrations = [];
        $onHoldSkip = true;
        foreach ($migrations as $key => $migration) {
            if (!$onHoldSkip) {
                $this->insertOnHoldMigrations($sortedMigrations, $onHold);
            }
            $onHoldSkip = false;

            if (!$migration->getRequiredMigrations()) {
                $sortedMigrations[$migration::getIdentifier()] = $migration;
                continue;
            }

            if ($this->checkMultipleIdentifier(
                array_keys($sortedMigrations),
                $migration->getRequiredMigrations())
            ) {
                $sortedMigrations[$migration::getIdentifier()] = $migration;
                continue;
            }

            $onHold[$key] = $migration;
            $onHoldSkip = true;
        }

        $this->insertOnHoldMigrations($sortedMigrations, $onHold);

        // Exception throw if count not equal to original migrations count (done)
        if (count($sortedMigrations) !== count($migrations)) {
            throw new \DomainException('Not all migrations can be executed');
        }

        return $sortedMigrations;
    }

    /**
     * @param MigrationStepInterface[] $migrations
     * @param MigrationStepInterface[] $onHoldMigrations
     */
    private function insertOnHoldMigrations(array &$migrations, array $onHoldMigrations)
    {
        foreach ($onHoldMigrations as $onHoldMigration) {
            if ($this->checkMultipleIdentifier(
                array_keys($migrations),
                $onHoldMigration->getRequiredMigrations())
            ) {
                $migrations[$onHoldMigration::getIdentifier()] = $onHoldMigration;
            }
        }
    }

    /**
     * @param string[] $identifiers
     * @param string[] $requiredIdentifiers
     */
    private function checkMultipleIdentifier(array $identifiers, array $requiredIdentifiers): bool
    {
        return array_diff($requiredIdentifiers, $identifiers) ? false : true;
    }
}

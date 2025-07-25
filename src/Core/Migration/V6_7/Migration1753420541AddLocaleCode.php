<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1753420541AddLocaleCode extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1753420541;
    }

    public function update(Connection $connection): void
    {
        // This method is intentionally left empty as the updateDestructive method handles the migration.
    }

    public function updateDestructive(Connection $connection): void
    {
        if ($this->hasAlreadyRun($connection)) {
            return;
        }

        $this->addNullableLocaleCode($connection);

        $snippets = $connection->fetchAllAssociative('SELECT id, locale_id FROM app_administration_snippet');

        if (\count($snippets) > 0) {
            $locales = $connection->fetchAllAssociativeIndexed('SELECT id, code FROM locale');

            $statement = $connection->prepare(<<<SQL
                UPDATE app_administration_snippet
                SET locale_code = :locale_code
                WHERE id = :id
            SQL);

            foreach ($snippets as $snippet) {
                $statement->bindValue('locale_code', $locales[$snippet['locale_id']]['code']);
                $statement->bindValue('id', $snippet['id']);
                $statement->executeStatement();
            }
        }

        $this->dropLocaleIdAndMakeLocaleCodeMandatory($connection);
    }

    private function hasAlreadyRun(Connection $connection): bool
    {
        return $connection->fetchOne('SHOW COLUMNS FROM app_administration_snippet LIKE "locale_code"') !== false;
    }

    private function addNullableLocaleCode(Connection $connection): void
    {
        $connection->executeStatement(<<<SQL
            ALTER TABLE app_administration_snippet
                ADD locale_code VARCHAR(255) DEFAULT NULL,
                ADD INDEX `idx.locale_code` (locale_code)
        SQL);
    }

    private function dropLocaleIdAndMakeLocaleCodeMandatory(Connection $connection): void
    {
        $connection->executeStatement(<<<SQL
            ALTER TABLE app_administration_snippet
                DROP FOREIGN KEY `fk.locale_id`,
                DROP locale_id,
                MODIFY locale_code VARCHAR(255) NOT NULL,
                ADD CONSTRAINT `uq.app_id_locale_code` UNIQUE (app_id, locale_code)
        SQL);
    }
}

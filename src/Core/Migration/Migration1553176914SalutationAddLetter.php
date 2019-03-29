<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

class Migration1553176914SalutationAddLetter extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1553176914;
    }

    public function update(Connection $connection): void
    {
        $this->addSalutationTranslationColumn($connection);
        $this->deleteDefaultTranslations($connection);
        $this->updateSalutationTranslations($connection);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }

    private function addSalutationTranslationColumn(Connection $connection): void
    {
        $connection->executeQuery("
            ALTER TABLE `salutation_translation`
            CHANGE `name` `display_name` VARCHAR(255) COLLATE 'utf8mb4_unicode_ci' NULL AFTER `language_id`,
            ADD    `letter_name`         VARCHAR(255) COLLATE 'utf8mb4_unicode_ci' NULL AFTER `display_name`;
        ");
    }

    private function deleteDefaultTranslations(Connection $connection): void
    {
        $connection->executeQuery('
            DELETE FROM `salutation_translation`
            WHERE `salutation_id` IN (:mr, :mrs, :miss, :diverse);
        ', [
            ':mr' => Uuid::fromHexToBytes(Defaults::SALUTATION_ID_MR),
            ':mrs' => Uuid::fromHexToBytes(Defaults::SALUTATION_ID_MRS),
            ':miss' => Uuid::fromHexToBytes(Defaults::SALUTATION_ID_MISS),
            ':diverse' => Uuid::fromHexToBytes(Defaults::SALUTATION_ID_DIVERSE),
        ]);
    }

    private function updateSalutationTranslations(Connection $connection): void
    {
        $mr = Uuid::fromHexToBytes(Defaults::SALUTATION_ID_MR);
        $mrs = Uuid::fromHexToBytes(Defaults::SALUTATION_ID_MRS);
        $miss = Uuid::fromHexToBytes(Defaults::SALUTATION_ID_MISS);
        $diverse = Uuid::fromHexToBytes(Defaults::SALUTATION_ID_DIVERSE);

        $languageEn = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        $languageDe = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM_DE);

        // Updates for: Mr.
        $connection->insert('salutation_translation', [
            'salutation_id' => $mr,
            'language_id' => $languageEn,
            'display_name' => 'Mr.',
            'letter_name' => 'Dear Mr.',
            'created_at' => date(Defaults::DATE_FORMAT),
        ]);
        $connection->insert('salutation_translation', [
            'salutation_id' => $mr,
            'language_id' => $languageDe,
            'display_name' => 'Herr',
            'letter_name' => 'Sehr geehrter Herr',
            'created_at' => date(Defaults::DATE_FORMAT),
        ]);

        // Updates for: Mrs.
        $connection->insert('salutation_translation', [
            'salutation_id' => $mrs,
            'language_id' => $languageEn,
            'display_name' => 'Mrs.',
            'letter_name' => 'Dear Mrs.',
            'created_at' => date(Defaults::DATE_FORMAT),
        ]);
        $connection->insert('salutation_translation', [
            'salutation_id' => $mrs,
            'language_id' => $languageDe,
            'display_name' => 'Frau',
            'letter_name' => 'Sehr geehrte Frau',
            'created_at' => date(Defaults::DATE_FORMAT),
        ]);

        // Updates for: Miss
        $connection->insert('salutation_translation', [
            'salutation_id' => $miss,
            'language_id' => $languageEn,
            'display_name' => 'Miss',
            'letter_name' => 'Dear Miss',
            'created_at' => date(Defaults::DATE_FORMAT),
        ]);
        $connection->insert('salutation_translation', [
            'salutation_id' => $miss,
            'language_id' => $languageDe,
            'display_name' => 'Fräulein',
            'letter_name' => 'Sehr geehrtes Fräulein',
            'created_at' => date(Defaults::DATE_FORMAT),
        ]);

        // Updates for: Diverse
        $connection->insert('salutation_translation', [
            'salutation_id' => $diverse,
            'language_id' => $languageEn,
            'display_name' => 'Mx.',
            'letter_name' => 'Dear Mx.',
            'created_at' => date(Defaults::DATE_FORMAT),
        ]);
        $connection->insert('salutation_translation', [
            'salutation_id' => $diverse,
            'language_id' => $languageDe,
            'display_name' => 'Divers',
            'letter_name' => ' ',
            'created_at' => date(Defaults::DATE_FORMAT),
        ]);
    }
}

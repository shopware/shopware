<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1578475268FixSloveneLocale extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1578475268;
    }

    public function update(Connection $connection): void
    {
        $enLangId = $this->fetchLanguageId('en-GB', $connection);
        if (!$enLangId) {
            return;
        }

        $localeId = $connection->query('SELECT id FROM locale WHERE code = "sl-SI"')->fetchColumn();
        if (!$localeId) {
            return;
        }

        $connection->executeUpdate(
            'UPDATE locale_translation
             SET name = :correctName
             WHERE locale_id = :locale_id AND language_id = :language_id
             AND name = :wrongName',
            [
                'locale_id' => $localeId,
                'language_id' => $enLangId,
                'wrongName' => 'Slovakian',
                'correctName' => 'Slovene',
            ]
        );
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }

    private function fetchLanguageId(string $code, Connection $connection): ?string
    {
        $langId = $connection->fetchColumn(
            'SELECT `language`.`id` FROM `language` INNER JOIN `locale` ON `language`.`translation_code_id` = `locale`.`id` WHERE `code` = :code LIMIT 1',
            ['code' => $code]
        );
        if ($langId === false) {
            return null;
        }

        return (string) $langId;
    }
}

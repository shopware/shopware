<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Struct\Uuid;

class Migration1553761969SalutationAddNotSpecified extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1553761969;
    }

    public function update(Connection $connection): void
    {
        $notSpecified = Uuid::uuid4()->getBytes();

        $languageEn = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        $languageDe = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM_DE);

        // Inserts for: Mr.
        $connection->insert('salutation', [
            'id' => $notSpecified,
            'salutation_key' => 'not_specified',
            'created_at' => date(Defaults::DATE_FORMAT),
        ]);
        $connection->insert('salutation_translation', [
            'salutation_id' => $notSpecified,
            'language_id' => $languageEn,
            'display_name' => 'Not specified',
            'letter_name' => ' ',
            'created_at' => date(Defaults::DATE_FORMAT),
        ]);
        $connection->insert('salutation_translation', [
            'salutation_id' => $notSpecified,
            'language_id' => $languageDe,
            'display_name' => 'Keine Angabe',
            'letter_name' => ' ',
            'created_at' => date(Defaults::DATE_FORMAT),
        ]);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}

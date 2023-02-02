<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\Traits\ImportTranslationsTrait;
use Shopware\Core\Migration\Traits\Translations;

class Migration1625465756DefaultSalutation extends MigrationStep
{
    use ImportTranslationsTrait;

    public const SALUTATION_KEY = 'undefined';
    public const SALUTATION_DISPLAY_NAME_EN = '';
    public const SALUTATION_DISPLAY_NAME_DE = '';

    public function getCreationTimestamp(): int
    {
        return 1625465756;
    }

    public function update(Connection $connection): void
    {
        $salutation = [
            'id' => Uuid::fromHexToBytes(Defaults::SALUTATION),
            'salutation_key' => self::SALUTATION_KEY,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ];

        try {
            $connection->insert('salutation', $salutation);
        } catch (UniqueConstraintViolationException $exception) {
            // Already exists, skip translation insertion too
            return;
        }

        $translation = new Translations(
            [
                'salutation_id' => Uuid::fromHexToBytes(Defaults::SALUTATION),
                'display_name' => self::SALUTATION_DISPLAY_NAME_DE,
                'letter_name' => '',
            ],
            [
                'salutation_id' => Uuid::fromHexToBytes(Defaults::SALUTATION),
                'display_name' => self::SALUTATION_DISPLAY_NAME_EN,
                'letter_name' => '',
            ]
        );

        $this->importTranslation('salutation_translation', $translation, $connection);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}

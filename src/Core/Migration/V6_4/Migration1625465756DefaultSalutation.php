<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\Traits\ImportTranslationsTrait;
use Shopware\Core\Migration\Traits\Translations;

/**
 * @internal
 */
#[Package('core')]
class Migration1625465756DefaultSalutation extends MigrationStep
{
    use ImportTranslationsTrait;

    final public const SALUTATION_KEY = 'undefined';
    final public const SALUTATION_DISPLAY_NAME_EN = '';
    final public const SALUTATION_DISPLAY_NAME_DE = '';
    private const DEFAULT_SALUTATION_ID = 'ed643807c9f84cc8b50132ea3ccb1c3b';

    public function getCreationTimestamp(): int
    {
        return 1625465756;
    }

    public function update(Connection $connection): void
    {
        $salutation = [
            'id' => Uuid::fromHexToBytes(self::DEFAULT_SALUTATION_ID),
            'salutation_key' => self::SALUTATION_KEY,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ];

        try {
            $connection->insert('salutation', $salutation);
        } catch (UniqueConstraintViolationException) {
            // Already exists, skip translation insertion too
            return;
        }

        $translation = new Translations(
            [
                'salutation_id' => Uuid::fromHexToBytes(self::DEFAULT_SALUTATION_ID),
                'display_name' => self::SALUTATION_DISPLAY_NAME_DE,
                'letter_name' => '',
            ],
            [
                'salutation_id' => Uuid::fromHexToBytes(self::DEFAULT_SALUTATION_ID),
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

<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\Traits\ImportTranslationsTrait;
use Shopware\Core\Migration\Traits\Translations;

/**
 * Seeds the shared `app_provided` document type. Every app-generated DocumentV2 document
 * references this single row so `document.document_type_id` can stay NOT NULL. The real
 * app identifier is stored in `document.config.documentType`.
 *
 * @internal
 */
#[Package('after-sales')]
class Migration1786369207SeedAppProvidedDocumentType extends MigrationStep
{
    use ImportTranslationsTrait;

    private const TECHNICAL_NAME = 'app_provided';

    public function getCreationTimestamp(): int
    {
        return 1786369207;
    }

    public function update(Connection $connection): void
    {
        $existing = $connection->fetchOne(
            'SELECT `id` FROM `document_type` WHERE `technical_name` = :technicalName',
            ['technicalName' => self::TECHNICAL_NAME]
        );

        if ($existing) {
            return;
        }

        $typeId = Uuid::randomBytes();

        $connection->insert('document_type', [
            'id' => $typeId,
            'technical_name' => self::TECHNICAL_NAME,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $translations = new Translations(
            ['document_type_id' => $typeId, 'name' => 'App-Dokument'],
            ['document_type_id' => $typeId, 'name' => 'App document'],
        );

        $this->importTranslation('document_type_translation', $translations, $connection);
    }
}

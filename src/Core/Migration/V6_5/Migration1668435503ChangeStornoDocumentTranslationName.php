<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_5;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Document\Renderer\StornoRenderer;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1668435503ChangeStornoDocumentTranslationName extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1668435503;
    }

    public function update(Connection $connection): void
    {
        $cancellationId = $connection->fetchOne(
            'SELECT `id` FROM `document_type` WHERE `technical_name` = :technicalName',
            ['technicalName' => StornoRenderer::TYPE]
        );
        $enLangId = $connection->fetchOne(
            'SELECT `language`.id FROM `language` INNER JOIN `locale` ON `language`.`locale_id` = `locale`.`id` WHERE `code` = :code LIMIT 1',
            ['code' => 'en-GB']
        );

        if ($cancellationId === null) {
            return;
        }

        $connection->executeStatement(
            'UPDATE `document_type_translation` SET `name` = :docName WHERE document_type_id = :typeId AND `language_id` = :languageId AND `updated_at` IS NULL',
            [
                'docName' => 'Cancellation invoice',
                'typeId' => $cancellationId,
                'languageId' => $enLangId,
            ]
        );

        $connection->executeStatement(
            'UPDATE `document_base_config` SET `name` = :name, `filename_prefix` = :prefix WHERE document_type_id = :typeId AND `updated_at` IS NULL',
            [
                'name' => 'cancellation_invoice',
                'prefix' => 'cancellation_invoice_',
                'typeId' => $cancellationId,
            ]
        );
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Document\Renderer\ZugferdEmbeddedCancellationInvoiceRenderer;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\Traits\ImportTranslationsTrait;
use Shopware\Core\Migration\Traits\Translations;

/**
 * @internal
 */
#[Package('framework')]
class Migration1773225412AddZugferdEmbeddedCancellationInvoice extends MigrationStep
{
    use ImportTranslationsTrait;

    public function getCreationTimestamp(): int
    {
        return 1773225412;
    }

    public function update(Connection $connection): void
    {
        $documentTypeId = $connection->fetchOne(
            'SELECT `id` FROM `document_type` WHERE technical_name = :technicalName',
            ['technicalName' => ZugferdEmbeddedCancellationInvoiceRenderer::TYPE]
        );

        if ($documentTypeId !== false) {
            return;
        }

        $createdAt = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $cancellationInvoiceId = Uuid::randomBytes();

        $connection->insert('document_type', [
            'id' => $cancellationInvoiceId,
            'technical_name' => ZugferdEmbeddedCancellationInvoiceRenderer::TYPE,
            'created_at' => $createdAt,
        ]);

        $translation = new Translations(
            ['document_type_id' => $cancellationInvoiceId, 'name' => 'ZUGFeRD Stornorechnung (eingebettet)'],
            ['document_type_id' => $cancellationInvoiceId, 'name' => 'ZUGFeRD Cancellation Invoice (embedded)']
        );

        $this->importTranslation(
            'document_type_translation',
            $translation,
            $connection
        );
    }
}

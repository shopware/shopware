<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_6;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Document\Renderer\ZugferdEmbeddedRenderer;
use Shopware\Core\Checkout\Document\Renderer\ZugferdRenderer;
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
class Migration1730790665ElectronicInvoice extends MigrationStep
{
    use ImportTranslationsTrait;

    public function getCreationTimestamp(): int
    {
        return 1730790665;
    }

    public function update(Connection $connection): void
    {
        $documentType = $connection->fetchOne('SELECT `id` FROM `document_type` WHERE technical_name like \'%zugferd%\'');
        if ($documentType !== false) {
            return;
        }

        $createdAt = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $electronicInvoiceId = Uuid::randomBytes();
        $embeddedInvoiceId = Uuid::randomBytes();

        $connection->insert('document_type', ['id' => $electronicInvoiceId, 'technical_name' => ZugferdRenderer::TYPE, 'created_at' => $createdAt]);
        $connection->insert('document_type', ['id' => $embeddedInvoiceId, 'technical_name' => ZugferdEmbeddedRenderer::TYPE, 'created_at' => $createdAt]);

        $zugferdTranslation = new Translations(
            ['document_type_id' => $electronicInvoiceId, 'name' => 'Rechnung: ZUGFeRD E-Rechnung'],
            ['document_type_id' => $electronicInvoiceId, 'name' => 'Invoice: ZUGFeRD E-invoice']
        );
        $embeddedTranslation = new Translations(
            ['document_type_id' => $embeddedInvoiceId, 'name' => 'Rechnung: PDF mit eingebetteter ZUGFeRD E-Rechnung'],
            ['document_type_id' => $embeddedInvoiceId, 'name' => 'Invoice: PDF with embedded ZUGFeRD E-invoice']
        );
        $this->importTranslation('document_type_translation', $zugferdTranslation, $connection);
        $this->importTranslation('document_type_translation', $embeddedTranslation, $connection);
    }
}

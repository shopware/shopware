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
        $types = [
            ZugferdRenderer::TYPE => [
                'de' => ['name' => 'Rechnung: ZUGFeRD E-Rechnung'],
                'en' => ['name' => 'Invoice: ZUGFeRD E-invoice'],
            ],
            ZugferdEmbeddedRenderer::TYPE => [
                'de' => ['name' => 'Rechnung: PDF mit eingebetteter ZUGFeRD E-Rechnung'],
                'en' => ['name' => 'Invoice: PDF with embedded ZUGFeRD E-invoice'],
            ],
        ];

        foreach ($types as $technicalName => $translations) {
            $this->addDocumentType($technicalName, $translations, $connection);
        }
    }

    /**
     * @param array<string, array<string, string>> $translations
     */
    private function addDocumentType(string $technicalName, array $translations, Connection $connection): void
    {
        $typeId = $connection->fetchOne(
            'SELECT `id` FROM `document_type` WHERE technical_name = :technicalName',
            ['technicalName' => $technicalName]
        );

        if ($typeId) {
            return;
        }

        $createdAt = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $typeId = Uuid::randomBytes();
        $connection->insert('document_type', ['id' => $typeId, 'technical_name' => $technicalName, 'created_at' => $createdAt]);

        $translation = new Translations(
            array_merge(['document_type_id' => $typeId], $translations['de']),
            array_merge(['document_type_id' => $typeId], $translations['en'])
        );

        $this->importTranslation('document_type_translation', $translation, $connection);
    }
}

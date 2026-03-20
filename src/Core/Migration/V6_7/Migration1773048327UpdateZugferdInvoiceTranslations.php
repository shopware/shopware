<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Document\Renderer\ZugferdEmbeddedRenderer;
use Shopware\Core\Checkout\Document\Renderer\ZugferdRenderer;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Migration\Traits\ImportTranslationsTrait;
use Shopware\Core\Migration\Traits\Translations;

/**
 * @internal
 */
#[Package('after-sales')]
class Migration1773048327UpdateZugferdInvoiceTranslations extends MigrationStep
{
    use ImportTranslationsTrait;

    public function getCreationTimestamp(): int
    {
        return 1773048327;
    }

    public function update(Connection $connection): void
    {
        $types = [
            ZugferdRenderer::TYPE => [
                'de' => ['name' => 'ZUGFeRD Rechnung'],
                'en' => ['name' => 'ZUGFeRD Invoice'],
            ],
            ZugferdEmbeddedRenderer::TYPE => [
                'de' => ['name' => 'ZUGFeRD Rechnung (eingebettet)'],
                'en' => ['name' => 'ZUGFeRD Invoice (embedded)'],
            ],
        ];

        foreach ($types as $technicalName => $translations) {
            $this->updateTranslation($technicalName, $translations, $connection);
        }
    }

    /**
     * @param array<string, array<string, string>> $translations
     */
    private function updateTranslation(string $technicalName, array $translations, Connection $connection): void
    {
        $typeId = $connection->fetchOne(
            'SELECT `id` FROM `document_type` WHERE technical_name = :technicalName',
            ['technicalName' => $technicalName]
        );

        if ($typeId === false) {
            return;
        }

        $translation = new Translations(
            array_merge(['document_type_id' => $typeId], $translations['de']),
            array_merge(['document_type_id' => $typeId], $translations['en'])
        );

        $this->importTranslation(
            'document_type_translation',
            $translation,
            $connection
        );
    }
}

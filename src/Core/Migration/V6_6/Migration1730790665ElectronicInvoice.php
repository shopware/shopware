<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_6;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Document\Renderer\ZugferdEmbeddedRenderer;
use Shopware\Core\Checkout\Document\Renderer\ZugferdRenderer;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('core')]
class Migration1730790665ElectronicInvoice extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1730790665;
    }

    public function update(Connection $connection): void
    {
        $documentType = $connection->fetchOne(
            'SELECT `id` FROM `document_type` WHERE technical_name like \'%zugferd%\''
        );

        if ($documentType !== false) {
            return;
        }

        $defaultLanguageId = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        $electronicInvoiceId = Uuid::randomBytes();
        $embeddedInvoiceId = Uuid::randomBytes();
        $rangeTypeId = Uuid::randomBytes();
        $numberRangeId = Uuid::randomBytes();
        $languageIds = $this->getLanguages($connection);

        $connection->insert('document_type', ['id' => $electronicInvoiceId, 'technical_name' => ZugferdRenderer::TYPE, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('document_type', ['id' => $embeddedInvoiceId, 'technical_name' => ZugferdEmbeddedRenderer::TYPE, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);

        $connection->insert('number_range_type', ['id' => $rangeTypeId, 'global' => 0, 'technical_name' => 'document_zugferd_invoice', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);

        $connection->insert('number_range', ['id' => $numberRangeId, 'global' => 1, 'type_id' => $rangeTypeId, 'pattern' => '{n}', 'start' => 1000, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);

        if (($languageIds['en-GB'] ?? null) !== $defaultLanguageId && ($languageIds['de-DE'] ?? null) !== $defaultLanguageId) {
            $connection->insert('document_type_translation', ['document_type_id' => $electronicInvoiceId, 'language_id' => $defaultLanguageId, 'name' => 'ZUGFeRD e-invoice', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
            $connection->insert('document_type_translation', ['document_type_id' => $embeddedInvoiceId, 'language_id' => $defaultLanguageId, 'name' => 'Embedded ZUGFeRD e-invoice', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);

            $connection->insert('number_range_type_translation', ['number_range_type_id' => $rangeTypeId, 'type_name' => 'ZUGFeRD e-invoice', 'language_id' => $defaultLanguageId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);

            $connection->insert('number_range_translation', ['number_range_id' => $numberRangeId, 'name' => 'ZUGFeRD invoices', 'language_id' => $defaultLanguageId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        }

        if (\array_key_exists('en-GB', $languageIds)) {
            $connection->insert('document_type_translation', ['document_type_id' => $electronicInvoiceId, 'language_id' => $languageIds['en-GB'], 'name' => 'ZUGFeRD e-invoice', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
            $connection->insert('document_type_translation', ['document_type_id' => $embeddedInvoiceId, 'language_id' => $languageIds['en-GB'], 'name' => 'Embedded ZUGFeRD e-invoice', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);

            $connection->insert('number_range_type_translation', ['number_range_type_id' => $rangeTypeId, 'type_name' => 'ZUGFeRD e-invoice', 'language_id' => $languageIds['en-GB'], 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);

            $connection->insert('number_range_translation', ['number_range_id' => $numberRangeId, 'name' => 'ZUGFeRD invoices', 'language_id' => $languageIds['en-GB'], 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        }

        if (\array_key_exists('de-DE', $languageIds)) {
            $connection->insert('document_type_translation', ['document_type_id' => $electronicInvoiceId, 'language_id' => $languageIds['de-DE'], 'name' => 'ZUGFeRD E-Rechnung', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
            $connection->insert('document_type_translation', ['document_type_id' => $embeddedInvoiceId, 'language_id' => $languageIds['de-DE'], 'name' => 'Eingebettete ZUGFeRD E-Rechnung', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);

            $connection->insert('number_range_type_translation', ['number_range_type_id' => $rangeTypeId, 'type_name' => 'ZUGFeRD E-Rechnung', 'language_id' => $languageIds['de-DE'], 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);

            $connection->insert('number_range_translation', ['number_range_id' => $numberRangeId, 'name' => 'ZUGFeRD Rechnungen', 'language_id' => $languageIds['de-DE'], 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        }
    }

    /**
     * @return array{en-GB?: string, de-DE?: string}
     */
    private function getLanguages(Connection $connection): array
    {
        $sql = <<<'SQL'
            SELECT `locale`.`code`, `language`.`id` as `id`
            FROM `language`
            INNER JOIN `locale` ON `locale`.`id` = `language`.`locale_id`
            WHERE `locale`.`code` IN (:codes);
        SQL;

        return $connection->executeQuery(
            $sql,
            ['codes' => ['en-GB', 'de-DE']],
            ['codes' => ArrayParameterType::STRING]
        )->fetchAllKeyValue();
    }
}

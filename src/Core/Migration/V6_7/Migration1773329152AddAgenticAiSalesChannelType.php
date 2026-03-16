<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
class Migration1773329152AddAgenticAiSalesChannelType extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1773329152;
    }

    public function update(Connection $connection): void
    {
        $salesChannelTypeId = Uuid::fromHexToBytes(Defaults::SALES_CHANNEL_TYPE_AGENTIC_AI);
        $defaultLanguageIds = $this->fetchDefaultLanguageIds($connection);

        $languageEN = $defaultLanguageIds['en-GB'] ?? null;
        $languageDE = $defaultLanguageIds['de-DE'] ?? null;

        $connection->transactional(function (Connection $connection) use ($salesChannelTypeId, $languageEN, $languageDE): void {
            $connection->insert('sales_channel_type', [
                'id' => $salesChannelTypeId,
                'icon_name' => 'default-object-rocket',
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);

            $connection->insert('sales_channel_type_translation', [
                'sales_channel_type_id' => $salesChannelTypeId,
                'language_id' => $languageEN,
                'name' => 'Agentic AI',
                'manufacturer' => 'shopware AG',
                'description' => 'Sales channel for agentic AI commerce platforms',
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);

            $connection->insert('sales_channel_type_translation', [
                'sales_channel_type_id' => $salesChannelTypeId,
                'language_id' => $languageDE,
                'name' => 'Agentenbasierte KI',
                'manufacturer' => 'shopware AG',
                'description' => 'Verkaufskanal für agentenbasierte KI-Handelsplatformen',
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        });
    }

    /**
     * @return array<string, string>
     */
    private function fetchDefaultLanguageIds(Connection $connection): array
    {
        $sql = <<<'SQL'
            SELECT locale.code, language.id
            FROM language
            INNER JOIN locale
                ON language.locale_id = locale.id
            WHERE locale.code = 'de-DE' OR locale.code = 'en-GB'
        SQL;

        return $connection->fetchAllKeyValue($sql);
    }
}

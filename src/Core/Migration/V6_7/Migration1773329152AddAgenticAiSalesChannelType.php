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
        $salesChannelTypeId = Uuid::fromHexToBytes(Defaults::SALES_CHANNEL_TYPE_AGENTIC_COMMERCE);
        $defaultLanguageIds = $this->fetchDefaultLanguageIds($connection);
        $systemLanguageId = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);

        $connection->transactional(function (Connection $connection) use ($salesChannelTypeId, $defaultLanguageIds, $systemLanguageId): void {
            $connection->insert('sales_channel_type', [
                'id' => $salesChannelTypeId,
                'icon_name' => 'regular-sparkle',
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);

            $translations = [
                $systemLanguageId => [
                    'name' => 'Agentic Commerce',
                    'manufacturer' => 'shopware AG',
                    'description' => 'Sales channel for agentic commerce platforms',
                ],
            ];

            $englishLanguageId = $defaultLanguageIds['en-GB'] ?? null;

            if ($englishLanguageId !== null && $englishLanguageId !== $systemLanguageId) {
                $translations[$englishLanguageId] = [
                    'name' => 'Agentic Commerce',
                    'manufacturer' => 'shopware AG',
                    'description' => 'Sales channel for agentic commerce platforms',
                ];
            }

            $germanLanguageId = $defaultLanguageIds['de-DE'] ?? null;

            if ($germanLanguageId !== null && $germanLanguageId !== $systemLanguageId) {
                $translations[$germanLanguageId] = [
                    'name' => 'Agentic Commerce',
                    'manufacturer' => 'shopware AG',
                    'description' => 'Verkaufskanal für Agentic-Commerce-Plattformen',
                ];
            }

            foreach ($translations as $languageId => $translation) {
                $connection->insert('sales_channel_type_translation', [
                    'sales_channel_type_id' => $salesChannelTypeId,
                    'language_id' => $languageId,
                    'name' => $translation['name'],
                    'manufacturer' => $translation['manufacturer'],
                    'description' => $translation['description'],
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]);
            }
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

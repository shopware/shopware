<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainDefinition;

class Migration1620820321AddDefaultDomainForHeadlessSaleschannel extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1620820321;
    }

    public function update(Connection $connection): void
    {
        $headlessSalesChannels = $connection->fetchFirstColumn(
            'SELECT `id` FROM `sales_channel` WHERE `type_id` = :headlessType',
            ['headlessType' => Uuid::fromHexToBytes(Defaults::SALES_CHANNEL_TYPE_API)]
        );

        $snippetSetId = $connection->fetchOne('SELECT id from snippet_set WHERE iso = :iso UNION SELECT id FROM snippet_set LIMIT 1', [
            'iso' => 'en-GB',
        ]);

        if ($snippetSetId === false) {
            return;
        }

        foreach ($headlessSalesChannels as $index => $headlessSalesChannelId) {
            $defaultDomainExist = $connection->fetchOne('SELECT id from sales_channel_domain WHERE sales_channel_id = :headlessSalesChannelId', [
                'headlessSalesChannelId' => $headlessSalesChannelId,
            ]);

            if ($defaultDomainExist) {
                continue;
            }

            $connection->insert(SalesChannelDomainDefinition::ENTITY_NAME, [
                'id' => Uuid::randomBytes(),
                'sales_channel_id' => $headlessSalesChannelId,
                'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
                'currency_id' => Uuid::fromHexToBytes(Defaults::CURRENCY),
                'snippet_set_id' => $snippetSetId,
                'url' => 'default.headless' . $index,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}

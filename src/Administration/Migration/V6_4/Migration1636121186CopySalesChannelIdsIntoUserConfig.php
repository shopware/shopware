<?php declare(strict_types=1);

namespace Shopware\Administration\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageDefinition;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelTranslation\SalesChannelTranslationDefinition;
use Shopware\Core\System\User\UserDefinition;

class Migration1636121186CopySalesChannelIdsIntoUserConfig extends MigrationStep
{
    private const CONFIG_KEY = 'sales-channel-favorites';
    private const MAX_RESULTS = 7;

    public function getCreationTimestamp(): int
    {
        return 1636121186;
    }

    public function update(Connection $connection): void
    {
        $ids = $this->fetchUserSalesChannelIds($connection);

        if (!$ids) {
            return;
        }

        $mapping = $this->getMappedData($ids);

        foreach ($mapping as $userId => $salesChannelIds) {
            $slicedIds = \array_slice($salesChannelIds, 0, self::MAX_RESULTS);

            $connection->insert('user_config', [
                'id' => Uuid::randomBytes(),
                'user_id' => $userId,
                '`key`' => self::CONFIG_KEY,
                '`value`' => json_encode($slicedIds),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }

    private function getMappedData(array $data): array
    {
        $mapping = [];
        foreach ($data as $salesChannelData) {
            $mapping[$salesChannelData['userId']][] = $salesChannelData['salesChannelId'];
        }

        return $mapping;
    }

    private function fetchUserSalesChannelIds(Connection $connection): array
    {
        return $connection->createQueryBuilder()
            ->select('user.id AS userId')
            ->addSelect('LOWER(HEX(translation.sales_channel_id)) AS salesChannelId')
            ->addSelect('translation.name')
            ->from(UserDefinition::ENTITY_NAME, 'user')
            ->innerJoin('user', LanguageDefinition::ENTITY_NAME, 'language', 'user.locale_id = language.locale_id')
            ->innerJoin('language', SalesChannelTranslationDefinition::ENTITY_NAME, 'translation', 'translation.language_id = language.id')
            ->orderBy('translation.name', 'ASC')
            ->execute()
            ->fetchAllAssociative();
    }
}

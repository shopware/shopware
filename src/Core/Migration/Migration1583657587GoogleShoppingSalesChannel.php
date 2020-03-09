<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

class Migration1583657587GoogleShoppingSalesChannel extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1583657587;
    }

    public function update(Connection $connection): void
    {
        $languageEN = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        $languageDE = $this->getDeDeLanguageId($connection);

        $googleShopping = Uuid::fromHexToBytes(Defaults::SALES_CHANNEL_TYPE_GOOGLE_SHOPPING);

        $connection->insert('sales_channel_type', [
            'id' => $googleShopping,
            'icon_name' => 'default-shopping-paper-bag',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $connection->insert('sales_channel_type_translation', [
            'sales_channel_type_id' => $googleShopping,
            'language_id' => $languageEN,
            'name' => 'Google Shopping',
            'manufacturer' => 'shopware AG',
            'description' => 'Sales channel for Google Shopping',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $connection->insert('sales_channel_type_translation', [
            'sales_channel_type_id' => $googleShopping,
            'language_id' => $languageDE,
            'name' => 'Google Shopping',
            'manufacturer' => 'shopware AG',
            'description' => 'Verkaufskanal für Google Shopping',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    private function getDeDeLanguageId(Connection $connection): string
    {
        return (string) $connection->fetchColumn(
            'SELECT id FROM language WHERE id != :default',
            ['default' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)]
        );
    }
}

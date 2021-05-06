<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1614249488ChangeProductSortingsToCheapestPrice extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1614249488;
    }

    public function update(Connection $connection): void
    {
        $this->migrateSortings($connection);
        $this->migrateCmsSortings($connection);
    }

    public function updateDestructive(Connection $connection): void
    {
        $connection->executeStatement('ALTER TABLE `product` DROP `listing_prices`');
    }

    private function migrateSortings(Connection $connection): void
    {
        $sortings = $connection->fetchAllAssociative('SELECT id, fields FROM product_sorting WHERE fields IS NOT NULL');

        foreach ($sortings as $sorting) {
            if (!isset($sorting['id'])) {
                continue;
            }
            if (!isset($sorting['fields'])) {
                continue;
            }

            $id = $sorting['id'];
            $fields = json_decode($sorting['fields'], true);
            $update = false;

            foreach ($fields as &$field) {
                if (!isset($field['field'])) {
                    continue;
                }
                if ($field['field'] !== 'product.listingPrices') {
                    continue;
                }
                $field['field'] = 'product.cheapestPrice';
                $update = true;
            }

            if ($update) {
                $connection->executeStatement(
                    '
                        UPDATE product_sorting
                        SET fields = :fields
                        WHERE id = :id
                    ',
                    ['fields' => json_encode($fields), 'id' => $id]
                );
            }
        }
    }

    private function migrateCmsSortings(Connection $connection): void
    {
        $elements = $connection->fetchAllAssociative("SELECT cms_slot_id, cms_slot_version_id, language_id, config FROM cms_slot_translation WHERE config LIKE '%listingPrices%'");

        foreach ($elements as $element) {
            $config = json_decode($element['config'], true);

            if (!isset($config['productStreamSorting'])) {
                continue;
            }
            $sorting = $config['productStreamSorting'];
            if (!isset($sorting['value'])) {
                continue;
            }
            if ($sorting['value'] === 'listingPrices:ASC') {
                $config['productStreamSorting']['value'] = 'cheapestPrice:ASC';
            } elseif ($sorting['value'] === 'listingPrices:DESC') {
                $config['productStreamSorting']['value'] = 'cheapestPrice:DESC';
            } else {
                continue;
            }

            $connection->executeStatement('UPDATE cms_slot_translation SET config = :config WHERE cms_slot_id = :id AND cms_slot_version_id = :version AND language_id = :language', [
                'config' => json_encode($config),
                'id' => $element['cms_slot_id'],
                'version' => $element['cms_slot_version_id'],
                'language' => $element['language_id'],
            ]);
        }
    }
}

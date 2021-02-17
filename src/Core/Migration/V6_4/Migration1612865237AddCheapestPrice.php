<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1612865237AddCheapestPrice extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1612865237;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate('ALTER TABLE `product` ADD `cheapest_price` longtext NULL;');
        $connection->executeUpdate('ALTER TABLE `product` ADD `cheapest_price_accessor` longtext NULL;');

        //@internal (flag:FEATURE_NEXT_10553) has to be moved into a new migration after feature flag removed
//        $this->migrateSortings($connection);
//        $this->migrateCmsSortings($connection);
    }

    public function updateDestructive(Connection $connection): void
    {
        //@internal (flag:FEATURE_NEXT_10553) has to be moved into a new migration after feature flag removed
//        $connection->executeUpdate('ALTER TABLE `product` DROP `listing_prices`');
    }

    //@internal (flag:FEATURE_NEXT_10553) has to be moved into a new migration after feature flag removed
//    private function migrateSortings(Connection $connection): void
//    {
//        $sortings = $connection->fetchAll('SELECT id, fields FROM product_sorting WHERE fields IS NOT NULL');
//
//        foreach ($sortings as $sorting) {
//            if (!isset($sorting['id'])) {
//                continue;
//            }
//            if (!isset($sorting['fields'])) {
//                continue;
//            }
//
//            $id = $sorting['id'];
//            $fields = json_decode($sorting['fields'], true);
//            $update = false;
//
//            foreach ($fields as &$field) {
//                if (!isset($field['field'])) {
//                    continue;
//                }
//                if ($field['field'] !== 'product.listingPrices') {
//                    continue;
//                }
//                $field['field'] = 'product.cheapestPrice';
//                $update = true;
//            }
//
//            if ($update) {
//                $connection->executeUpdate('UPDATE product_sorting SET fields = :fields WHERE id = :id', ['fields' => json_encode($fields), 'id' => $id]);
//            }
//        }
//    }
//
//    //@internal (flag:FEATURE_NEXT_10553) has to be moved into a new migration after feature flag removed
//    private function migrateCmsSortings(Connection $connection): void
//    {
//        $elements = $connection->fetchAll("SELECT cms_slot_id, cms_slot_version_id, language_id, config FROM cms_slot_translation WHERE config LIKE '%listingPrices%'");
//
//        foreach ($elements as $element) {
//            $config = json_decode($element['config'], true);
//
//            if (!isset($config['productStreamSorting'])) {
//                continue;
//            }
//            $sorting = $config['productStreamSorting'];
//            if (!isset($sorting['value'])) {
//                continue;
//            }
//            if ($sorting['value'] === 'listingPrices:ASC') {
//                $config['productStreamSorting']['value'] = 'cheapestPrice:ASC';
//            } elseif ($sorting['value'] === 'listingPrices:DESC') {
//                $config['productStreamSorting']['value'] = 'cheapestPrice:ASC';
//            } else {
//                continue;
//            }
//
//            $connection->executeUpdate('UPDATE cms_slot_translation SET config = :config WHERE cms_slot_id = :id AND cms_slot_version_id = :version AND language_id = :language', [
//                'config' => json_encode($config),
//                'id' => $element['cms_slot_id'],
//                'version' => $element['cms_slot_version_id'],
//                'language' => $element['language_id']
//            ]);
//        }
//    }
}

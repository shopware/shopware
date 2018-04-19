<?php

use Shopware\Bundle\SearchBundle\Sorting\PopularitySorting;
use Shopware\Bundle\SearchBundle\Sorting\PriceSorting;
use Shopware\Bundle\SearchBundle\Sorting\ProductNameSorting;
use Shopware\Bundle\SearchBundle\Sorting\ReleaseDateSorting;
use Shopware\Bundle\SearchBundle\Sorting\SearchRankingSorting;
use Shopware\Framework\Migration\AbstractMigration;

class Migrations_Migration917 extends AbstractMigration
{
    /**
     * @inheritdoc
     */
    public function up($modus)
    {
        $this->addSortingModule();

        $this->addDefaultSortings();

        $this->importSortingTranslations();

        $this->addCategorySortings();

        $this->addSortingsToProductStreams();

        $this->importProductStreamSortings();

        $this->moveCategoryDefaultSorting();

        $this->addSearchConfiguration();
    }

    private function addSortingModule()
    {
        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `s_search_custom_sorting` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `label` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `active` int(1) unsigned NOT NULL,
  `display_in_categories` int(1) unsigned NOT NULL,
  `position` int(11) NOT NULL,
  `sortings` LONGTEXT NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sorting` (`display_in_categories`,`position`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
SQL;
        $this->addSql($sql);

        $sql = <<<SQL
SET @formId = (SELECT id FROM s_core_config_forms WHERE name = 'Frontend' LIMIT 1);

INSERT INTO `s_core_config_forms` (`parent_id`, `name`, `label`, `description`, `position`, `plugin_id`) VALUES
(@formId, 'CustomSearch', 'Filter / Sortierung', NULL, 0, NULL);
SQL;

        $this->addSql($sql);

        $sql = <<<SQL
SET @formId = (SELECT id FROM s_core_config_forms WHERE name = 'CustomSearch');

INSERT INTO `s_core_config_form_translations` (`form_id`, `locale_id`, `label`, `description`)
VALUES (@formId, '2', 'Filter / Sortings', NULL);
SQL;
        $this->addSql($sql);
    }

    private function addCategorySortings()
    {
        $sql = <<<SQL
ALTER TABLE `s_categories`
  ADD `hide_sortings` INT(1) NOT NULL DEFAULT '0',
  ADD `sorting_ids` TEXT NULL;
SQL;
        $this->addSql($sql);
    }

    private function addSortingsToProductStreams()
    {
        $this->addSql(
            'ALTER TABLE `s_product_streams` ADD `sorting_id` INT NULL DEFAULT NULL;'
        );
    }

    private function addDefaultSortings()
    {
        $sql = <<<SQL
INSERT INTO `s_search_custom_sorting` (`id`, `label`, `active`, `display_in_categories`, `position`, `sortings`) VALUES
(1, 'Erscheinungsdatum', 1, 1, -10, '{"Shopware\\\\\\\Bundle\\\\\\\SearchBundle\\\\\\\Sorting\\\\\\\ReleaseDateSorting":{"direction":"DESC"}}'),
(2, 'Beliebtheit', 1, 1, 1, '{"Shopware\\\\\\\Bundle\\\\\\\SearchBundle\\\\\\\Sorting\\\\\\\PopularitySorting":{"direction":"DESC"}}'),
(3, 'Niedrigster Preis', 1, 1, 2, '{"Shopware\\\\\\\Bundle\\\\\\\SearchBundle\\\\\\\Sorting\\\\\\\PriceSorting":{"direction":"ASC"}}'),
(4, 'Höchster Preis', 1, 1, 3, '{"Shopware\\\\\\\Bundle\\\\\\\SearchBundle\\\\\\\Sorting\\\\\\\PriceSorting":{"direction":"DESC"}}'),
(5, 'Artikelbezeichnung', 1, 1, 4, '{"Shopware\\\\\\\Bundle\\\\\\\SearchBundle\\\\\\\Sorting\\\\\\\ProductNameSorting":{"direction":"ASC"}}'),
(7, 'Beste Ergebnisse', 1, 0, 6, '{"Shopware\\\\\\\Bundle\\\\\\\SearchBundle\\\\\\\Sorting\\\\\\\SearchRankingSorting":{}}');
SQL;

        $this->addSql($sql);
    }

    /**
     * @param int $translationShopId
     * @param int $localeId
     * @return array
     */
    private function getExistingSortingTranslations($translationShopId, $localeId)
    {
        $translations = $this->connection->query(
            "SELECT `name`, `value` FROM s_core_snippets WHERE `name`
             IN ('ListingSortRelevance', 'ListingSortRelease', 'ListingSortRating', 'ListingSortPriceHighest', 'ListingSortName', 'ListingSortPriceLowest')
             AND shopID = " . $translationShopId . " AND localeID = " . $localeId
        )->fetchAll(PDO::FETCH_ASSOC);

        $insert = [];
        foreach ($translations as $translation) {
            switch ($translation['name']) {
                case 'ListingSortRelevance':
                    $insert[7] = ['label' => $translation['value']];
                    break;
                case 'ListingSortRating':
                    $insert[2] = ['label' => $translation['value']];
                    break;
                case 'ListingSortRelease':
                    $insert[1] = ['label' => $translation['value']];
                    break;
                case 'ListingSortPriceHighest':
                    $insert[4] = ['label' => $translation['value']];
                    break;
                case 'ListingSortName':
                    $insert[5] = ['label' => $translation['value']];
                    break;
                case 'ListingSortPriceLowest':
                    $insert[3] = ['label' => $translation['value']];
                    break;
            }
        }
        return $insert;
    }

    private function importProductStreamSortings()
    {
        $streamSortings = $this->connection->query("SELECT id, name, sorting FROM s_product_streams WHERE sorting IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC);
        $newSortings = [];
        foreach ($streamSortings as $sorting) {
            $id = $this->getIdOfStreamSorting($sorting['sorting']);
            if ($id) {
                $this->addSql("UPDATE s_product_streams SET sorting_id = " . (int) $id . " WHERE id = " . (int) $sorting['id']);
                continue;
            }
            $key = md5($sorting['sorting']);
            $newSortings[$key] = $sorting;
        }

        foreach ($newSortings as $sorting) {
            $name = 'Stream import: ' . $sorting['name'] . ' [' . $sorting['id'] . ']';

            $this->addSql("
INSERT INTO `s_search_custom_sorting` (`label`, `active`, `display_in_categories`, `position`, `sortings`) VALUES
('".$name."', 1, 0, 0, '". str_replace("\\", "\\\\", $sorting['sorting']) ."');
            ");

            $this->addSql("UPDATE s_product_streams SET sorting_id = (SELECT id FROM s_search_custom_sorting WHERE name = '". $name ."' LIMIT 1) WHERE id  = " . (int) $sorting['id']);
        }
    }

    /**
     * @param string $sorting
     * @return int|null
     */
    private function getIdOfStreamSorting($sorting)
    {
        $sorting = json_decode($sorting, true);
        $classes = array_keys($sorting);
        $class = array_shift($classes);
        $parameters = [];
        if (!empty($sorting)) {
            $parameters = array_shift($sorting);
        }

        switch ($class) {
            case PopularitySorting::class:
                return 2;
            case PriceSorting::class:
                if ($parameters['direction'] == 'desc') {
                    return 4;
                }
                return 3;
            case ReleaseDateSorting::class:
                return 1;
            case ProductNameSorting::class:
                return 5;
            case SearchRankingSorting::class:
                return 7;
        }
        return null;
    }

    private function importSortingTranslations()
    {
        $shops = $this->connection->query("SELECT id, main_id, locale_id FROM s_core_shops")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($shops as $shop) {
            $translationShopId = $shop['main_id'] ?: $shop['id'];
            $localeId = $shop['locale_id'];

            $insert = $this->getExistingSortingTranslations($translationShopId, $localeId);

            if (!empty($insert)) {
                $this->addSql(
                    "INSERT IGNORE INTO s_core_translations (objecttype, objectdata, objectkey, objectlanguage)
                     VALUES ('custom_sorting', '" . serialize($insert) . "', '1', " . $shop['id'] . ")"
                );
            }
        }
    }

    private function moveCategoryDefaultSorting()
    {
        $this->addSql("SET @formId = (SELECT id FROM s_core_config_forms WHERE name = 'Frontend30' LIMIT 1)");
        $this->addSql("
            UPDATE s_core_config_elements
            SET form_id = @formId,
                `type` = 'custom-sorting-selection',
                 label = 'Kategorie Standard Sortierung',
                 `scope` = 1
            WHERE name = 'defaultListingSorting'
        ");

        $this->addSql("SET @elementId = (SELECT id FROM s_core_config_elements WHERE name = 'defaultListingSorting' LIMIT 1)");

        $this->addSql("
INSERT IGNORE INTO `s_core_config_element_translations` (`id` ,`element_id` ,`locale_id` ,`label` ,`description`)
VALUES (NULL,  @elementId,  '2',  'Default category sorting', NULL);
");
    }

    private function addSearchConfiguration()
    {
        $this->addSql("SET @formId = (SELECT id FROM s_core_config_forms WHERE name = 'Search' LIMIT 1);");

        $this->addSql("
INSERT INTO `s_core_config_elements` (`form_id`, `name`, `value`, `label`, `description`, `type`, `required`, `position`, `scope`, `options`)
VALUES (@formId, 'searchSortings', 's:13:\"|7|1|2|3|4|5|\";', 'Verfügbare Sortierungen', '', 'custom-sorting-grid', '1', '0', '1', NULL);
        ");

        $this->addSql("SET @elementId = (SELECT id FROM s_core_config_elements WHERE name = 'searchSortings' LIMIT 1)");
        $this->addSql("
INSERT IGNORE INTO `s_core_config_element_translations` (`id` ,`element_id` ,`locale_id` ,`label` ,`description`)
VALUES (NULL,  @elementId,  '2',  'Available sortings', NULL);
        ");
    }
}

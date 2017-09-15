<?php

class Migrations_Migration20170912151300 extends Shopware\Framework\Migration\AbstractMigration
{
    const OBJECTTYPE_ARTICLE = 'article';
    const OBJECTTYPE_VARIANT = 'variant';
    const OBJECTTYPE_MAIL = 'config_mails';
    const OBJECTTYPE_PAYMENT = 'config_payment';
    const OBJECTTYPE_PROPERTYGROUP = 'propertygroup';
    const OBJECTTYPE_PROPERTYOPTION = 'propertyoption';
    const OBJECTTYPE_PROPERTYVALUE = 'propertyvalue';

    public function up($modus): void
    {
        $translations = $this->getTranslations();

        foreach ($translations as $translation) {
            $objectData = $this->getUnserializedData($translation['objectdata']);

            if (empty($objectData)) {
                continue;
            }

            switch ($translation['objecttype']) {
                case self::OBJECTTYPE_ARTICLE:
                    $this->importProductTranslations($translation, $objectData);
                    break;
                case self::OBJECTTYPE_VARIANT:
                    $this->importProductVariantTranslations($translation, $objectData);
                    break;
                case self::OBJECTTYPE_MAIL:
                    $this->importMailTranslations($translation, $objectData);
                    break;
                case self::OBJECTTYPE_PAYMENT:
                    $this->importPaymentTranslations($translation, $objectData);
                    break;
                case self::OBJECTTYPE_PROPERTYGROUP:
                    $this->importFilters($translation, $objectData);
                    break;
                case self::OBJECTTYPE_PROPERTYOPTION:
                    $this->importFilterOptions($translation, $objectData);
                    break;
                case self::OBJECTTYPE_PROPERTYVALUE:
                    $this->importFilterOptionValues($translation, $objectData);
            }
        }
    }

    private function getTranslations(): array
    {
        $sql = <<<'SQL'
SELECT t.*
FROM s_core_translations t
WHERE t.objectlanguage != "en"
SQL;

        return $this->getConnection()->query($sql)->fetchAll();
    }

    private function importProductTranslations(array $translation, array $objectData): void
    {
        $translationData = [
            'txtArtikel' => '',
            'txtkeywords' => '',
            'txtshortdescription' => '',
            'txtlangbeschreibung' => '',
            'metaTitle' => '',
            'txtpackunit' => '',
        ];

        $translationData = array_merge($translationData, $objectData);
        $translationData = $this->fixQuotes($translationData);

        $sql = "INSERT IGNORE INTO `product_translation` (`product_uuid`, `language_uuid`, `name`, `keywords`, `description`, `description_long`, `meta_title`)
VALUES ('SWAG-PRODUCT-UUID-" . $translation['objectkey'] . "', 'SWAG-SHOP-UUID-" . $translation['objectlanguage'] ."', ". $translationData['txtArtikel'] .", ". $translationData['txtkeywords'] .", ". $translationData['txtshortdescription'] .", ". $translationData['txtlangbeschreibung'] .", ". $translationData['metaTitle'] .")";

        $this->addSql($sql);

        if (!empty($translationData['txtpackunit'])) {
            $sql = "INSERT IGNORE INTO `product_detail_translation` (product_detail_uuid, language_uuid, pack_unit) VALUES ((SELECT product_detail.uuid as product_detail_uuid FROM product_detail LEFT JOIN product ON product.id = product_detail.product_id WHERE product_detail.is_main = 1 AND product.id = '" . $translation['objectkey'] . "'), 'SWAG-SHOP-UUID-" . $translation['objectlanguage'] ."', ". $translationData['txtpackunit'] .")";

            $this->addSql($sql);
        }
    }

    private function importProductVariantTranslations(array $translation, array $objectData): void
    {
        $translationData = [
            'txtzusatztxt' => '',
            'txtpackunit' => '',
        ];

        $translationData = array_merge($translationData, $objectData);
        $translationData = $this->fixQuotes($translationData);

        $sql = "INSERT IGNORE INTO `product_detail_translation` (product_detail_uuid, language_uuid, additional_text, pack_unit) 
VALUES ((SELECT product_detail.uuid as product_detail_uuid FROM product_detail WHERE product_detail.id = '" . $translation['objectkey'] . "'), 'SWAG-SHOP-UUID-" . $translation['objectlanguage'] ."', ". $translationData['txtzusatztxt'] .", ". $translationData['txtpackunit'] .")";

        $this->addSql($sql);
    }

    private function importMailTranslations(array $translation, array $objectData): void
    {
        $translationData = [
            'fromMail' => '',
            'fromName' => '',
            'subject' => '',
            'content' => '',
            'contentHtml' => '',
        ];

        $translationData = array_merge($translationData, $objectData);
        $translationData = $this->fixQuotes($translationData);

        $sql = "INSERT IGNORE INTO `mail_translation` (mail_uuid, language_uuid, from_mail, from_name, subject, content, content_html)
VALUES ('SWAG-MAIL-UUID-" . $translation['objectkey'] . "', 'SWAG-SHOP-UUID-" . $translation['objectlanguage'] ."', ". $translationData['fromMail'] .", ". $translationData['fromName'] .", ". $translationData['subject'] .", ". $translationData['content'] .", ". $translationData['contentHtml'] .")";

        $this->addSql($sql);
    }

    private function importPaymentTranslations(array $translation, array $objectData): void
    {
        foreach ($objectData as $key => $paymentMethod) {
            $translationData = [
                'description' => '',
                'additionalDescription' => '',
            ];
            $paymentMethod = array_merge($translationData, $paymentMethod);
            $translationData = $this->fixQuotes($paymentMethod);

            $sql = "INSERT IGNORE INTO `payment_method_translation` (payment_method_uuid, language_uuid, description, additional_description)
VALUES ((SELECT uuid as payment_method_uuid FROM `payment_method` WHERE id = ". $key ."), 'SWAG-SHOP-UUID-" . $translation['objectlanguage'] ."', ". $translationData['description'] .", ". $translationData['additionalDescription'] .")";

            $this->addSql($sql);
        }
    }

    private function importFilters($translation, $objectData): void
    {
        $translationData = [
            'groupName' => '',
        ];

        $translationData = array_merge($translationData, $objectData);
        $translationData = $this->fixQuotes($translationData);

        $sql = "INSERT IGNORE INTO `filter_translation` (filter_uuid, language_uuid, name)
VALUES ('SWAG-FILTER-UUID-" . $translation['objectkey'] . "', 'SWAG-SHOP-UUID-" . $translation['objectlanguage'] ."', ". $translationData['groupName'] .")";

        $this->addSql($sql);
    }

    private function importFilterOptions($translation, $objectData): void
    {
        $translationData = [
            'optionName' => '',
        ];

        $translationData = array_merge($translationData, $objectData);
        $translationData = $this->fixQuotes($translationData);

        $sql = "INSERT IGNORE INTO `filter_option_translation` (filter_option_uuid, language_uuid, name)
VALUES ('SWAG-FILTER-OPTION-UUID-" . $translation['objectkey'] . "', 'SWAG-SHOP-UUID-" . $translation['objectlanguage'] ."', ". $translationData['optionName'] .")";

        $this->addSql($sql);
    }

    private function importFilterOptionValues($translation, $objectData): void
    {
        $translationData = [
            'optionValue' => '',
        ];

        $translationData = array_merge($translationData, $objectData);
        $translationData = $this->fixQuotes($translationData);

        $sql = "INSERT IGNORE INTO `filter_value_translation` (filter_value_uuid, language_uuid, value)
VALUES ('SWAG-FILTER-VALUE-UUID-" . $translation['objectkey'] . "', 'SWAG-SHOP-UUID-" . $translation['objectlanguage'] ."', ". $translationData['optionValue'] .")";

        $this->addSql($sql);
    }

    /**
     * Try to repair broken serialized arrays
     *
     * @param string $serializedString
     * @return array
     */
    private function repairSerializedString(string $serializedString): array
    {
        $str = preg_replace('/^a:\d+:\{/', '', $serializedString);

        return $this->repairRecursive($str);
    }

    private function repairRecursive(&$stringPart): array
    {
        $fixedData = [];
        $index = null;
        $length = strlen($stringPart);
        $i = 0;

        while(strlen($stringPart)) {
            $i++;

            if ($i > $length) {
                break;
            }

            if (substr($stringPart, 0, 1) ==='}') {
                $stringPart = substr($stringPart, 1);

                return $fixedData;
            }

            $part = substr($stringPart, 0, 2);
            switch($part) {
                case 's:':
                    $re = '/^s:\d+:"([^\"]*)";/';
                    if (preg_match($re, $stringPart, $m)) {
                        if ($index === null) {
                            $index = $m[1];
                        } else {
                            $fixedData[$index] = $m[1];
                            $index = null;
                        }
                        $stringPart = preg_replace($re, '', $stringPart);
                    }
                    break;
                case 'i:':
                    $re = '/^i:(\d+);/';
                    if (preg_match($re, $stringPart, $m)) {
                        if ($index === null) {
                            $index = (int) $m[1];
                        } else {
                            $fixedData[$index] = (int) $m[1];
                            $index = null;
                        }
                        $stringPart = preg_replace($re, '', $stringPart);
                    }
                    break;
                case 'b:':
                    $re = '/^b:[01];/';
                    if (preg_match($re, $stringPart, $m)) {
                        $fixedData[$index] = (bool) $m[1];
                        $index = null;
                        $stringPart = preg_replace($re, '', $stringPart);
                    }
                    break;
                case 'a:':
                    $re = '/^a:\d+:\{/';
                    if (preg_match($re, $stringPart, $m)) {
                        $stringPart = preg_replace('/^a:\d+:\{/', '', $stringPart);
                        $fixedData[$index] = $this->repairRecursive($stringPart);
                        $index = null;
                    }
                    break;
                case 'N;':
                    $stringPart = substr($stringPart, 2);
                    $fixedData[$index] = null;
                    $index = null;
                    break;
            }
        }

        return $fixedData;
    }

    private function getUnserializedData(string $objectdata): array
    {
        $unserialized = @unserialize($objectdata, []);

        if (false === $unserialized || !is_array($unserialized)) {
            $unserialized = $this->repairSerializedString($objectdata);
        }

        return $unserialized;
    }

    private function fixQuotes(array $translationData): array
    {
        return array_map(function($value) {
            return $this->connection->quote($value);
        }, $translationData);
    }
}

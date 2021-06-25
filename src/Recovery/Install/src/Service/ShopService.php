<?php declare(strict_types=1);

namespace Shopware\Recovery\Install\Service;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Recovery\Common\Service\SystemConfigService;
use Shopware\Recovery\Exception\LanguageNotFoundException;
use Shopware\Recovery\Install\Struct\Shop;

class ShopService
{
    /**
     * @var \PDO
     */
    private $connection;

    /**
     * @var SystemConfigService
     */
    private $systemConfig;

    public function __construct(\PDO $connection, SystemConfigService $systemConfig)
    {
        $this->connection = $connection;
        $this->systemConfig = $systemConfig;
    }

    public function updateShop(Shop $shop): void
    {
        if (empty($shop->locale) || empty($shop->host)) {
            throw new \RuntimeException('Please fill in all required fields. (shop configuration)');
        }

        try {
            $this->updateBasicInformation($shop);
            $this->setDefaultLanguage($shop);
            $this->setDefaultCurrency($shop);

            $this->deleteAllSalesChannelCurrencies();

            $this->createSalesChannel($shop);
            $this->createSalesChannelDomain($shop);
            $this->updateSalesChannelName($shop);
        } catch (\Exception $e) {
            throw new \RuntimeException($e->getMessage(), 0, $e);
        }
    }

    protected function getFirstActiveShippingMethodId(): string
    {
        return $this->connection
            ->query('SELECT id FROM shipping_method WHERE `active` = 1')
            ->fetchColumn();
    }

    protected function getFirstActivePaymentMethodId(): string
    {
        return $this->connection
            ->query('SELECT id FROM payment_method WHERE `active` = 1 ORDER BY position')
            ->fetchColumn();
    }

    private function setDefaultCurrency(Shop $shop): void
    {
        $stmt = $this->connection->prepare('SELECT iso_code FROM currency WHERE id = ?');
        $stmt->execute([Uuid::fromHexToBytes(Defaults::CURRENCY)]);
        $currentCurrencyIso = $stmt->fetchColumn();

        if (!$currentCurrencyIso) {
            throw new \RuntimeException('Default currency not found');
        }

        if (mb_strtoupper($currentCurrencyIso) === mb_strtoupper($shop->currency)) {
            return;
        }

        $newDefaultCurrencyId = $this->getCurrencyId($shop->currency);

        $stmt = $this->connection->prepare('UPDATE currency SET id = :newId WHERE id = :oldId');

        // assign new uuid to old DEFAULT
        $stmt->execute([
            'newId' => Uuid::randomBytes(),
            'oldId' => Uuid::fromHexToBytes(Defaults::CURRENCY),
        ]);

        // change id to DEFAULT
        $stmt->execute([
            'newId' => Uuid::fromHexToBytes(Defaults::CURRENCY),
            'oldId' => $newDefaultCurrencyId,
        ]);

        $stmt = $this->connection->prepare(
            'SET @fixFactor = (SELECT 1/factor FROM currency WHERE iso_code = :newDefault);
             UPDATE currency
             SET factor = IF(iso_code = :newDefault, 1, factor * @fixFactor);'
        );
        $stmt->execute(['newDefault' => $shop->currency]);
    }

    private function getLocaleTranslations(string $localeId): array
    {
        $stmt = $this->connection->prepare(
            'SELECT locale_id, language_id, name, territory
             FROM locale_translation
             WHERE locale_id = :locale_id'
        );
        $stmt->execute(['locale_id' => $localeId]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function swapDefaultLanguageId(string $newLanguageId): void
    {
        $stmt = $this->connection->prepare(
            'UPDATE language
             SET id = :newId
             WHERE id = :oldId'
        );

        // assign new uuid to old DEFAULT
        $stmt->execute([
            'newId' => Uuid::randomBytes(),
            'oldId' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
        ]);

        // change id to DEFAULT
        $stmt->execute([
            'newId' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            'oldId' => $newLanguageId,
        ]);
    }

    private function setDefaultLanguage(Shop $shop): void
    {
        $currentLocaleStmt = $this->connection->prepare(
            'SELECT locale.id, locale.code
             FROM language
             INNER JOIN locale ON translation_code_id = locale.id
             WHERE language.id = ?'
        );
        $currentLocaleStmt->execute([Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)]);
        $currentLocale = $currentLocaleStmt->fetch(\PDO::FETCH_ASSOC);

        if (!$currentLocale) {
            throw new \RuntimeException('Default language locale not found');
        }

        $currentLocaleId = $currentLocale['id'];
        $newDefaultLocaleId = $this->getLocaleId($shop->locale);

        // locales match -> do nothing.
        if ($currentLocaleId === $newDefaultLocaleId) {
            return;
        }

        $newDefaultLanguageId = $this->getLanguageId($shop->locale);

        if (!$newDefaultLanguageId) {
            $newDefaultLanguageId = $this->createNewLanguageEntry($shop->locale);
        }

        if ($shop->locale === 'de-DE' && $currentLocale['code'] === 'en-GB') {
            $this->swapDefaultLanguageId($newDefaultLanguageId);
        } else {
            $this->changeDefaultLanguageData($newDefaultLanguageId, $currentLocale, $shop);
        }
    }

    private function getSalesChannelAccessKey(): string
    {
        return 'SWSC' . mb_strtoupper(str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(random_bytes(16))));
    }

    private function changeDefaultLanguageData(string $newDefaultLanguageId, array $currentLocaleData, Shop $shop): void
    {
        $enGbLanguageId = $this->getLanguageId('en-GB');
        $currentLocaleId = $currentLocaleData['id'];
        $name = $shop->locale;

        $newDefaultLocaleId = $this->getLocaleId($shop->locale);

        if (!$newDefaultLanguageId && $enGbLanguageId) {
            $stmt = $this->connection->prepare(
                'SELECT name FROM locale_translation
                 WHERE language_id = :language_id
                 AND locale_id = :locale_id'
            );
            $stmt->execute(['language_id' => $enGbLanguageId, 'locale_id' => $newDefaultLocaleId]);
            $name = $stmt->fetchColumn();
        }

        // swap locale.code
        $stmt = $this->connection->prepare(
            'UPDATE locale SET code = :code WHERE id = :locale_id'
        );
        $stmt->execute(['code' => 'x-' . $shop->locale . '_tmp', 'locale_id' => $currentLocaleId]);
        $stmt->execute(['code' => $currentLocaleData['code'], 'locale_id' => $newDefaultLocaleId]);
        $stmt->execute(['code' => $shop->locale, 'locale_id' => $currentLocaleId]);

        // swap locale_translation.{name,territory}
        $setTrans = $this->connection->prepare(
            'UPDATE locale_translation
             SET name = :name, territory = :territory
             WHERE locale_id = :locale_id AND language_id = :language_id'
        );

        $currentTrans = $this->getLocaleTranslations($currentLocaleId);
        $newDefTrans = $this->getLocaleTranslations($newDefaultLocaleId);

        foreach ($currentTrans as $trans) {
            $trans['locale_id'] = $newDefaultLocaleId;
            $setTrans->execute($trans);
        }
        foreach ($newDefTrans as $trans) {
            $trans['locale_id'] = $currentLocaleId;
            $setTrans->execute($trans);
        }

        $updLang = $this->connection->prepare('UPDATE language SET name = :name WHERE id = :language_id');

        // new default language does not exist -> just set to name
        if (!$newDefaultLanguageId) {
            $updLang->execute(['name' => $name, 'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)]);

            return;
        }

        $langName = $this->connection->prepare('SELECT name FROM language WHERE id = :language_id');
        $langName->execute(['language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)]);
        $current = $langName->fetchColumn();

        $langName->execute(['language_id' => $newDefaultLanguageId]);
        $new = $langName->fetchColumn();

        // swap name
        $updLang->execute(['name' => $new, 'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)]);
        $updLang->execute(['name' => $current, 'language_id' => $newDefaultLanguageId]);
    }

    private function createSalesChannel(Shop $shop): void
    {
        $shop->salesChannelId = Uuid::randomBytes();
        $id = $shop->salesChannelId;
        $typeId = Defaults::SALES_CHANNEL_TYPE_STOREFRONT;

        $paymentMethod = $this->getFirstActivePaymentMethodId();
        $shippingMethod = $this->getFirstActiveShippingMethodId();

        $languageId = $this->getLanguageId($shop->locale);

        $currencyId = $this->getCurrencyId($shop->currency);

        $countryId = $this->getCountryId($shop->country);

        $statement = $this->connection->prepare(
            'INSERT INTO sales_channel (
                 id,
                 type_id, access_key, navigation_category_id, navigation_category_version_id,
                 language_id, currency_id, payment_method_id,
                 shipping_method_id, country_id, customer_group_id, created_at
             ) VALUES (
                 ?,
                 UNHEX(?), ?, ?, UNHEX(?),
                 ?, ?, ?,
                 ?, ?, UNHEX(?), ?
             )'
        );
        $statement->execute([
            $id,
            $typeId, $this->getSalesChannelAccessKey(), $this->getRootCategoryId(), Defaults::LIVE_VERSION,
            $languageId, $currencyId, $paymentMethod,
            $shippingMethod, $countryId, Defaults::FALLBACK_CUSTOMER_GROUP, (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $statement = $this->connection->prepare(
            'INSERT INTO sales_channel_translation (sales_channel_id, language_id, `name`, created_at)
             VALUES (?, UNHEX(?), ?, ?)'
        );
        $statement->execute([$id, Defaults::LANGUAGE_SYSTEM, $shop->name, (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);

        $statement = $this->connection->prepare(
            'INSERT INTO sales_channel_language (sales_channel_id, language_id)
             VALUES (?, UNHEX(?))'
        );
        $statement->execute([$id, Defaults::LANGUAGE_SYSTEM]);

        $statement = $this->connection->prepare(
            'INSERT INTO sales_channel_currency (sales_channel_id, currency_id)
             VALUES (?, ?)'
        );
        $statement->execute([$id, $currencyId]);

        $statement = $this->connection->prepare(
            'INSERT INTO sales_channel_payment_method (sales_channel_id, payment_method_id)
             VALUES (?, ?)'
        );
        $statement->execute([$id, $paymentMethod]);

        $statement = $this->connection->prepare(
            'INSERT INTO sales_channel_shipping_method (sales_channel_id, shipping_method_id)
             VALUES (?, ?)'
        );
        $statement->execute([$id, $shippingMethod]);

        $statement = $this->connection->prepare(
            'INSERT INTO sales_channel_country (sales_channel_id, country_id)
             VALUES (?, ?)'
        );
        $statement->execute([$id, $countryId]);

        $this->addAdditionalCurrenciesToSalesChannel($shop, $id);
        $this->removeUnwantedCurrencies($shop);
    }

    private function getRootCategoryId(): string
    {
        return $this->connection
            ->query('SELECT id FROM category WHERE `active` = 1 AND parent_id IS NULL')
            ->fetchColumn();
    }

    private function createSalesChannelDomain(Shop $shop): void
    {
        $languageId = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        $snippetSetId = $this->getSnippetSet($shop->locale)
            ?? $this->getSnippetSet('en-GB');
        $currencyId = $this->getCurrencyId($shop->currency);

        $insertSql = <<<'SQL'
INSERT INTO sales_channel_domain (id, sales_channel_id, language_id, url, currency_id, snippet_set_id, custom_fields, created_at, updated_at)
VALUES (:id, :salesChannelId, :languageId, :url, :currencyId, :snippetSetId, NULL, :createdAt, null)
SQL;

        $insertSalesChannel = $this->connection->prepare($insertSql);

        $insertSalesChannel->execute([
            'id' => Uuid::randomBytes(),
            'salesChannelId' => $shop->salesChannelId,
            'languageId' => $languageId,
            'url' => 'http://' . $shop->host . $shop->basePath,
            'currencyId' => $currencyId,
            'snippetSetId' => $snippetSetId,
            'createdAt' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $insertSalesChannel->execute([
            'id' => Uuid::randomBytes(),
            'salesChannelId' => $shop->salesChannelId,
            'languageId' => $languageId,
            'url' => 'https://' . $shop->host . $shop->basePath,
            'currencyId' => $currencyId,
            'snippetSetId' => $snippetSetId,
            'createdAt' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function updateSalesChannelName(Shop $shop): void
    {
        $prepareStmt = $this->connection->prepare('UPDATE sales_channel_translation SET name = ? WHERE name = "Storefront"');
        $prepareStmt->execute([$shop->name]);
    }

    private function setSystemConfig(string $key, $value): void
    {
        $value = json_encode(['_value' => $value], \JSON_UNESCAPED_UNICODE | \JSON_PRESERVE_ZERO_FRACTION);

        $stmt = $this->connection->prepare('SELECT id FROM `system_config` WHERE configuration_key = ?');
        $stmt->execute([$key]);
        $id = $stmt->fetchColumn() ?: null;
        if ($id) {
            $prepareStmt = $this->connection->prepare(
                'UPDATE system_config
                 SET configuration_value = ?
                 WHERE id = ?'
            );
            $prepareStmt->execute([$value, $id]);

            return;
        }

        $id = Uuid::randomBytes();

        $prepareStmt = $this->connection->prepare(
            'INSERT INTO system_config (id, configuration_key, configuration_value, sales_channel_id)
             VALUES (?, ?, ?, NULL)'
        );
        $prepareStmt->execute([$id, $key, $value]);
    }

    private function updateBasicInformation(Shop $shop): void
    {
        $this->systemConfig->set('core.basicInformation.shopName', $shop->name);
        $this->systemConfig->set('core.basicInformation.email', $shop->email);
    }

    private function getLanguageId(string $iso): ?string
    {
        $stmt = $this->connection->prepare(
            'SELECT language.id
             FROM `language`
             INNER JOIN locale ON locale.id = language.translation_code_id
             WHERE LOWER(locale.code) = LOWER(?)'
        );
        $stmt->execute([$iso]);

        return $stmt->fetchColumn() ?: null;
    }

    private function getLocaleId(string $iso): string
    {
        $stmt = $this->connection->prepare('SELECT locale.id FROM  locale WHERE LOWER(locale.code) = LOWER(?)');
        $stmt->execute([$iso]);
        $id = $stmt->fetchColumn();

        if (!$id) {
            throw new \RuntimeException('Locale with iso-code ' . $iso . ' not found');
        }

        return (string) $id;
    }

    private function getCurrencyId(string $currencyName): string
    {
        $stmt = $this->connection->prepare(
            'SELECT id FROM currency WHERE LOWER(iso_code) = LOWER(?)'
        );
        $stmt->execute([$currencyName]);
        $fetchCurrencyId = $stmt->fetchColumn();

        if (!$fetchCurrencyId) {
            throw new \RuntimeException('Currency with iso-code ' . $currencyName . ' not found');
        }

        return (string) $fetchCurrencyId;
    }

    private function getSnippetSet(string $iso): ?string
    {
        $stmt = $this->connection->prepare(
            'SELECT id FROM snippet_set WHERE LOWER(iso) = LOWER(?)'
        );
        $stmt->execute([$iso]);

        return $stmt->fetchColumn() ?: null;
    }

    private function getCountryId(string $iso): string
    {
        $stmt = $this->connection->prepare(
            'SELECT id FROM country WHERE LOWER(iso3) = LOWER(?)'
        );
        $stmt->execute([$iso]);
        $fetchCountryId = $stmt->fetchColumn();
        if (!$fetchCountryId) {
            throw new \RuntimeException('Country with iso-code ' . $iso . ' not found');
        }

        return (string) $fetchCountryId;
    }

    private function createNewLanguageEntry(string $iso)
    {
        $id = Uuid::randomBytes();

        $stmt = $this->connection->prepare(
            '
            SELECT LOWER (HEX(locale.id))
            FROM `locale`
            WHERE LOWER(locale.code) = LOWER(?)'
        );
        $stmt->execute([$iso]);
        $localeId = $stmt->fetchColumn();

        $stmt = $this->connection->prepare(
            '
            SELECT LOWER(language.id)
            FROM `language`
            WHERE LOWER(language.name) = LOWER(?)'
        );
        $stmt->execute(['english']);
        $englishId = $stmt->fetchColumn();

        $stmt = $this->connection->prepare(
            '
            SELECT locale_translation.name
            FROM `locale_translation`
            WHERE LOWER(HEX(locale_id)) = ?
            AND LOWER(language_id) = ?'
        );
        //Always use the English name since we dont have the name in the language itself
        $stmt->execute([$localeId, $englishId]);
        $name = $stmt->fetchColumn();
        if (!$name) {
            throw new LanguageNotFoundException("locale_translation.name for iso: '" . $iso . "', localeId: '" . $localeId . "' not found!");
        }

        $stmt = $this->connection->prepare(
            '
            INSERT INTO `language`
            (id,name,locale_id,translation_code_id)
            VALUES
            (?,?,UNHEX(?),UNHEX(?))'
        );

        $stmt->execute([$id, $name, $localeId, $localeId]);

        return $id;
    }

    /**
     * get the id of the sales channel via the sales channel type id
     */
    private function getIdOfSalesChannelViaTypeId(string $typeId): string
    {
        $statement = $this->connection->prepare('SELECT id FROM sales_channel WHERE type_id = UNHEX(?)');
        $statement->execute([$typeId]);
        $salesChannelId = $statement->fetchColumn();

        return $salesChannelId;
    }

    private function addAdditionalCurrenciesToSalesChannel(Shop $shop, string $salesChannelId): void
    {
        $idOfHeadlessSalesChannel = $this->getIdOfSalesChannelViaTypeId(Defaults::SALES_CHANNEL_TYPE_API);

        // set the default currency of the headless sales channel
        $statement = $this->connection->prepare('UPDATE sales_channel SET currency_id = ? WHERE id = ?');
        $defaultCurrencyId = $this->getCurrencyId($shop->currency);
        $statement->execute([$defaultCurrencyId, $idOfHeadlessSalesChannel]);

        // remove all currencies from the headless sales channel, except the default currency
        $statement = $this->connection->prepare('DELETE FROM sales_channel_currency WHERE sales_channel_id = ? AND currency_id != UNHEX(?)');
        $statement->execute([$idOfHeadlessSalesChannel, $defaultCurrencyId]);

        if ($shop->additionalCurrencies === null) {
            return;
        }

        $salesChannelsToBeEdited = [];
        $salesChannelsToBeEdited[] = $idOfHeadlessSalesChannel;
        $salesChannelsToBeEdited[] = $salesChannelId;

        // set the currencies of the headless sales channel to the ones from the default sales channel
        foreach ($salesChannelsToBeEdited as $currentSalesChannelId) {
            foreach ($shop->additionalCurrencies as $additionalCurrency) {
                $currencyId = $this->getCurrencyId($additionalCurrency);

                // add additional currencies
                $statement = $this->connection->prepare('INSERT INTO sales_channel_currency (sales_channel_id, currency_id) VALUES (?, ?)');
                $statement->execute([$currentSalesChannelId, $currencyId]);
            }
        }
    }

    private function removeUnwantedCurrencies(Shop $shop): void
    {
        // change default currency for dummy sales channel domain to the default currency to avoid foreign key constraints
        $statement = $this->connection->prepare('UPDATE sales_channel_domain SET currency_id = ? WHERE 1');
        $statement->execute([$this->getCurrencyId($shop->currency)]);

        // remove all currencies except the default currency when no additional currency is selected
        if ($shop->additionalCurrencies === null) {
            $statement = $this->connection->prepare('DELETE FROM currency WHERE iso_code != ?');
            $statement->execute([$shop->currency]);

            return;
        }

        $selectedCurrencies = $shop->additionalCurrencies;
        $selectedCurrencies[] = $shop->currency;

        $inputParameters = str_repeat('?,', \count($shop->additionalCurrencies) - 1) . '?';
        $statement = $this->connection->prepare('DELETE FROM currency WHERE iso_code NOT IN (' . $inputParameters . ', ?)');
        $statement->execute($selectedCurrencies);
    }

    private function deleteAllSalesChannelCurrencies(): void
    {
        $this->connection->exec('DELETE FROM sales_channel_currency');
    }
}

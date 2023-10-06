<?php declare(strict_types=1);

namespace Shopware\Core\Installer\Configuration;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Installer\Controller\ShopConfigurationController;
use Shopware\Core\Maintenance\System\Service\ShopConfigurator;

/**
 * @internal
 *
 * @codeCoverageIgnore - Is tested by integration test, does not make sense to unit test
 * as the sole purpose of this class is to configure the DB according to the configuration
 *
 * @phpstan-import-type Shop from ShopConfigurationController
 */
#[Package('core')]
class ShopConfigurationService
{
    /**
     * @param Shop $shop
     */
    public function updateShop(array $shop, Connection $connection): void
    {
        if (empty($shop['locale']) || empty($shop['host'])) {
            throw new \RuntimeException('Please fill in all required fields. (shop configuration)');
        }

        $shopConfigurator = new ShopConfigurator($connection);
        $shopConfigurator->updateBasicInformation($shop['name'], $shop['email']);
        $shopConfigurator->setDefaultLanguage($shop['locale']);
        $shopConfigurator->setDefaultCurrency($shop['currency']);

        $this->deleteAllSalesChannelCurrencies($connection);

        $newSalesChannelId = Uuid::randomBytes();
        $this->createSalesChannel($newSalesChannelId, $shop, $connection);
        $this->createSalesChannelDomain($newSalesChannelId, $shop, $connection);
    }

    /**
     * @param Shop $shop
     */
    private function createSalesChannel(string $newId, array $shop, Connection $connection): void
    {
        $typeId = Defaults::SALES_CHANNEL_TYPE_STOREFRONT;

        $paymentMethod = $this->getFirstActivePaymentMethodId($connection);
        $shippingMethod = $this->getFirstActiveShippingMethodId($connection);

        $languageId = $this->getLanguageId($shop['locale'], $connection);

        $currencyId = $this->getCurrencyId($shop['currency'], $connection);

        $countryId = $this->getCountryId($shop['country'], $connection);

        $statement = $connection->prepare(
            'INSERT INTO sales_channel (
                id,
                type_id, access_key, navigation_category_id, navigation_category_version_id,
                language_id, currency_id, payment_method_id,
                shipping_method_id, country_id, customer_group_id, created_at
            ) VALUES (
                ?,
                UNHEX(?), ?, ?, UNHEX(?),
                ?, ?, ?,
                ?, ?, ?, ?
            )'
        );
        $statement->executeStatement([
            $newId,
            $typeId,
            AccessKeyHelper::generateAccessKey('sales-channel'),
            $this->getRootCategoryId($connection), Defaults::LIVE_VERSION,
            $languageId,
            $currencyId,
            $paymentMethod,
            $shippingMethod,
            $countryId,
            $this->getCustomerGroupId($connection),
            (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $statement = $connection->prepare(
            'INSERT INTO sales_channel_translation (sales_channel_id, language_id, `name`, created_at)
             VALUES (?, UNHEX(?), ?, ?)'
        );
        $statement->executeStatement([$newId, Defaults::LANGUAGE_SYSTEM, $shop['name'], (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);

        $statement = $connection->prepare(
            'INSERT INTO sales_channel_language (sales_channel_id, language_id)
             VALUES (?, UNHEX(?))'
        );
        $statement->executeStatement([$newId, Defaults::LANGUAGE_SYSTEM]);

        $statement = $connection->prepare(
            'INSERT INTO sales_channel_currency (sales_channel_id, currency_id)
             VALUES (?, ?)'
        );
        $statement->executeStatement([$newId, $currencyId]);

        $statement = $connection->prepare(
            'INSERT INTO sales_channel_payment_method (sales_channel_id, payment_method_id)
             VALUES (?, ?)'
        );
        $statement->executeStatement([$newId, $paymentMethod]);

        $statement = $connection->prepare(
            'INSERT INTO sales_channel_shipping_method (sales_channel_id, shipping_method_id)
             VALUES (?, ?)'
        );
        $statement->executeStatement([$newId, $shippingMethod]);

        $statement = $connection->prepare(
            'INSERT INTO sales_channel_country (sales_channel_id, country_id)
             VALUES (?, ?)'
        );
        $statement->executeStatement([$newId, $countryId]);

        $this->addAdditionalCurrenciesToSalesChannel($shop, $newId, $connection);
        $this->removeUnwantedCurrencies($shop, $connection);
    }

    private function getRootCategoryId(Connection $connection): string
    {
        return $connection
            ->fetchOne('SELECT id FROM category WHERE `active` = 1 AND parent_id IS NULL');
    }

    /**
     * @param Shop $shop
     */
    private function createSalesChannelDomain(string $newId, array $shop, Connection $connection): void
    {
        $languageId = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        $snippetSetId = $this->getSnippetSet($shop['locale'], $connection)
            ?? $this->getSnippetSet('en-GB', $connection);
        $currencyId = $this->getCurrencyId($shop['currency'], $connection);

        $insertSql = <<<'SQL'
INSERT INTO sales_channel_domain (id, sales_channel_id, language_id, url, currency_id, snippet_set_id, custom_fields, created_at, updated_at)
VALUES (:id, :salesChannelId, :languageId, :url, :currencyId, :snippetSetId, NULL, :createdAt, null)
SQL;

        $insertSalesChannel = $connection->prepare($insertSql);

        $insertSalesChannel->executeStatement([
            'id' => Uuid::randomBytes(),
            'salesChannelId' => $newId,
            'languageId' => $languageId,
            'url' => 'http://' . $shop['host'] . $shop['basePath'],
            'currencyId' => $currencyId,
            'snippetSetId' => $snippetSetId,
            'createdAt' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $insertSalesChannel->executeStatement([
            'id' => Uuid::randomBytes(),
            'salesChannelId' => $newId,
            'languageId' => $languageId,
            'url' => 'https://' . $shop['host'] . $shop['basePath'],
            'currencyId' => $currencyId,
            'snippetSetId' => $snippetSetId,
            'createdAt' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function getFirstActiveShippingMethodId(Connection $connection): string
    {
        return $connection
            ->fetchOne('SELECT id FROM shipping_method WHERE `active` = 1 ORDER BY position');
    }

    private function getFirstActivePaymentMethodId(Connection $connection): string
    {
        return $connection
            ->fetchOne('SELECT id FROM payment_method WHERE `active` = 1 ORDER BY position');
    }

    private function getLanguageId(string $iso, Connection $connection): ?string
    {
        return $connection->fetchOne(
            'SELECT language.id
             FROM `language`
             INNER JOIN locale ON locale.id = language.translation_code_id
             WHERE LOWER(locale.code) = LOWER(?)',
            [$iso]
        ) ?: null;
    }

    private function getCurrencyId(string $currencyName, Connection $connection): string
    {
        $fetchCurrencyId = $connection->fetchOne(
            'SELECT id FROM currency WHERE LOWER(iso_code) = LOWER(?)',
            [$currencyName]
        );

        if (!$fetchCurrencyId) {
            throw new \RuntimeException('Currency with iso-code ' . $currencyName . ' not found');
        }

        return (string) $fetchCurrencyId;
    }

    private function getSnippetSet(string $iso, Connection $connection): ?string
    {
        return $connection->fetchOne(
            'SELECT id FROM snippet_set WHERE LOWER(iso) = LOWER(:iso)',
            ['iso' => $iso]
        ) ?: null;
    }

    private function getCountryId(string $iso, Connection $connection): string
    {
        $fetchCountryId = $connection->fetchOne('SELECT id FROM country WHERE LOWER(iso3) = LOWER(?)', [$iso]);

        if (!$fetchCountryId) {
            throw new \RuntimeException('Country with iso-code ' . $iso . ' not found');
        }

        return $fetchCountryId;
    }

    /**
     * get the id of the sales channel via the sales channel type id
     */
    private function getIdOfSalesChannelViaTypeId(string $typeId, Connection $connection): string
    {
        return $connection->fetchOne('SELECT id FROM sales_channel WHERE type_id = UNHEX(?)', [$typeId]);
    }

    /**
     * @param Shop $shop
     */
    private function addAdditionalCurrenciesToSalesChannel(array $shop, string $salesChannelId, Connection $connection): void
    {
        $idOfHeadlessSalesChannel = $this->getIdOfSalesChannelViaTypeId(Defaults::SALES_CHANNEL_TYPE_API, $connection);

        // set the default currency of the headless sales channel
        $statement = $connection->prepare('UPDATE sales_channel SET currency_id = ? WHERE id = ?');
        $defaultCurrencyId = $this->getCurrencyId($shop['currency'], $connection);
        $statement->executeStatement([$defaultCurrencyId, $idOfHeadlessSalesChannel]);

        // remove all currencies from the headless sales channel, except the default currency
        $statement = $connection->prepare('DELETE FROM sales_channel_currency WHERE sales_channel_id = ? AND currency_id != UNHEX(?)');
        $statement->executeStatement([$idOfHeadlessSalesChannel, $defaultCurrencyId]);

        if ($shop['additionalCurrencies'] === null) {
            return;
        }

        $salesChannelsToBeEdited = [];
        $salesChannelsToBeEdited[] = $idOfHeadlessSalesChannel;
        $salesChannelsToBeEdited[] = $salesChannelId;

        // set the currencies of the headless sales channel to the ones from the default sales channel
        foreach ($salesChannelsToBeEdited as $currentSalesChannelId) {
            foreach ($shop['additionalCurrencies'] as $additionalCurrency) {
                $currencyId = $this->getCurrencyId($additionalCurrency, $connection);

                // add additional currencies
                $statement = $connection->prepare('INSERT INTO sales_channel_currency (sales_channel_id, currency_id) VALUES (?, ?)');
                $statement->executeStatement([$currentSalesChannelId, $currencyId]);
            }
        }
    }

    /**
     * @param Shop $shop
     */
    private function removeUnwantedCurrencies(array $shop, Connection $connection): void
    {
        // change default currency for dummy sales channel domain to the default currency to avoid foreign key constraints
        $connection->executeStatement(
            'UPDATE sales_channel_domain SET currency_id = :currencyId',
            ['currencyId' => $this->getCurrencyId($shop['currency'], $connection)]
        );

        // remove all currencies except the default currency when no additional currency is selected
        if ($shop['additionalCurrencies'] === null) {
            $connection->executeStatement(
                'DELETE FROM currency WHERE iso_code != :currency',
                ['currency' => $shop['currency']]
            );

            return;
        }

        $selectedCurrencies = $shop['additionalCurrencies'];
        $selectedCurrencies[] = $shop['currency'];

        $connection->executeStatement(
            'DELETE FROM currency WHERE iso_code NOT IN (:currencies)',
            ['currencies' => array_unique($selectedCurrencies)],
            ['currencies' => ArrayParameterType::STRING]
        );
    }

    private function deleteAllSalesChannelCurrencies(Connection $connection): void
    {
        $connection->executeStatement('DELETE FROM sales_channel_currency');
    }

    private function getCustomerGroupId(Connection $connection): string
    {
        return $connection->fetchOne('SELECT customer_group_id FROM sales_channel');
    }
}

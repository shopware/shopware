<?php declare(strict_types=1);

namespace Shopware\Recovery\Install\Service;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Maintenance\SalesChannel\Service\SalesChannelCreator;
use Shopware\Core\Maintenance\System\Service\ShopConfigurator;
use Shopware\Recovery\Install\Struct\Shop;

class ShopService
{
    private Connection $connection;

    private ShopConfigurator $shopConfigurator;

    private SalesChannelCreator $salesChannelCreator;

    public function __construct(Connection $connection, ShopConfigurator $shopConfigurator, SalesChannelCreator $salesChannelCreator)
    {
        $this->connection = $connection;
        $this->shopConfigurator = $shopConfigurator;
        $this->salesChannelCreator = $salesChannelCreator;
    }

    public function updateShop(Shop $shop): void
    {
        if (empty($shop->locale) || empty($shop->host)) {
            throw new \RuntimeException('Please fill in all required fields. (shop configuration)');
        }

        try {
            $this->shopConfigurator->updateBasicInformation($shop->name, $shop->email);
            $this->shopConfigurator->setDefaultLanguage($shop->locale);
            $this->shopConfigurator->setDefaultCurrency($shop->currency);

            $this->deleteAllSalesChannelCurrencies();

            $snippetSetId = $this->getSnippetSet($shop->locale)
                ?? $this->getSnippetSet('en-GB');
            $salesChannelOverwrites = [
                'domains' => [
                    [
                        'url' => 'http://' . $shop->host . $shop->basePath,
                        'languageId' => Defaults::LANGUAGE_SYSTEM,
                        'snippetSetId' => Uuid::fromBytesToHex($snippetSetId),
                        'currencyId' => Defaults::CURRENCY,
                    ],
                    [
                        'url' => 'https://' . $shop->host . $shop->basePath,
                        'languageId' => Defaults::LANGUAGE_SYSTEM,
                        'snippetSetId' => Uuid::fromBytesToHex($snippetSetId),
                        'currencyId' => Defaults::CURRENCY,
                    ],
                ],
            ];

            $salesChannelId = Uuid::randomHex();
            $this->salesChannelCreator->createSalesChannel(
                $salesChannelId,
                $shop->name,
                Defaults::SALES_CHANNEL_TYPE_STOREFRONT,
                Defaults::LANGUAGE_SYSTEM,
                Defaults::CURRENCY,
                null,
                null,
                $this->getCountryId($shop->country),
                null,
                null,
                [],
                [],
                [],
                [],
                [],
                $salesChannelOverwrites
            );

            $this->addAdditionalCurrenciesToSalesChannel($shop, Uuid::fromHexToBytes($salesChannelId));
            $this->removeUnwantedCurrencies($shop);
        } catch (\Exception $e) {
            throw new \RuntimeException($e->getMessage(), 0, $e);
        }
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
        return $this->connection->fetchOne(
            'SELECT id FROM snippet_set WHERE LOWER(iso) = LOWER(:iso)',
            ['iso' => $iso]
        ) ?: null;
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

        return Uuid::fromBytesToHex($fetchCountryId);
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
        $this->connection->executeStatement(
            'UPDATE sales_channel_domain SET currency_id = :currencyId',
            ['currencyId' => $this->getCurrencyId($shop->currency)]
        );

        // remove all currencies except the default currency when no additional currency is selected
        if ($shop->additionalCurrencies === null) {
            $this->connection->executeStatement(
                'DELETE FROM currency WHERE iso_code != :currency',
                ['currency' => $shop->currency]
            );

            return;
        }

        $selectedCurrencies = $shop->additionalCurrencies;
        $selectedCurrencies[] = $shop->currency;

        $this->connection->executeStatement(
            'DELETE FROM currency WHERE iso_code NOT IN (:currencies)',
            ['currencies' => array_unique($selectedCurrencies)],
            ['currencies' => Connection::PARAM_STR_ARRAY]
        );
    }

    private function deleteAllSalesChannelCurrencies(): void
    {
        $this->connection->executeStatement('DELETE FROM sales_channel_currency');
    }
}

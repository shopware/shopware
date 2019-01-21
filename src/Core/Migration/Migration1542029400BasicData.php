<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\FetchMode;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\DebitPayment;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\InvoicePayment;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\SEPAPayment;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Doctrine\MultiInsertQueryQueue;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Struct\Uuid;

class Migration1542029400BasicData extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1542029400;
    }

    public function update(Connection $connection): void
    {
        $hasData = $connection->executeQuery('SELECT 1 FROM `language` LIMIT 1')->fetch();
        if ($hasData) {
            return;
        }

        $this->createLanguage($connection);
        $this->createLocale($connection);

        $this->createCatalog($connection);
        $this->createCountry($connection);
        $this->createCurrency($connection);
        $this->createCustomerGroup($connection);
        $this->createPaymentMethod($connection);
        $this->createShippingMethod($connection);
        $this->createTax($connection);
        $this->createSalesChannelTypes($connection);
        $this->createSalesChannel($connection);
        $this->createProductManufacturer($connection);
        $this->createOrderState($connection);
        $this->createOrderTransactionState($connection);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }

    private function createLanguage(Connection $connection): void
    {
        $localeEn = Uuid::fromHexToBytes(Defaults::LOCALE_SYSTEM);
        $localeDe = uuid::fromHexToBytes(Defaults::LOCALE_SYSTEM_DE);
        $languageEn = uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        $languageDe = uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM_DE);

        // first locales
        $connection->insert('locale', ['id' => $localeEn, 'code' => 'en_GB', 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('locale', ['id' => $localeDe, 'code' => 'de_DE', 'created_at' => date(Defaults::DATE_FORMAT)]);

        // second languages
        $connection->insert('language', [
            'id' => $languageEn,
            'name' => 'English',
            'locale_id' => $localeEn,
            'created_at' => date(Defaults::DATE_FORMAT),
        ]);

        $connection->insert('language', [
            'id' => $languageDe,
            'name' => 'Deutsch',
            'locale_id' => $localeDe,
            'created_at' => date(Defaults::DATE_FORMAT),
        ]);

        // third translations
        $connection->insert('locale_translation', [
            'locale_id' => $localeEn,
            'language_id' => $languageEn,
            'name' => 'English',
            'territory' => 'United Kingdom',
            'created_at' => date(Defaults::DATE_FORMAT),
        ]);
        $connection->insert('locale_translation', [
            'locale_id' => $localeEn,
            'language_id' => $languageDe,
            'name' => 'Englisch',
            'territory' => 'Vereinigtes Königreich',
            'created_at' => date(Defaults::DATE_FORMAT),
        ]);
        $connection->insert('locale_translation', [
            'locale_id' => $localeDe,
            'language_id' => $languageEn,
            'name' => 'German',
            'territory' => 'Germany',
            'created_at' => date(Defaults::DATE_FORMAT),
        ]);
        $connection->insert('locale_translation', [
            'locale_id' => $localeDe,
            'language_id' => $languageDe,
            'name' => 'Deutsch',
            'territory' => 'Deutschland',
            'created_at' => date(Defaults::DATE_FORMAT),
        ]);
    }

    private function createLocale(Connection $connection): void
    {
        $localeData = include __DIR__ . '/../locales.php';

        $queue = new MultiInsertQueryQueue($connection);
        $languageEn = uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        $languageDe = uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM_DE);

        foreach ($localeData as $locale) {
            if (\in_array($locale['locale'], ['en_GB', 'de_DE'])) {
                continue;
            }

            $localeId = Uuid::uuid4()->getBytes();

            $queue->addInsert(
                'locale',
                ['id' => $localeId, 'code' => $locale['locale'], 'created_at' => date(Defaults::DATE_FORMAT)]
            );

            $queue->addInsert(
                'locale_translation',
                [
                    'locale_id' => $localeId,
                    'language_id' => $languageEn,
                    'created_at' => date(Defaults::DATE_FORMAT),
                    'name' => $locale['name']['en_GB'],
                    'territory' => $locale['territory']['en_GB'],
                ]
            );

            $queue->addInsert(
                'locale_translation',
                [
                    'locale_id' => $localeId,
                    'language_id' => $languageDe,
                    'created_at' => date(Defaults::DATE_FORMAT),
                    'name' => $locale['name']['de_DE'],
                    'territory' => $locale['territory']['de_DE'],
                ]
            );
        }

        $queue->execute();
    }

    private function createCatalog(Connection $connection): void
    {
        $catalogId = Uuid::fromHexToBytes(Defaults::CATALOG);
        $languageEN = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        $languageDE = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM_DE);

        $connection->insert('catalog', [
            'id' => $catalogId,
            'created_at' => date(Defaults::DATE_FORMAT),
        ]);

        $connection->insert('catalog_translation', [
            'catalog_id' => $catalogId,
            'language_id' => $languageEN,
            'name' => 'Default catalogue',
            'created_at' => date(Defaults::DATE_FORMAT),
        ]);

        $connection->insert('catalog_translation', [
            'catalog_id' => $catalogId,
            'language_id' => $languageDE,
            'name' => 'Standardkatalog',
            'created_at' => date(Defaults::DATE_FORMAT),
        ]);
    }

    private function createCountry(Connection $connection): void
    {
        $languageDE = function (string $countryId, string $name) {
            return [
                'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM_DE),
                'name' => $name,
                'country_id' => Uuid::fromHexToBytes($countryId),
                'created_at' => date(Defaults::DATE_FORMAT),
            ];
        };

        $languageEN = function (string $countryId, string $name) {
            return [
                'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
                'name' => $name,
                'country_id' => Uuid::fromHexToBytes($countryId),
                'created_at' => date(Defaults::DATE_FORMAT),
            ];
        };

        $connection->insert('country', ['id' => Uuid::fromHexToBytes(Defaults::COUNTRY), 'iso' => 'DE', 'position' => 1, 'iso3' => 'DEU', 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('country_translation', $languageDE(Defaults::COUNTRY, 'Deutschland'));
        $connection->insert('country_translation', $languageEN(Defaults::COUNTRY, 'Germany'));

        $connection->insert('country', ['id' => Uuid::fromHexToBytes('ffe61e1c99154f9597014a310ab5482d'), 'iso' => 'GR', 'position' => 10, 'iso3' => 'GRC', 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('country_translation', $languageEN('ffe61e1c99154f9597014a310ab5482d', 'Greece'));
        $connection->insert('country_translation', $languageDE('ffe61e1c99154f9597014a310ab5482d', 'Griechenland'));

        $connection->insert('country', ['id' => Uuid::fromHexToBytes('6c72828ec5e240588a35114cf1d4d5ef'), 'iso' => 'GB', 'position' => 10, 'iso3' => 'GBR', 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('country_translation', $languageEN('6c72828ec5e240588a35114cf1d4d5ef', 'Great Britain'));
        $connection->insert('country_translation', $languageDE('6c72828ec5e240588a35114cf1d4d5ef', 'Großbritannien'));

        $connection->insert('country', ['id' => Uuid::fromHexToBytes('584c3ff22f5644789705383bde891fc9'), 'iso' => 'IE', 'position' => 10, 'iso3' => 'IRL', 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('country_translation', $languageEN('584c3ff22f5644789705383bde891fc9', 'Ireland'));
        $connection->insert('country_translation', $languageDE('584c3ff22f5644789705383bde891fc9', 'Irland'));

        $connection->insert('country', ['id' => Uuid::fromHexToBytes('b72b9b7cd26b4a40a36f2e76a1bf42c1'), 'iso' => 'IS', 'position' => 10, 'iso3' => 'ISL', 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('country_translation', $languageEN('b72b9b7cd26b4a40a36f2e76a1bf42c1', 'Iceland'));
        $connection->insert('country_translation', $languageDE('b72b9b7cd26b4a40a36f2e76a1bf42c1', 'Island'));

        $connection->insert('country', ['id' => Uuid::fromHexToBytes('92ca022e9d28492e9ea173f279fa6755'), 'iso' => 'IT', 'position' => 10, 'active' => 1, 'iso3' => 'ITA', 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('country_translation', $languageEN('92ca022e9d28492e9ea173f279fa6755', 'Italy'));
        $connection->insert('country_translation', $languageDE('92ca022e9d28492e9ea173f279fa6755', 'Italien'));

        $connection->insert('country', ['id' => Uuid::fromHexToBytes('e130d974fd6c438485972fe00b5cd609'), 'iso' => 'JP', 'position' => 10, 'iso3' => 'JPN', 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('country_translation', $languageEN('e130d974fd6c438485972fe00b5cd609', 'Japan'));
        $connection->insert('country_translation', $languageDE('e130d974fd6c438485972fe00b5cd609', 'Japan'));

        $connection->insert('country', ['id' => Uuid::fromHexToBytes('a453634acb414768b2542ae9a57639b5'), 'iso' => 'CA', 'position' => 10, 'iso3' => 'CAN', 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('country_translation', $languageEN('a453634acb414768b2542ae9a57639b5', 'Canada'));
        $connection->insert('country_translation', $languageDE('a453634acb414768b2542ae9a57639b5', 'Kanada'));

        $connection->insert('country', ['id' => Uuid::fromHexToBytes('e5cbe4b2105843c3bdef2e9c03eccaae'), 'iso' => 'LU', 'position' => 10, 'iso3' => 'LUX', 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('country_translation', $languageEN('e5cbe4b2105843c3bdef2e9c03eccaae', 'Luxembourg'));
        $connection->insert('country_translation', $languageDE('e5cbe4b2105843c3bdef2e9c03eccaae', 'Luxemburg'));

        $connection->insert('country', ['id' => Uuid::fromHexToBytes('9deee5660fd1474fbecdf6fc1809add3'), 'iso' => 'NA', 'position' => 10, 'iso3' => 'NAM', 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('country_translation', $languageEN('9deee5660fd1474fbecdf6fc1809add3', 'Namibia'));
        $connection->insert('country_translation', $languageDE('9deee5660fd1474fbecdf6fc1809add3', 'Namibia'));

        $connection->insert('country', ['id' => Uuid::fromHexToBytes('04ed51ccbb2341bc9b352d78e64213fb'), 'iso' => 'NL', 'position' => 10, 'active' => 1, 'iso3' => 'NLD', 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('country_translation', $languageEN('04ed51ccbb2341bc9b352d78e64213fb', 'Netherlands'));
        $connection->insert('country_translation', $languageDE('04ed51ccbb2341bc9b352d78e64213fb', 'Niederlande'));

        $connection->insert('country', ['id' => Uuid::fromHexToBytes('e216449bd67646cc9176a1d57a2f8094'), 'iso' => 'NO', 'position' => 10, 'iso3' => 'NOR', 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('country_translation', $languageEN('e216449bd67646cc9176a1d57a2f8094', 'Norway'));
        $connection->insert('country_translation', $languageDE('e216449bd67646cc9176a1d57a2f8094', 'Norwegen'));

        $connection->insert('country', ['id' => Uuid::fromHexToBytes('c650574d63d34834b836d8e7f0339ca8'), 'iso' => 'AT', 'position' => 1, 'active' => 1, 'iso3' => 'AUT', 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('country_translation', $languageEN('c650574d63d34834b836d8e7f0339ca8', 'Austria'));
        $connection->insert('country_translation', $languageDE('c650574d63d34834b836d8e7f0339ca8', 'Österreich'));

        $connection->insert('country', ['id' => Uuid::fromHexToBytes('a40ed5b07bca4b06995a0a56b1170155'), 'iso' => 'PT', 'position' => 10, 'iso3' => 'PRT', 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('country_translation', $languageEN('a40ed5b07bca4b06995a0a56b1170155', 'Portugal'));
        $connection->insert('country_translation', $languageDE('a40ed5b07bca4b06995a0a56b1170155', 'Portugal'));

        $connection->insert('country', ['id' => Uuid::fromHexToBytes('f7b0810e24234ae397c769b260a02474'), 'iso' => 'SE', 'position' => 10, 'iso3' => 'SWE', 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('country_translation', $languageEN('f7b0810e24234ae397c769b260a02474', 'Sweden'));
        $connection->insert('country_translation', $languageDE('f7b0810e24234ae397c769b260a02474', 'Schweden'));

        $connection->insert('country', ['id' => Uuid::fromHexToBytes('4f52e121f6724b968c00d05829f9a38d'), 'iso' => 'CH', 'position' => 10, 'tax_free' => 1, 'active' => 1, 'iso3' => 'CHE', 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('country_translation', $languageEN('4f52e121f6724b968c00d05829f9a38d', 'Switzerland'));
        $connection->insert('country_translation', $languageDE('4f52e121f6724b968c00d05829f9a38d', 'Schweiz'));

        $connection->insert('country', ['id' => Uuid::fromHexToBytes('2aba3f2990e044c78bf53b87fb6c3af3'), 'iso' => 'ES', 'position' => 10, 'active' => 1, 'iso3' => 'ESP', 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('country_translation', $languageEN('2aba3f2990e044c78bf53b87fb6c3af3', 'Spain'));
        $connection->insert('country_translation', $languageDE('2aba3f2990e044c78bf53b87fb6c3af3', 'Spanien'));

        $connection->insert('country', ['id' => Uuid::fromHexToBytes('bdcb207c87ab4648b5acde9138f48894'), 'iso' => 'US', 'position' => 10, 'iso3' => 'USA', 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('country_translation', $languageEN('bdcb207c87ab4648b5acde9138f48894', 'USA'));
        $connection->insert('country_translation', $languageDE('bdcb207c87ab4648b5acde9138f48894', 'USA'));

        $connection->insert('country', ['id' => Uuid::fromHexToBytes('e163778197a24b61bd2ae72d006a6d3c'), 'iso' => 'LI', 'position' => 10, 'iso3' => 'LIE', 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('country_translation', $languageEN('e163778197a24b61bd2ae72d006a6d3c', 'Liechtenstein'));
        $connection->insert('country_translation', $languageDE('e163778197a24b61bd2ae72d006a6d3c', 'Liechtenstein'));

        $connection->insert('country', ['id' => Uuid::fromHexToBytes('448744a58b9f44e58c40804fef6520f8'), 'iso' => 'AE', 'position' => 10, 'active' => 1, 'iso3' => 'ARE', 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('country_translation', $languageEN('448744a58b9f44e58c40804fef6520f8', 'Arab Emirates'));
        $connection->insert('country_translation', $languageDE('448744a58b9f44e58c40804fef6520f8', 'Arabische Emirate'));

        $connection->insert('country', ['id' => Uuid::fromHexToBytes('259f7c2be0b44eb6a273a70ea6dd8029'), 'iso' => 'PL', 'position' => 10, 'iso3' => 'POL', 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('country_translation', $languageEN('259f7c2be0b44eb6a273a70ea6dd8029', 'Poland'));
        $connection->insert('country_translation', $languageDE('259f7c2be0b44eb6a273a70ea6dd8029', 'Polen'));

        $connection->insert('country', ['id' => Uuid::fromHexToBytes('d99834aefa4941b490dae37d0027f6bc'), 'iso' => 'HU', 'position' => 10, 'iso3' => 'HUN', 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('country_translation', $languageEN('d99834aefa4941b490dae37d0027f6bc', 'Hungary'));
        $connection->insert('country_translation', $languageDE('d99834aefa4941b490dae37d0027f6bc', 'Ungarn'));

        $connection->insert('country', ['id' => Uuid::fromHexToBytes('11cf2cdd303c41d7bf66808bfe7769a5'), 'iso' => 'TR', 'position' => 10, 'iso3' => 'TUR', 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('country_translation', $languageEN('11cf2cdd303c41d7bf66808bfe7769a5', 'Turkey'));
        $connection->insert('country_translation', $languageDE('11cf2cdd303c41d7bf66808bfe7769a5', 'Türkei'));

        $connection->insert('country', ['id' => Uuid::fromHexToBytes('b240408078894b0491634af5963f0c04'), 'iso' => 'CZ', 'position' => 10, 'iso3' => 'CZE', 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('country_translation', $languageEN('b240408078894b0491634af5963f0c04', 'Czech Republic'));
        $connection->insert('country_translation', $languageDE('b240408078894b0491634af5963f0c04', 'Tschechische Republik'));

        $connection->insert('country', ['id' => Uuid::fromHexToBytes('8021ae3dd9ec4675920c16152473e5cc'), 'iso' => 'SK', 'position' => 10, 'iso3' => 'SVK', 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('country_translation', $languageEN('8021ae3dd9ec4675920c16152473e5cc', 'Slovenia'));
        $connection->insert('country_translation', $languageDE('8021ae3dd9ec4675920c16152473e5cc', 'Slowenien'));

        $connection->insert('country', ['id' => Uuid::fromHexToBytes('1d56b07f6a5e4ee0a4e23abc06ba9b1e'), 'iso' => 'RO', 'position' => 10, 'iso3' => 'ROU', 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('country_translation', $languageEN('1d56b07f6a5e4ee0a4e23abc06ba9b1e', 'Romania'));
        $connection->insert('country_translation', $languageDE('1d56b07f6a5e4ee0a4e23abc06ba9b1e', 'Rumänien'));

        $connection->insert('country', ['id' => Uuid::fromHexToBytes('68fea9f12c9c46748382b1f48a32014f'), 'iso' => 'BR', 'position' => 10, 'iso3' => 'BRA', 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('country_translation', $languageEN('68fea9f12c9c46748382b1f48a32014f', 'Brazil'));
        $connection->insert('country_translation', $languageDE('68fea9f12c9c46748382b1f48a32014f', 'Brasilien'));

        $connection->insert('country', ['id' => Uuid::fromHexToBytes('1c91bf01a6a547a78497abf7b8e4e5db'), 'iso' => 'IL', 'position' => 10, 'iso3' => 'ISR', 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('country_translation', $languageEN('1c91bf01a6a547a78497abf7b8e4e5db', 'Isreal'));
        $connection->insert('country_translation', $languageDE('1c91bf01a6a547a78497abf7b8e4e5db', 'Isreal'));

        $connection->insert('country', ['id' => Uuid::fromHexToBytes('5a4aa22452e04acca23185d4d21bb3bf'), 'iso' => 'AU', 'position' => 10, 'active' => 1, 'iso3' => 'AUS', 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('country_translation', $languageEN('5a4aa22452e04acca23185d4d21bb3bf', 'Australia'));
        $connection->insert('country_translation', $languageDE('5a4aa22452e04acca23185d4d21bb3bf', 'Australien'));

        $connection->insert('country', ['id' => Uuid::fromHexToBytes('2e54611a053b4b19afccca547f50bf56'), 'iso' => 'BE', 'position' => 10, 'active' => 1, 'iso3' => 'BEL', 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('country_translation', $languageEN('2e54611a053b4b19afccca547f50bf56', 'Belgium'));
        $connection->insert('country_translation', $languageDE('2e54611a053b4b19afccca547f50bf56', 'Belgien'));

        $connection->insert('country', ['id' => Uuid::fromHexToBytes('1d7911d918714c3ea9f0d7339afd3d43'), 'iso' => 'DK', 'position' => 10, 'active' => 1, 'iso3' => 'DNK', 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('country_translation', $languageEN('1d7911d918714c3ea9f0d7339afd3d43', 'Denmark'));
        $connection->insert('country_translation', $languageDE('1d7911d918714c3ea9f0d7339afd3d43', 'Dänemark'));

        $connection->insert('country', ['id' => Uuid::fromHexToBytes('9d8661d69c10416c858dbf408ec2500a'), 'iso' => 'FI', 'position' => 10, 'active' => 1, 'iso3' => 'FIN', 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('country_translation', $languageEN('9d8661d69c10416c858dbf408ec2500a', 'Finland'));
        $connection->insert('country_translation', $languageDE('9d8661d69c10416c858dbf408ec2500a', 'Finnland'));

        $connection->insert('country', ['id' => Uuid::fromHexToBytes('e85c25e1cdfc4cd4af49d54c34aa3d25'), 'iso' => 'FR', 'position' => 10, 'iso3' => 'FRA', 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('country_translation', $languageEN('e85c25e1cdfc4cd4af49d54c34aa3d25', 'France'));
        $connection->insert('country_translation', $languageDE('e85c25e1cdfc4cd4af49d54c34aa3d25', 'Frankreich'));
    }

    private function createCurrency(Connection $connection): void
    {
        $EUR = Uuid::fromHexToBytes(Defaults::CURRENCY);
        $USD = Uuid::uuid4()->getBytes();
        $GBP = Uuid::uuid4()->getBytes();

        $languageEN = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);

        $connection->insert('currency', ['id' => $EUR, 'is_default' => 1, 'factor' => 1, 'symbol' => '€', 'placed_in_front' => 0, 'position' => 1, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('currency_translation', ['currency_id' => $EUR, 'language_id' => $languageEN, 'short_name' => 'EUR', 'name' => 'Euro', 'created_at' => date(Defaults::DATE_FORMAT)]);

        $connection->insert('currency', ['id' => $USD, 'is_default' => 0, 'factor' => 1.17085, 'symbol' => '$', 'placed_in_front' => 0, 'position' => 1, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('currency_translation', ['currency_id' => $USD, 'language_id' => $languageEN, 'short_name' => 'USD', 'name' => 'US-Dollar', 'created_at' => date(Defaults::DATE_FORMAT)]);

        $connection->insert('currency', ['id' => $GBP, 'is_default' => 0, 'factor' => 0.89157, 'symbol' => '£', 'placed_in_front' => 0, 'position' => 1, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('currency_translation', ['currency_id' => $GBP, 'language_id' => $languageEN, 'short_name' => 'GBP', 'name' => 'Pound', 'created_at' => date(Defaults::DATE_FORMAT)]);
    }

    private function createCustomerGroup(Connection $connection): void
    {
        $groupId = Uuid::fromHexToBytes(Defaults::FALLBACK_CUSTOMER_GROUP);
        $languageEN = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);

        $connection->insert('customer_group', ['id' => $groupId, 'display_gross' => 1, 'input_gross' => 1, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('customer_group_translation', ['customer_group_id' => $groupId, 'language_id' => $languageEN, 'name' => 'Standard customer group', 'created_at' => date(Defaults::DATE_FORMAT)]);
    }

    private function createPaymentMethod(Connection $connection): void
    {
        $languageEN = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);

        $debit = Uuid::fromHexToBytes(Defaults::PAYMENT_METHOD_DEBIT);
        $invoice = Uuid::fromHexToBytes(Defaults::PAYMENT_METHOD_INVOICE);
        $sepa = Uuid::fromHexToBytes(Defaults::PAYMENT_METHOD_SEPA);

        $connection->insert('payment_method', ['id' => $debit, 'technical_name' => 'debit', 'template' => 'debit.tpl', 'class' => DebitPayment::class, 'hide' => 0, 'percentage_surcharge' => -10, 'position' => 4, 'active' => 0, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('payment_method_translation', ['payment_method_id' => $debit, 'language_id' => $languageEN, 'name' => 'Direct Debit', 'additional_description' => 'Additional text', 'created_at' => date(Defaults::DATE_FORMAT)]);

        $connection->insert('payment_method', ['id' => $invoice, 'technical_name' => 'invoice', 'template' => 'invoice.tpl', 'class' => InvoicePayment::class, 'hide' => 0, 'position' => 5, 'active' => 1, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('payment_method_translation', ['payment_method_id' => $invoice, 'language_id' => $languageEN, 'name' => 'Invoice', 'additional_description' => 'Payment by invoice. Shopware provides automatic invoicing for all customers on orders after the first, in order to avoid defaults on payment.', 'created_at' => date(Defaults::DATE_FORMAT)]);

        $connection->insert('payment_method', ['id' => $sepa, 'technical_name' => 'sepa', 'template' => '@Checkout/frontend/sepa.html.twig', 'class' => SEPAPayment::class, 'hide' => 0, 'position' => 0, 'active' => 1, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('payment_method_translation', ['payment_method_id' => $sepa, 'language_id' => $languageEN, 'name' => 'SEPA direct debit', 'additional_description' => 'SEPA invoice', 'created_at' => date(Defaults::DATE_FORMAT)]);
    }

    private function createShippingMethod(Connection $connection): void
    {
        $standard = Uuid::fromHexToBytes(Defaults::SHIPPING_METHOD);
        $express = Uuid::uuid4()->getBytes();

        $languageEN = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);

        $connection->insert('shipping_method', ['id' => $standard, 'type' => 0, 'active' => 1, 'position' => 1, 'calculation' => 1, 'bind_shippingfree' => 0, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('shipping_method_translation', ['shipping_method_id' => $standard, 'language_id' => $languageEN, 'name' => 'Standard', 'created_at' => date(Defaults::DATE_FORMAT)]);

        $connection->insert('shipping_method', ['id' => $express, 'type' => 0, 'active' => 1, 'position' => 2, 'calculation' => 1, 'surcharge_calculation' => 5, 'bind_shippingfree' => 0, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('shipping_method_translation', ['shipping_method_id' => $express, 'language_id' => $languageEN, 'name' => 'Express', 'created_at' => date(Defaults::DATE_FORMAT)]);
    }

    private function createTax(Connection $connection): void
    {
        $tax19 = Uuid::uuid4()->getBytes();
        $tax7 = Uuid::uuid4()->getBytes();
        $tax20 = Uuid::uuid4()->getBytes();
        $tax5 = Uuid::uuid4()->getBytes();
        $tax0 = Uuid::uuid4()->getBytes();

        $connection->insert('tax', ['id' => $tax19, 'tax_rate' => 19, 'name' => '19%', 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('tax', ['id' => $tax7, 'tax_rate' => 7, 'name' => '7%', 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('tax', ['id' => $tax20, 'tax_rate' => 20, 'name' => '20%', 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('tax', ['id' => $tax5, 'tax_rate' => 5, 'name' => '5%', 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('tax', ['id' => $tax0, 'tax_rate' => 0, 'name' => '0%', 'created_at' => date(Defaults::DATE_FORMAT)]);
    }

    private function createSalesChannelTypes(Connection $connection): void
    {
        $languageEN = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);

        $storefront = Uuid::fromHexToBytes(Defaults::SALES_CHANNEL_STOREFRONT);
        $storefrontApi = Uuid::fromHexToBytes(Defaults::SALES_CHANNEL_STOREFRONT_API);

        $connection->insert('sales_channel_type', ['id' => $storefront, 'icon_name' => 'default-building-shop', 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('sales_channel_type_translation', ['sales_channel_type_id' => $storefront, 'language_id' => $languageEN, 'name' => 'Storefront', 'manufacturer' => 'shopware AG', 'description' => 'Default storefront sales channel', 'created_at' => date(Defaults::DATE_FORMAT)]);

        $connection->insert('sales_channel_type', ['id' => $storefrontApi, 'icon_name' => 'default-shopping-basket', 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('sales_channel_type_translation', ['sales_channel_type_id' => $storefrontApi, 'language_id' => $languageEN, 'name' => 'Storefront API', 'manufacturer' => 'shopware AG', 'description' => 'Default Storefront-API', 'created_at' => date(Defaults::DATE_FORMAT)]);
    }

    private function createProductManufacturer(Connection $connection): void
    {
        $id = Uuid::uuid4()->getBytes();
        $languageEN = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        $catalogId = Uuid::fromHexToBytes(Defaults::CATALOG);
        $versionId = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);

        $connection->insert('product_manufacturer', ['id' => $id, 'catalog_id' => $catalogId, 'version_id' => $versionId, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('product_manufacturer_translation', ['product_manufacturer_id' => $id, 'product_manufacturer_version_id' => $versionId, 'catalog_id' => $catalogId, 'language_id' => $languageEN, 'name' => 'shopware AG', 'created_at' => date(Defaults::DATE_FORMAT)]);
    }

    private function createSalesChannel(Connection $connection): void
    {
        $currencies = $connection->executeQuery('SELECT id FROM currency')->fetchAll(FetchMode::COLUMN);
        $languages = $connection->executeQuery('SELECT id FROM language')->fetchAll(FetchMode::COLUMN);
        $shippingMethods = $connection->executeQuery('SELECT id FROM shipping_method')->fetchAll(FetchMode::COLUMN);
        $paymentMethods = $connection->executeQuery('SELECT id FROM payment_method')->fetchAll(FetchMode::COLUMN);

        $id = Uuid::uuid4()->getBytes();
        $languageEN = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);

        $connection->insert('sales_channel', [
            'id' => $id,
            'type_id' => Uuid::fromHexToBytes(Defaults::SALES_CHANNEL_STOREFRONT_API),
            'access_key' => AccessKeyHelper::generateAccessKey('sales-channel'),
            'active' => 1,
            'tax_calculation_type' => 'vertical',
            'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            'currency_id' => Uuid::fromHexToBytes(Defaults::CURRENCY),
            'payment_method_id' => Uuid::fromHexToBytes(Defaults::PAYMENT_METHOD_INVOICE),
            'shipping_method_id' => Uuid::fromHexToBytes(Defaults::SHIPPING_METHOD),
            'country_id' => Uuid::fromHexToBytes(Defaults::COUNTRY),
            'created_at' => date(Defaults::DATE_FORMAT),
        ]);

        $connection->insert('sales_channel_translation', ['sales_channel_id' => $id, 'language_id' => $languageEN, 'name' => 'Storefront API', 'created_at' => date(Defaults::DATE_FORMAT)]);

        // catalog
        $connection->insert('sales_channel_catalog', ['sales_channel_id' => $id, 'catalog_id' => Uuid::fromHexToBytes(Defaults::CATALOG), 'created_at' => date(Defaults::DATE_FORMAT)]);

        // country
        $connection->insert('sales_channel_country', ['sales_channel_id' => $id, 'country_id' => Uuid::fromHexToBytes(Defaults::COUNTRY), 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('sales_channel_country', ['sales_channel_id' => $id, 'country_id' => Uuid::fromHexToBytes('6c72828ec5e240588a35114cf1d4d5ef'), 'created_at' => date(Defaults::DATE_FORMAT)]);

        // currency
        foreach ($currencies as $currency) {
            $connection->insert('sales_channel_currency', ['sales_channel_id' => $id, 'currency_id' => $currency, 'created_at' => date(Defaults::DATE_FORMAT)]);
        }

        // language
        foreach ($languages as $language) {
            $connection->insert('sales_channel_language', ['sales_channel_id' => $id, 'language_id' => $language, 'created_at' => date(Defaults::DATE_FORMAT)]);
        }

        // currency
        foreach ($shippingMethods as $shippingMethod) {
            $connection->insert('sales_channel_shipping_method', ['sales_channel_id' => $id, 'shipping_method_id' => $shippingMethod, 'created_at' => date(Defaults::DATE_FORMAT)]);
        }

        // currency
        foreach ($paymentMethods as $paymentMethod) {
            $connection->insert('sales_channel_payment_method', ['sales_channel_id' => $id, 'payment_method_id' => $paymentMethod, 'created_at' => date(Defaults::DATE_FORMAT)]);
        }
    }

    private function createOrderState(Connection $connection): void
    {
        $open = Uuid::fromHexToBytes(Defaults::ORDER_STATE_OPEN);
        $completed = Uuid::uuid4()->getBytes();
        $cancelled = Uuid::uuid4()->getBytes();
        $inProgress = Uuid::uuid4()->getBytes();
        $partiallyCompleted = Uuid::uuid4()->getBytes();
        $rejected = Uuid::uuid4()->getBytes();
        $readyForDelivery = Uuid::uuid4()->getBytes();
        $partiallyDelivered = Uuid::uuid4()->getBytes();
        $completelyDelivered = Uuid::uuid4()->getBytes();
        $clarificationRequired = Uuid::uuid4()->getBytes();

        $queue = new MultiInsertQueryQueue($connection);
        $versionId = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);
        $languageEN = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);

        $queue->addInsert('order_state', ['id' => $open, 'version_id' => $versionId, 'position' => 1, 'has_mail' => 0, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $queue->addInsert('order_state_translation', ['order_state_id' => $open, 'order_state_version_id' => $versionId, 'language_id' => $languageEN, 'description' => 'Open', 'created_at' => date(Defaults::DATE_FORMAT)]);

        $queue->addInsert('order_state', ['id' => $completed, 'version_id' => $versionId, 'position' => 2, 'has_mail' => 0, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $queue->addInsert('order_state_translation', ['order_state_id' => $completed, 'order_state_version_id' => $versionId, 'language_id' => $languageEN, 'description' => 'Completed', 'created_at' => date(Defaults::DATE_FORMAT)]);

        $queue->addInsert('order_state', ['id' => $cancelled, 'version_id' => $versionId, 'position' => 3, 'has_mail' => 0, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $queue->addInsert('order_state_translation', ['order_state_id' => $cancelled, 'order_state_version_id' => $versionId, 'language_id' => $languageEN, 'description' => 'Cancelled', 'created_at' => date(Defaults::DATE_FORMAT)]);

        $queue->addInsert('order_state', ['id' => $inProgress, 'version_id' => $versionId, 'position' => 4, 'has_mail' => 0, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $queue->addInsert('order_state_translation', ['order_state_id' => $inProgress, 'order_state_version_id' => $versionId, 'language_id' => $languageEN, 'description' => 'In progress', 'created_at' => date(Defaults::DATE_FORMAT)]);

        $queue->addInsert('order_state', ['id' => $partiallyCompleted, 'version_id' => $versionId, 'position' => 5, 'has_mail' => 0, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $queue->addInsert('order_state_translation', ['order_state_id' => $partiallyCompleted, 'order_state_version_id' => $versionId, 'language_id' => $languageEN, 'description' => 'Partially completed', 'created_at' => date(Defaults::DATE_FORMAT)]);

        $queue->addInsert('order_state', ['id' => $rejected, 'version_id' => $versionId, 'position' => 6, 'has_mail' => 0, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $queue->addInsert('order_state_translation', ['order_state_id' => $rejected, 'order_state_version_id' => $versionId, 'language_id' => $languageEN, 'description' => 'Cancelled (rejected)', 'created_at' => date(Defaults::DATE_FORMAT)]);

        $queue->addInsert('order_state', ['id' => $readyForDelivery, 'version_id' => $versionId, 'position' => 7, 'has_mail' => 0, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $queue->addInsert('order_state_translation', ['order_state_id' => $readyForDelivery, 'order_state_version_id' => $versionId, 'language_id' => $languageEN, 'description' => 'Ready for delivery', 'created_at' => date(Defaults::DATE_FORMAT)]);

        $queue->addInsert('order_state', ['id' => $partiallyDelivered, 'version_id' => $versionId, 'position' => 8, 'has_mail' => 0, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $queue->addInsert('order_state_translation', ['order_state_id' => $partiallyDelivered, 'order_state_version_id' => $versionId, 'language_id' => $languageEN, 'description' => 'Partially delivered', 'created_at' => date(Defaults::DATE_FORMAT)]);

        $queue->addInsert('order_state', ['id' => $completelyDelivered, 'version_id' => $versionId, 'position' => 9, 'has_mail' => 0, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $queue->addInsert('order_state_translation', ['order_state_id' => $completelyDelivered, 'order_state_version_id' => $versionId, 'language_id' => $languageEN, 'description' => 'Completely delivered', 'created_at' => date(Defaults::DATE_FORMAT)]);

        $queue->addInsert('order_state', ['id' => $clarificationRequired, 'version_id' => $versionId, 'position' => 10, 'has_mail' => 0, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $queue->addInsert('order_state_translation', ['order_state_id' => $clarificationRequired, 'order_state_version_id' => $versionId, 'language_id' => $languageEN, 'description' => 'Clarification required', 'created_at' => date(Defaults::DATE_FORMAT)]);

        $queue->execute();
    }

    private function createOrderTransactionState(Connection $connection): void
    {
        $completed = Uuid::fromHexToBytes(Defaults::ORDER_TRANSACTION_COMPLETED);
        $cancelled = Uuid::fromHexToBytes(Defaults::ORDER_TRANSACTION_FAILED);
        $open = Uuid::fromHexToBytes(Defaults::ORDER_TRANSACTION_OPEN);
        $partiallyInvoiced = Uuid::uuid4()->getBytes();
        $completelyInvoiced = Uuid::uuid4()->getBytes();
        $partiallyPaid = Uuid::uuid4()->getBytes();
        $completelyPaid = Uuid::uuid4()->getBytes();
        $firstReminder = Uuid::uuid4()->getBytes();
        $secondReminder = Uuid::uuid4()->getBytes();
        $thirdReminder = Uuid::uuid4()->getBytes();
        $encashment = Uuid::uuid4()->getBytes();
        $reserved = Uuid::uuid4()->getBytes();
        $delayed = Uuid::uuid4()->getBytes();
        $recrediting = Uuid::uuid4()->getBytes();
        $review = Uuid::uuid4()->getBytes();
        $noCredit = Uuid::uuid4()->getBytes();
        $creditPreliminarily = Uuid::uuid4()->getBytes();
        $creditAccepted = Uuid::uuid4()->getBytes();
        $paymentOrdered = Uuid::uuid4()->getBytes();
        $timeExtension = Uuid::uuid4()->getBytes();

        $queue = new MultiInsertQueryQueue($connection);
        $versionId = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);
        $languageEN = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);

        $queue->addInsert('order_transaction_state', ['id' => $completed, 'version_id' => $versionId, 'position' => 1, 'has_mail' => 0, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $queue->addInsert('order_transaction_state_translation', ['order_transaction_state_id' => $completed, 'order_transaction_state_version_id' => $versionId, 'language_id' => $languageEN, 'description' => 'Completed', 'created_at' => date(Defaults::DATE_FORMAT)]);

        $queue->addInsert('order_transaction_state', ['id' => $cancelled, 'version_id' => $versionId, 'position' => 2, 'has_mail' => 0, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $queue->addInsert('order_transaction_state_translation', ['order_transaction_state_id' => $cancelled, 'order_transaction_state_version_id' => $versionId, 'language_id' => $languageEN, 'description' => 'Cancelled', 'created_at' => date(Defaults::DATE_FORMAT)]);

        $queue->addInsert('order_transaction_state', ['id' => $open, 'version_id' => $versionId, 'position' => 3, 'has_mail' => 0, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $queue->addInsert('order_transaction_state_translation', ['order_transaction_state_id' => $open, 'order_transaction_state_version_id' => $versionId, 'language_id' => $languageEN, 'description' => 'Open', 'created_at' => date(Defaults::DATE_FORMAT)]);

        $queue->addInsert('order_transaction_state', ['id' => $partiallyInvoiced, 'version_id' => $versionId, 'position' => 4, 'has_mail' => 0, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $queue->addInsert('order_transaction_state_translation', ['order_transaction_state_id' => $partiallyInvoiced, 'order_transaction_state_version_id' => $versionId, 'language_id' => $languageEN, 'description' => 'Partially invoiced', 'created_at' => date(Defaults::DATE_FORMAT)]);

        $queue->addInsert('order_transaction_state', ['id' => $completelyInvoiced, 'version_id' => $versionId, 'position' => 5, 'has_mail' => 0, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $queue->addInsert('order_transaction_state_translation', ['order_transaction_state_id' => $completelyInvoiced, 'order_transaction_state_version_id' => $versionId, 'language_id' => $languageEN, 'description' => 'Completely invoiced', 'created_at' => date(Defaults::DATE_FORMAT)]);

        $queue->addInsert('order_transaction_state', ['id' => $partiallyPaid, 'version_id' => $versionId, 'position' => 6, 'has_mail' => 0, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $queue->addInsert('order_transaction_state_translation', ['order_transaction_state_id' => $partiallyPaid, 'order_transaction_state_version_id' => $versionId, 'language_id' => $languageEN, 'description' => 'Partially paid', 'created_at' => date(Defaults::DATE_FORMAT)]);

        $queue->addInsert('order_transaction_state', ['id' => $completelyPaid, 'version_id' => $versionId, 'position' => 7, 'has_mail' => 0, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $queue->addInsert('order_transaction_state_translation', ['order_transaction_state_id' => $completelyPaid, 'order_transaction_state_version_id' => $versionId, 'language_id' => $languageEN, 'description' => 'Completely paid', 'created_at' => date(Defaults::DATE_FORMAT)]);

        $queue->addInsert('order_transaction_state', ['id' => $firstReminder, 'version_id' => $versionId, 'position' => 8, 'has_mail' => 0, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $queue->addInsert('order_transaction_state_translation', ['order_transaction_state_id' => $firstReminder, 'order_transaction_state_version_id' => $versionId, 'language_id' => $languageEN, 'description' => '1st reminder', 'created_at' => date(Defaults::DATE_FORMAT)]);

        $queue->addInsert('order_transaction_state', ['id' => $secondReminder, 'version_id' => $versionId, 'position' => 9, 'has_mail' => 0, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $queue->addInsert('order_transaction_state_translation', ['order_transaction_state_id' => $secondReminder, 'order_transaction_state_version_id' => $versionId, 'language_id' => $languageEN, 'description' => '2nd reminder', 'created_at' => date(Defaults::DATE_FORMAT)]);

        $queue->addInsert('order_transaction_state', ['id' => $thirdReminder, 'version_id' => $versionId, 'position' => 10, 'has_mail' => 0, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $queue->addInsert('order_transaction_state_translation', ['order_transaction_state_id' => $thirdReminder, 'order_transaction_state_version_id' => $versionId, 'language_id' => $languageEN, 'description' => '3rd reminder', 'created_at' => date(Defaults::DATE_FORMAT)]);

        $queue->addInsert('order_transaction_state', ['id' => $encashment, 'version_id' => $versionId, 'position' => 11, 'has_mail' => 0, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $queue->addInsert('order_transaction_state_translation', ['order_transaction_state_id' => $encashment, 'order_transaction_state_version_id' => $versionId, 'language_id' => $languageEN, 'description' => 'Encashment', 'created_at' => date(Defaults::DATE_FORMAT)]);

        $queue->addInsert('order_transaction_state', ['id' => $reserved, 'version_id' => $versionId, 'position' => 12, 'has_mail' => 0, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $queue->addInsert('order_transaction_state_translation', ['order_transaction_state_id' => $reserved, 'order_transaction_state_version_id' => $versionId, 'language_id' => $languageEN, 'description' => 'Reserved', 'created_at' => date(Defaults::DATE_FORMAT)]);

        $queue->addInsert('order_transaction_state', ['id' => $delayed, 'version_id' => $versionId, 'position' => 13, 'has_mail' => 0, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $queue->addInsert('order_transaction_state_translation', ['order_transaction_state_id' => $delayed, 'order_transaction_state_version_id' => $versionId, 'language_id' => $languageEN, 'description' => 'Delayed', 'created_at' => date(Defaults::DATE_FORMAT)]);

        $queue->addInsert('order_transaction_state', ['id' => $recrediting, 'version_id' => $versionId, 'position' => 14, 'has_mail' => 0, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $queue->addInsert('order_transaction_state_translation', ['order_transaction_state_id' => $recrediting, 'order_transaction_state_version_id' => $versionId, 'language_id' => $languageEN, 'description' => 'Re-crediting', 'created_at' => date(Defaults::DATE_FORMAT)]);

        $queue->addInsert('order_transaction_state', ['id' => $review, 'version_id' => $versionId, 'position' => 15, 'has_mail' => 0, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $queue->addInsert('order_transaction_state_translation', ['order_transaction_state_id' => $review, 'order_transaction_state_version_id' => $versionId, 'language_id' => $languageEN, 'description' => 'Review necessary', 'created_at' => date(Defaults::DATE_FORMAT)]);

        $queue->addInsert('order_transaction_state', ['id' => $noCredit, 'version_id' => $versionId, 'position' => 16, 'has_mail' => 0, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $queue->addInsert('order_transaction_state_translation', ['order_transaction_state_id' => $noCredit, 'order_transaction_state_version_id' => $versionId, 'language_id' => $languageEN, 'description' => 'No credit approved', 'created_at' => date(Defaults::DATE_FORMAT)]);

        $queue->addInsert('order_transaction_state', ['id' => $creditPreliminarily, 'version_id' => $versionId, 'position' => 17, 'has_mail' => 0, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $queue->addInsert('order_transaction_state_translation', ['order_transaction_state_id' => $creditPreliminarily, 'order_transaction_state_version_id' => $versionId, 'language_id' => $languageEN, 'description' => 'Credit preliminarily accepted', 'created_at' => date(Defaults::DATE_FORMAT)]);

        $queue->addInsert('order_transaction_state', ['id' => $creditAccepted, 'version_id' => $versionId, 'position' => 18, 'has_mail' => 0, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $queue->addInsert('order_transaction_state_translation', ['order_transaction_state_id' => $creditAccepted, 'order_transaction_state_version_id' => $versionId, 'language_id' => $languageEN, 'description' => 'Credit accepted', 'created_at' => date(Defaults::DATE_FORMAT)]);

        $queue->addInsert('order_transaction_state', ['id' => $paymentOrdered, 'version_id' => $versionId, 'position' => 19, 'has_mail' => 0, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $queue->addInsert('order_transaction_state_translation', ['order_transaction_state_id' => $paymentOrdered, 'order_transaction_state_version_id' => $versionId, 'language_id' => $languageEN, 'description' => 'Payment ordered', 'created_at' => date(Defaults::DATE_FORMAT)]);

        $queue->addInsert('order_transaction_state', ['id' => $timeExtension, 'version_id' => $versionId, 'position' => 20, 'has_mail' => 0, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $queue->addInsert('order_transaction_state_translation', ['order_transaction_state_id' => $timeExtension, 'order_transaction_state_version_id' => $versionId, 'language_id' => $languageEN, 'description' => 'Time extension registered', 'created_at' => date(Defaults::DATE_FORMAT)]);

        $queue->execute();
    }
}

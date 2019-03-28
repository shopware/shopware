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
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\StateMachine\OrderDeliveryStateMachine;
use Shopware\Core\System\StateMachine\OrderStateMachine;
use Shopware\Core\System\StateMachine\OrderTransactionStateMachine;

class Migration1536233420BasicData extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233420;
    }

    public function update(Connection $connection): void
    {
        $hasData = $connection->executeQuery('SELECT 1 FROM `language` LIMIT 1')->fetch();
        if ($hasData) {
            return;
        }

        $this->createLanguage($connection);
        $this->createLocale($connection);

        $this->createCountry($connection);
        $this->createCurrency($connection);
        $this->createCustomerGroup($connection);
        $this->createPaymentMethod($connection);
        $this->createShippingMethod($connection);
        $this->createTax($connection);
        $this->createSalesChannelTypes($connection);
        $this->createSalesChannel($connection);
        $this->createProductManufacturer($connection);
        $this->createDefaultSnippetSets($connection);
        $this->createDefaultMediaFolders($connection);

        $this->createOrderStateMachine($connection);
        $this->createOrderDeliveryStateMachine($connection);
        $this->createOrderTransactionStateMachine($connection);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }

    private function createLanguage(Connection $connection): void
    {
        $localeEn = Uuid::fromHexToBytes(Defaults::LOCALE_SYSTEM);
        $localeDe = Uuid::fromHexToBytes(Defaults::LOCALE_SYSTEM_DE);
        $languageEn = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        $languageDe = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM_DE);

        // first locales
        $connection->insert('locale', ['id' => $localeEn, 'code' => 'en_GB', 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('locale', ['id' => $localeDe, 'code' => 'de_DE', 'created_at' => date(Defaults::DATE_FORMAT)]);

        // second languages
        $connection->insert('language', [
            'id' => $languageEn,
            'name' => 'English',
            'locale_id' => $localeEn,
            'translation_code_id' => $localeEn,
            'created_at' => date(Defaults::DATE_FORMAT),
        ]);

        $connection->insert('language', [
            'id' => $languageDe,
            'name' => 'Deutsch',
            'locale_id' => $localeDe,
            'translation_code_id' => $localeDe,
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
        $languageEn = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        $languageDe = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM_DE);

        foreach ($localeData as $locale) {
            if (\in_array($locale['locale'], ['en_GB', 'de_DE'], true)) {
                continue;
            }

            $localeId = Uuid::randomBytes();

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
        $USD = Uuid::randomBytes();
        $GBP = Uuid::randomBytes();

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

        $debit = Uuid::randomBytes();
        $invoice = Uuid::randomBytes();
        $sepa = Uuid::randomBytes();

        $connection->insert('payment_method', ['id' => $debit, 'technical_name' => 'debit', 'template' => 'debit.tpl', 'class' => DebitPayment::class, 'percentage_surcharge' => -10, 'position' => 4, 'active' => 0, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('payment_method_translation', ['payment_method_id' => $debit, 'language_id' => $languageEN, 'name' => 'Direct Debit', 'additional_description' => 'Additional text', 'created_at' => date(Defaults::DATE_FORMAT)]);

        $connection->insert('payment_method', ['id' => $invoice, 'technical_name' => 'invoice', 'template' => 'invoice.tpl', 'class' => InvoicePayment::class, 'position' => 5, 'active' => 1, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('payment_method_translation', ['payment_method_id' => $invoice, 'language_id' => $languageEN, 'name' => 'Invoice', 'additional_description' => 'Payment by invoice. Shopware provides automatic invoicing for all customers on orders after the first, in order to avoid defaults on payment.', 'created_at' => date(Defaults::DATE_FORMAT)]);

        $connection->insert('payment_method', ['id' => $sepa, 'technical_name' => 'sepa', 'template' => '@Checkout/frontend/sepa.html.twig', 'class' => SEPAPayment::class, 'position' => 0, 'active' => 1, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('payment_method_translation', ['payment_method_id' => $sepa, 'language_id' => $languageEN, 'name' => 'SEPA direct debit', 'additional_description' => 'SEPA invoice', 'created_at' => date(Defaults::DATE_FORMAT)]);
    }

    private function createShippingMethod(Connection $connection): void
    {
        $standard = Uuid::randomBytes();
        $express = Uuid::randomBytes();

        $languageEN = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);

        $connection->insert('shipping_method', ['id' => $standard, 'type' => 0, 'active' => 1, 'position' => 1, 'calculation' => 1, 'bind_shippingfree' => 0, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('shipping_method_translation', ['shipping_method_id' => $standard, 'language_id' => $languageEN, 'name' => 'Standard', 'created_at' => date(Defaults::DATE_FORMAT)]);

        $connection->insert('shipping_method', ['id' => $express, 'type' => 0, 'active' => 1, 'position' => 2, 'calculation' => 1, 'surcharge_calculation' => 5, 'bind_shippingfree' => 0, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('shipping_method_translation', ['shipping_method_id' => $express, 'language_id' => $languageEN, 'name' => 'Express', 'created_at' => date(Defaults::DATE_FORMAT)]);
    }

    private function createTax(Connection $connection): void
    {
        $tax19 = Uuid::randomBytes();
        $tax7 = Uuid::randomBytes();
        $tax20 = Uuid::randomBytes();
        $tax5 = Uuid::randomBytes();
        $tax0 = Uuid::randomBytes();

        $connection->insert('tax', ['id' => $tax19, 'tax_rate' => 19, 'name' => '19%', 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('tax', ['id' => $tax7, 'tax_rate' => 7, 'name' => '7%', 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('tax', ['id' => $tax20, 'tax_rate' => 20, 'name' => '20%', 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('tax', ['id' => $tax5, 'tax_rate' => 5, 'name' => '5%', 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('tax', ['id' => $tax0, 'tax_rate' => 1, 'name' => '1%', 'created_at' => date(Defaults::DATE_FORMAT)]);
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
        $id = Uuid::randomBytes();
        $languageEN = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        $versionId = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);

        $connection->insert('product_manufacturer', ['id' => $id, 'version_id' => $versionId, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('product_manufacturer_translation', ['product_manufacturer_id' => $id, 'product_manufacturer_version_id' => $versionId, 'language_id' => $languageEN, 'name' => 'shopware AG', 'created_at' => date(Defaults::DATE_FORMAT)]);
    }

    private function createSalesChannel(Connection $connection): void
    {
        $currencies = $connection->executeQuery('SELECT id FROM currency')->fetchAll(FetchMode::COLUMN);
        $languages = $connection->executeQuery('SELECT id FROM language')->fetchAll(FetchMode::COLUMN);
        $shippingMethods = $connection->executeQuery('SELECT id FROM shipping_method')->fetchAll(FetchMode::COLUMN);
        $paymentMethods = $connection->executeQuery('SELECT id FROM payment_method')->fetchAll(FetchMode::COLUMN);
        $defaultPaymentMethod = $connection->executeQuery('SELECT id FROM payment_method WHERE active = 1 ORDER BY `position`')->fetchColumn();
        $defaultShippingMethod = $connection->executeQuery('SELECT id FROM shipping_method WHERE active = 1 ORDER BY `position`')->fetchColumn();

        $id = Uuid::randomBytes();
        $languageEN = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);

        $connection->insert('sales_channel', [
            'id' => $id,
            'type_id' => Uuid::fromHexToBytes(Defaults::SALES_CHANNEL_STOREFRONT_API),
            'access_key' => AccessKeyHelper::generateAccessKey('sales-channel'),
            'active' => 1,
            'tax_calculation_type' => 'vertical',
            'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            'currency_id' => Uuid::fromHexToBytes(Defaults::CURRENCY),
            'payment_method_id' => $defaultPaymentMethod,
            'shipping_method_id' => $defaultShippingMethod,
            'country_id' => Uuid::fromHexToBytes(Defaults::COUNTRY),
            'created_at' => date(Defaults::DATE_FORMAT),
        ]);

        $connection->insert('sales_channel_translation', ['sales_channel_id' => $id, 'language_id' => $languageEN, 'name' => 'Storefront API', 'created_at' => date(Defaults::DATE_FORMAT)]);

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

    private function createDefaultSnippetSets(Connection $connection): void
    {
        $queue = new MultiInsertQueryQueue($connection);

        $queue->addInsert('snippet_set', ['id' => Uuid::fromHexToBytes(Defaults::SNIPPET_BASE_SET_DE), 'name' => 'BASE de_DE', 'base_file' => 'messages.de_DE', 'iso' => 'de_DE', 'created_at' => date(Defaults::DATE_FORMAT)]);
        $queue->addInsert('snippet_set', ['id' => Uuid::fromHexToBytes(Defaults::SNIPPET_BASE_SET_EN), 'name' => 'BASE en_GB', 'base_file' => 'messages.en_GB', 'iso' => 'en_GB', 'created_at' => date(Defaults::DATE_FORMAT)]);

        $queue->execute();
    }

    private function createDefaultMediaFolders(Connection $connection): void
    {
        $queue = new MultiInsertQueryQueue($connection);

        $queue->addInsert('media_default_folder', ['id' => Uuid::randomBytes(), 'association_fields' => '["productMedia"]', 'entity' => 'product', 'created_at' => date(Defaults::DATE_FORMAT)]);
        $queue->addInsert('media_default_folder', ['id' => Uuid::randomBytes(), 'association_fields' => '["productManufacturers"]', 'entity' => 'product_manufacturer', 'created_at' => date(Defaults::DATE_FORMAT)]);

        $queue->execute();
    }

    private function createOrderStateMachine(Connection $connection): void
    {
        $stateMachineId = Uuid::randomBytes();
        $openId = Uuid::randomBytes();
        $completedId = Uuid::randomBytes();
        $inProgressId = Uuid::randomBytes();
        $canceledId = Uuid::randomBytes();

        $germanId = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM_DE);
        $englishId = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);

        $translationDE = ['language_id' => $germanId, 'created_at' => date(Defaults::DATE_FORMAT)];
        $translationEN = ['language_id' => $englishId, 'created_at' => date(Defaults::DATE_FORMAT)];

        // state machine
        $connection->insert('state_machine', [
            'id' => $stateMachineId,
            'technical_name' => OrderStateMachine::NAME,
            'created_at' => date(Defaults::DATE_FORMAT),
        ]);

        $connection->insert('state_machine_translation', array_merge($translationDE, [
            'state_machine_id' => $stateMachineId,
            'name' => 'Bestellstatus',
            'created_at' => date(Defaults::DATE_FORMAT),
        ]));

        $connection->insert('state_machine_translation', array_merge($translationEN, [
            'state_machine_id' => $stateMachineId,
            'name' => 'Order state',
            'created_at' => date(Defaults::DATE_FORMAT),
        ]));

        // states
        $connection->insert('state_machine_state', ['id' => $openId, 'state_machine_id' => $stateMachineId, 'technical_name' => OrderStateMachine::STATE_OPEN, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $openId, 'name' => 'Offen']));
        $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $openId, 'name' => 'Open']));

        $connection->insert('state_machine_state', ['id' => $completedId, 'state_machine_id' => $stateMachineId, 'technical_name' => OrderStateMachine::STATE_COMPLETED, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $completedId, 'name' => 'Abgeschlossen']));
        $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $completedId, 'name' => 'Done']));

        $connection->insert('state_machine_state', ['id' => $inProgressId, 'state_machine_id' => $stateMachineId, 'technical_name' => OrderStateMachine::STATE_IN_PROGRESS, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $inProgressId, 'name' => 'In Bearbeitung']));
        $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $inProgressId, 'name' => 'In progress']));

        $connection->insert('state_machine_state', ['id' => $canceledId, 'state_machine_id' => $stateMachineId, 'technical_name' => OrderStateMachine::STATE_CANCELLED, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $canceledId, 'name' => 'Abgebrochen']));
        $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $canceledId, 'name' => 'Cancelled']));

        // transitions
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'process', 'from_state_id' => $openId, 'to_state_id' => $inProgressId, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'cancel', 'from_state_id' => $openId, 'to_state_id' => $canceledId, 'created_at' => date(Defaults::DATE_FORMAT)]);

        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'cancel', 'from_state_id' => $inProgressId, 'to_state_id' => $canceledId, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'complete', 'from_state_id' => $inProgressId, 'to_state_id' => $completedId, 'created_at' => date(Defaults::DATE_FORMAT)]);

        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'reopen', 'from_state_id' => $canceledId, 'to_state_id' => $openId, 'created_at' => date(Defaults::DATE_FORMAT)]);
        // set initial state
        $connection->update('state_machine', ['initial_state_id' => $openId], ['id' => $stateMachineId]);
    }

    private function createOrderDeliveryStateMachine(Connection $connection): void
    {
        $stateMachineId = Uuid::randomBytes();
        $openId = Uuid::randomBytes();
        $cancelledId = Uuid::randomBytes();

        $shippedId = Uuid::randomBytes();
        $shippedPartiallyId = Uuid::randomBytes();

        $returnedId = Uuid::randomBytes();
        $returnedPartiallyId = Uuid::randomBytes();

        $germanId = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM_DE);
        $englishId = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);

        $translationDE = ['language_id' => $germanId, 'created_at' => date(Defaults::DATE_FORMAT)];
        $translationEN = ['language_id' => $englishId, 'created_at' => date(Defaults::DATE_FORMAT)];

        // state machine
        $connection->insert('state_machine', [
            'id' => $stateMachineId,
            'technical_name' => OrderDeliveryStateMachine::NAME,
            'created_at' => date(Defaults::DATE_FORMAT),
        ]);

        $connection->insert('state_machine_translation', array_merge($translationDE, [
            'state_machine_id' => $stateMachineId,
            'name' => 'Bestellstatus',
            'created_at' => date(Defaults::DATE_FORMAT),
        ]));

        $connection->insert('state_machine_translation', array_merge($translationEN, [
            'state_machine_id' => $stateMachineId,
            'name' => 'Order state',
            'created_at' => date(Defaults::DATE_FORMAT),
        ]));

        // states
        $connection->insert('state_machine_state', ['id' => $openId, 'state_machine_id' => $stateMachineId, 'technical_name' => OrderDeliveryStateMachine::STATE_OPEN, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $openId, 'name' => 'Offen']));
        $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $openId, 'name' => 'Open']));

        $connection->insert('state_machine_state', ['id' => $shippedId, 'state_machine_id' => $stateMachineId, 'technical_name' => OrderDeliveryStateMachine::STATE_SHIPPED, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $shippedId, 'name' => 'Versandt']));
        $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $shippedId, 'name' => 'Shipped']));

        $connection->insert('state_machine_state', ['id' => $shippedPartiallyId, 'state_machine_id' => $stateMachineId, 'technical_name' => OrderDeliveryStateMachine::STATE_PARTIALLY_SHIPPED, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $shippedPartiallyId, 'name' => 'Teilweise versandt']));
        $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $shippedPartiallyId, 'name' => 'Shipped (partially)']));

        $connection->insert('state_machine_state', ['id' => $returnedId, 'state_machine_id' => $stateMachineId, 'technical_name' => OrderDeliveryStateMachine::STATE_RETURNED, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $returnedId, 'name' => 'Retour']));
        $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $returnedId, 'name' => 'Returned']));

        $connection->insert('state_machine_state', ['id' => $returnedPartiallyId, 'state_machine_id' => $stateMachineId, 'technical_name' => OrderDeliveryStateMachine::STATE_PARTIALLY_RETURNED, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $returnedPartiallyId, 'name' => 'Teilretour']));
        $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $returnedPartiallyId, 'name' => 'Returned (partially)']));

        $connection->insert('state_machine_state', ['id' => $cancelledId, 'state_machine_id' => $stateMachineId, 'technical_name' => OrderDeliveryStateMachine::STATE_CANCELLED, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $cancelledId, 'name' => 'Abgebrochen']));
        $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $cancelledId, 'name' => 'Cancelled']));

        // transitions
        // from "open" to *
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'ship', 'from_state_id' => $openId, 'to_state_id' => $shippedId, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'ship_partially', 'from_state_id' => $openId, 'to_state_id' => $shippedPartiallyId, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'cancel', 'from_state_id' => $openId, 'to_state_id' => $cancelledId, 'created_at' => date(Defaults::DATE_FORMAT)]);

        // from "shipped" to *
        // $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'ship', 'from_state_id' => $shippedId, 'to_state_id' => $shippedId, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'retour', 'from_state_id' => $shippedId, 'to_state_id' => $returnedId, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'retour_partially', 'from_state_id' => $shippedId, 'to_state_id' => $returnedPartiallyId, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'cancel', 'from_state_id' => $shippedId, 'to_state_id' => $cancelledId, 'created_at' => date(Defaults::DATE_FORMAT)]);

        // from shipped_partially
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'retour', 'from_state_id' => $shippedPartiallyId, 'to_state_id' => $returnedId, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'retour_partially', 'from_state_id' => $shippedPartiallyId, 'to_state_id' => $returnedPartiallyId, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'ship', 'from_state_id' => $shippedPartiallyId, 'to_state_id' => $shippedId, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'cancel', 'from_state_id' => $shippedPartiallyId, 'to_state_id' => $cancelledId, 'created_at' => date(Defaults::DATE_FORMAT)]);

        // set initial state
        $connection->update('state_machine', ['initial_state_id' => $openId], ['id' => $stateMachineId]);
    }

    private function createOrderTransactionStateMachine(Connection $connection): void
    {
        $stateMachineId = Uuid::randomBytes();

        $openId = Uuid::randomBytes();
        $paidId = Uuid::randomBytes();
        $paidPartiallyId = Uuid::randomBytes();
        $cancelledId = Uuid::randomBytes();
        $remindedId = Uuid::randomBytes();
        $refundedId = Uuid::randomBytes();
        $refundedPartiallyId = Uuid::randomBytes();

        $germanId = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM_DE);
        $englishId = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);

        $translationDE = ['language_id' => $germanId, 'created_at' => date(Defaults::DATE_FORMAT)];
        $translationEN = ['language_id' => $englishId, 'created_at' => date(Defaults::DATE_FORMAT)];

        // state machine
        $connection->insert('state_machine', [
            'id' => $stateMachineId,
            'technical_name' => OrderTransactionStateMachine::NAME,
            'created_at' => date(Defaults::DATE_FORMAT),
        ]);

        $connection->insert('state_machine_translation', array_merge($translationDE, [
            'state_machine_id' => $stateMachineId,
            'name' => 'Zahlungsstatus',
            'created_at' => date(Defaults::DATE_FORMAT),
        ]));

        $connection->insert('state_machine_translation', array_merge($translationEN, [
            'state_machine_id' => $stateMachineId,
            'name' => 'Payment state',
            'created_at' => date(Defaults::DATE_FORMAT),
        ]));

        // states
        $connection->insert('state_machine_state', ['id' => $openId, 'state_machine_id' => $stateMachineId, 'technical_name' => OrderTransactionStateMachine::STATE_OPEN, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $openId, 'name' => 'Offen']));
        $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $openId, 'name' => 'Open']));

        $connection->insert('state_machine_state', ['id' => $paidId, 'state_machine_id' => $stateMachineId, 'technical_name' => OrderTransactionStateMachine::STATE_PAID, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $paidId, 'name' => 'Bezahlt']));
        $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $paidId, 'name' => 'Paid']));

        $connection->insert('state_machine_state', ['id' => $paidPartiallyId, 'state_machine_id' => $stateMachineId, 'technical_name' => OrderTransactionStateMachine::STATE_PARTIALLY_PAID, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $paidPartiallyId, 'name' => 'Teilweise bezahlt']));
        $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $paidPartiallyId, 'name' => 'Paid (partially)']));

        $connection->insert('state_machine_state', ['id' => $refundedId, 'state_machine_id' => $stateMachineId, 'technical_name' => OrderTransactionStateMachine::STATE_REFUNDED, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $refundedId, 'name' => 'Erstattet']));
        $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $refundedId, 'name' => 'Refunded']));

        $connection->insert('state_machine_state', ['id' => $refundedPartiallyId, 'state_machine_id' => $stateMachineId, 'technical_name' => OrderTransactionStateMachine::STATE_PARTIALLY_REFUNDED, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $refundedPartiallyId, 'name' => 'Teilweise erstattet']));
        $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $refundedPartiallyId, 'name' => 'Refunded (partially)']));

        $connection->insert('state_machine_state', ['id' => $cancelledId, 'state_machine_id' => $stateMachineId, 'technical_name' => OrderTransactionStateMachine::STATE_CANCELLED, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $cancelledId, 'name' => 'Abgebrochen']));
        $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $cancelledId, 'name' => 'Cancelled']));

        $connection->insert('state_machine_state', ['id' => $remindedId, 'state_machine_id' => $stateMachineId, 'technical_name' => OrderTransactionStateMachine::STATE_REMINDED, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $remindedId, 'name' => 'Erinnert']));
        $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $remindedId, 'name' => 'Reminded']));

        // transitions
        // from "open" to *
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'pay', 'from_state_id' => $openId, 'to_state_id' => $paidId, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'pay_partially', 'from_state_id' => $openId, 'to_state_id' => $paidPartiallyId, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'cancel', 'from_state_id' => $openId, 'to_state_id' => $cancelledId, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'remind', 'from_state_id' => $openId, 'to_state_id' => $remindedId, 'created_at' => date(Defaults::DATE_FORMAT)]);

        // from "reminded" to *
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'pay', 'from_state_id' => $remindedId, 'to_state_id' => $paidId, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'pay_partially', 'from_state_id' => $remindedId, 'to_state_id' => $paidPartiallyId, 'created_at' => date(Defaults::DATE_FORMAT)]);

        // from "paid_partially" to *
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'remind', 'from_state_id' => $paidPartiallyId, 'to_state_id' => $remindedId, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'pay', 'from_state_id' => $paidPartiallyId, 'to_state_id' => $paidId, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'refund_partially', 'from_state_id' => $paidPartiallyId, 'to_state_id' => $refundedPartiallyId, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'refund', 'from_state_id' => $paidPartiallyId, 'to_state_id' => $refundedId, 'created_at' => date(Defaults::DATE_FORMAT)]);

        // from "paid" to *
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'refund_partially', 'from_state_id' => $paidId, 'to_state_id' => $refundedPartiallyId, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'refund', 'from_state_id' => $paidId, 'to_state_id' => $refundedId, 'created_at' => date(Defaults::DATE_FORMAT)]);

        // from "refunded_partially" to *
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'refund', 'from_state_id' => $refundedPartiallyId, 'to_state_id' => $refundedId, 'created_at' => date(Defaults::DATE_FORMAT)]);

        // set initial state
        $connection->update('state_machine', ['initial_state_id' => $openId], ['id' => $stateMachineId]);
    }
}

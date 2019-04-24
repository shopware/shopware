<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\FetchMode;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryStates;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\DebitPayment;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\InvoicePayment;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\SEPAPayment;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Doctrine\MultiInsertQueryQueue;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

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
        $localeEn = Uuid::randomBytes();
        $localeDe = Uuid::randomBytes();
        $languageEn = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        $languageDe = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM_DE);

        // first locales
        $connection->insert('locale', ['id' => $localeEn, 'code' => 'en_GB', 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('locale', ['id' => $localeDe, 'code' => 'de_DE', 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);

        // second languages
        $connection->insert('language', [
            'id' => $languageEn,
            'name' => 'English',
            'locale_id' => $localeEn,
            'translation_code_id' => $localeEn,
            'created_at' => date(Defaults::STORAGE_DATE_FORMAT),
        ]);

        $connection->insert('language', [
            'id' => $languageDe,
            'name' => 'Deutsch',
            'locale_id' => $localeDe,
            'translation_code_id' => $localeDe,
            'created_at' => date(Defaults::STORAGE_DATE_FORMAT),
        ]);

        // third translations
        $connection->insert('locale_translation', [
            'locale_id' => $localeEn,
            'language_id' => $languageEn,
            'name' => 'English',
            'territory' => 'United Kingdom',
            'created_at' => date(Defaults::STORAGE_DATE_FORMAT),
        ]);
        $connection->insert('locale_translation', [
            'locale_id' => $localeEn,
            'language_id' => $languageDe,
            'name' => 'Englisch',
            'territory' => 'Vereinigtes Königreich',
            'created_at' => date(Defaults::STORAGE_DATE_FORMAT),
        ]);
        $connection->insert('locale_translation', [
            'locale_id' => $localeDe,
            'language_id' => $languageEn,
            'name' => 'German',
            'territory' => 'Germany',
            'created_at' => date(Defaults::STORAGE_DATE_FORMAT),
        ]);
        $connection->insert('locale_translation', [
            'locale_id' => $localeDe,
            'language_id' => $languageDe,
            'name' => 'Deutsch',
            'territory' => 'Deutschland',
            'created_at' => date(Defaults::STORAGE_DATE_FORMAT),
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
                ['id' => $localeId, 'code' => $locale['locale'], 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]
            );

            $queue->addInsert(
                'locale_translation',
                [
                    'locale_id' => $localeId,
                    'language_id' => $languageEn,
                    'created_at' => date(Defaults::STORAGE_DATE_FORMAT),
                    'name' => $locale['name']['en_GB'],
                    'territory' => $locale['territory']['en_GB'],
                ]
            );

            $queue->addInsert(
                'locale_translation',
                [
                    'locale_id' => $localeId,
                    'language_id' => $languageDe,
                    'created_at' => date(Defaults::STORAGE_DATE_FORMAT),
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
                'country_id' => $countryId,
                'created_at' => date(Defaults::STORAGE_DATE_FORMAT),
            ];
        };

        $languageEN = function (string $countryId, string $name) {
            return [
                'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
                'name' => $name,
                'country_id' => $countryId,
                'created_at' => date(Defaults::STORAGE_DATE_FORMAT),
            ];
        };

        $deId = Uuid::randomBytes();
        $connection->insert('country', ['id' => $deId, 'iso' => 'DE', 'position' => 1, 'iso3' => 'DEU', 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('country_translation', $languageDE($deId, 'Deutschland'));
        $connection->insert('country_translation', $languageEN($deId, 'Germany'));

        $grId = Uuid::randomBytes();
        $connection->insert('country', ['id' => $grId, 'iso' => 'GR', 'position' => 10, 'iso3' => 'GRC', 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('country_translation', $languageEN($grId, 'Greece'));
        $connection->insert('country_translation', $languageDE($grId, 'Griechenland'));

        $gbId = Uuid::randomBytes();
        $connection->insert('country', ['id' => $gbId, 'iso' => 'GB', 'position' => 5, 'iso3' => 'GBR', 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('country_translation', $languageEN($gbId, 'Great Britain'));
        $connection->insert('country_translation', $languageDE($gbId, 'Großbritannien'));

        $ieId = Uuid::randomBytes();
        $connection->insert('country', ['id' => $ieId, 'iso' => 'IE', 'position' => 10, 'iso3' => 'IRL', 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('country_translation', $languageEN($ieId, 'Ireland'));
        $connection->insert('country_translation', $languageDE($ieId, 'Irland'));

        $isId = Uuid::randomBytes();
        $connection->insert('country', ['id' => $isId, 'iso' => 'IS', 'position' => 10, 'iso3' => 'ISL', 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('country_translation', $languageEN($isId, 'Iceland'));
        $connection->insert('country_translation', $languageDE($isId, 'Island'));

        $itId = Uuid::randomBytes();
        $connection->insert('country', ['id' => $itId, 'iso' => 'IT', 'position' => 10, 'active' => 1, 'iso3' => 'ITA', 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('country_translation', $languageEN($itId, 'Italy'));
        $connection->insert('country_translation', $languageDE($itId, 'Italien'));

        $jpId = Uuid::randomBytes();
        $connection->insert('country', ['id' => $jpId, 'iso' => 'JP', 'position' => 10, 'iso3' => 'JPN', 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('country_translation', $languageEN($jpId, 'Japan'));
        $connection->insert('country_translation', $languageDE($jpId, 'Japan'));

        $caId = Uuid::randomBytes();
        $connection->insert('country', ['id' => $caId, 'iso' => 'CA', 'position' => 10, 'iso3' => 'CAN', 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('country_translation', $languageEN($caId, 'Canada'));
        $connection->insert('country_translation', $languageDE($caId, 'Kanada'));

        $luId = Uuid::randomBytes();
        $connection->insert('country', ['id' => $luId, 'iso' => 'LU', 'position' => 10, 'iso3' => 'LUX', 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('country_translation', $languageEN($luId, 'Luxembourg'));
        $connection->insert('country_translation', $languageDE($luId, 'Luxemburg'));

        $naId = Uuid::randomBytes();
        $connection->insert('country', ['id' => $naId, 'iso' => 'NA', 'position' => 10, 'iso3' => 'NAM', 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('country_translation', $languageEN($naId, 'Namibia'));
        $connection->insert('country_translation', $languageDE($naId, 'Namibia'));

        $nlId = Uuid::randomBytes();
        $connection->insert('country', ['id' => $nlId, 'iso' => 'NL', 'position' => 10, 'active' => 1, 'iso3' => 'NLD', 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('country_translation', $languageEN($nlId, 'Netherlands'));
        $connection->insert('country_translation', $languageDE($nlId, 'Niederlande'));

        $noId = Uuid::randomBytes();
        $connection->insert('country', ['id' => $noId, 'iso' => 'NO', 'position' => 10, 'iso3' => 'NOR', 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('country_translation', $languageEN($noId, 'Norway'));
        $connection->insert('country_translation', $languageDE($noId, 'Norwegen'));

        $atId = Uuid::randomBytes();
        $connection->insert('country', ['id' => $atId, 'iso' => 'AT', 'position' => 10, 'active' => 1, 'iso3' => 'AUT', 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('country_translation', $languageEN($atId, 'Austria'));
        $connection->insert('country_translation', $languageDE($atId, 'Österreich'));

        $ptId = Uuid::randomBytes();
        $connection->insert('country', ['id' => $ptId, 'iso' => 'PT', 'position' => 10, 'iso3' => 'PRT', 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('country_translation', $languageEN($ptId, 'Portugal'));
        $connection->insert('country_translation', $languageDE($ptId, 'Portugal'));

        $seId = Uuid::randomBytes();
        $connection->insert('country', ['id' => $seId, 'iso' => 'SE', 'position' => 10, 'iso3' => 'SWE', 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('country_translation', $languageEN($seId, 'Sweden'));
        $connection->insert('country_translation', $languageDE($seId, 'Schweden'));

        $chId = Uuid::randomBytes();
        $connection->insert('country', ['id' => $chId, 'iso' => 'CH', 'position' => 10, 'tax_free' => 1, 'active' => 1, 'iso3' => 'CHE', 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('country_translation', $languageEN($chId, 'Switzerland'));
        $connection->insert('country_translation', $languageDE($chId, 'Schweiz'));

        $esId = Uuid::randomBytes();
        $connection->insert('country', ['id' => $esId, 'iso' => 'ES', 'position' => 10, 'active' => 1, 'iso3' => 'ESP', 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('country_translation', $languageEN($esId, 'Spain'));
        $connection->insert('country_translation', $languageDE($esId, 'Spanien'));

        $usId = Uuid::randomBytes();
        $connection->insert('country', ['id' => $usId, 'iso' => 'US', 'position' => 10, 'iso3' => 'USA', 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('country_translation', $languageEN($usId, 'USA'));
        $connection->insert('country_translation', $languageDE($usId, 'USA'));

        $liId = Uuid::randomBytes();
        $connection->insert('country', ['id' => $liId, 'iso' => 'LI', 'position' => 10, 'iso3' => 'LIE', 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('country_translation', $languageEN($liId, 'Liechtenstein'));
        $connection->insert('country_translation', $languageDE($liId, 'Liechtenstein'));

        $aeId = Uuid::randomBytes();
        $connection->insert('country', ['id' => $aeId, 'iso' => 'AE', 'position' => 10, 'active' => 1, 'iso3' => 'ARE', 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('country_translation', $languageEN($aeId, 'Arab Emirates'));
        $connection->insert('country_translation', $languageDE($aeId, 'Arabische Emirate'));

        $plId = Uuid::randomBytes();
        $connection->insert('country', ['id' => $plId, 'iso' => 'PL', 'position' => 10, 'iso3' => 'POL', 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('country_translation', $languageEN($plId, 'Poland'));
        $connection->insert('country_translation', $languageDE($plId, 'Polen'));

        $huId = Uuid::randomBytes();
        $connection->insert('country', ['id' => $huId, 'iso' => 'HU', 'position' => 10, 'iso3' => 'HUN', 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('country_translation', $languageEN($huId, 'Hungary'));
        $connection->insert('country_translation', $languageDE($huId, 'Ungarn'));

        $trId = Uuid::randomBytes();
        $connection->insert('country', ['id' => $trId, 'iso' => 'TR', 'position' => 10, 'iso3' => 'TUR', 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('country_translation', $languageEN($trId, 'Turkey'));
        $connection->insert('country_translation', $languageDE($trId, 'Türkei'));

        $czId = Uuid::randomBytes();
        $connection->insert('country', ['id' => $czId, 'iso' => 'CZ', 'position' => 10, 'iso3' => 'CZE', 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('country_translation', $languageEN($czId, 'Czech Republic'));
        $connection->insert('country_translation', $languageDE($czId, 'Tschechische Republik'));

        $skId = Uuid::randomBytes();
        $connection->insert('country', ['id' => $skId, 'iso' => 'SK', 'position' => 10, 'iso3' => 'SVK', 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('country_translation', $languageEN($skId, 'Slovenia'));
        $connection->insert('country_translation', $languageDE($skId, 'Slowenien'));

        $roId = Uuid::randomBytes();
        $connection->insert('country', ['id' => $roId, 'iso' => 'RO', 'position' => 10, 'iso3' => 'ROU', 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('country_translation', $languageEN($roId, 'Romania'));
        $connection->insert('country_translation', $languageDE($roId, 'Rumänien'));

        $brId = Uuid::randomBytes();
        $connection->insert('country', ['id' => $brId, 'iso' => 'BR', 'position' => 10, 'iso3' => 'BRA', 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('country_translation', $languageEN($brId, 'Brazil'));
        $connection->insert('country_translation', $languageDE($brId, 'Brasilien'));

        $ilId = Uuid::randomBytes();
        $connection->insert('country', ['id' => $ilId, 'iso' => 'IL', 'position' => 10, 'iso3' => 'ISR', 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('country_translation', $languageEN($ilId, 'Isreal'));
        $connection->insert('country_translation', $languageDE($ilId, 'Isreal'));

        $auId = Uuid::randomBytes();
        $connection->insert('country', ['id' => $auId, 'iso' => 'AU', 'position' => 10, 'active' => 1, 'iso3' => 'AUS', 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('country_translation', $languageEN($auId, 'Australia'));
        $connection->insert('country_translation', $languageDE($auId, 'Australien'));

        $beId = Uuid::randomBytes();
        $connection->insert('country', ['id' => $beId, 'iso' => 'BE', 'position' => 10, 'active' => 1, 'iso3' => 'BEL', 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('country_translation', $languageEN($beId, 'Belgium'));
        $connection->insert('country_translation', $languageDE($beId, 'Belgien'));

        $dkId = Uuid::randomBytes();
        $connection->insert('country', ['id' => $dkId, 'iso' => 'DK', 'position' => 10, 'active' => 1, 'iso3' => 'DNK', 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('country_translation', $languageEN($dkId, 'Denmark'));
        $connection->insert('country_translation', $languageDE($dkId, 'Dänemark'));

        $fiId = Uuid::randomBytes();
        $connection->insert('country', ['id' => $fiId, 'iso' => 'FI', 'position' => 10, 'active' => 1, 'iso3' => 'FIN', 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('country_translation', $languageEN($fiId, 'Finland'));
        $connection->insert('country_translation', $languageDE($fiId, 'Finnland'));

        $frId = Uuid::randomBytes();
        $connection->insert('country', ['id' => $frId, 'iso' => 'FR', 'position' => 10, 'iso3' => 'FRA', 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('country_translation', $languageEN($frId, 'France'));
        $connection->insert('country_translation', $languageDE($frId, 'Frankreich'));
    }

    private function createCurrency(Connection $connection): void
    {
        $EUR = Uuid::fromHexToBytes(Defaults::CURRENCY);
        $USD = Uuid::randomBytes();
        $GBP = Uuid::randomBytes();

        $languageEN = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);

        $connection->insert('currency', ['id' => $EUR, 'is_default' => 1, 'factor' => 1, 'symbol' => '€', 'placed_in_front' => 0, 'position' => 1, 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('currency_translation', ['currency_id' => $EUR, 'language_id' => $languageEN, 'short_name' => 'EUR', 'name' => 'Euro', 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);

        $connection->insert('currency', ['id' => $USD, 'is_default' => 0, 'factor' => 1.17085, 'symbol' => '$', 'placed_in_front' => 0, 'position' => 1, 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('currency_translation', ['currency_id' => $USD, 'language_id' => $languageEN, 'short_name' => 'USD', 'name' => 'US-Dollar', 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);

        $connection->insert('currency', ['id' => $GBP, 'is_default' => 0, 'factor' => 0.89157, 'symbol' => '£', 'placed_in_front' => 0, 'position' => 1, 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('currency_translation', ['currency_id' => $GBP, 'language_id' => $languageEN, 'short_name' => 'GBP', 'name' => 'Pound', 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
    }

    private function createCustomerGroup(Connection $connection): void
    {
        $groupId = Uuid::fromHexToBytes(Defaults::FALLBACK_CUSTOMER_GROUP);
        $languageEN = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);

        $connection->insert('customer_group', ['id' => $groupId, 'display_gross' => 1, 'input_gross' => 1, 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('customer_group_translation', ['customer_group_id' => $groupId, 'language_id' => $languageEN, 'name' => 'Standard customer group', 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
    }

    private function createPaymentMethod(Connection $connection): void
    {
        $languageEN = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);

        $debit = Uuid::randomBytes();
        $invoice = Uuid::randomBytes();
        $sepa = Uuid::randomBytes();

        $connection->insert('payment_method', ['id' => $debit, 'technical_name' => 'debit', 'template' => 'debit.tpl', 'class' => DebitPayment::class, 'percentage_surcharge' => -10, 'position' => 4, 'active' => 0, 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('payment_method_translation', ['payment_method_id' => $debit, 'language_id' => $languageEN, 'name' => 'Direct Debit', 'additional_description' => 'Additional text', 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);

        $connection->insert('payment_method', ['id' => $invoice, 'technical_name' => 'invoice', 'template' => 'invoice.tpl', 'class' => InvoicePayment::class, 'position' => 5, 'active' => 1, 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('payment_method_translation', ['payment_method_id' => $invoice, 'language_id' => $languageEN, 'name' => 'Invoice', 'additional_description' => 'Payment by invoice. Shopware provides automatic invoicing for all customers on orders after the first, in order to avoid defaults on payment.', 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);

        $connection->insert('payment_method', ['id' => $sepa, 'technical_name' => 'sepa', 'template' => '@Checkout/frontend/sepa.html.twig', 'class' => SEPAPayment::class, 'position' => 0, 'active' => 1, 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('payment_method_translation', ['payment_method_id' => $sepa, 'language_id' => $languageEN, 'name' => 'SEPA direct debit', 'additional_description' => 'SEPA invoice', 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
    }

    private function createShippingMethod(Connection $connection): void
    {
        $standard = Uuid::randomBytes();
        $express = Uuid::randomBytes();

        $languageEN = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);

        $connection->insert('shipping_method', ['id' => $standard, 'type' => 0, 'active' => 1, 'position' => 1, 'calculation' => 1, 'bind_shippingfree' => 0, 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('shipping_method_translation', ['shipping_method_id' => $standard, 'language_id' => $languageEN, 'name' => 'Standard', 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('shipping_method_price', ['id' => Uuid::randomBytes(), 'shipping_method_id' => $standard, 'calculation' => 0, 'currency_id' => Uuid::fromHexToBytes(Defaults::CURRENCY), 'price' => 0, 'quantity_start' => 0, 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);

        $connection->insert('shipping_method', ['id' => $express, 'type' => 0, 'active' => 1, 'position' => 2, 'calculation' => 1, 'surcharge_calculation' => 5, 'bind_shippingfree' => 0, 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('shipping_method_translation', ['shipping_method_id' => $express, 'language_id' => $languageEN, 'name' => 'Express', 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('shipping_method_price', ['id' => Uuid::randomBytes(), 'shipping_method_id' => $express, 'calculation' => 0, 'currency_id' => Uuid::fromHexToBytes(Defaults::CURRENCY), 'price' => 0, 'quantity_start' => 0, 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
    }

    private function createTax(Connection $connection): void
    {
        $tax19 = Uuid::randomBytes();
        $tax7 = Uuid::randomBytes();
        $tax20 = Uuid::randomBytes();
        $tax5 = Uuid::randomBytes();
        $tax0 = Uuid::randomBytes();

        $connection->insert('tax', ['id' => $tax19, 'tax_rate' => 19, 'name' => '19%', 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('tax', ['id' => $tax7, 'tax_rate' => 7, 'name' => '7%', 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('tax', ['id' => $tax20, 'tax_rate' => 20, 'name' => '20%', 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('tax', ['id' => $tax5, 'tax_rate' => 5, 'name' => '5%', 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('tax', ['id' => $tax0, 'tax_rate' => 1, 'name' => '1%', 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
    }

    private function createSalesChannelTypes(Connection $connection): void
    {
        $languageEN = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);

        $storefront = Uuid::fromHexToBytes(Defaults::SALES_CHANNEL_TYPE_STOREFRONT);
        $storefrontApi = Uuid::fromHexToBytes(Defaults::SALES_CHANNEL_TYPE_API);

        $connection->insert('sales_channel_type', ['id' => $storefront, 'icon_name' => 'default-building-shop', 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('sales_channel_type_translation', ['sales_channel_type_id' => $storefront, 'language_id' => $languageEN, 'name' => 'Storefront', 'manufacturer' => 'shopware AG', 'description' => 'Default storefront sales channel', 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);

        $connection->insert('sales_channel_type', ['id' => $storefrontApi, 'icon_name' => 'default-shopping-basket', 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('sales_channel_type_translation', ['sales_channel_type_id' => $storefrontApi, 'language_id' => $languageEN, 'name' => 'Storefront API', 'manufacturer' => 'shopware AG', 'description' => 'Default Storefront-API', 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
    }

    private function createProductManufacturer(Connection $connection): void
    {
        $id = Uuid::randomBytes();
        $languageEN = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        $versionId = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);

        $connection->insert('product_manufacturer', ['id' => $id, 'version_id' => $versionId, 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('product_manufacturer_translation', ['product_manufacturer_id' => $id, 'product_manufacturer_version_id' => $versionId, 'language_id' => $languageEN, 'name' => 'shopware AG', 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
    }

    private function createSalesChannel(Connection $connection): void
    {
        $currencies = $connection->executeQuery('SELECT id FROM currency')->fetchAll(FetchMode::COLUMN);
        $languages = $connection->executeQuery('SELECT id FROM language')->fetchAll(FetchMode::COLUMN);
        $shippingMethods = $connection->executeQuery('SELECT id FROM shipping_method')->fetchAll(FetchMode::COLUMN);
        $paymentMethods = $connection->executeQuery('SELECT id FROM payment_method')->fetchAll(FetchMode::COLUMN);
        $defaultPaymentMethod = $connection->executeQuery('SELECT id FROM payment_method WHERE active = 1 ORDER BY `position`')->fetchColumn();
        $defaultShippingMethod = $connection->executeQuery('SELECT id FROM shipping_method WHERE active = 1 ORDER BY `position`')->fetchColumn();
        $countryStatement = $connection->executeQuery('SELECT id FROM country WHERE active = 1 ORDER BY `position`');
        $defaultCountry = $countryStatement->fetchColumn();

        $id = Uuid::fromHexToBytes(Defaults::SALES_CHANNEL);
        $languageEN = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);

        $connection->insert('sales_channel', [
            'id' => $id,
            'type_id' => Uuid::fromHexToBytes(Defaults::SALES_CHANNEL_TYPE_API),
            'access_key' => AccessKeyHelper::generateAccessKey('sales-channel'),
            'active' => 1,
            'tax_calculation_type' => 'vertical',
            'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            'currency_id' => Uuid::fromHexToBytes(Defaults::CURRENCY),
            'payment_method_id' => $defaultPaymentMethod,
            'shipping_method_id' => $defaultShippingMethod,
            'country_id' => $defaultCountry,
            'created_at' => date(Defaults::STORAGE_DATE_FORMAT),
        ]);

        $connection->insert('sales_channel_translation', ['sales_channel_id' => $id, 'language_id' => $languageEN, 'name' => 'Storefront API', 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);

        // country
        $connection->insert('sales_channel_country', ['sales_channel_id' => $id, 'country_id' => $defaultCountry, 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('sales_channel_country', ['sales_channel_id' => $id, 'country_id' => $countryStatement->fetchColumn(), 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);

        // currency
        foreach ($currencies as $currency) {
            $connection->insert('sales_channel_currency', ['sales_channel_id' => $id, 'currency_id' => $currency, 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        }

        // language
        foreach ($languages as $language) {
            $connection->insert('sales_channel_language', ['sales_channel_id' => $id, 'language_id' => $language, 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        }

        // currency
        foreach ($shippingMethods as $shippingMethod) {
            $connection->insert('sales_channel_shipping_method', ['sales_channel_id' => $id, 'shipping_method_id' => $shippingMethod, 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        }

        // currency
        foreach ($paymentMethods as $paymentMethod) {
            $connection->insert('sales_channel_payment_method', ['sales_channel_id' => $id, 'payment_method_id' => $paymentMethod, 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        }
    }

    private function createDefaultSnippetSets(Connection $connection): void
    {
        $queue = new MultiInsertQueryQueue($connection);

        $queue->addInsert('snippet_set', ['id' => Uuid::randomBytes(), 'name' => 'BASE de_DE', 'base_file' => 'messages.de_DE', 'iso' => 'de_DE', 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        $queue->addInsert('snippet_set', ['id' => Uuid::randomBytes(), 'name' => 'BASE en_GB', 'base_file' => 'messages.en_GB', 'iso' => 'en_GB', 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);

        $queue->execute();
    }

    private function createDefaultMediaFolders(Connection $connection): void
    {
        $queue = new MultiInsertQueryQueue($connection);

        $queue->addInsert('media_default_folder', ['id' => Uuid::randomBytes(), 'association_fields' => '["productMedia"]', 'entity' => 'product', 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        $queue->addInsert('media_default_folder', ['id' => Uuid::randomBytes(), 'association_fields' => '["productManufacturers"]', 'entity' => 'product_manufacturer', 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);

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

        $translationDE = ['language_id' => $germanId, 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)];
        $translationEN = ['language_id' => $englishId, 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)];

        // state machine
        $connection->insert('state_machine', [
            'id' => $stateMachineId,
            'technical_name' => OrderStates::STATE_MACHINE,
            'created_at' => date(Defaults::STORAGE_DATE_FORMAT),
        ]);

        $connection->insert('state_machine_translation', array_merge($translationDE, [
            'state_machine_id' => $stateMachineId,
            'name' => 'Bestellstatus',
            'created_at' => date(Defaults::STORAGE_DATE_FORMAT),
        ]));

        $connection->insert('state_machine_translation', array_merge($translationEN, [
            'state_machine_id' => $stateMachineId,
            'name' => 'Order state',
            'created_at' => date(Defaults::STORAGE_DATE_FORMAT),
        ]));

        // states
        $connection->insert('state_machine_state', ['id' => $openId, 'state_machine_id' => $stateMachineId, 'technical_name' => OrderStates::STATE_OPEN, 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $openId, 'name' => 'Offen']));
        $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $openId, 'name' => 'Open']));

        $connection->insert('state_machine_state', ['id' => $completedId, 'state_machine_id' => $stateMachineId, 'technical_name' => OrderStates::STATE_COMPLETED, 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $completedId, 'name' => 'Abgeschlossen']));
        $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $completedId, 'name' => 'Done']));

        $connection->insert('state_machine_state', ['id' => $inProgressId, 'state_machine_id' => $stateMachineId, 'technical_name' => OrderStates::STATE_IN_PROGRESS, 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $inProgressId, 'name' => 'In Bearbeitung']));
        $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $inProgressId, 'name' => 'In progress']));

        $connection->insert('state_machine_state', ['id' => $canceledId, 'state_machine_id' => $stateMachineId, 'technical_name' => OrderStates::STATE_CANCELLED, 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $canceledId, 'name' => 'Abgebrochen']));
        $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $canceledId, 'name' => 'Cancelled']));

        // transitions
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'process', 'from_state_id' => $openId, 'to_state_id' => $inProgressId, 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'cancel', 'from_state_id' => $openId, 'to_state_id' => $canceledId, 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);

        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'cancel', 'from_state_id' => $inProgressId, 'to_state_id' => $canceledId, 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'complete', 'from_state_id' => $inProgressId, 'to_state_id' => $completedId, 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);

        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'reopen', 'from_state_id' => $canceledId, 'to_state_id' => $openId, 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
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

        $translationDE = ['language_id' => $germanId, 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)];
        $translationEN = ['language_id' => $englishId, 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)];

        // state machine
        $connection->insert('state_machine', [
            'id' => $stateMachineId,
            'technical_name' => OrderDeliveryStates::STATE_MACHINE,
            'created_at' => date(Defaults::STORAGE_DATE_FORMAT),
        ]);

        $connection->insert('state_machine_translation', array_merge($translationDE, [
            'state_machine_id' => $stateMachineId,
            'name' => 'Bestellstatus',
            'created_at' => date(Defaults::STORAGE_DATE_FORMAT),
        ]));

        $connection->insert('state_machine_translation', array_merge($translationEN, [
            'state_machine_id' => $stateMachineId,
            'name' => 'Order state',
            'created_at' => date(Defaults::STORAGE_DATE_FORMAT),
        ]));

        // states
        $connection->insert('state_machine_state', ['id' => $openId, 'state_machine_id' => $stateMachineId, 'technical_name' => OrderDeliveryStates::STATE_OPEN, 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $openId, 'name' => 'Offen']));
        $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $openId, 'name' => 'Open']));

        $connection->insert('state_machine_state', ['id' => $shippedId, 'state_machine_id' => $stateMachineId, 'technical_name' => OrderDeliveryStates::STATE_SHIPPED, 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $shippedId, 'name' => 'Versandt']));
        $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $shippedId, 'name' => 'Shipped']));

        $connection->insert('state_machine_state', ['id' => $shippedPartiallyId, 'state_machine_id' => $stateMachineId, 'technical_name' => OrderDeliveryStates::STATE_PARTIALLY_SHIPPED, 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $shippedPartiallyId, 'name' => 'Teilweise versandt']));
        $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $shippedPartiallyId, 'name' => 'Shipped (partially)']));

        $connection->insert('state_machine_state', ['id' => $returnedId, 'state_machine_id' => $stateMachineId, 'technical_name' => OrderDeliveryStates::STATE_RETURNED, 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $returnedId, 'name' => 'Retour']));
        $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $returnedId, 'name' => 'Returned']));

        $connection->insert('state_machine_state', ['id' => $returnedPartiallyId, 'state_machine_id' => $stateMachineId, 'technical_name' => OrderDeliveryStates::STATE_PARTIALLY_RETURNED, 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $returnedPartiallyId, 'name' => 'Teilretour']));
        $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $returnedPartiallyId, 'name' => 'Returned (partially)']));

        $connection->insert('state_machine_state', ['id' => $cancelledId, 'state_machine_id' => $stateMachineId, 'technical_name' => OrderDeliveryStates::STATE_CANCELLED, 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $cancelledId, 'name' => 'Abgebrochen']));
        $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $cancelledId, 'name' => 'Cancelled']));

        // transitions
        // from "open" to *
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'ship', 'from_state_id' => $openId, 'to_state_id' => $shippedId, 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'ship_partially', 'from_state_id' => $openId, 'to_state_id' => $shippedPartiallyId, 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'cancel', 'from_state_id' => $openId, 'to_state_id' => $cancelledId, 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);

        // from "shipped" to *
        // $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'ship', 'from_state_id' => $shippedId, 'to_state_id' => $shippedId, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'retour', 'from_state_id' => $shippedId, 'to_state_id' => $returnedId, 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'retour_partially', 'from_state_id' => $shippedId, 'to_state_id' => $returnedPartiallyId, 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'cancel', 'from_state_id' => $shippedId, 'to_state_id' => $cancelledId, 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);

        // from shipped_partially
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'retour', 'from_state_id' => $shippedPartiallyId, 'to_state_id' => $returnedId, 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'retour_partially', 'from_state_id' => $shippedPartiallyId, 'to_state_id' => $returnedPartiallyId, 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'ship', 'from_state_id' => $shippedPartiallyId, 'to_state_id' => $shippedId, 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'cancel', 'from_state_id' => $shippedPartiallyId, 'to_state_id' => $cancelledId, 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);

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

        $translationDE = ['language_id' => $germanId, 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)];
        $translationEN = ['language_id' => $englishId, 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)];

        // state machine
        $connection->insert('state_machine', [
            'id' => $stateMachineId,
            'technical_name' => OrderTransactionStates::STATE_MACHINE,
            'created_at' => date(Defaults::STORAGE_DATE_FORMAT),
        ]);

        $connection->insert('state_machine_translation', array_merge($translationDE, [
            'state_machine_id' => $stateMachineId,
            'name' => 'Zahlungsstatus',
            'created_at' => date(Defaults::STORAGE_DATE_FORMAT),
        ]));

        $connection->insert('state_machine_translation', array_merge($translationEN, [
            'state_machine_id' => $stateMachineId,
            'name' => 'Payment state',
            'created_at' => date(Defaults::STORAGE_DATE_FORMAT),
        ]));

        // states
        $connection->insert('state_machine_state', ['id' => $openId, 'state_machine_id' => $stateMachineId, 'technical_name' => OrderTransactionStates::STATE_OPEN, 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $openId, 'name' => 'Offen']));
        $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $openId, 'name' => 'Open']));

        $connection->insert('state_machine_state', ['id' => $paidId, 'state_machine_id' => $stateMachineId, 'technical_name' => OrderTransactionStates::STATE_PAID, 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $paidId, 'name' => 'Bezahlt']));
        $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $paidId, 'name' => 'Paid']));

        $connection->insert('state_machine_state', ['id' => $paidPartiallyId, 'state_machine_id' => $stateMachineId, 'technical_name' => OrderTransactionStates::STATE_PARTIALLY_PAID, 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $paidPartiallyId, 'name' => 'Teilweise bezahlt']));
        $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $paidPartiallyId, 'name' => 'Paid (partially)']));

        $connection->insert('state_machine_state', ['id' => $refundedId, 'state_machine_id' => $stateMachineId, 'technical_name' => OrderTransactionStates::STATE_REFUNDED, 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $refundedId, 'name' => 'Erstattet']));
        $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $refundedId, 'name' => 'Refunded']));

        $connection->insert('state_machine_state', ['id' => $refundedPartiallyId, 'state_machine_id' => $stateMachineId, 'technical_name' => OrderTransactionStates::STATE_PARTIALLY_REFUNDED, 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $refundedPartiallyId, 'name' => 'Teilweise erstattet']));
        $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $refundedPartiallyId, 'name' => 'Refunded (partially)']));

        $connection->insert('state_machine_state', ['id' => $cancelledId, 'state_machine_id' => $stateMachineId, 'technical_name' => OrderTransactionStates::STATE_CANCELLED, 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $cancelledId, 'name' => 'Abgebrochen']));
        $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $cancelledId, 'name' => 'Cancelled']));

        $connection->insert('state_machine_state', ['id' => $remindedId, 'state_machine_id' => $stateMachineId, 'technical_name' => OrderTransactionStates::STATE_REMINDED, 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $remindedId, 'name' => 'Erinnert']));
        $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $remindedId, 'name' => 'Reminded']));

        // transitions
        // from "open" to *
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'pay', 'from_state_id' => $openId, 'to_state_id' => $paidId, 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'pay_partially', 'from_state_id' => $openId, 'to_state_id' => $paidPartiallyId, 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'cancel', 'from_state_id' => $openId, 'to_state_id' => $cancelledId, 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'remind', 'from_state_id' => $openId, 'to_state_id' => $remindedId, 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);

        // from "reminded" to *
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'pay', 'from_state_id' => $remindedId, 'to_state_id' => $paidId, 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'pay_partially', 'from_state_id' => $remindedId, 'to_state_id' => $paidPartiallyId, 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);

        // from "paid_partially" to *
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'remind', 'from_state_id' => $paidPartiallyId, 'to_state_id' => $remindedId, 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'pay', 'from_state_id' => $paidPartiallyId, 'to_state_id' => $paidId, 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'refund_partially', 'from_state_id' => $paidPartiallyId, 'to_state_id' => $refundedPartiallyId, 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'refund', 'from_state_id' => $paidPartiallyId, 'to_state_id' => $refundedId, 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);

        // from "paid" to *
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'refund_partially', 'from_state_id' => $paidId, 'to_state_id' => $refundedPartiallyId, 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'refund', 'from_state_id' => $paidId, 'to_state_id' => $refundedId, 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);

        // from "refunded_partially" to *
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'refund', 'from_state_id' => $refundedPartiallyId, 'to_state_id' => $refundedId, 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);

        // set initial state
        $connection->update('state_machine', ['initial_state_id' => $openId], ['id' => $stateMachineId]);
    }
}

<?php declare(strict_types=1);

namespace Shopware\Framework\Command;

use Doctrine\DBAL\Connection;
use Shopware\Framework\Struct\Uuid;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CreateTenantCommand extends ContainerAwareCommand
{
    /**
     * @var Connection
     */
    private $connection;

    public function __construct(?string $name = null, Connection $connection)
    {
        parent::__construct($name);
        $this->connection = $connection;
    }

    protected function configure()
    {
        $this->addOption('tenant-id', 'id', InputOption::VALUE_REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var string $tenantId */
        $tenantId = $input->getOption('tenant-id');

        if (!$tenantId) {
            throw new \Exception('No tenant id provided');
        }
        if (!Uuid::isValid($tenantId)) {
            throw new \Exception('Invalid uuid provided');
        }

        $bytes = hex2bin($tenantId);

        $this->connection->executeUpdate('SET NAMES utf8mb4;');
        $this->connection->executeUpdate('SET FOREIGN_KEY_CHECKS=0;');

        $this->importApplication($bytes);
        $this->importCatalog($bytes);
        $this->importLanguage($bytes);
        $this->importCountry($bytes);
        $this->importCountryArea($bytes);
        $this->importCountryState($bytes);
        $this->importOrderState($bytes);
        $this->importOrderTransaction($bytes);
        $this->importCurrency($bytes);
        $this->importCustomerGroup($bytes);
        $this->importLocale($bytes);
        $this->importPaymentMethod($bytes);
        $this->importShippingMethod($bytes);
        $this->importTax($bytes);
        $this->importListingSorting($bytes);

        $this->connection->executeUpdate('SET FOREIGN_KEY_CHECKS=1;');
    }

    private function importTable(string $tenantId, string $table, array $columns, array $tenantColumns, array $records): void
    {
        foreach ($records as $record) {
            $combined = array_combine($columns, $record);

            foreach ($tenantColumns as $column) {
                $fk = str_replace('tenant_id', 'id', $column);

                if (!isset($combined[$fk]) && $column !== 'tenant_id') {
                    continue;
                }

                $combined[$column] = $tenantId;
            }

            $this->connection->insert($table, $combined);
        }
    }

    private function importApplication(string $tenantId)
    {
        $this->importTable(
            $tenantId,
            'application',
            ['id', 'type', 'name', 'configuration', 'access_key', 'secret_access_key', 'catalog_ids', 'currency_ids', 'language_ids', 'tax_calculation_type', 'language_id', 'currency_id', 'payment_method_id', 'shipping_method_id', 'country_id', 'currency_version_id', 'payment_method_version_id', 'shipping_method_version_id', 'country_version_id'],
            ['tenant_id', 'language_tenant_id', 'currency_tenant_id', 'payment_method_tenant_id', 'shipping_method_tenant_id', 'country_tenant_id'],
            [
                [hex2bin('ffffffffffffffffffffffffffffffff'), 'api', 'Default endpoint', null, 'TzhovH7sgws8n9UjgEdDEzNkA6xURua8', 'eZvM4Mumq/h1#88q6U4wvCi-AkTY', '["ffffffffffffffffffffffffffffffff"]', '["4c8eba11bd3546d786afbed481a6e665","2824ea63db6741109e2378ddcc9cec84"]', '["ffffffffffffffffffffffffffffffff"]', 'vertical', hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('4c8eba11bd3546d786afbed481a6e665'), hex2bin('77573b9cf7914cb5a9519945bff1d95b'), hex2bin('417beeb2dddf45d1b90188fd211343c3'), hex2bin('bd5e2dcf547e4df6bb1ff58a554bc69e'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff')],
            ]
        );
    }

    private function importCatalog(string $tenantId)
    {
        $this->importTable(
            $tenantId,
            'catalog',
            ['id', 'name'],
            ['tenant_id'],
            [
                [hex2bin('ffffffffffffffffffffffffffffffff'), 'Default catalog'],
            ]
        );
    }

    private function importLanguage(string $tenantId)
    {
        $this->importTable(
            $tenantId,
            'language',
            ['id', 'name', 'parent_id', 'locale_id', 'locale_version_id'],
            ['tenant_id', 'parent_tenant_id', 'locale_tenant_id'],
            [
                [hex2bin('ffffffffffffffffffffffffffffffff'), 'Default language', null, hex2bin('2f3663edb7614308a60188c21c7963d5'), hex2bin('ffffffffffffffffffffffffffffffff')],
            ]
        );
    }

    private function importCountry(string $tenantId)
    {
        $this->importTable(
            $tenantId,
            'country',
            ['id', 'version_id', 'iso', 'country_area_id', 'position', 'shipping_free', 'tax_free', 'taxfree_for_vat_id', 'taxfree_vatid_checked', 'active', 'iso3', 'display_state_in_registration', 'force_state_in_registration', 'created_at', 'updated_at'],
            ['tenant_id', 'country_area_tenant_id'],
            [
                [hex2bin('ffe61e1c99154f9597014a310ab5482d'), hex2bin('ffffffffffffffffffffffffffffffff'), 'GR', hex2bin('dde2e7c598144e73ba03b093107ce5cf'), 10, 0, 0, 0, 0, 0, 'GRC', 0, 0, '2017-12-14 15:25:56', null],
                [hex2bin('6c72828ec5e240588a35114cf1d4d5ef'), hex2bin('ffffffffffffffffffffffffffffffff'), 'GB', hex2bin('dde2e7c598144e73ba03b093107ce5cf'), 10, 0, 0, 0, 0, 0, 'GBR', 0, 0, '2017-12-14 15:25:56', null],
                [hex2bin('584c3ff22f5644789705383bde891fc9'), hex2bin('ffffffffffffffffffffffffffffffff'), 'IE', hex2bin('dde2e7c598144e73ba03b093107ce5cf'), 10, 0, 0, 0, 0, 0, 'IRL', 0, 0, '2017-12-14 15:25:56', null],
                [hex2bin('b72b9b7cd26b4a40a36f2e76a1bf42c1'), hex2bin('ffffffffffffffffffffffffffffffff'), 'IS', hex2bin('dde2e7c598144e73ba03b093107ce5cf'), 10, 0, 0, 0, 0, 0, 'ISL', 0, 0, '2017-12-14 15:25:56', null],
                [hex2bin('92ca022e9d28492e9ea173f279fa6755'), hex2bin('ffffffffffffffffffffffffffffffff'), 'IT', hex2bin('dde2e7c598144e73ba03b093107ce5cf'), 10, 0, 0, 0, 0, 1, 'ITA', 0, 0, '2017-12-14 15:25:56', null],
                [hex2bin('e130d974fd6c438485972fe00b5cd609'), hex2bin('ffffffffffffffffffffffffffffffff'), 'JP', hex2bin('e0353dc44ccb465a9c980aa6758abcf6'), 10, 0, 0, 0, 0, 0, 'JPN', 0, 0, '2017-12-14 15:25:56', null],
                [hex2bin('a453634acb414768b2542ae9a57639b5'), hex2bin('ffffffffffffffffffffffffffffffff'), 'CA', hex2bin('e0353dc44ccb465a9c980aa6758abcf6'), 10, 0, 0, 0, 0, 0, 'CAN', 0, 0, '2017-12-14 15:25:56', null],
                [hex2bin('e5cbe4b2105843c3bdef2e9c03eccaae'), hex2bin('ffffffffffffffffffffffffffffffff'), 'LU', hex2bin('dde2e7c598144e73ba03b093107ce5cf'), 10, 0, 0, 0, 0, 0, 'LUX', 0, 0, '2017-12-14 15:25:56', null],
                [hex2bin('bd5e2dcf547e4df6bb1ff58a554bc69e'), hex2bin('ffffffffffffffffffffffffffffffff'), 'DE', hex2bin('5cff02b1029741a4891c430bcd9e3603'), 1, 0, 0, 0, 0, 1, 'DEU', 0, 0, '2017-12-14 15:25:56', null],
                [hex2bin('9deee5660fd1474fbecdf6fc1809add3'), hex2bin('ffffffffffffffffffffffffffffffff'), 'NA', hex2bin('e0353dc44ccb465a9c980aa6758abcf6'), 10, 0, 0, 0, 0, 0, 'NAM', 0, 0, '2017-12-14 15:25:56', null],
                [hex2bin('04ed51ccbb2341bc9b352d78e64213fb'), hex2bin('ffffffffffffffffffffffffffffffff'), 'NL', hex2bin('dde2e7c598144e73ba03b093107ce5cf'), 10, 0, 0, 0, 0, 1, 'NLD', 0, 0, '2017-12-14 15:25:56', null],
                [hex2bin('e216449bd67646cc9176a1d57a2f8094'), hex2bin('ffffffffffffffffffffffffffffffff'), 'NO', hex2bin('dde2e7c598144e73ba03b093107ce5cf'), 10, 0, 0, 0, 0, 0, 'NOR', 0, 0, '2017-12-14 15:25:56', null],
                [hex2bin('c650574d63d34834b836d8e7f0339ca8'), hex2bin('ffffffffffffffffffffffffffffffff'), 'AT', hex2bin('dde2e7c598144e73ba03b093107ce5cf'), 1, 0, 0, 0, 0, 1, 'AUT', 0, 0, '2017-12-14 15:25:56', null],
                [hex2bin('a40ed5b07bca4b06995a0a56b1170155'), hex2bin('ffffffffffffffffffffffffffffffff'), 'PT', hex2bin('dde2e7c598144e73ba03b093107ce5cf'), 10, 0, 0, 0, 0, 0, 'PRT', 0, 0, '2017-12-14 15:25:56', null],
                [hex2bin('f7b0810e24234ae397c769b260a02474'), hex2bin('ffffffffffffffffffffffffffffffff'), 'SE', hex2bin('dde2e7c598144e73ba03b093107ce5cf'), 10, 0, 0, 0, 0, 0, 'SWE', 0, 0, '2017-12-14 15:25:56', null],
                [hex2bin('4f52e121f6724b968c00d05829f9a38d'), hex2bin('ffffffffffffffffffffffffffffffff'), 'CH', hex2bin('dde2e7c598144e73ba03b093107ce5cf'), 10, 0, 1, 0, 0, 1, 'CHE', 0, 0, '2017-12-14 15:25:56', null],
                [hex2bin('2aba3f2990e044c78bf53b87fb6c3af3'), hex2bin('ffffffffffffffffffffffffffffffff'), 'ES', hex2bin('dde2e7c598144e73ba03b093107ce5cf'), 10, 0, 0, 0, 0, 1, 'ESP', 0, 0, '2017-12-14 15:25:56', null],
                [hex2bin('bdcb207c87ab4648b5acde9138f48894'), hex2bin('ffffffffffffffffffffffffffffffff'), 'US', hex2bin('e0353dc44ccb465a9c980aa6758abcf6'), 10, 0, 0, 0, 0, 0, 'USA', 0, 0, '2017-12-14 15:25:56', null],
                [hex2bin('e163778197a24b61bd2ae72d006a6d3c'), hex2bin('ffffffffffffffffffffffffffffffff'), 'LI', hex2bin('dde2e7c598144e73ba03b093107ce5cf'), 10, 0, 0, 0, 0, 0, 'LIE', 0, 0, '2017-12-14 15:25:56', null],
                [hex2bin('448744a58b9f44e58c40804fef6520f8'), hex2bin('ffffffffffffffffffffffffffffffff'), 'AE', hex2bin('e0353dc44ccb465a9c980aa6758abcf6'), 10, 0, 0, 0, 0, 1, 'ARE', 0, 0, '2017-12-14 15:25:56', null],
                [hex2bin('259f7c2be0b44eb6a273a70ea6dd8029'), hex2bin('ffffffffffffffffffffffffffffffff'), 'PL', hex2bin('dde2e7c598144e73ba03b093107ce5cf'), 10, 0, 0, 0, 0, 0, 'POL', 0, 0, '2017-12-14 15:25:56', null],
                [hex2bin('d99834aefa4941b490dae37d0027f6bc'), hex2bin('ffffffffffffffffffffffffffffffff'), 'HU', hex2bin('dde2e7c598144e73ba03b093107ce5cf'), 10, 0, 0, 0, 0, 0, 'HUN', 0, 0, '2017-12-14 15:25:56', null],
                [hex2bin('11cf2cdd303c41d7bf66808bfe7769a5'), hex2bin('ffffffffffffffffffffffffffffffff'), 'TR', hex2bin('e0353dc44ccb465a9c980aa6758abcf6'), 10, 0, 0, 0, 0, 0, 'TUR', 0, 0, '2017-12-14 15:25:56', null],
                [hex2bin('b240408078894b0491634af5963f0c04'), hex2bin('ffffffffffffffffffffffffffffffff'), 'CZ', hex2bin('dde2e7c598144e73ba03b093107ce5cf'), 10, 0, 0, 0, 0, 0, 'CZE', 0, 0, '2017-12-14 15:25:56', null],
                [hex2bin('8021ae3dd9ec4675920c16152473e5cc'), hex2bin('ffffffffffffffffffffffffffffffff'), 'SK', hex2bin('dde2e7c598144e73ba03b093107ce5cf'), 10, 0, 0, 0, 0, 0, 'SVK', 0, 0, '2017-12-14 15:25:56', null],
                [hex2bin('1d56b07f6a5e4ee0a4e23abc06ba9b1e'), hex2bin('ffffffffffffffffffffffffffffffff'), 'RO', hex2bin('dde2e7c598144e73ba03b093107ce5cf'), 10, 0, 0, 0, 0, 0, 'ROU', 0, 0, '2017-12-14 15:25:56', null],
                [hex2bin('68fea9f12c9c46748382b1f48a32014f'), hex2bin('ffffffffffffffffffffffffffffffff'), 'BR', hex2bin('e0353dc44ccb465a9c980aa6758abcf6'), 10, 0, 0, 0, 0, 0, 'BRA', 0, 0, '2017-12-14 15:25:56', null],
                [hex2bin('1c91bf01a6a547a78497abf7b8e4e5db'), hex2bin('ffffffffffffffffffffffffffffffff'), 'IL', hex2bin('e0353dc44ccb465a9c980aa6758abcf6'), 10, 0, 0, 0, 0, 0, 'ISR', 0, 0, '2017-12-14 15:25:56', null],
                [hex2bin('5a4aa22452e04acca23185d4d21bb3bf'), hex2bin('ffffffffffffffffffffffffffffffff'), 'AU', hex2bin('e0353dc44ccb465a9c980aa6758abcf6'), 10, 0, 0, 0, 0, 1, 'AUS', 0, 0, '2017-12-14 15:25:56', null],
                [hex2bin('2e54611a053b4b19afccca547f50bf56'), hex2bin('ffffffffffffffffffffffffffffffff'), 'BE', hex2bin('dde2e7c598144e73ba03b093107ce5cf'), 10, 0, 0, 0, 0, 1, 'BEL', 0, 0, '2017-12-14 15:25:56', null],
                [hex2bin('1d7911d918714c3ea9f0d7339afd3d43'), hex2bin('ffffffffffffffffffffffffffffffff'), 'DK', hex2bin('dde2e7c598144e73ba03b093107ce5cf'), 10, 0, 0, 0, 0, 1, 'DNK', 0, 0, '2017-12-14 15:25:56', null],
                [hex2bin('9d8661d69c10416c858dbf408ec2500a'), hex2bin('ffffffffffffffffffffffffffffffff'), 'FI', hex2bin('dde2e7c598144e73ba03b093107ce5cf'), 10, 0, 0, 0, 0, 1, 'FIN', 0, 0, '2017-12-14 15:25:56', null],
                [hex2bin('e85c25e1cdfc4cd4af49d54c34aa3d25'), hex2bin('ffffffffffffffffffffffffffffffff'), 'FR', hex2bin('dde2e7c598144e73ba03b093107ce5cf'), 10, 0, 0, 0, 0, 0, 'FRA', 0, 0, '2017-12-14 15:25:56', null],
            ]
        );

        $this->importTable(
            $tenantId,
            'country_translation',
            ['country_id', 'country_version_id', 'language_id', 'name'],
            ['country_tenant_id', 'language_tenant_id'],
            [
                [hex2bin('ffe61e1c99154f9597014a310ab5482d'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Griechenland'],
                [hex2bin('6c72828ec5e240588a35114cf1d4d5ef'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Großbritannien'],
                [hex2bin('584c3ff22f5644789705383bde891fc9'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Irland'],
                [hex2bin('b72b9b7cd26b4a40a36f2e76a1bf42c1'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Island'],
                [hex2bin('92ca022e9d28492e9ea173f279fa6755'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Italien'],
                [hex2bin('e130d974fd6c438485972fe00b5cd609'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Japan'],
                [hex2bin('a453634acb414768b2542ae9a57639b5'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Kanada'],
                [hex2bin('e5cbe4b2105843c3bdef2e9c03eccaae'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Luxemburg'],
                [hex2bin('bd5e2dcf547e4df6bb1ff58a554bc69e'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Deutschland'],
                [hex2bin('9deee5660fd1474fbecdf6fc1809add3'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Namibia'],
                [hex2bin('04ed51ccbb2341bc9b352d78e64213fb'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Niederlande'],
                [hex2bin('e216449bd67646cc9176a1d57a2f8094'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Norwegen'],
                [hex2bin('c650574d63d34834b836d8e7f0339ca8'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Österreich'],
                [hex2bin('a40ed5b07bca4b06995a0a56b1170155'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Portugal'],
                [hex2bin('f7b0810e24234ae397c769b260a02474'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Schweden'],
                [hex2bin('4f52e121f6724b968c00d05829f9a38d'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Schweiz'],
                [hex2bin('2aba3f2990e044c78bf53b87fb6c3af3'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Spanien'],
                [hex2bin('bdcb207c87ab4648b5acde9138f48894'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'USA'],
                [hex2bin('e163778197a24b61bd2ae72d006a6d3c'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Liechtenstein'],
                [hex2bin('448744a58b9f44e58c40804fef6520f8'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Arabische Emirate'],
                [hex2bin('259f7c2be0b44eb6a273a70ea6dd8029'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Polen'],
                [hex2bin('d99834aefa4941b490dae37d0027f6bc'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Ungarn'],
                [hex2bin('11cf2cdd303c41d7bf66808bfe7769a5'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Türkei'],
                [hex2bin('b240408078894b0491634af5963f0c04'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Tschechien'],
                [hex2bin('8021ae3dd9ec4675920c16152473e5cc'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Slowakei'],
                [hex2bin('1d56b07f6a5e4ee0a4e23abc06ba9b1e'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Rumänien'],
                [hex2bin('68fea9f12c9c46748382b1f48a32014f'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Brasilien'],
                [hex2bin('1c91bf01a6a547a78497abf7b8e4e5db'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Israel'],
                [hex2bin('5a4aa22452e04acca23185d4d21bb3bf'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Australien'],
                [hex2bin('2e54611a053b4b19afccca547f50bf56'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Belgien'],
                [hex2bin('1d7911d918714c3ea9f0d7339afd3d43'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Dänemark'],
                [hex2bin('9d8661d69c10416c858dbf408ec2500a'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Finnland'],
                [hex2bin('e85c25e1cdfc4cd4af49d54c34aa3d25'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Frankreich'],
            ]
        );
    }

    private function importCountryArea(string $tenantId)
    {
        $this->importTable(
            $tenantId,
            'country_area',
            ['id', 'version_id', 'active', 'created_at', 'updated_at'],
            ['tenant_id'],
            [
                [hex2bin('5cff02b1029741a4891c430bcd9e3603'), hex2bin('ffffffffffffffffffffffffffffffff'), 1, '2017-12-14 15:25:56', null],
                [hex2bin('e0353dc44ccb465a9c980aa6758abcf6'), hex2bin('ffffffffffffffffffffffffffffffff'), 1, '2017-12-14 15:25:56', null],
                [hex2bin('dde2e7c598144e73ba03b093107ce5cf'), hex2bin('ffffffffffffffffffffffffffffffff'), 1, '2017-12-14 15:25:56', null],
            ]
        );

        $this->importTable(
            $tenantId,
            'country_area_translation',
            ['country_area_id', 'country_area_version_id', 'language_id', 'name'],
            ['country_area_tenant_id', 'language_tenant_id'],
            [
                [hex2bin('5cff02b1029741a4891c430bcd9e3603'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'deutschland'],
                [hex2bin('e0353dc44ccb465a9c980aa6758abcf6'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'welt'],
                [hex2bin('dde2e7c598144e73ba03b093107ce5cf'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'europa'],
            ]
        );
    }

    private function importCountryState(string $tenantId)
    {
        $this->importTable(
            $tenantId,
            'country_state',
            ['id', 'country_id', 'country_version_id', 'version_id', 'short_code', 'position', 'active', 'created_at', 'updated_at'],
            ['tenant_id', 'country_tenant_id'],
            [
                [hex2bin('7adc050dffa94b89ae4c834ecc456ee6'), hex2bin('bd5e2dcf547e4df6bb1ff58a554bc69e'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'HH', 0, 1, '2017-12-14 15:25:56', null],
                [hex2bin('14226386aa114ffea37bb639651bee5c'), hex2bin('bd5e2dcf547e4df6bb1ff58a554bc69e'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'HE', 0, 1, '2017-12-14 15:25:56', null],
                [hex2bin('05d7d9eb32dd4067a9b45c1630f637df'), hex2bin('bd5e2dcf547e4df6bb1ff58a554bc69e'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'MV', 0, 1, '2017-12-14 15:25:56', null],
                [hex2bin('552634a945dc42ca99d4da322015efff'), hex2bin('bd5e2dcf547e4df6bb1ff58a554bc69e'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'RP', 0, 1, '2017-12-14 15:25:56', null],
                [hex2bin('8db06cf9d2864542bc20721a4fd33d29'), hex2bin('bd5e2dcf547e4df6bb1ff58a554bc69e'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'SL', 0, 1, '2017-12-14 15:25:56', null],
                [hex2bin('feca62f3423d414b894a2942754cbdba'), hex2bin('bd5e2dcf547e4df6bb1ff58a554bc69e'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'SN', 0, 1, '2017-12-14 15:25:56', null],
                [hex2bin('002978df504b42a889b6772f1fa38352'), hex2bin('bd5e2dcf547e4df6bb1ff58a554bc69e'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'ST', 0, 1, '2017-12-14 15:25:56', null],
                [hex2bin('772d9c7ff07446d7aa050acb6d883675'), hex2bin('bd5e2dcf547e4df6bb1ff58a554bc69e'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'SH', 0, 1, '2017-12-14 15:25:56', null],
                [hex2bin('a871a61bddf247e1b80152f411769427'), hex2bin('bd5e2dcf547e4df6bb1ff58a554bc69e'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'TH', 0, 1, '2017-12-14 15:25:56', null],
                [hex2bin('371caef5a4df401bbed6cc47730a0a25'), hex2bin('bd5e2dcf547e4df6bb1ff58a554bc69e'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'NI', 0, 1, '2017-12-14 15:25:56', null],
                [hex2bin('d7b6246e97ba4fe7b8b0f806726e7692'), hex2bin('bdcb207c87ab4648b5acde9138f48894'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'AL', 0, 1, '2017-12-14 15:25:56', null],
                [hex2bin('4c167be7eb3046f0b10c6f5f1c45d3bb'), hex2bin('bdcb207c87ab4648b5acde9138f48894'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'AK', 0, 1, '2017-12-14 15:25:56', null],
                [hex2bin('1fd9081b629f41b7bfc2364f99b7e57f'), hex2bin('bdcb207c87ab4648b5acde9138f48894'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'AZ', 0, 1, '2017-12-14 15:25:56', null],
                [hex2bin('9fe1b4959d1b437e8315321babc393d0'), hex2bin('bdcb207c87ab4648b5acde9138f48894'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'AR', 0, 1, '2017-12-14 15:25:56', null],
                [hex2bin('a95a10cd03da4700986a7b027c3a0e31'), hex2bin('bdcb207c87ab4648b5acde9138f48894'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'CA', 0, 1, '2017-12-14 15:25:56', null],
                [hex2bin('78ba54ad32994d83a5171312465ad1db'), hex2bin('bdcb207c87ab4648b5acde9138f48894'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'CO', 0, 1, '2017-12-14 15:25:56', null],
                [hex2bin('2c0ad3e249164a4faa4c242e104a4b02'), hex2bin('bdcb207c87ab4648b5acde9138f48894'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'CT', 0, 1, '2017-12-14 15:25:56', null],
                [hex2bin('6de89c3960804cef8f8d983c5348d289'), hex2bin('bdcb207c87ab4648b5acde9138f48894'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'DE', 0, 1, '2017-12-14 15:25:56', null],
                [hex2bin('7f7b26bc822b4299b1ad36663172dc14'), hex2bin('bdcb207c87ab4648b5acde9138f48894'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'FL', 0, 1, '2017-12-14 15:25:56', null],
                [hex2bin('0115cb9a4e5f43ab9e713dfd5fd2ef00'), hex2bin('bdcb207c87ab4648b5acde9138f48894'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'GA', 0, 1, '2017-12-14 15:25:56', null],
                [hex2bin('9f834bad88204d9896f31993624ac74c'), hex2bin('bd5e2dcf547e4df6bb1ff58a554bc69e'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'NW', 0, 1, '2017-12-14 15:25:56', null],
                [hex2bin('134aac207e3d421083689a39bd3877cc'), hex2bin('bdcb207c87ab4648b5acde9138f48894'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'HI', 0, 1, '2017-12-14 15:25:56', null],
                [hex2bin('e7519503db3648f9a48cefe5e17be567'), hex2bin('bdcb207c87ab4648b5acde9138f48894'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'ID', 0, 1, '2017-12-14 15:25:56', null],
                [hex2bin('9d20b9e37d4e4adfbd66ac85658c828d'), hex2bin('bdcb207c87ab4648b5acde9138f48894'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'IL', 0, 1, '2017-12-14 15:25:56', null],
                [hex2bin('29a45b771956491a99f1184d027dc7f1'), hex2bin('bdcb207c87ab4648b5acde9138f48894'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'IN', 0, 1, '2017-12-14 15:25:56', null],
                [hex2bin('16dc652542a94e69b192353d24bdc2de'), hex2bin('bdcb207c87ab4648b5acde9138f48894'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'IA', 0, 1, '2017-12-14 15:25:56', null],
                [hex2bin('022117c5d92a45acacdba7c0c9110293'), hex2bin('bdcb207c87ab4648b5acde9138f48894'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'KS', 0, 1, '2017-12-14 15:25:56', null],
                [hex2bin('a2874ca93fb34d0588e88f18e93a1f3d'), hex2bin('bdcb207c87ab4648b5acde9138f48894'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'KY', 0, 1, '2017-12-14 15:25:56', null],
                [hex2bin('cc3cd9f9330d466c87e4e2b3fb69ada1'), hex2bin('bdcb207c87ab4648b5acde9138f48894'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'LA', 0, 1, '2017-12-14 15:25:56', null],
                [hex2bin('eea743e3f5c9406eabfab562305a61c7'), hex2bin('bdcb207c87ab4648b5acde9138f48894'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'ME', 0, 1, '2017-12-14 15:25:56', null],
                [hex2bin('a0b885eb852a4cf982e9c1e0dde17159'), hex2bin('bdcb207c87ab4648b5acde9138f48894'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'MD', 0, 1, '2017-12-14 15:25:56', null],
                [hex2bin('dd18e22cde914bd9910a63cf6ec86630'), hex2bin('bdcb207c87ab4648b5acde9138f48894'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'MA', 0, 1, '2017-12-14 15:25:56', null],
                [hex2bin('03807fd52c9e40e192f8b11f2f14a538'), hex2bin('bdcb207c87ab4648b5acde9138f48894'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'MI', 0, 1, '2017-12-14 15:25:56', null],
                [hex2bin('aeb332f67f7d4210971d1c6c1a010d16'), hex2bin('bdcb207c87ab4648b5acde9138f48894'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'MN', 0, 1, '2017-12-14 15:25:56', null],
                [hex2bin('d7279d6ab300431c8004aa8c4617142a'), hex2bin('bdcb207c87ab4648b5acde9138f48894'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'MS', 0, 1, '2017-12-14 15:25:56', null],
                [hex2bin('2b43a2233005467b925f4ee69d9e1346'), hex2bin('bdcb207c87ab4648b5acde9138f48894'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'MO', 0, 1, '2017-12-14 15:25:56', null],
                [hex2bin('3c91bc17fa534870b2d3d24d1b2d42bd'), hex2bin('bdcb207c87ab4648b5acde9138f48894'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'MT', 0, 1, '2017-12-14 15:25:56', null],
                [hex2bin('005e91e909e84a738e28ef398df8c361'), hex2bin('bdcb207c87ab4648b5acde9138f48894'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'NE', 0, 1, '2017-12-14 15:25:56', null],
                [hex2bin('1565cb07aba54a76ae9e0fb5b15a53e6'), hex2bin('bdcb207c87ab4648b5acde9138f48894'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'NV', 0, 1, '2017-12-14 15:25:56', null],
                [hex2bin('e8dc65d4ca8f4bde85b4f16067c75abe'), hex2bin('bdcb207c87ab4648b5acde9138f48894'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'NH', 0, 1, '2017-12-14 15:25:56', null],
                [hex2bin('c25f6f25a4d54e719b39e53a51df2dac'), hex2bin('bdcb207c87ab4648b5acde9138f48894'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'NJ', 0, 1, '2017-12-14 15:25:56', null],
                [hex2bin('68cfb65c5bf9480c9f1cd4b88fd64afc'), hex2bin('bd5e2dcf547e4df6bb1ff58a554bc69e'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'BW', 0, 1, '2017-12-14 15:25:56', null],
                [hex2bin('b8d7147a03f24cd7af834bd4b27ecc11'), hex2bin('bdcb207c87ab4648b5acde9138f48894'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'NM', 0, 1, '2017-12-14 15:25:56', null],
                [hex2bin('4ad533b8633e4e8499e4fc181f7be68b'), hex2bin('bdcb207c87ab4648b5acde9138f48894'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'NY', 0, 1, '2017-12-14 15:25:56', null],
                [hex2bin('e0d1f234397545a6a696b53c4cc68e1a'), hex2bin('bdcb207c87ab4648b5acde9138f48894'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'NC', 0, 1, '2017-12-14 15:25:56', null],
                [hex2bin('8b1c7f38492b40bc86c1646e5b3d28ab'), hex2bin('bdcb207c87ab4648b5acde9138f48894'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'ND', 0, 1, '2017-12-14 15:25:56', null],
                [hex2bin('c0266b916faa4ddc8577e51bff8c4ac1'), hex2bin('bdcb207c87ab4648b5acde9138f48894'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'OH', 0, 1, '2017-12-14 15:25:56', null],
                [hex2bin('112dfdd5a55f480b8c7b7a2e397bac9f'), hex2bin('bdcb207c87ab4648b5acde9138f48894'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'OK', 0, 1, '2017-12-14 15:25:56', null],
                [hex2bin('34084f4a584e417898b9eb71b3e8785c'), hex2bin('bdcb207c87ab4648b5acde9138f48894'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'OR', 0, 1, '2017-12-14 15:25:56', null],
                [hex2bin('d0c988f6f82e4491b19f7f604f83e904'), hex2bin('bdcb207c87ab4648b5acde9138f48894'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'PA', 0, 1, '2017-12-14 15:25:56', null],
                [hex2bin('697d50726d4f4ef0935b06094c26eb17'), hex2bin('bdcb207c87ab4648b5acde9138f48894'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'RI', 0, 1, '2017-12-14 15:25:56', null],
                [hex2bin('cdfee470ea214942b2659f1b59319e9d'), hex2bin('bdcb207c87ab4648b5acde9138f48894'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'SC', 0, 1, '2017-12-14 15:25:56', null],
                [hex2bin('2e3bc98e36f94a0786ddd4b1f14f05c5'), hex2bin('bd5e2dcf547e4df6bb1ff58a554bc69e'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'BY', 0, 1, '2017-12-14 15:25:56', null],
                [hex2bin('8467df0d6571438394efd9e86b882eb0'), hex2bin('bdcb207c87ab4648b5acde9138f48894'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'SD', 0, 1, '2017-12-14 15:25:56', null],
                [hex2bin('46a14761feea4ae29490c9d04ff8aa2c'), hex2bin('bdcb207c87ab4648b5acde9138f48894'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'TN', 0, 1, '2017-12-14 15:25:56', null],
                [hex2bin('1e279e21defe4db3a71bcda7e41da67e'), hex2bin('bdcb207c87ab4648b5acde9138f48894'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'TX', 0, 1, '2017-12-14 15:25:56', null],
                [hex2bin('1d4c9fa0b1494b41a679dafe13cdddf1'), hex2bin('bdcb207c87ab4648b5acde9138f48894'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'UT', 0, 1, '2017-12-14 15:25:56', null],
                [hex2bin('28bb6e5fd903425985015b4bb119998a'), hex2bin('bdcb207c87ab4648b5acde9138f48894'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'VT', 0, 1, '2017-12-14 15:25:56', null],
                [hex2bin('8bdc6f449a2143fb8c9e1df5baf1848b'), hex2bin('bdcb207c87ab4648b5acde9138f48894'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'VA', 0, 1, '2017-12-14 15:25:56', null],
                [hex2bin('b514988533454fba84e10495bb2d8f4c'), hex2bin('bdcb207c87ab4648b5acde9138f48894'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'WA', 0, 1, '2017-12-14 15:25:56', null],
                [hex2bin('b5282cfa3c94480b8ed178f4302797bf'), hex2bin('bdcb207c87ab4648b5acde9138f48894'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'WV', 0, 1, '2017-12-14 15:25:56', null],
                [hex2bin('0dcd436e8b2e4fc9928ba95c2987aa3d'), hex2bin('bdcb207c87ab4648b5acde9138f48894'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'WI', 0, 1, '2017-12-14 15:25:56', null],
                [hex2bin('177bbd1d3fcb4ed8b0c766209cd47982'), hex2bin('bdcb207c87ab4648b5acde9138f48894'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'WY', 0, 1, '2017-12-14 15:25:56', null],
                [hex2bin('aa58264d165f418cb977724eaedcbc7c'), hex2bin('bd5e2dcf547e4df6bb1ff58a554bc69e'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'BE', 0, 1, '2017-12-14 15:25:56', null],
                [hex2bin('72693bfe1a3745c898bf223e7d159509'), hex2bin('bd5e2dcf547e4df6bb1ff58a554bc69e'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'BB', 0, 1, '2017-12-14 15:25:56', null],
                [hex2bin('d39df6c2540b452a9aa09d5347638319'), hex2bin('bd5e2dcf547e4df6bb1ff58a554bc69e'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'HB', 0, 1, '2017-12-14 15:25:56', null],
            ]
        );

        $this->importTable(
            $tenantId,
            'country_state_translation',
            ['country_state_id', 'country_state_version_id',  'language_id', 'name'],
            ['country_state_tenant_id', 'language_tenant_id'],
            [
                [hex2bin('7adc050dffa94b89ae4c834ecc456ee6'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Hamburg'],
                [hex2bin('14226386aa114ffea37bb639651bee5c'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Hessen'],
                [hex2bin('05d7d9eb32dd4067a9b45c1630f637df'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Mecklenburg-Vorpommern'],
                [hex2bin('552634a945dc42ca99d4da322015efff'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Rheinland-Pfalz'],
                [hex2bin('8db06cf9d2864542bc20721a4fd33d29'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Saarland'],
                [hex2bin('feca62f3423d414b894a2942754cbdba'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Sachsen'],
                [hex2bin('002978df504b42a889b6772f1fa38352'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Sachsen-Anhalt'],
                [hex2bin('772d9c7ff07446d7aa050acb6d883675'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Schleswig-Holstein'],
                [hex2bin('a871a61bddf247e1b80152f411769427'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Thüringen'],
                [hex2bin('371caef5a4df401bbed6cc47730a0a25'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Niedersachsen'],
                [hex2bin('d7b6246e97ba4fe7b8b0f806726e7692'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Alabama'],
                [hex2bin('4c167be7eb3046f0b10c6f5f1c45d3bb'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Alaska'],
                [hex2bin('1fd9081b629f41b7bfc2364f99b7e57f'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Arizona'],
                [hex2bin('9fe1b4959d1b437e8315321babc393d0'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Arkansas'],
                [hex2bin('a95a10cd03da4700986a7b027c3a0e31'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Kalifornien'],
                [hex2bin('78ba54ad32994d83a5171312465ad1db'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Colorado'],
                [hex2bin('2c0ad3e249164a4faa4c242e104a4b02'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Connecticut'],
                [hex2bin('6de89c3960804cef8f8d983c5348d289'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Delaware'],
                [hex2bin('7f7b26bc822b4299b1ad36663172dc14'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Florida'],
                [hex2bin('0115cb9a4e5f43ab9e713dfd5fd2ef00'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Georgia'],
                [hex2bin('9f834bad88204d9896f31993624ac74c'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Nordrhein-Westfalen'],
                [hex2bin('134aac207e3d421083689a39bd3877cc'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Hawaii'],
                [hex2bin('e7519503db3648f9a48cefe5e17be567'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Idaho'],
                [hex2bin('9d20b9e37d4e4adfbd66ac85658c828d'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Illinois'],
                [hex2bin('29a45b771956491a99f1184d027dc7f1'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Indiana'],
                [hex2bin('16dc652542a94e69b192353d24bdc2de'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Iowa'],
                [hex2bin('022117c5d92a45acacdba7c0c9110293'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Kansas'],
                [hex2bin('a2874ca93fb34d0588e88f18e93a1f3d'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Kentucky'],
                [hex2bin('cc3cd9f9330d466c87e4e2b3fb69ada1'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Louisiana'],
                [hex2bin('eea743e3f5c9406eabfab562305a61c7'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Maine'],
                [hex2bin('a0b885eb852a4cf982e9c1e0dde17159'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Maryland'],
                [hex2bin('dd18e22cde914bd9910a63cf6ec86630'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Massachusetts'],
                [hex2bin('03807fd52c9e40e192f8b11f2f14a538'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Michigan'],
                [hex2bin('aeb332f67f7d4210971d1c6c1a010d16'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Minnesota'],
                [hex2bin('d7279d6ab300431c8004aa8c4617142a'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Mississippi'],
                [hex2bin('2b43a2233005467b925f4ee69d9e1346'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Missouri'],
                [hex2bin('3c91bc17fa534870b2d3d24d1b2d42bd'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Montana'],
                [hex2bin('005e91e909e84a738e28ef398df8c361'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Nebraska'],
                [hex2bin('1565cb07aba54a76ae9e0fb5b15a53e6'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Nevada'],
                [hex2bin('e8dc65d4ca8f4bde85b4f16067c75abe'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'New Hampshire'],
                [hex2bin('c25f6f25a4d54e719b39e53a51df2dac'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'New Jersey'],
                [hex2bin('68cfb65c5bf9480c9f1cd4b88fd64afc'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Baden-Württemberg'],
                [hex2bin('b8d7147a03f24cd7af834bd4b27ecc11'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'New Mexico'],
                [hex2bin('4ad533b8633e4e8499e4fc181f7be68b'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'New York'],
                [hex2bin('e0d1f234397545a6a696b53c4cc68e1a'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'North Carolina'],
                [hex2bin('8b1c7f38492b40bc86c1646e5b3d28ab'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'North Dakota'],
                [hex2bin('c0266b916faa4ddc8577e51bff8c4ac1'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Ohio'],
                [hex2bin('112dfdd5a55f480b8c7b7a2e397bac9f'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Oklahoma'],
                [hex2bin('34084f4a584e417898b9eb71b3e8785c'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Oregon'],
                [hex2bin('d0c988f6f82e4491b19f7f604f83e904'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Pennsylvania'],
                [hex2bin('697d50726d4f4ef0935b06094c26eb17'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Rhode Island'],
                [hex2bin('cdfee470ea214942b2659f1b59319e9d'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'South Carolina'],
                [hex2bin('2e3bc98e36f94a0786ddd4b1f14f05c5'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Bayern'],
                [hex2bin('8467df0d6571438394efd9e86b882eb0'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'South Dakota'],
                [hex2bin('46a14761feea4ae29490c9d04ff8aa2c'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Tennessee'],
                [hex2bin('1e279e21defe4db3a71bcda7e41da67e'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Texas'],
                [hex2bin('1d4c9fa0b1494b41a679dafe13cdddf1'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Utah'],
                [hex2bin('28bb6e5fd903425985015b4bb119998a'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Vermont'],
                [hex2bin('8bdc6f449a2143fb8c9e1df5baf1848b'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Virginia'],
                [hex2bin('b514988533454fba84e10495bb2d8f4c'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Washington'],
                [hex2bin('b5282cfa3c94480b8ed178f4302797bf'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'West Virginia'],
                [hex2bin('0dcd436e8b2e4fc9928ba95c2987aa3d'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Wisconsin'],
                [hex2bin('177bbd1d3fcb4ed8b0c766209cd47982'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Wyoming'],
                [hex2bin('aa58264d165f418cb977724eaedcbc7c'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Berlin'],
                [hex2bin('72693bfe1a3745c898bf223e7d159509'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Brandenburg'],
                [hex2bin('d39df6c2540b452a9aa09d5347638319'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Bremen'],
            ]
        );
    }

    private function importOrderState(string $tenantId)
    {
        $this->importTable(
            $tenantId,
            'order_state',
            ['id', 'version_id', 'position', 'has_mail', 'created_at', 'updated_at'],
            ['tenant_id'],
            [
                [hex2bin('1194A493806742C9B85E61F1F2CF9BE8'), hex2bin('ffffffffffffffffffffffffffffffff'), 1, 0, '2018-01-08 09:12:15', null],
            ]
        );

        $this->importTable(
            $tenantId,
            'order_state_translation',
            ['description', 'order_state_version_id', 'order_state_id', 'language_id'],
            ['order_state_tenant_id', 'language_tenant_id'],
            [
                ['Offen', hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('1194A493806742C9B85E61F1F2CF9BE8'), hex2bin('ffffffffffffffffffffffffffffffff')],
            ]
        );
    }

    private function importOrderTransaction(string $tenantId)
    {
        $this->importTable(
            $tenantId,
            'order_transaction_state',
            ['id', 'version_id', 'position', 'has_mail', 'created_at', 'updated_at'],
            ['tenant_id'],
            [
                [hex2bin('099E79DBFA9F43E4876B172FF58359F2'), hex2bin('ffffffffffffffffffffffffffffffff'), 3, 0, '2018-03-08 10:45:04', null],
                [hex2bin('60025B03849340BA8D1ABF7E58AA2B9F'), hex2bin('ffffffffffffffffffffffffffffffff'), 1, 0, '2018-03-08 10:45:04', null],
                [hex2bin('B64BFC7F379144829365A6994A3B56E6'), hex2bin('ffffffffffffffffffffffffffffffff'), 2, 0, '2018-03-08 10:45:04', null],
            ]
        );

        $this->importTable(
            $tenantId,
            'order_transaction_state_translation',
            ['order_transaction_state_id', 'order_transaction_state_version_id', 'language_id', 'language_version_id', 'description'],
            ['order_transaction_state_tenant_id', 'language_tenant_id'],
            [
                [hex2bin('099E79DBFA9F43E4876B172FF58359F2'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('FFA32A50E2D04CF38389A53F8D6CD594'), hex2bin('FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFF'), 'Open'],
                [hex2bin('60025B03849340BA8D1ABF7E58AA2B9F'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('FFA32A50E2D04CF38389A53F8D6CD594'), hex2bin('FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFF'), 'Fully paid'],
                [hex2bin('B64BFC7F379144829365A6994A3B56E6'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('FFA32A50E2D04CF38389A53F8D6CD594'), hex2bin('FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFF'), 'Failed'],
            ]
        );
    }

    private function importCurrency(string $tenantId)
    {
        $this->importTable(
            $tenantId,
            'currency',
            ['id', 'version_id', 'is_default', 'factor', 'symbol', 'symbol_position', 'position', 'created_at', 'updated_at'],
            ['tenant_id'],
            [
                [hex2bin('4c8eba11bd3546d786afbed481a6e665'), hex2bin('ffffffffffffffffffffffffffffffff'), 1, 1, '€', 0, 0, '2017-12-14 15:25:57', null],
                [hex2bin('2824ea63db6741109e2378ddcc9cec84'), hex2bin('ffffffffffffffffffffffffffffffff'), 0, 1.3625, '$', 0, 0, '2017-12-14 15:25:57', null],
            ]
        );

        $this->importTable(
            $tenantId,
            'currency_translation',
            ['currency_id', 'currency_version_id', 'language_id', 'short_name', 'name'],
            ['currency_tenant_id', 'language_tenant_id'],
            [
                [hex2bin('4c8eba11bd3546d786afbed481a6e665'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'EUR', 'Euro'],
                [hex2bin('2824ea63db6741109e2378ddcc9cec84'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'USD', 'US-Dollar'],
            ]
        );
    }

    private function importCustomerGroup(string $tenantId)
    {
        $this->importTable(
            $tenantId,
            'customer_group',
            ['id', 'version_id', 'display_gross', 'input_gross', 'has_global_discount', 'percentage_global_discount', 'minimum_order_amount', 'minimum_order_amount_surcharge', 'created_at', 'updated_at'],
            ['tenant_id'],
            [
                [hex2bin('3294e6f6372b415fac7371cbc191548f'), hex2bin('ffffffffffffffffffffffffffffffff'), 1, 1, 0, 0, 10, 5, '2017-12-14 15:25:58', null],
                [hex2bin('60532312f7a74e9d90e59746958ac64e'), hex2bin('ffffffffffffffffffffffffffffffff'), 0, 0, 0, 0, 0, 0, '2017-12-14 15:25:58', null],
            ]
        );

        $this->importTable(
            $tenantId,
            'customer_group_translation',
            ['customer_group_id', 'customer_group_version_id', 'language_id', 'name'],
            ['customer_group_tenant_id', 'language_tenant_id'],
            [
                [hex2bin('3294e6f6372b415fac7371cbc191548f'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Shopkunden'],
                [hex2bin('60532312f7a74e9d90e59746958ac64e'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'B2B / Händler netto'],
            ]
        );
    }

    private function importLocale(string $tenantId)
    {
        $this->importTable(
            $tenantId,
            'locale',
            ['id', 'version_id', 'code', 'created_at', 'updated_at'],
            ['tenant_id'],
            [
                    [hex2bin('7b52d9dd2b0640ec90be9f57edf29be7'), hex2bin('ffffffffffffffffffffffffffffffff'), 'de_DE', '2017-12-14 15:25:59', null],
                    [hex2bin('6468611c0f774305a5ca265cb8b6adbb'), hex2bin('ffffffffffffffffffffffffffffffff'), 'ar_AE', '2017-12-14 15:25:59', null],
                    [hex2bin('ab09518a825942fbaaaf9e5951ae9113'), hex2bin('ffffffffffffffffffffffffffffffff'), 'fa_AF', '2017-12-14 15:25:59', null],
                    [hex2bin('1967fd0bde6644b2b6132b5694a19ecb'), hex2bin('ffffffffffffffffffffffffffffffff'), 'fa_IR', '2017-12-14 15:25:59', null],
                    [hex2bin('6b3fd72c2d77470a9842c41a63b27fd9'), hex2bin('ffffffffffffffffffffffffffffffff'), 'fi_FI', '2017-12-14 15:25:59', null],
                    [hex2bin('1f5092adda8940ae83d888c293e6c5e8'), hex2bin('ffffffffffffffffffffffffffffffff'), 'fil_PH', '2017-12-14 15:25:59', null],
                    [hex2bin('c6dfbe7cf0044de499c676f939061572'), hex2bin('ffffffffffffffffffffffffffffffff'), 'fo_FO', '2017-12-14 15:25:59', null],
                    [hex2bin('458029c478cf4de48cb1929af6b49f56'), hex2bin('ffffffffffffffffffffffffffffffff'), 'fr_BE', '2017-12-14 15:25:59', null],
                    [hex2bin('c9633f6c797f4344bfcdb9277fc0dcca'), hex2bin('ffffffffffffffffffffffffffffffff'), 'fr_CA', '2017-12-14 15:25:59', null],
                    [hex2bin('71f184e6fb914693842db2d2008aadf2'), hex2bin('ffffffffffffffffffffffffffffffff'), 'fr_CH', '2017-12-14 15:25:59', null],
                    [hex2bin('03fb597a642b447c97dfd3e1ebe3a8d4'), hex2bin('ffffffffffffffffffffffffffffffff'), 'fr_FR', '2017-12-14 15:25:59', null],
                    [hex2bin('7d0baf560b994fe18b7023674722bebb'), hex2bin('ffffffffffffffffffffffffffffffff'), 'fr_LU', '2017-12-14 15:25:59', null],
                    [hex2bin('1a166699bd364015aa872387d3770f84'), hex2bin('ffffffffffffffffffffffffffffffff'), 'ar_BH', '2017-12-14 15:25:59', null],
                    [hex2bin('8285eb7a14da48ceb7374eb4f16570f7'), hex2bin('ffffffffffffffffffffffffffffffff'), 'fr_MC', '2017-12-14 15:25:59', null],
                    [hex2bin('e78ac901fd9b4ed18bf28caaa77ee74b'), hex2bin('ffffffffffffffffffffffffffffffff'), 'fr_SN', '2017-12-14 15:25:59', null],
                    [hex2bin('a900012c8a81473aa7c8653a5c80f509'), hex2bin('ffffffffffffffffffffffffffffffff'), 'fur_IT', '2017-12-14 15:25:59', null],
                    [hex2bin('ccf55a76d7684b1f80dd7eb208c8ab25'), hex2bin('ffffffffffffffffffffffffffffffff'), 'ga_IE', '2017-12-14 15:25:59', null],
                    [hex2bin('cb5a4a5de2214e9f909692f36a1a10de'), hex2bin('ffffffffffffffffffffffffffffffff'), 'gaa_GH', '2017-12-14 15:25:59', null],
                    [hex2bin('91baad7dd12342a98f06079925271f0d'), hex2bin('ffffffffffffffffffffffffffffffff'), 'gez_ER', '2017-12-14 15:25:59', null],
                    [hex2bin('d9c9e0a82cfb42a5a253775436ad106c'), hex2bin('ffffffffffffffffffffffffffffffff'), 'gez_ET', '2017-12-14 15:25:59', null],
                    [hex2bin('275ee5ab76d84257ab6d4496c5e4e838'), hex2bin('ffffffffffffffffffffffffffffffff'), 'gl_ES', '2017-12-14 15:25:59', null],
                    [hex2bin('2ffe9f822c2442658eb1b6aad9ded850'), hex2bin('ffffffffffffffffffffffffffffffff'), 'gsw_CH', '2017-12-14 15:25:59', null],
                    [hex2bin('43abaa4456e0485ab5baafd39ef2fbc4'), hex2bin('ffffffffffffffffffffffffffffffff'), 'gu_IN', '2017-12-14 15:25:59', null],
                    [hex2bin('a4b7b6e6b9db4395b2279eaeb9065a08'), hex2bin('ffffffffffffffffffffffffffffffff'), 'ar_DZ', '2017-12-14 15:25:59', null],
                    [hex2bin('13de79afcfe041038052505cac84a3d6'), hex2bin('ffffffffffffffffffffffffffffffff'), 'gv_GB', '2017-12-14 15:25:59', null],
                    [hex2bin('ecd617039ffd42f4b2adafb0a1b93ca7'), hex2bin('ffffffffffffffffffffffffffffffff'), 'ha_GH', '2017-12-14 15:25:59', null],
                    [hex2bin('8c89cb4663e14855bc03dfdda8f99169'), hex2bin('ffffffffffffffffffffffffffffffff'), 'ha_NE', '2017-12-14 15:25:59', null],
                    [hex2bin('0ea82a89f6aa4c91b54323103c488bce'), hex2bin('ffffffffffffffffffffffffffffffff'), 'ha_NG', '2017-12-14 15:25:59', null],
                    [hex2bin('08492d3423e14114b4a8353787749308'), hex2bin('ffffffffffffffffffffffffffffffff'), 'ha_SD', '2017-12-14 15:25:59', null],
                    [hex2bin('5292a212064345a298faa3b0e8499c0e'), hex2bin('ffffffffffffffffffffffffffffffff'), 'haw_US', '2017-12-14 15:25:59', null],
                    [hex2bin('d3ec559bcbb34c619b1d566018013309'), hex2bin('ffffffffffffffffffffffffffffffff'), 'he_IL', '2017-12-14 15:25:59', null],
                    [hex2bin('bb8eb40270f8470c93f1b75f3fd0a7eb'), hex2bin('ffffffffffffffffffffffffffffffff'), 'hi_IN', '2017-12-14 15:25:59', null],
                    [hex2bin('31386b509e804455b3ec26e6c28f19fc'), hex2bin('ffffffffffffffffffffffffffffffff'), 'hr_HR', '2017-12-14 15:25:59', null],
                    [hex2bin('80b31abf23444673b7fdb0084fbc010e'), hex2bin('ffffffffffffffffffffffffffffffff'), 'hu_HU', '2017-12-14 15:25:59', null],
                    [hex2bin('2733adcf5eb94e3f9a31412f7a1294fa'), hex2bin('ffffffffffffffffffffffffffffffff'), 'ar_EG', '2017-12-14 15:25:59', null],
                    [hex2bin('6d3b2f751f364279979739bd2bd6b59e'), hex2bin('ffffffffffffffffffffffffffffffff'), 'hy_AM', '2017-12-14 15:25:59', null],
                    [hex2bin('2324485b21814b46ae0033dd3baef55c'), hex2bin('ffffffffffffffffffffffffffffffff'), 'id_ID', '2017-12-14 15:25:59', null],
                    [hex2bin('a6a5691372fa4c7a9ab14148bea51272'), hex2bin('ffffffffffffffffffffffffffffffff'), 'ig_NG', '2017-12-14 15:25:59', null],
                    [hex2bin('a930a9c1d5504a299e177d62e07e6b13'), hex2bin('ffffffffffffffffffffffffffffffff'), 'ii_CN', '2017-12-14 15:25:59', null],
                    [hex2bin('c626598c2e644640ad5282c854f37983'), hex2bin('ffffffffffffffffffffffffffffffff'), 'is_IS', '2017-12-14 15:25:59', null],
                    [hex2bin('4ccedb86c20d47bd8ff3e2b0e1154a9b'), hex2bin('ffffffffffffffffffffffffffffffff'), 'it_CH', '2017-12-14 15:25:59', null],
                    [hex2bin('7d105b4275cb4b3d963bfc85d41402d4'), hex2bin('ffffffffffffffffffffffffffffffff'), 'it_IT', '2017-12-14 15:25:59', null],
                    [hex2bin('aada3192db9b45229aef91fc1b787a27'), hex2bin('ffffffffffffffffffffffffffffffff'), 'ja_JP', '2017-12-14 15:25:59', null],
                    [hex2bin('6690c11d573945a3a41ea5e87d5a31a4'), hex2bin('ffffffffffffffffffffffffffffffff'), 'ka_GE', '2017-12-14 15:25:59', null],
                    [hex2bin('f3b31a636ee6471788ecb948b31cedf8'), hex2bin('ffffffffffffffffffffffffffffffff'), 'kaj_NG', '2017-12-14 15:25:59', null],
                    [hex2bin('7dc700a676164b4083b11b725052e6e1'), hex2bin('ffffffffffffffffffffffffffffffff'), 'ar_IQ', '2017-12-14 15:25:59', null],
                    [hex2bin('67dcdde95f3f4b319ab97f499f4adeb6'), hex2bin('ffffffffffffffffffffffffffffffff'), 'kam_KE', '2017-12-14 15:25:59', null],
                    [hex2bin('b36c66efaadb4b938cc231a8209fbb11'), hex2bin('ffffffffffffffffffffffffffffffff'), 'kcg_NG', '2017-12-14 15:25:59', null],
                    [hex2bin('f4aa1ab8ff424d1d8505fcc19486547f'), hex2bin('ffffffffffffffffffffffffffffffff'), 'kfo_CI', '2017-12-14 15:25:59', null],
                    [hex2bin('f2459a5f6a8443faa55978bfdf8eadbe'), hex2bin('ffffffffffffffffffffffffffffffff'), 'kk_KZ', '2017-12-14 15:25:59', null],
                    [hex2bin('bb6a8d38a10844df9ae0694455f24f99'), hex2bin('ffffffffffffffffffffffffffffffff'), 'kl_GL', '2017-12-14 15:25:59', null],
                    [hex2bin('ab695a27f6db46ba972f81eb2a0cea70'), hex2bin('ffffffffffffffffffffffffffffffff'), 'km_KH', '2017-12-14 15:25:59', null],
                    [hex2bin('b7327288b3ac4972aae10266d1f2eaed'), hex2bin('ffffffffffffffffffffffffffffffff'), 'kn_IN', '2017-12-14 15:25:59', null],
                    [hex2bin('29f5dd91d20c4f8796430babdb158cd3'), hex2bin('ffffffffffffffffffffffffffffffff'), 'ko_KR', '2017-12-14 15:25:59', null],
                    [hex2bin('b835923ea44c43608b20dc65cd9a67b9'), hex2bin('ffffffffffffffffffffffffffffffff'), 'kok_IN', '2017-12-14 15:25:59', null],
                    [hex2bin('f9dbc88d5e314a8da93e5ab1c128f22c'), hex2bin('ffffffffffffffffffffffffffffffff'), 'kpe_GN', '2017-12-14 15:25:59', null],
                    [hex2bin('25273dd0e22a4bb68bf443b0c2ee1ce3'), hex2bin('ffffffffffffffffffffffffffffffff'), 'ar_JO', '2017-12-14 15:25:59', null],
                    [hex2bin('63f1958f860e44f3b15acef13941063b'), hex2bin('ffffffffffffffffffffffffffffffff'), 'kpe_LR', '2017-12-14 15:25:59', null],
                    [hex2bin('8d02182d344e4d6582d3f8f0a40c1ec1'), hex2bin('ffffffffffffffffffffffffffffffff'), 'ku_IQ', '2017-12-14 15:25:59', null],
                    [hex2bin('33a84a106ab5490da59cb498f9585418'), hex2bin('ffffffffffffffffffffffffffffffff'), 'ku_IR', '2017-12-14 15:25:59', null],
                    [hex2bin('b25c0d582e3d49e596a56098b058806e'), hex2bin('ffffffffffffffffffffffffffffffff'), 'ku_SY', '2017-12-14 15:25:59', null],
                    [hex2bin('11d92697b6ed40029b201425228ef593'), hex2bin('ffffffffffffffffffffffffffffffff'), 'ku_TR', '2017-12-14 15:25:59', null],
                    [hex2bin('d5b5f6f1375748e6869550dac50534f7'), hex2bin('ffffffffffffffffffffffffffffffff'), 'kw_GB', '2017-12-14 15:25:59', null],
                    [hex2bin('ab6e568562af4e2e9f2b2dbe05b741fe'), hex2bin('ffffffffffffffffffffffffffffffff'), 'ky_KG', '2017-12-14 15:25:59', null],
                    [hex2bin('749023fbd35b4d3bbf6066564e50f5d1'), hex2bin('ffffffffffffffffffffffffffffffff'), 'ln_CD', '2017-12-14 15:25:59', null],
                    [hex2bin('c821fbb406cd42869497e3285dda6e11'), hex2bin('ffffffffffffffffffffffffffffffff'), 'ln_CG', '2017-12-14 15:25:59', null],
                    [hex2bin('70620c7ba8c2449d999b5df6c17d145d'), hex2bin('ffffffffffffffffffffffffffffffff'), 'lo_LA', '2017-12-14 15:25:59', null],
                    [hex2bin('540ce4a983ad40afbaf5dbcd4666d550'), hex2bin('ffffffffffffffffffffffffffffffff'), 'ar_KW', '2017-12-14 15:25:59', null],
                    [hex2bin('63fe474d18844e18953710718c520179'), hex2bin('ffffffffffffffffffffffffffffffff'), 'lt_LT', '2017-12-14 15:25:59', null],
                    [hex2bin('6a2b312b5d6b4c749c1abdc7ddfc2e4f'), hex2bin('ffffffffffffffffffffffffffffffff'), 'lv_LV', '2017-12-14 15:25:59', null],
                    [hex2bin('d7e7c2749a504a58b21f8453ba6272ff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'mk_MK', '2017-12-14 15:25:59', null],
                    [hex2bin('c2bdf6f4df364955bfd154905d9165af'), hex2bin('ffffffffffffffffffffffffffffffff'), 'ml_IN', '2017-12-14 15:25:59', null],
                    [hex2bin('00b4688f65684f5ba8e63bfbc3857ea7'), hex2bin('ffffffffffffffffffffffffffffffff'), 'mn_CN', '2017-12-14 15:25:59', null],
                    [hex2bin('72b837bbd7294f95bcdbddb7c1495be5'), hex2bin('ffffffffffffffffffffffffffffffff'), 'mn_MN', '2017-12-14 15:25:59', null],
                    [hex2bin('21599099cdbf4e77b989644a2b8ac91e'), hex2bin('ffffffffffffffffffffffffffffffff'), 'mr_IN', '2017-12-14 15:25:59', null],
                    [hex2bin('9aa4a88f3a42428195f8f1ea73cb382c'), hex2bin('ffffffffffffffffffffffffffffffff'), 'ms_BN', '2017-12-14 15:25:59', null],
                    [hex2bin('18a299dd77fb4c8ebe64cf86c225097b'), hex2bin('ffffffffffffffffffffffffffffffff'), 'ms_MY', '2017-12-14 15:25:59', null],
                    [hex2bin('68ae4f7d86b34db5a36aeeb1607e1b32'), hex2bin('ffffffffffffffffffffffffffffffff'), 'mt_MT', '2017-12-14 15:25:59', null],
                    [hex2bin('3537ff61bda24a14b2e41b3ba15a1c9c'), hex2bin('ffffffffffffffffffffffffffffffff'), 'ar_LB', '2017-12-14 15:25:59', null],
                    [hex2bin('cd1d85afab5a4043ac924234328289e3'), hex2bin('ffffffffffffffffffffffffffffffff'), 'my_MM', '2017-12-14 15:25:59', null],
                    [hex2bin('3cf32749d80748929ee532d55aed7808'), hex2bin('ffffffffffffffffffffffffffffffff'), 'nb_NO', '2017-12-14 15:25:59', null],
                    [hex2bin('a54446981bd14281bb943208b2d4f0ac'), hex2bin('ffffffffffffffffffffffffffffffff'), 'nds_DE', '2017-12-14 15:25:59', null],
                    [hex2bin('8b2763c98c554c5192c943d7755ee7da'), hex2bin('ffffffffffffffffffffffffffffffff'), 'ne_IN', '2017-12-14 15:25:59', null],
                    [hex2bin('90e4ae0d69614663a5b16a2708e55069'), hex2bin('ffffffffffffffffffffffffffffffff'), 'ne_NP', '2017-12-14 15:25:59', null],
                    [hex2bin('57b8810893ff48229a9418967071ca53'), hex2bin('ffffffffffffffffffffffffffffffff'), 'nl_BE', '2017-12-14 15:25:59', null],
                    [hex2bin('d8d54f6228e34cb5b72ec9e966e1a119'), hex2bin('ffffffffffffffffffffffffffffffff'), 'nl_NL', '2017-12-14 15:25:59', null],
                    [hex2bin('d3560278b19d4372a6e5c319ba04862f'), hex2bin('ffffffffffffffffffffffffffffffff'), 'nn_NO', '2017-12-14 15:25:59', null],
                    [hex2bin('dab435705ee147b881a9226ea62c5ed9'), hex2bin('ffffffffffffffffffffffffffffffff'), 'nr_ZA', '2017-12-14 15:25:59', null],
                    [hex2bin('70885a2fe0d34ff8a3b60a8c67ada0b2'), hex2bin('ffffffffffffffffffffffffffffffff'), 'nso_ZA', '2017-12-14 15:25:59', null],
                    [hex2bin('e3ba1499cee743ea9acf5a0fc854ee85'), hex2bin('ffffffffffffffffffffffffffffffff'), 'ar_LY', '2017-12-14 15:25:59', null],
                    [hex2bin('5be608dfc5ba4cf9ae10981f828d238c'), hex2bin('ffffffffffffffffffffffffffffffff'), 'ny_MW', '2017-12-14 15:25:59', null],
                    [hex2bin('f672f4b2f8c044a2af9bad85e0a08a06'), hex2bin('ffffffffffffffffffffffffffffffff'), 'oc_FR', '2017-12-14 15:25:59', null],
                    [hex2bin('71ecafa13800482c88b4bc4a59d4e79e'), hex2bin('ffffffffffffffffffffffffffffffff'), 'om_ET', '2017-12-14 15:25:59', null],
                    [hex2bin('eafb6d475a0048b3b1a684cabe482d28'), hex2bin('ffffffffffffffffffffffffffffffff'), 'om_KE', '2017-12-14 15:25:59', null],
                    [hex2bin('84073771c77f41e0a03dcdcfc2282e5f'), hex2bin('ffffffffffffffffffffffffffffffff'), 'or_IN', '2017-12-14 15:25:59', null],
                    [hex2bin('0bc4b51524984b7e9f8b1feec6b3c536'), hex2bin('ffffffffffffffffffffffffffffffff'), 'pa_IN', '2017-12-14 15:25:59', null],
                    [hex2bin('d03530f446a5421e97e2ff7b683366bf'), hex2bin('ffffffffffffffffffffffffffffffff'), 'pa_PK', '2017-12-14 15:25:59', null],
                    [hex2bin('5dd4b2ea860d49758cec35a35fe3ea54'), hex2bin('ffffffffffffffffffffffffffffffff'), 'pl_PL', '2017-12-14 15:25:59', null],
                    [hex2bin('966cce7d3b88483ba4d252959d8d0e6b'), hex2bin('ffffffffffffffffffffffffffffffff'), 'ps_AF', '2017-12-14 15:25:59', null],
                    [hex2bin('7ffe65d6544648d2b45e1f18c52e487c'), hex2bin('ffffffffffffffffffffffffffffffff'), 'pt_BR', '2017-12-14 15:25:59', null],
                    [hex2bin('2f45f3a4995448a4a104dea99f6adf08'), hex2bin('ffffffffffffffffffffffffffffffff'), 'ar_MA', '2017-12-14 15:25:59', null],
                    [hex2bin('366fddcf03e649bfa558745bc843b27c'), hex2bin('ffffffffffffffffffffffffffffffff'), 'pt_PT', '2017-12-14 15:25:59', null],
                    [hex2bin('355a7dc3c5d643578d99780219a6a07e'), hex2bin('ffffffffffffffffffffffffffffffff'), 'ro_MD', '2017-12-14 15:25:59', null],
                    [hex2bin('8622da119e2d4da78ad3a88562342303'), hex2bin('ffffffffffffffffffffffffffffffff'), 'ro_RO', '2017-12-14 15:25:59', null],
                    [hex2bin('ba0f948780ec4c53b43941ebb6ccbb1a'), hex2bin('ffffffffffffffffffffffffffffffff'), 'ru_RU', '2017-12-14 15:25:59', null],
                    [hex2bin('7709c4c442f44d60ac01e2b96875b7c0'), hex2bin('ffffffffffffffffffffffffffffffff'), 'ru_UA', '2017-12-14 15:25:59', null],
                    [hex2bin('7985c433b7c64b12a018f150ab5a1529'), hex2bin('ffffffffffffffffffffffffffffffff'), 'rw_RW', '2017-12-14 15:25:59', null],
                    [hex2bin('9ad7b91790724c61a82c3cfe93645bef'), hex2bin('ffffffffffffffffffffffffffffffff'), 'sa_IN', '2017-12-14 15:25:59', null],
                    [hex2bin('cd0de34f766b498ca4dd46a2dd24bd35'), hex2bin('ffffffffffffffffffffffffffffffff'), 'se_FI', '2017-12-14 15:25:59', null],
                    [hex2bin('dfa5457155eb49fea27943bf3dc2f48f'), hex2bin('ffffffffffffffffffffffffffffffff'), 'se_NO', '2017-12-14 15:25:59', null],
                    [hex2bin('b1c75da2bbf5417aaec82b58df7bea43'), hex2bin('ffffffffffffffffffffffffffffffff'), 'sh_BA', '2017-12-14 15:25:59', null],
                    [hex2bin('2f3663edb7614308a60188c21c7963d5'), hex2bin('ffffffffffffffffffffffffffffffff'), 'en_GB', '2017-12-14 15:25:59', null],
                    [hex2bin('9aad0f2a1c424d98b81bcd3d2faf0bda'), hex2bin('ffffffffffffffffffffffffffffffff'), 'ar_OM', '2017-12-14 15:25:59', null],
                    [hex2bin('c84e91e448294fd5b910dabb754a0acf'), hex2bin('ffffffffffffffffffffffffffffffff'), 'sh_CS', '2017-12-14 15:25:59', null],
                    [hex2bin('4f6abb958d76460c9581e4b3dcecbb8e'), hex2bin('ffffffffffffffffffffffffffffffff'), 'sh_YU', '2017-12-14 15:25:59', null],
                    [hex2bin('3b7b74f5f23e43598423ad00d9f0ca52'), hex2bin('ffffffffffffffffffffffffffffffff'), 'si_LK', '2017-12-14 15:25:59', null],
                    [hex2bin('6be9c0f234644766a5051e0ab55e41ca'), hex2bin('ffffffffffffffffffffffffffffffff'), 'sid_ET', '2017-12-14 15:25:59', null],
                    [hex2bin('1240bb005de546ef9e980b639e4c04a1'), hex2bin('ffffffffffffffffffffffffffffffff'), 'sk_SK', '2017-12-14 15:25:59', null],
                    [hex2bin('1984f39fdb824558becc7d90cd868c7c'), hex2bin('ffffffffffffffffffffffffffffffff'), 'sl_SI', '2017-12-14 15:25:59', null],
                    [hex2bin('ec6fd5e6be73407f84c1b0a180c802ae'), hex2bin('ffffffffffffffffffffffffffffffff'), 'so_DJ', '2017-12-14 15:25:59', null],
                    [hex2bin('dd7c423eb77f4283af0b875ed524d214'), hex2bin('ffffffffffffffffffffffffffffffff'), 'so_ET', '2017-12-14 15:25:59', null],
                    [hex2bin('de211b3ea3774837b5cc81edbbfacfa4'), hex2bin('ffffffffffffffffffffffffffffffff'), 'so_KE', '2017-12-14 15:25:59', null],
                    [hex2bin('abd65e745a3048acb978793478926d68'), hex2bin('ffffffffffffffffffffffffffffffff'), 'so_SO', '2017-12-14 15:25:59', null],
                    [hex2bin('824dbd6cf5eb4e66bc346ed4dd4b7944'), hex2bin('ffffffffffffffffffffffffffffffff'), 'ar_QA', '2017-12-14 15:25:59', null],
                    [hex2bin('2cc7ebce3743499398cb5c6eb5cf9161'), hex2bin('ffffffffffffffffffffffffffffffff'), 'sq_AL', '2017-12-14 15:25:59', null],
                    [hex2bin('340756898ba64b07b8dca201ff6686b7'), hex2bin('ffffffffffffffffffffffffffffffff'), 'sr_BA', '2017-12-14 15:25:59', null],
                    [hex2bin('022b45ad03834454aa41959042aa6d83'), hex2bin('ffffffffffffffffffffffffffffffff'), 'sr_CS', '2017-12-14 15:25:59', null],
                    [hex2bin('c0a25a5910364bd28d6bfa8f806e611d'), hex2bin('ffffffffffffffffffffffffffffffff'), 'sr_ME', '2017-12-14 15:25:59', null],
                    [hex2bin('d79faffcf4fd4f94b083e65e3e7fba1a'), hex2bin('ffffffffffffffffffffffffffffffff'), 'sr_RS', '2017-12-14 15:25:59', null],
                    [hex2bin('ab1d6991c50c429095cf02ace40d8368'), hex2bin('ffffffffffffffffffffffffffffffff'), 'sr_YU', '2017-12-14 15:25:59', null],
                    [hex2bin('f76f5280d30c4d41bbdd6b52aa6bf9bd'), hex2bin('ffffffffffffffffffffffffffffffff'), 'ss_SZ', '2017-12-14 15:25:59', null],
                    [hex2bin('3bbcfcbe32a9424b95884fed18b84fd7'), hex2bin('ffffffffffffffffffffffffffffffff'), 'ss_ZA', '2017-12-14 15:25:59', null],
                    [hex2bin('6c46211170f2490791a532753578501c'), hex2bin('ffffffffffffffffffffffffffffffff'), 'st_LS', '2017-12-14 15:25:59', null],
                    [hex2bin('e9033d791a794726b9f8674353f304d8'), hex2bin('ffffffffffffffffffffffffffffffff'), 'st_ZA', '2017-12-14 15:25:59', null],
                    [hex2bin('52a141d757ff4abca9fd8277a60962a9'), hex2bin('ffffffffffffffffffffffffffffffff'), 'ar_SA', '2017-12-14 15:25:59', null],
                    [hex2bin('33a1654a65e44739a16305c821453da4'), hex2bin('ffffffffffffffffffffffffffffffff'), 'sv_FI', '2017-12-14 15:25:59', null],
                    [hex2bin('c448a02ef9a34386843b7b888f71f84a'), hex2bin('ffffffffffffffffffffffffffffffff'), 'sv_SE', '2017-12-14 15:25:59', null],
                    [hex2bin('a92c44386ccd4b3595bf23c34ed0e67b'), hex2bin('ffffffffffffffffffffffffffffffff'), 'sw_KE', '2017-12-14 15:25:59', null],
                    [hex2bin('417b4ba5af2145f0b9598ecb171c65da'), hex2bin('ffffffffffffffffffffffffffffffff'), 'sw_TZ', '2017-12-14 15:25:59', null],
                    [hex2bin('27f0a51203fb415bbc986405b779ff07'), hex2bin('ffffffffffffffffffffffffffffffff'), 'syr_SY', '2017-12-14 15:25:59', null],
                    [hex2bin('e7d42311a5d243278f1cd6fbbca2c25c'), hex2bin('ffffffffffffffffffffffffffffffff'), 'ta_IN', '2017-12-14 15:25:59', null],
                    [hex2bin('dcc080d2502c4aec9009e2a7f7b216c3'), hex2bin('ffffffffffffffffffffffffffffffff'), 'te_IN', '2017-12-14 15:25:59', null],
                    [hex2bin('52bed3e53b474a9e9a6042e45d6286f1'), hex2bin('ffffffffffffffffffffffffffffffff'), 'tg_TJ', '2017-12-14 15:25:59', null],
                    [hex2bin('c11cbaf3f6274099925bb79263ccef4a'), hex2bin('ffffffffffffffffffffffffffffffff'), 'th_TH', '2017-12-14 15:25:59', null],
                    [hex2bin('5d08c4033b894a63a445971cb5308306'), hex2bin('ffffffffffffffffffffffffffffffff'), 'ti_ER', '2017-12-14 15:25:59', null],
                    [hex2bin('d0c73d0e5d0c43618a54a69acb736f4a'), hex2bin('ffffffffffffffffffffffffffffffff'), 'ar_SD', '2017-12-14 15:25:59', null],
                    [hex2bin('75a3fc2c05934e7ab71c56cbd07dbdb4'), hex2bin('ffffffffffffffffffffffffffffffff'), 'ti_ET', '2017-12-14 15:25:59', null],
                    [hex2bin('437b03b211bc4f148b67e79e2e3176bb'), hex2bin('ffffffffffffffffffffffffffffffff'), 'tig_ER', '2017-12-14 15:25:59', null],
                    [hex2bin('c627c3404cfa4744bc70b568b2a3c7ab'), hex2bin('ffffffffffffffffffffffffffffffff'), 'tn_ZA', '2017-12-14 15:25:59', null],
                    [hex2bin('109d93d853864102bf3f4e621988f7f2'), hex2bin('ffffffffffffffffffffffffffffffff'), 'to_TO', '2017-12-14 15:25:59', null],
                    [hex2bin('30cdf6a43a4c4b97b92f0091b7996e41'), hex2bin('ffffffffffffffffffffffffffffffff'), 'tr_TR', '2017-12-14 15:25:59', null],
                    [hex2bin('d0efe2ed8662465dae0e2d1bf03b091b'), hex2bin('ffffffffffffffffffffffffffffffff'), 'ts_ZA', '2017-12-14 15:25:59', null],
                    [hex2bin('a40c28733a5842bbb2583b88cf01f06f'), hex2bin('ffffffffffffffffffffffffffffffff'), 'tt_RU', '2017-12-14 15:25:59', null],
                    [hex2bin('52fe5eb97b174e7aa63783acbbc9a97e'), hex2bin('ffffffffffffffffffffffffffffffff'), 'ug_CN', '2017-12-14 15:25:59', null],
                    [hex2bin('e3a69696824d415e81eb416ce5ca5b69'), hex2bin('ffffffffffffffffffffffffffffffff'), 'uk_UA', '2017-12-14 15:25:59', null],
                    [hex2bin('a39b21436e1a4b03a69c784c2f9eadf8'), hex2bin('ffffffffffffffffffffffffffffffff'), 'ar_SY', '2017-12-14 15:25:59', null],
                    [hex2bin('badefc666c09416dac9612999d9e552a'), hex2bin('ffffffffffffffffffffffffffffffff'), 'ur_IN', '2017-12-14 15:25:59', null],
                    [hex2bin('aea666a53fef421c841f8663ab70d60c'), hex2bin('ffffffffffffffffffffffffffffffff'), 'ur_PK', '2017-12-14 15:25:59', null],
                    [hex2bin('f7808917b8434cb0940352f0ae214efc'), hex2bin('ffffffffffffffffffffffffffffffff'), 'uz_AF', '2017-12-14 15:25:59', null],
                    [hex2bin('2e185dcd51464b01a427f2f75173fd53'), hex2bin('ffffffffffffffffffffffffffffffff'), 'uz_UZ', '2017-12-14 15:25:59', null],
                    [hex2bin('f1dcfb019cd6488fbd8070b27d7e8603'), hex2bin('ffffffffffffffffffffffffffffffff'), 've_ZA', '2017-12-14 15:25:59', null],
                    [hex2bin('fadcb90700114c5993557f4bb2d92460'), hex2bin('ffffffffffffffffffffffffffffffff'), 'vi_VN', '2017-12-14 15:25:59', null],
                    [hex2bin('9ec94e6352224de0852a140f28c75703'), hex2bin('ffffffffffffffffffffffffffffffff'), 'wal_ET', '2017-12-14 15:25:59', null],
                    [hex2bin('20973ea73a4546cfa43791da4eb8c426'), hex2bin('ffffffffffffffffffffffffffffffff'), 'wo_SN', '2017-12-14 15:25:59', null],
                    [hex2bin('4bae11ce4ec6469786f2af4b63f94f23'), hex2bin('ffffffffffffffffffffffffffffffff'), 'xh_ZA', '2017-12-14 15:25:59', null],
                    [hex2bin('85e4907739bd4ddcb2c777b41fa20f75'), hex2bin('ffffffffffffffffffffffffffffffff'), 'yo_NG', '2017-12-14 15:25:59', null],
                    [hex2bin('791f15a62602420098562d29ab61eba8'), hex2bin('ffffffffffffffffffffffffffffffff'), 'ar_TN', '2017-12-14 15:25:59', null],
                    [hex2bin('35c3c4dfd3364da79f0620fe8520cd15'), hex2bin('ffffffffffffffffffffffffffffffff'), 'zh_CN', '2017-12-14 15:25:59', null],
                    [hex2bin('461fdc0e9b13488c82f1be3db9800c6e'), hex2bin('ffffffffffffffffffffffffffffffff'), 'zh_HK', '2017-12-14 15:25:59', null],
                    [hex2bin('9120da7b2cfa4c03b97b1754744e0368'), hex2bin('ffffffffffffffffffffffffffffffff'), 'zh_MO', '2017-12-14 15:25:59', null],
                    [hex2bin('abc3064384fc4f7fa63655d106bf0955'), hex2bin('ffffffffffffffffffffffffffffffff'), 'zh_SG', '2017-12-14 15:25:59', null],
                    [hex2bin('26aa2725d80049cba131fa03f9c492b3'), hex2bin('ffffffffffffffffffffffffffffffff'), 'zh_TW', '2017-12-14 15:25:59', null],
                    [hex2bin('8a4821d8256b490ca29739982b802045'), hex2bin('ffffffffffffffffffffffffffffffff'), 'zu_ZA', '2017-12-14 15:25:59', null],
                    [hex2bin('4ce60695909f4dc6ad199090fdcf95a1'), hex2bin('ffffffffffffffffffffffffffffffff'), 'ar_YE', '2017-12-14 15:25:59', null],
                    [hex2bin('15639dadd83b4c9e869a620002fa1a7a'), hex2bin('ffffffffffffffffffffffffffffffff'), 'as_IN', '2017-12-14 15:25:59', null],
                    [hex2bin('65afa499a81b419bbb8b7b16fcfb12d2'), hex2bin('ffffffffffffffffffffffffffffffff'), 'az_AZ', '2017-12-14 15:25:59', null],
                    [hex2bin('858714b28c3246ad86601aa08b5debc0'), hex2bin('ffffffffffffffffffffffffffffffff'), 'be_BY', '2017-12-14 15:25:59', null],
                    [hex2bin('787d38ec5e244647bf69b84e2f212808'), hex2bin('ffffffffffffffffffffffffffffffff'), 'aa_DJ', '2017-12-14 15:25:59', null],
                    [hex2bin('4cec18e4bc55407aa9591990f74fd61b'), hex2bin('ffffffffffffffffffffffffffffffff'), 'bg_BG', '2017-12-14 15:25:59', null],
                    [hex2bin('4b4b461cd4f94f0cb9c854382c70cea8'), hex2bin('ffffffffffffffffffffffffffffffff'), 'bn_BD', '2017-12-14 15:25:59', null],
                    [hex2bin('038dec2102a9445bb66eb09b4e5a2c1f'), hex2bin('ffffffffffffffffffffffffffffffff'), 'bn_IN', '2017-12-14 15:25:59', null],
                    [hex2bin('71b367341e7d4f6d9a2412b8d9819144'), hex2bin('ffffffffffffffffffffffffffffffff'), 'bo_CN', '2017-12-14 15:25:59', null],
                    [hex2bin('22051084ae324ab5ad29f5051efcd4d5'), hex2bin('ffffffffffffffffffffffffffffffff'), 'bo_IN', '2017-12-14 15:25:59', null],
                    [hex2bin('3250c52a1b7b4837822c6842d73c535c'), hex2bin('ffffffffffffffffffffffffffffffff'), 'bs_BA', '2017-12-14 15:25:59', null],
                    [hex2bin('46a7926775cf48c786eef6dd395b4880'), hex2bin('ffffffffffffffffffffffffffffffff'), 'byn_ER', '2017-12-14 15:25:59', null],
                    [hex2bin('264644c5881a422eace9fe65b386c3f9'), hex2bin('ffffffffffffffffffffffffffffffff'), 'ca_ES', '2017-12-14 15:25:59', null],
                    [hex2bin('d6a597ef9fe64d33b4975088dba06fc1'), hex2bin('ffffffffffffffffffffffffffffffff'), 'cch_NG', '2017-12-14 15:25:59', null],
                    [hex2bin('e505cf030ff94f4da119bf7af95e015d'), hex2bin('ffffffffffffffffffffffffffffffff'), 'cs_CZ', '2017-12-14 15:25:59', null],
                    [hex2bin('2ec6cb588ad0480ea7441bbc992bcdfc'), hex2bin('ffffffffffffffffffffffffffffffff'), 'aa_ER', '2017-12-14 15:25:59', null],
                    [hex2bin('cc16afc51e894a4e83dffe15c932c441'), hex2bin('ffffffffffffffffffffffffffffffff'), 'cy_GB', '2017-12-14 15:25:59', null],
                    [hex2bin('509200ddb457437ba16b24c369f2bb82'), hex2bin('ffffffffffffffffffffffffffffffff'), 'da_DK', '2017-12-14 15:25:59', null],
                    [hex2bin('fc7b7266f656477e9a15ae23de8b0523'), hex2bin('ffffffffffffffffffffffffffffffff'), 'de_AT', '2017-12-14 15:25:59', null],
                    [hex2bin('3dba282b2ae84d5ba360de0ddc480d1c'), hex2bin('ffffffffffffffffffffffffffffffff'), 'de_BE', '2017-12-14 15:25:59', null],
                    [hex2bin('e17810ef31cb48b88ce871f3a03e1025'), hex2bin('ffffffffffffffffffffffffffffffff'), 'de_CH', '2017-12-14 15:25:59', null],
                    [hex2bin('7c7f6c130b89474bb41b460e03a70440'), hex2bin('ffffffffffffffffffffffffffffffff'), 'de_LI', '2017-12-14 15:25:59', null],
                    [hex2bin('ef5e0be1b1144e0a8688875a032a5f21'), hex2bin('ffffffffffffffffffffffffffffffff'), 'de_LU', '2017-12-14 15:25:59', null],
                    [hex2bin('655478c87d3f496abfacd50eb1b48d43'), hex2bin('ffffffffffffffffffffffffffffffff'), 'dv_MV', '2017-12-14 15:25:59', null],
                    [hex2bin('43741e431644414b8dc95ad61755b992'), hex2bin('ffffffffffffffffffffffffffffffff'), 'dz_BT', '2017-12-14 15:25:59', null],
                    [hex2bin('0acb980d8d3a412cbf00842fb340feb5'), hex2bin('ffffffffffffffffffffffffffffffff'), 'ee_GH', '2017-12-14 15:25:59', null],
                    [hex2bin('e42c0c780921432b97edc5d513980b21'), hex2bin('ffffffffffffffffffffffffffffffff'), 'aa_ET', '2017-12-14 15:25:59', null],
                    [hex2bin('a1e7965325834edeb20307bf390b8da1'), hex2bin('ffffffffffffffffffffffffffffffff'), 'ee_TG', '2017-12-14 15:25:59', null],
                    [hex2bin('67d9636c67614673bd8d265d295d194c'), hex2bin('ffffffffffffffffffffffffffffffff'), 'el_CY', '2017-12-14 15:25:59', null],
                    [hex2bin('6961054faeb74e4f8851a1dfb8cb193b'), hex2bin('ffffffffffffffffffffffffffffffff'), 'el_GR', '2017-12-14 15:25:59', null],
                    [hex2bin('318d2533eb03461b88fda25c9a02653b'), hex2bin('ffffffffffffffffffffffffffffffff'), 'en_AS', '2017-12-14 15:25:59', null],
                    [hex2bin('8e91d9b70b704eccbc14202ba5c4882e'), hex2bin('ffffffffffffffffffffffffffffffff'), 'en_AU', '2017-12-14 15:25:59', null],
                    [hex2bin('d8f3a4d870384c25b4d02654ddad64fd'), hex2bin('ffffffffffffffffffffffffffffffff'), 'en_BE', '2017-12-14 15:25:59', null],
                    [hex2bin('3e4526223d714186be74d231352e5db3'), hex2bin('ffffffffffffffffffffffffffffffff'), 'en_BW', '2017-12-14 15:25:59', null],
                    [hex2bin('c97a13c07c4147338426d1131eab69e9'), hex2bin('ffffffffffffffffffffffffffffffff'), 'en_BZ', '2017-12-14 15:25:59', null],
                    [hex2bin('6590ee8662f845e08b727f7aeec84831'), hex2bin('ffffffffffffffffffffffffffffffff'), 'en_CA', '2017-12-14 15:25:59', null],
                    [hex2bin('77fc5f00b6d146d98cc40aff79eb5c00'), hex2bin('ffffffffffffffffffffffffffffffff'), 'en_GU', '2017-12-14 15:25:59', null],
                    [hex2bin('801202f021334cf1b93cb0113b5d11d3'), hex2bin('ffffffffffffffffffffffffffffffff'), 'af_NA', '2017-12-14 15:25:59', null],
                    [hex2bin('abf8604e9b384f0689d848e011fa0241'), hex2bin('ffffffffffffffffffffffffffffffff'), 'en_HK', '2017-12-14 15:25:59', null],
                    [hex2bin('220b605583914529bcc82683f75045d3'), hex2bin('ffffffffffffffffffffffffffffffff'), 'en_IE', '2017-12-14 15:25:59', null],
                    [hex2bin('51aafd199c7c4eb09b43f91c596865e1'), hex2bin('ffffffffffffffffffffffffffffffff'), 'en_IN', '2017-12-14 15:25:59', null],
                    [hex2bin('92b7f309a62b411883f5a5ce89843ba8'), hex2bin('ffffffffffffffffffffffffffffffff'), 'en_JM', '2017-12-14 15:25:59', null],
                    [hex2bin('db559634117743aaba2c907150ac7064'), hex2bin('ffffffffffffffffffffffffffffffff'), 'en_MH', '2017-12-14 15:25:59', null],
                    [hex2bin('b694a0f7dc654a96b940614a7fc1b3f6'), hex2bin('ffffffffffffffffffffffffffffffff'), 'en_MP', '2017-12-14 15:25:59', null],
                    [hex2bin('0ee0a1df025249538c1f1a391fb99a3d'), hex2bin('ffffffffffffffffffffffffffffffff'), 'en_MT', '2017-12-14 15:25:59', null],
                    [hex2bin('6ab8dc296338438fbd7d4fd13406f5e9'), hex2bin('ffffffffffffffffffffffffffffffff'), 'en_NA', '2017-12-14 15:25:59', null],
                    [hex2bin('776f28188e434088872010a771e67408'), hex2bin('ffffffffffffffffffffffffffffffff'), 'en_NZ', '2017-12-14 15:25:59', null],
                    [hex2bin('5af0fd645e5c42eebc08446be0c9f137'), hex2bin('ffffffffffffffffffffffffffffffff'), 'en_PH', '2017-12-14 15:25:59', null],
                    [hex2bin('b68eb5f561924a5fbe6200e844469222'), hex2bin('ffffffffffffffffffffffffffffffff'), 'af_ZA', '2017-12-14 15:25:59', null],
                    [hex2bin('1a9f4bb15ea142ec93409b48204326d5'), hex2bin('ffffffffffffffffffffffffffffffff'), 'en_PK', '2017-12-14 15:25:59', null],
                    [hex2bin('accdb020676044618d7ed9025a5fb26f'), hex2bin('ffffffffffffffffffffffffffffffff'), 'en_SG', '2017-12-14 15:25:59', null],
                    [hex2bin('32c59129d3f84200ab479eaccd5a3377'), hex2bin('ffffffffffffffffffffffffffffffff'), 'en_TT', '2017-12-14 15:25:59', null],
                    [hex2bin('a5d76921c82d415e88d76670fd6ae8dd'), hex2bin('ffffffffffffffffffffffffffffffff'), 'en_UM', '2017-12-14 15:25:59', null],
                    [hex2bin('d36a2c5d1f404f6f90572993897c2824'), hex2bin('ffffffffffffffffffffffffffffffff'), 'en_US', '2017-12-14 15:25:59', null],
                    [hex2bin('e1bb080073ee4095b11cf2b414c168d9'), hex2bin('ffffffffffffffffffffffffffffffff'), 'en_VI', '2017-12-14 15:25:59', null],
                    [hex2bin('e3cf90b4f4b0457195a4fa078e822037'), hex2bin('ffffffffffffffffffffffffffffffff'), 'en_ZA', '2017-12-14 15:25:59', null],
                    [hex2bin('0eb28971b99d44d9b9cb37f713a8bd92'), hex2bin('ffffffffffffffffffffffffffffffff'), 'en_ZW', '2017-12-14 15:25:59', null],
                    [hex2bin('230d5978efda488ea80c69ce22afd008'), hex2bin('ffffffffffffffffffffffffffffffff'), 'es_AR', '2017-12-14 15:25:59', null],
                    [hex2bin('19a1b1874011459d9cac0868534ad2a5'), hex2bin('ffffffffffffffffffffffffffffffff'), 'es_BO', '2017-12-14 15:25:59', null],
                    [hex2bin('2b50e52772b54d65a605a90417e234a4'), hex2bin('ffffffffffffffffffffffffffffffff'), 'ak_GH', '2017-12-14 15:25:59', null],
                    [hex2bin('72039519ada248eca6f066969a03717c'), hex2bin('ffffffffffffffffffffffffffffffff'), 'es_CL', '2017-12-14 15:25:59', null],
                    [hex2bin('16059d9b9ae04854846025397e687de1'), hex2bin('ffffffffffffffffffffffffffffffff'), 'es_CO', '2017-12-14 15:25:59', null],
                    [hex2bin('174969081e324959943afa829f9a2ea5'), hex2bin('ffffffffffffffffffffffffffffffff'), 'es_CR', '2017-12-14 15:25:59', null],
                    [hex2bin('5087c8b0da5f41e79604be3e0c081918'), hex2bin('ffffffffffffffffffffffffffffffff'), 'es_DO', '2017-12-14 15:25:59', null],
                    [hex2bin('b85ae453fcd94734971dfe4fc8becffb'), hex2bin('ffffffffffffffffffffffffffffffff'), 'es_EC', '2017-12-14 15:25:59', null],
                    [hex2bin('ee2edb8fbbbe4402b6f7bb6b29f24494'), hex2bin('ffffffffffffffffffffffffffffffff'), 'es_ES', '2017-12-14 15:25:59', null],
                    [hex2bin('5de1d1620301481fb3125118db4b3cca'), hex2bin('ffffffffffffffffffffffffffffffff'), 'es_GT', '2017-12-14 15:25:59', null],
                    [hex2bin('5d1b33e545ff4d3bbe68268bf288fe99'), hex2bin('ffffffffffffffffffffffffffffffff'), 'es_HN', '2017-12-14 15:25:59', null],
                    [hex2bin('4771c56731fb4fa8901a895af1fab756'), hex2bin('ffffffffffffffffffffffffffffffff'), 'es_MX', '2017-12-14 15:25:59', null],
                    [hex2bin('16930d551d6548a4b6a79f156b90b4ca'), hex2bin('ffffffffffffffffffffffffffffffff'), 'es_NI', '2017-12-14 15:25:59', null],
                    [hex2bin('3569183cd45f4b459c4e1bcb0936b258'), hex2bin('ffffffffffffffffffffffffffffffff'), 'am_ET', '2017-12-14 15:25:59', null],
                    [hex2bin('b47ee4c5e52e4302922de64dc70be679'), hex2bin('ffffffffffffffffffffffffffffffff'), 'es_PA', '2017-12-14 15:25:59', null],
                    [hex2bin('bf30d531acf94ba7b21bb328a793bccf'), hex2bin('ffffffffffffffffffffffffffffffff'), 'es_PE', '2017-12-14 15:25:59', null],
                    [hex2bin('15a76fb0e3c140b98a522368fb480097'), hex2bin('ffffffffffffffffffffffffffffffff'), 'es_PR', '2017-12-14 15:25:59', null],
                    [hex2bin('546d97038eaf437eb110a6af780f4d2d'), hex2bin('ffffffffffffffffffffffffffffffff'), 'es_PY', '2017-12-14 15:25:59', null],
                    [hex2bin('b027d25dc8534bb089c197efc46c952c'), hex2bin('ffffffffffffffffffffffffffffffff'), 'es_SV', '2017-12-14 15:25:59', null],
                    [hex2bin('2a3cbc5da5c64f2fb216c4687211cc9d'), hex2bin('ffffffffffffffffffffffffffffffff'), 'es_US', '2017-12-14 15:25:59', null],
                    [hex2bin('249e48390244447ba89570f5af0baef4'), hex2bin('ffffffffffffffffffffffffffffffff'), 'es_UY', '2017-12-14 15:25:59', null],
                    [hex2bin('8a368fb88b1947b78ed13db81210a5b8'), hex2bin('ffffffffffffffffffffffffffffffff'), 'es_VE', '2017-12-14 15:25:59', null],
                    [hex2bin('dc0f93ca81814f36bbf3446b78b3d27d'), hex2bin('ffffffffffffffffffffffffffffffff'), 'et_EE', '2017-12-14 15:25:59', null],
                    [hex2bin('31996fe03e604296827c0de7ae910245'), hex2bin('ffffffffffffffffffffffffffffffff'), 'eu_ES', '2017-12-14 15:25:59', null],
            ]
        );

        $this->importTable(
            $tenantId,
            'locale_translation',
            ['locale_id', 'locale_version_id', 'language_id', 'name', 'territory'],
            ['locale_tenant_id', 'language_tenant_id'],
            [
                [hex2bin('7b52d9dd2b0640ec90be9f57edf29be7'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Deutsch', 'Deutschland'],
                [hex2bin('6468611c0f774305a5ca265cb8b6adbb'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Arabisch', 'Vereinigte Arabische Emirate'],
                [hex2bin('ab09518a825942fbaaaf9e5951ae9113'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Persisch', 'Afghanistan'],
                [hex2bin('1967fd0bde6644b2b6132b5694a19ecb'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Persisch', 'Iran'],
                [hex2bin('6b3fd72c2d77470a9842c41a63b27fd9'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Finnisch', 'Finnland'],
                [hex2bin('1f5092adda8940ae83d888c293e6c5e8'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Filipino', 'Philippinen'],
                [hex2bin('c6dfbe7cf0044de499c676f939061572'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Färöisch', 'Färöer'],
                [hex2bin('458029c478cf4de48cb1929af6b49f56'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Französisch', 'Belgien'],
                [hex2bin('c9633f6c797f4344bfcdb9277fc0dcca'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Französisch', 'Kanada'],
                [hex2bin('71f184e6fb914693842db2d2008aadf2'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Französisch', 'Schweiz'],
                [hex2bin('03fb597a642b447c97dfd3e1ebe3a8d4'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Französisch', 'Frankreich'],
                [hex2bin('7d0baf560b994fe18b7023674722bebb'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Französisch', 'Luxemburg'],
                [hex2bin('1a166699bd364015aa872387d3770f84'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Arabisch', 'Bahrain'],
                [hex2bin('8285eb7a14da48ceb7374eb4f16570f7'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Französisch', 'Monaco'],
                [hex2bin('e78ac901fd9b4ed18bf28caaa77ee74b'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Französisch', 'Senegal'],
                [hex2bin('a900012c8a81473aa7c8653a5c80f509'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Friulisch', 'Italien'],
                [hex2bin('ccf55a76d7684b1f80dd7eb208c8ab25'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Irisch', 'Irland'],
                [hex2bin('cb5a4a5de2214e9f909692f36a1a10de'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Ga-Sprache', 'Ghana'],
                [hex2bin('91baad7dd12342a98f06079925271f0d'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Geez', 'Eritrea'],
                [hex2bin('d9c9e0a82cfb42a5a253775436ad106c'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Geez', 'Äthiopien'],
                [hex2bin('275ee5ab76d84257ab6d4496c5e4e838'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Galizisch', 'Spanien'],
                [hex2bin('2ffe9f822c2442658eb1b6aad9ded850'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Schweizerdeutsch', 'Schweiz'],
                [hex2bin('43abaa4456e0485ab5baafd39ef2fbc4'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Gujarati', 'Indien'],
                [hex2bin('a4b7b6e6b9db4395b2279eaeb9065a08'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Arabisch', 'Algerien'],
                [hex2bin('13de79afcfe041038052505cac84a3d6'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Manx', 'Vereinigtes Königreich'],
                [hex2bin('ecd617039ffd42f4b2adafb0a1b93ca7'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Hausa', 'Ghana'],
                [hex2bin('8c89cb4663e14855bc03dfdda8f99169'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Hausa', 'Niger'],
                [hex2bin('0ea82a89f6aa4c91b54323103c488bce'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Hausa', 'Nigeria'],
                [hex2bin('08492d3423e14114b4a8353787749308'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Hausa', 'Sudan'],
                [hex2bin('5292a212064345a298faa3b0e8499c0e'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Hawaiisch', 'Vereinigte Staaten'],
                [hex2bin('d3ec559bcbb34c619b1d566018013309'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Hebräisch', 'Israel'],
                [hex2bin('bb8eb40270f8470c93f1b75f3fd0a7eb'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Hindi', 'Indien'],
                [hex2bin('31386b509e804455b3ec26e6c28f19fc'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Kroatisch', 'Kroatien'],
                [hex2bin('80b31abf23444673b7fdb0084fbc010e'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Ungarisch', 'Ungarn'],
                [hex2bin('2733adcf5eb94e3f9a31412f7a1294fa'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Arabisch', 'Ägypten'],
                [hex2bin('6d3b2f751f364279979739bd2bd6b59e'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Armenisch', 'Armenien'],
                [hex2bin('2324485b21814b46ae0033dd3baef55c'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Indonesisch', 'Indonesien'],
                [hex2bin('a6a5691372fa4c7a9ab14148bea51272'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Igbo-Sprache', 'Nigeria'],
                [hex2bin('a930a9c1d5504a299e177d62e07e6b13'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Sichuan Yi', 'China'],
                [hex2bin('c626598c2e644640ad5282c854f37983'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Isländisch', 'Island'],
                [hex2bin('4ccedb86c20d47bd8ff3e2b0e1154a9b'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Italienisch', 'Schweiz'],
                [hex2bin('7d105b4275cb4b3d963bfc85d41402d4'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Italienisch', 'Italien'],
                [hex2bin('aada3192db9b45229aef91fc1b787a27'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Japanisch', 'Japan'],
                [hex2bin('6690c11d573945a3a41ea5e87d5a31a4'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Georgisch', 'Georgien'],
                [hex2bin('f3b31a636ee6471788ecb948b31cedf8'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Jju', 'Nigeria'],
                [hex2bin('7dc700a676164b4083b11b725052e6e1'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Arabisch', 'Irak'],
                [hex2bin('67dcdde95f3f4b319ab97f499f4adeb6'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Kamba', 'Kenia'],
                [hex2bin('b36c66efaadb4b938cc231a8209fbb11'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Tyap', 'Nigeria'],
                [hex2bin('f4aa1ab8ff424d1d8505fcc19486547f'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Koro', 'Côte d?Ivoire'],
                [hex2bin('f2459a5f6a8443faa55978bfdf8eadbe'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Kasachisch', 'Kasachstan'],
                [hex2bin('bb6a8d38a10844df9ae0694455f24f99'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Grönländisch', 'Grönland'],
                [hex2bin('ab695a27f6db46ba972f81eb2a0cea70'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Kambodschanisch', 'Kambodscha'],
                [hex2bin('b7327288b3ac4972aae10266d1f2eaed'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Kannada', 'Indien'],
                [hex2bin('29f5dd91d20c4f8796430babdb158cd3'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Koreanisch', 'Republik Korea'],
                [hex2bin('b835923ea44c43608b20dc65cd9a67b9'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Konkani', 'Indien'],
                [hex2bin('f9dbc88d5e314a8da93e5ab1c128f22c'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Kpelle-Sprache', 'Guinea'],
                [hex2bin('25273dd0e22a4bb68bf443b0c2ee1ce3'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Arabisch', 'Jordanien'],
                [hex2bin('63f1958f860e44f3b15acef13941063b'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Kpelle-Sprache', 'Liberia'],
                [hex2bin('8d02182d344e4d6582d3f8f0a40c1ec1'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Kurdisch', 'Irak'],
                [hex2bin('33a84a106ab5490da59cb498f9585418'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Kurdisch', 'Iran'],
                [hex2bin('b25c0d582e3d49e596a56098b058806e'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Kurdisch', 'Syrien'],
                [hex2bin('11d92697b6ed40029b201425228ef593'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Kurdisch', 'Türkei'],
                [hex2bin('d5b5f6f1375748e6869550dac50534f7'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Kornisch', 'Vereinigtes Königreich'],
                [hex2bin('ab6e568562af4e2e9f2b2dbe05b741fe'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Kirgisisch', 'Kirgisistan'],
                [hex2bin('749023fbd35b4d3bbf6066564e50f5d1'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Lingala', 'Demokratische Republik Kongo'],
                [hex2bin('c821fbb406cd42869497e3285dda6e11'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Lingala', 'Kongo'],
                [hex2bin('70620c7ba8c2449d999b5df6c17d145d'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Laotisch', 'Laos'],
                [hex2bin('540ce4a983ad40afbaf5dbcd4666d550'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Arabisch', 'Kuwait'],
                [hex2bin('63fe474d18844e18953710718c520179'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Litauisch', 'Litauen'],
                [hex2bin('6a2b312b5d6b4c749c1abdc7ddfc2e4f'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Lettisch', 'Lettland'],
                [hex2bin('d7e7c2749a504a58b21f8453ba6272ff'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Mazedonisch', 'Mazedonien'],
                [hex2bin('c2bdf6f4df364955bfd154905d9165af'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Malayalam', 'Indien'],
                [hex2bin('00b4688f65684f5ba8e63bfbc3857ea7'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Mongolisch', 'China'],
                [hex2bin('72b837bbd7294f95bcdbddb7c1495be5'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Mongolisch', 'Mongolei'],
                [hex2bin('21599099cdbf4e77b989644a2b8ac91e'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Marathi', 'Indien'],
                [hex2bin('9aa4a88f3a42428195f8f1ea73cb382c'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Malaiisch', 'Brunei Darussalam'],
                [hex2bin('18a299dd77fb4c8ebe64cf86c225097b'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Malaiisch', 'Malaysia'],
                [hex2bin('68ae4f7d86b34db5a36aeeb1607e1b32'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Maltesisch', 'Malta'],
                [hex2bin('3537ff61bda24a14b2e41b3ba15a1c9c'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Arabisch', 'Libanon'],
                [hex2bin('cd1d85afab5a4043ac924234328289e3'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Birmanisch', 'Myanmar'],
                [hex2bin('3cf32749d80748929ee532d55aed7808'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Norwegisch Bokmål', 'Norwegen'],
                [hex2bin('a54446981bd14281bb943208b2d4f0ac'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Niederdeutsch', 'Deutschland'],
                [hex2bin('8b2763c98c554c5192c943d7755ee7da'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Nepalesisch', 'Indien'],
                [hex2bin('90e4ae0d69614663a5b16a2708e55069'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Nepalesisch', 'Nepal'],
                [hex2bin('57b8810893ff48229a9418967071ca53'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Niederländisch', 'Belgien'],
                [hex2bin('d8d54f6228e34cb5b72ec9e966e1a119'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Niederländisch', 'Niederlande'],
                [hex2bin('d3560278b19d4372a6e5c319ba04862f'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Norwegisch Nynorsk', 'Norwegen'],
                [hex2bin('dab435705ee147b881a9226ea62c5ed9'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Süd-Ndebele-Sprache', 'Südafrika'],
                [hex2bin('70885a2fe0d34ff8a3b60a8c67ada0b2'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Nord-Sotho-Sprache', 'Südafrika'],
                [hex2bin('e3ba1499cee743ea9acf5a0fc854ee85'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Arabisch', 'Libyen'],
                [hex2bin('5be608dfc5ba4cf9ae10981f828d238c'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Nyanja-Sprache', 'Malawi'],
                [hex2bin('f672f4b2f8c044a2af9bad85e0a08a06'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Okzitanisch', 'Frankreich'],
                [hex2bin('71ecafa13800482c88b4bc4a59d4e79e'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Oromo', 'Äthiopien'],
                [hex2bin('eafb6d475a0048b3b1a684cabe482d28'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Oromo', 'Kenia'],
                [hex2bin('84073771c77f41e0a03dcdcfc2282e5f'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Orija', 'Indien'],
                [hex2bin('0bc4b51524984b7e9f8b1feec6b3c536'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Pandschabisch', 'Indien'],
                [hex2bin('d03530f446a5421e97e2ff7b683366bf'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Pandschabisch', 'Pakistan'],
                [hex2bin('5dd4b2ea860d49758cec35a35fe3ea54'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Polnisch', 'Polen'],
                [hex2bin('966cce7d3b88483ba4d252959d8d0e6b'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Paschtu', 'Afghanistan'],
                [hex2bin('7ffe65d6544648d2b45e1f18c52e487c'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Portugiesisch', 'Brasilien'],
                [hex2bin('2f45f3a4995448a4a104dea99f6adf08'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Arabisch', 'Marokko'],
                [hex2bin('366fddcf03e649bfa558745bc843b27c'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Portugiesisch', 'Portugal'],
                [hex2bin('355a7dc3c5d643578d99780219a6a07e'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Rumänisch', 'Republik Moldau'],
                [hex2bin('8622da119e2d4da78ad3a88562342303'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Rumänisch', 'Rumänien'],
                [hex2bin('ba0f948780ec4c53b43941ebb6ccbb1a'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Russisch', 'Russische Föderation'],
                [hex2bin('7709c4c442f44d60ac01e2b96875b7c0'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Russisch', 'Ukraine'],
                [hex2bin('7985c433b7c64b12a018f150ab5a1529'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Ruandisch', 'Ruanda'],
                [hex2bin('9ad7b91790724c61a82c3cfe93645bef'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Sanskrit', 'Indien'],
                [hex2bin('cd0de34f766b498ca4dd46a2dd24bd35'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Nord-Samisch', 'Finnland'],
                [hex2bin('dfa5457155eb49fea27943bf3dc2f48f'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Nord-Samisch', 'Norwegen'],
                [hex2bin('b1c75da2bbf5417aaec82b58df7bea43'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Serbo-Kroatisch', 'Bosnien und Herzegowina'],
                [hex2bin('2f3663edb7614308a60188c21c7963d5'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Englisch', 'Vereinigtes Königreich'],
                [hex2bin('9aad0f2a1c424d98b81bcd3d2faf0bda'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Arabisch', 'Oman'],
                [hex2bin('c84e91e448294fd5b910dabb754a0acf'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Serbo-Kroatisch', 'Serbien und Montenegro'],
                [hex2bin('4f6abb958d76460c9581e4b3dcecbb8e'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Serbo-Kroatisch', ''],
                [hex2bin('3b7b74f5f23e43598423ad00d9f0ca52'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Singhalesisch', 'Sri Lanka'],
                [hex2bin('6be9c0f234644766a5051e0ab55e41ca'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Sidamo', 'Äthiopien'],
                [hex2bin('1240bb005de546ef9e980b639e4c04a1'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Slowakisch', 'Slowakei'],
                [hex2bin('1984f39fdb824558becc7d90cd868c7c'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Slowenisch', 'Slowenien'],
                [hex2bin('ec6fd5e6be73407f84c1b0a180c802ae'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Somali', 'Dschibuti'],
                [hex2bin('dd7c423eb77f4283af0b875ed524d214'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Somali', 'Äthiopien'],
                [hex2bin('de211b3ea3774837b5cc81edbbfacfa4'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Somali', 'Kenia'],
                [hex2bin('abd65e745a3048acb978793478926d68'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Somali', 'Somalia'],
                [hex2bin('824dbd6cf5eb4e66bc346ed4dd4b7944'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Arabisch', 'Katar'],
                [hex2bin('2cc7ebce3743499398cb5c6eb5cf9161'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Albanisch', 'Albanien'],
                [hex2bin('340756898ba64b07b8dca201ff6686b7'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Serbisch', 'Bosnien und Herzegowina'],
                [hex2bin('022b45ad03834454aa41959042aa6d83'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Serbisch', 'Serbien und Montenegro'],
                [hex2bin('c0a25a5910364bd28d6bfa8f806e611d'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Serbisch', 'Montenegro'],
                [hex2bin('d79faffcf4fd4f94b083e65e3e7fba1a'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Serbisch', 'Serbien'],
                [hex2bin('ab1d6991c50c429095cf02ace40d8368'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Serbisch', ''],
                [hex2bin('f76f5280d30c4d41bbdd6b52aa6bf9bd'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Swazi', 'Swasiland'],
                [hex2bin('3bbcfcbe32a9424b95884fed18b84fd7'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Swazi', 'Südafrika'],
                [hex2bin('6c46211170f2490791a532753578501c'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Süd-Sotho-Sprache', 'Lesotho'],
                [hex2bin('e9033d791a794726b9f8674353f304d8'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Süd-Sotho-Sprache', 'Südafrika'],
                [hex2bin('52a141d757ff4abca9fd8277a60962a9'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Arabisch', 'Saudi-Arabien'],
                [hex2bin('33a1654a65e44739a16305c821453da4'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Schwedisch', 'Finnland'],
                [hex2bin('c448a02ef9a34386843b7b888f71f84a'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Schwedisch', 'Schweden'],
                [hex2bin('a92c44386ccd4b3595bf23c34ed0e67b'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Suaheli', 'Kenia'],
                [hex2bin('417b4ba5af2145f0b9598ecb171c65da'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Suaheli', 'Tansania'],
                [hex2bin('27f0a51203fb415bbc986405b779ff07'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Syrisch', 'Syrien'],
                [hex2bin('e7d42311a5d243278f1cd6fbbca2c25c'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Tamilisch', 'Indien'],
                [hex2bin('dcc080d2502c4aec9009e2a7f7b216c3'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Telugu', 'Indien'],
                [hex2bin('52bed3e53b474a9e9a6042e45d6286f1'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Tadschikisch', 'Tadschikistan'],
                [hex2bin('c11cbaf3f6274099925bb79263ccef4a'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Thailändisch', 'Thailand'],
                [hex2bin('5d08c4033b894a63a445971cb5308306'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Tigrinja', 'Eritrea'],
                [hex2bin('d0c73d0e5d0c43618a54a69acb736f4a'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Arabisch', 'Sudan'],
                [hex2bin('75a3fc2c05934e7ab71c56cbd07dbdb4'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Tigrinja', 'Äthiopien'],
                [hex2bin('437b03b211bc4f148b67e79e2e3176bb'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Tigre', 'Eritrea'],
                [hex2bin('c627c3404cfa4744bc70b568b2a3c7ab'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Tswana-Sprache', 'Südafrika'],
                [hex2bin('109d93d853864102bf3f4e621988f7f2'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Tongaisch', 'Tonga'],
                [hex2bin('30cdf6a43a4c4b97b92f0091b7996e41'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Türkisch', 'Türkei'],
                [hex2bin('d0efe2ed8662465dae0e2d1bf03b091b'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Tsonga', 'Südafrika'],
                [hex2bin('a40c28733a5842bbb2583b88cf01f06f'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Tatarisch', 'Russische Föderation'],
                [hex2bin('52fe5eb97b174e7aa63783acbbc9a97e'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Uigurisch', 'China'],
                [hex2bin('e3a69696824d415e81eb416ce5ca5b69'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Ukrainisch', 'Ukraine'],
                [hex2bin('a39b21436e1a4b03a69c784c2f9eadf8'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Arabisch', 'Syrien'],
                [hex2bin('badefc666c09416dac9612999d9e552a'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Urdu', 'Indien'],
                [hex2bin('aea666a53fef421c841f8663ab70d60c'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Urdu', 'Pakistan'],
                [hex2bin('f7808917b8434cb0940352f0ae214efc'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Usbekisch', 'Afghanistan'],
                [hex2bin('2e185dcd51464b01a427f2f75173fd53'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Usbekisch', 'Usbekistan'],
                [hex2bin('f1dcfb019cd6488fbd8070b27d7e8603'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Venda-Sprache', 'Südafrika'],
                [hex2bin('fadcb90700114c5993557f4bb2d92460'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Vietnamesisch', 'Vietnam'],
                [hex2bin('9ec94e6352224de0852a140f28c75703'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Walamo-Sprache', 'Äthiopien'],
                [hex2bin('20973ea73a4546cfa43791da4eb8c426'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Wolof', 'Senegal'],
                [hex2bin('4bae11ce4ec6469786f2af4b63f94f23'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Xhosa', 'Südafrika'],
                [hex2bin('85e4907739bd4ddcb2c777b41fa20f75'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Yoruba', 'Nigeria'],
                [hex2bin('791f15a62602420098562d29ab61eba8'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Arabisch', 'Tunesien'],
                [hex2bin('35c3c4dfd3364da79f0620fe8520cd15'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Chinesisch', 'China'],
                [hex2bin('461fdc0e9b13488c82f1be3db9800c6e'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Chinesisch', 'Sonderverwaltungszone Hongkong'],
                [hex2bin('9120da7b2cfa4c03b97b1754744e0368'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Chinesisch', 'Sonderverwaltungszone Macao'],
                [hex2bin('abc3064384fc4f7fa63655d106bf0955'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Chinesisch', 'Singapur'],
                [hex2bin('26aa2725d80049cba131fa03f9c492b3'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Chinesisch', 'Taiwan'],
                [hex2bin('8a4821d8256b490ca29739982b802045'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Zulu', 'Südafrika'],
                [hex2bin('4ce60695909f4dc6ad199090fdcf95a1'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Arabisch', 'Jemen'],
                [hex2bin('15639dadd83b4c9e869a620002fa1a7a'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Assamesisch', 'Indien'],
                [hex2bin('65afa499a81b419bbb8b7b16fcfb12d2'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Aserbaidschanisch', 'Aserbaidschan'],
                [hex2bin('858714b28c3246ad86601aa08b5debc0'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Weißrussisch', 'Belarus'],
                [hex2bin('787d38ec5e244647bf69b84e2f212808'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Afar', 'Dschibuti'],
                [hex2bin('4cec18e4bc55407aa9591990f74fd61b'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Bulgarisch', 'Bulgarien'],
                [hex2bin('4b4b461cd4f94f0cb9c854382c70cea8'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Bengalisch', 'Bangladesch'],
                [hex2bin('038dec2102a9445bb66eb09b4e5a2c1f'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Bengalisch', 'Indien'],
                [hex2bin('71b367341e7d4f6d9a2412b8d9819144'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Tibetisch', 'China'],
                [hex2bin('22051084ae324ab5ad29f5051efcd4d5'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Tibetisch', 'Indien'],
                [hex2bin('3250c52a1b7b4837822c6842d73c535c'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Bosnisch', 'Bosnien und Herzegowina'],
                [hex2bin('46a7926775cf48c786eef6dd395b4880'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Blin', 'Eritrea'],
                [hex2bin('264644c5881a422eace9fe65b386c3f9'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Katalanisch', 'Spanien'],
                [hex2bin('d6a597ef9fe64d33b4975088dba06fc1'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Atsam', 'Nigeria'],
                [hex2bin('e505cf030ff94f4da119bf7af95e015d'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Tschechisch', 'Tschechische Republik'],
                [hex2bin('2ec6cb588ad0480ea7441bbc992bcdfc'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Afar', 'Eritrea'],
                [hex2bin('cc16afc51e894a4e83dffe15c932c441'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Walisisch', 'Vereinigtes Königreich'],
                [hex2bin('509200ddb457437ba16b24c369f2bb82'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Dänisch', 'Dänemark'],
                [hex2bin('fc7b7266f656477e9a15ae23de8b0523'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Deutsch', 'Österreich'],
                [hex2bin('3dba282b2ae84d5ba360de0ddc480d1c'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Deutsch', 'Belgien'],
                [hex2bin('e17810ef31cb48b88ce871f3a03e1025'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Deutsch', 'Schweiz'],
                [hex2bin('7c7f6c130b89474bb41b460e03a70440'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Deutsch', 'Liechtenstein'],
                [hex2bin('ef5e0be1b1144e0a8688875a032a5f21'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Deutsch', 'Luxemburg'],
                [hex2bin('655478c87d3f496abfacd50eb1b48d43'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Maledivisch', 'Malediven'],
                [hex2bin('43741e431644414b8dc95ad61755b992'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Bhutanisch', 'Bhutan'],
                [hex2bin('0acb980d8d3a412cbf00842fb340feb5'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Ewe-Sprache', 'Ghana'],
                [hex2bin('e42c0c780921432b97edc5d513980b21'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Afar', 'Äthiopien'],
                [hex2bin('a1e7965325834edeb20307bf390b8da1'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Ewe-Sprache', 'Togo'],
                [hex2bin('67d9636c67614673bd8d265d295d194c'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Griechisch', 'Zypern'],
                [hex2bin('6961054faeb74e4f8851a1dfb8cb193b'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Griechisch', 'Griechenland'],
                [hex2bin('318d2533eb03461b88fda25c9a02653b'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Englisch', 'Amerikanisch-Samoa'],
                [hex2bin('8e91d9b70b704eccbc14202ba5c4882e'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Englisch', 'Australien'],
                [hex2bin('d8f3a4d870384c25b4d02654ddad64fd'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Englisch', 'Belgien'],
                [hex2bin('3e4526223d714186be74d231352e5db3'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Englisch', 'Botsuana'],
                [hex2bin('c97a13c07c4147338426d1131eab69e9'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Englisch', 'Belize'],
                [hex2bin('6590ee8662f845e08b727f7aeec84831'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Englisch', 'Kanada'],
                [hex2bin('77fc5f00b6d146d98cc40aff79eb5c00'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Englisch', 'Guam'],
                [hex2bin('801202f021334cf1b93cb0113b5d11d3'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Afrikaans', 'Namibia'],
                [hex2bin('abf8604e9b384f0689d848e011fa0241'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Englisch', 'Sonderverwaltungszone Hongkong'],
                [hex2bin('220b605583914529bcc82683f75045d3'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Englisch', 'Irland'],
                [hex2bin('51aafd199c7c4eb09b43f91c596865e1'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Englisch', 'Indien'],
                [hex2bin('92b7f309a62b411883f5a5ce89843ba8'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Englisch', 'Jamaika'],
                [hex2bin('db559634117743aaba2c907150ac7064'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Englisch', 'Marshallinseln'],
                [hex2bin('b694a0f7dc654a96b940614a7fc1b3f6'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Englisch', 'Nördliche Marianen'],
                [hex2bin('0ee0a1df025249538c1f1a391fb99a3d'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Englisch', 'Malta'],
                [hex2bin('6ab8dc296338438fbd7d4fd13406f5e9'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Englisch', 'Namibia'],
                [hex2bin('776f28188e434088872010a771e67408'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Englisch', 'Neuseeland'],
                [hex2bin('5af0fd645e5c42eebc08446be0c9f137'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Englisch', 'Philippinen'],
                [hex2bin('b68eb5f561924a5fbe6200e844469222'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Afrikaans', 'Südafrika'],
                [hex2bin('1a9f4bb15ea142ec93409b48204326d5'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Englisch', 'Pakistan'],
                [hex2bin('accdb020676044618d7ed9025a5fb26f'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Englisch', 'Singapur'],
                [hex2bin('32c59129d3f84200ab479eaccd5a3377'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Englisch', 'Trinidad und Tobago'],
                [hex2bin('a5d76921c82d415e88d76670fd6ae8dd'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Englisch', 'Amerikanisch-Ozeanien'],
                [hex2bin('d36a2c5d1f404f6f90572993897c2824'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Englisch', 'Vereinigte Staaten'],
                [hex2bin('e1bb080073ee4095b11cf2b414c168d9'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Englisch', 'Amerikanische Jungferninseln'],
                [hex2bin('e3cf90b4f4b0457195a4fa078e822037'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Englisch', 'Südafrika'],
                [hex2bin('0eb28971b99d44d9b9cb37f713a8bd92'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Englisch', 'Simbabwe'],
                [hex2bin('230d5978efda488ea80c69ce22afd008'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Spanisch', 'Argentinien'],
                [hex2bin('19a1b1874011459d9cac0868534ad2a5'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Spanisch', 'Bolivien'],
                [hex2bin('2b50e52772b54d65a605a90417e234a4'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Akan', 'Ghana'],
                [hex2bin('72039519ada248eca6f066969a03717c'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Spanisch', 'Chile'],
                [hex2bin('16059d9b9ae04854846025397e687de1'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Spanisch', 'Kolumbien'],
                [hex2bin('174969081e324959943afa829f9a2ea5'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Spanisch', 'Costa Rica'],
                [hex2bin('5087c8b0da5f41e79604be3e0c081918'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Spanisch', 'Dominikanische Republik'],
                [hex2bin('b85ae453fcd94734971dfe4fc8becffb'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Spanisch', 'Ecuador'],
                [hex2bin('ee2edb8fbbbe4402b6f7bb6b29f24494'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Spanisch', 'Spanien'],
                [hex2bin('5de1d1620301481fb3125118db4b3cca'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Spanisch', 'Guatemala'],
                [hex2bin('5d1b33e545ff4d3bbe68268bf288fe99'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Spanisch', 'Honduras'],
                [hex2bin('4771c56731fb4fa8901a895af1fab756'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Spanisch', 'Mexiko'],
                [hex2bin('16930d551d6548a4b6a79f156b90b4ca'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Spanisch', 'Nicaragua'],
                [hex2bin('3569183cd45f4b459c4e1bcb0936b258'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Amharisch', 'Äthiopien'],
                [hex2bin('b47ee4c5e52e4302922de64dc70be679'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Spanisch', 'Panama'],
                [hex2bin('bf30d531acf94ba7b21bb328a793bccf'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Spanisch', 'Peru'],
                [hex2bin('15a76fb0e3c140b98a522368fb480097'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Spanisch', 'Puerto Rico'],
                [hex2bin('546d97038eaf437eb110a6af780f4d2d'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Spanisch', 'Paraguay'],
                [hex2bin('b027d25dc8534bb089c197efc46c952c'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Spanisch', 'El Salvador'],
                [hex2bin('2a3cbc5da5c64f2fb216c4687211cc9d'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Spanisch', 'Vereinigte Staaten'],
                [hex2bin('249e48390244447ba89570f5af0baef4'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Spanisch', 'Uruguay'],
                [hex2bin('8a368fb88b1947b78ed13db81210a5b8'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Spanisch', 'Venezuela'],
                [hex2bin('dc0f93ca81814f36bbf3446b78b3d27d'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Estnisch', 'Estland'],
                [hex2bin('31996fe03e604296827c0de7ae910245'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Baskisch', 'Spanien'],
            ]
        );
    }

    private function importPaymentMethod(string $tenantId)
    {
        $this->importTable(
            $tenantId,
            'payment_method',
            ['id', 'version_id', 'technical_name', 'template', 'class', '`table`', 'hide', 'percentage_surcharge', 'absolute_surcharge', 'surcharge_string', 'position', 'active', 'allow_esd', 'used_iframe', 'hide_prospect', 'action', 'plugin_id', 'source', 'mobile_inactive', 'risk_rules', 'created_at', 'updated_at'],
            ['tenant_id'],
            [
                [hex2bin('e84976ace9ab4928a3dcc387b66dbaa6'), hex2bin('ffffffffffffffffffffffffffffffff'), 'debit', 'debit.tpl', 'Shopware\\Checkout\\Payment\\Cart\\PaymentHandler\\DebitPayment', '', 0, -10, null, '', 4, 0, 0, '', 0, '', null, null, 0, null, '2017-12-14 15:45:46', null],
                [hex2bin('77573b9cf7914cb5a9519945bff1d95b'), hex2bin('ffffffffffffffffffffffffffffffff'), 'cash', 'cash.tpl', 'Shopware\\Checkout\\Payment\\Cart\\PaymentHandler\\CashPayment', '', 0, null, null, '', 2, 1, 0, '', 0, null, null, null, 0, null, '2017-12-14 15:45:46', null],
                [hex2bin('19d144ffe15f4772860d59fca7f207c1'), hex2bin('ffffffffffffffffffffffffffffffff'), 'invoice', 'invoice.tpl', 'Shopware\\Checkout\\Payment\\Cart\\PaymentHandler\\InvoicePayment', '', 0, null, 5, '', 3, 1, 1, '', 0, '', null, null, 0, null, '2017-12-14 15:45:46', null],
                [hex2bin('47160b00cd064b0188176451f9f3c247'), hex2bin('ffffffffffffffffffffffffffffffff'), 'prepayment', 'prepayment.tpl', 'Shopware\\Checkout\\Payment\\Cart\\PaymentHandler\\PrePayment', '', 0, null, null, '', 1, 1, 0, '', 0, null, null, null, 0, null, '2017-12-14 15:45:46', null],
                [hex2bin('a6ddadce4cb441f3976a32505049f037'), hex2bin('ffffffffffffffffffffffffffffffff'), 'sepa', '@Checkout/frontend/sepa.html.twig', 'Shopware\\Checkout\\Payment\\Cart\\PaymentHandler\\SEPAPayment', '', 0, null, null, '', 5, 1, 1, '', 0, '', null, null, 0, null, '2017-12-14 15:45:46', null],
            ]
        );

        $this->importTable(
            $tenantId,
            'payment_method_translation',
            ['payment_method_id', 'payment_method_version_id', 'language_id', 'name', 'additional_description'],
            ['payment_method_tenant_id', 'language_tenant_id'],
            [
                [hex2bin('e84976ace9ab4928a3dcc387b66dbaa6'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Debit', 'Additional text'],
                [hex2bin('77573b9cf7914cb5a9519945bff1d95b'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Cash on delivery', '(including 2.00 Euro VAT)'],
                [hex2bin('19d144ffe15f4772860d59fca7f207c1'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Invoice', 'Payment by invoice. Shopware provides automatic invoicing for all customers on orders after the first, in order to avoid defaults on payment.'],
                [hex2bin('47160b00cd064b0188176451f9f3c247'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Paid in advance', 'The goods are delivered directly upon receipt of payment.'],
                [hex2bin('a6ddadce4cb441f3976a32505049f037'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'SEPA direct debit', ''],
            ]
        );
    }

    private function importShippingMethod(string $tenantId)
    {
        $this->importTable(
            $tenantId,
            'shipping_method',
            ['id', 'version_id', 'type', 'active', 'position', 'calculation', 'surcharge_calculation', 'tax_calculation', 'shipping_free', 'bind_shippingfree', 'bind_time_from', 'bind_time_to', 'bind_instock', 'bind_laststock', 'bind_weekday_from', 'bind_weekday_to', 'bind_weight_from', 'bind_weight_to', 'bind_price_from', 'bind_price_to', 'bind_sql', 'status_link', 'calculation_sql', 'created_at', 'updated_at'],
            ['tenant_id'],
            [
                [hex2bin('417beeb2dddf45d1b90188fd211343c3'), hex2bin('ffffffffffffffffffffffffffffffff'), 0, 1, 1, 1, 3, 0, null, 0, null, null, null, 0, null, null, null, 1.000, null, null, null, '', null, '2017-12-14 15:45:50', null],
            ]
        );
        $this->importTable(
            $tenantId,
            'shipping_method_translation',
            ['shipping_method_id', 'shipping_method_version_id', 'language_id', 'name', 'description', 'comment'],
            ['shipping_method_tenant_id', 'language_tenant_id'],
            [
                 [hex2bin('417beeb2dddf45d1b90188fd211343c3'), hex2bin('ffffffffffffffffffffffffffffffff'), hex2bin('ffffffffffffffffffffffffffffffff'), 'Standard Versand', '', ''],
            ]
        );
    }

    private function importTax(string $tenantId)
    {
        $this->importTable(
            $tenantId,
            'tax',
            ['id', 'version_id', 'tax_rate', 'name', 'created_at', 'updated_at'],
            ['tenant_id'],
            [
                [hex2bin('4926035368e34d9fa695e017d7a231b9'), hex2bin('ffffffffffffffffffffffffffffffff'), '19', '19%', '2017-12-14 15:51:51', null],
                [hex2bin('a297709e9e914995af8263ee214583e6'), hex2bin('ffffffffffffffffffffffffffffffff'), '7', '7%', '2017-12-14 15:51:51', null],
            ]
        );
    }

    private function importListingSorting(string $tenantId)
    {
        $this->importTable(
            $tenantId,
            'listing_sorting',
            ['id', 'version_id', 'active', 'unique_key', 'display_in_categories', 'position', 'payload', 'created_at', 'updated_at'],
            ['tenant_id'],
            [
                [hex2bin('361D52E6A9894467B4FEAF5E5A799383'), hex2bin('FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFF'), 1, 'cheapest-price', 1, 1, '[{"_class":"Shopware\\\\Framework\\\\ORM\\\\Search\\\\Sorting\\\\FieldSorting","field":"product.listingPrices","direction":"ASC","extensions":[]}]', '2018-03-22 15:10:21', null],
                [hex2bin('4F0B50F58286488FB8D88B43934534E2'), hex2bin('FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFF'), 1, 'highest-price', 1, 1, '[{"_class":"Shopware\\\\Framework\\\\ORM\\\\Search\\\\Sorting\\\\FieldSorting","field":"product.listingPrices","direction":"DESC","extensions":[]}]', '2018-03-22 15:10:21', null],
                [hex2bin('5727B79736A44CB1B1CC904820570DB9'), hex2bin('FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFF'), 1, 'product-name', 1, 1, '[{"_class":"Shopware\\\\Framework\\\\ORM\\\\Search\\\\Sorting\\\\FieldSorting","field":"product.name","direction":"ASC","extensions":[]}]', '2018-03-22 15:10:21', null],
            ]
        );
        $this->importTable(
            $tenantId,
            'listing_sorting_translation',
            ['listing_sorting_id', 'language_id', 'listing_sorting_version_id', 'label'],
            ['listing_sorting_tenant_id', 'language_tenant_id'],
            [
                [hex2bin('361D52E6A9894467B4FEAF5E5A799383'), hex2bin('FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFF'), hex2bin('FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFF'), 'Cheapest price'],
                [hex2bin('4F0B50F58286488FB8D88B43934534E2'), hex2bin('FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFF'), hex2bin('FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFF'), 'Highest price'],
                [hex2bin('5727B79736A44CB1B1CC904820570DB9'), hex2bin('FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFF'), hex2bin('FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFF'), 'Product name'],
            ]
        );
    }
}

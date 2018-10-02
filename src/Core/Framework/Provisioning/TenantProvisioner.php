<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Provisioning;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Exception\InvalidUuidException;
use Shopware\Core\Framework\Struct\Uuid;

class TenantProvisioner
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var string
     */
    private $defaultId;

    /**
     * @var string
     */
    private $tenantIdBin;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;

        $this->defaultId = hex2bin(Defaults::TENANT_ID);
    }

    public function provision(string $tenantId = null): string
    {
        $tenantId = $tenantId ?? Uuid::uuid4()->getHex();
        $this->tenantIdBin = Uuid::fromHexToBytes($tenantId);

        if (!Uuid::isValid($tenantId)) {
            throw new InvalidUuidException($tenantId);
        }

        $this->connection->executeUpdate('SET FOREIGN_KEY_CHECKS=0;');
        $this->connection->beginTransaction();

        try {
            $this->createCatalog();
            $this->createLanguage();
            $this->createCountry();
            $this->createOrderState();
            $this->createOrderTransactionState();
            $this->createCurrency();
            $this->createCustomerGroup();
            $this->createLocale();
            $this->createPaymentMethod();
            $this->createShippingMethod();
            $this->createTax();
            $this->createSalesChannelTypes();
            $this->createSalesChannel();
            $this->createProductManufacturer();

            $this->connection->commit();
        } catch (\Exception $ex) {
            $this->connection->rollBack();
            throw $ex;
        } finally {
            $this->connection->executeUpdate('SET FOREIGN_KEY_CHECKS=1;');
        }

        return $tenantId;
    }

    private function importTable(string $table, array $columns, array $tenantColumns, array $records): void
    {
        foreach ($records as $record) {
            $combined = array_combine($columns, $record);

            foreach ($tenantColumns as $column) {
                $fk = str_replace('tenant_id', 'id', $column);

                if (!isset($combined[$fk]) && $column !== 'tenant_id') {
                    continue;
                }

                $combined[$column] = $this->tenantIdBin;
            }

            $this->connection->insert($table, $combined);
        }
    }

    private function createCatalog(): void
    {
        $this->importTable(
            'catalog',
            ['id', 'created_at'],
            ['tenant_id'],
            [
                [$this->defaultId, $this->now()],
            ]
        );

        $this->importTable(
            'catalog_translation',
            ['catalog_id', 'language_id', 'name', 'created_at'],
            ['catalog_tenant_id', 'language_tenant_id'],
            [
                [$this->defaultId, $this->defaultId, 'Default catalogue', $this->now()],
            ]
        );
    }

    private function createLanguage(): void
    {
        $this->importTable(
            'language',
            ['id', 'name', 'parent_id', 'locale_id', 'locale_version_id', 'created_at'],
            ['tenant_id', 'parent_tenant_id', 'locale_tenant_id'],
            [
                [$this->defaultId, 'English', null, $this->defaultId, $this->defaultId, $this->now()],
            ]
        );
    }

    private function createCountry(): void
    {
        $this->importTable(
            'country',
            ['id', 'version_id', 'iso', 'country_area_id', 'position', 'shipping_free', 'tax_free', 'taxfree_for_vat_id', 'taxfree_vatid_checked', 'active', 'iso3', 'display_state_in_registration', 'force_state_in_registration', 'created_at', 'updated_at'],
            ['tenant_id', 'country_area_tenant_id'],
            [
                [hex2bin('ffe61e1c99154f9597014a310ab5482d'), $this->defaultId, 'GR', hex2bin('dde2e7c598144e73ba03b093107ce5cf'), 10, 0, 0, 0, 0, 0, 'GRC', 0, 0, $this->now(), null],
                [hex2bin('6c72828ec5e240588a35114cf1d4d5ef'), $this->defaultId, 'GB', hex2bin('dde2e7c598144e73ba03b093107ce5cf'), 10, 0, 0, 0, 0, 0, 'GBR', 0, 0, $this->now(), null],
                [hex2bin('584c3ff22f5644789705383bde891fc9'), $this->defaultId, 'IE', hex2bin('dde2e7c598144e73ba03b093107ce5cf'), 10, 0, 0, 0, 0, 0, 'IRL', 0, 0, $this->now(), null],
                [hex2bin('b72b9b7cd26b4a40a36f2e76a1bf42c1'), $this->defaultId, 'IS', hex2bin('dde2e7c598144e73ba03b093107ce5cf'), 10, 0, 0, 0, 0, 0, 'ISL', 0, 0, $this->now(), null],
                [hex2bin('92ca022e9d28492e9ea173f279fa6755'), $this->defaultId, 'IT', hex2bin('dde2e7c598144e73ba03b093107ce5cf'), 10, 0, 0, 0, 0, 1, 'ITA', 0, 0, $this->now(), null],
                [hex2bin('e130d974fd6c438485972fe00b5cd609'), $this->defaultId, 'JP', hex2bin('e0353dc44ccb465a9c980aa6758abcf6'), 10, 0, 0, 0, 0, 0, 'JPN', 0, 0, $this->now(), null],
                [hex2bin('a453634acb414768b2542ae9a57639b5'), $this->defaultId, 'CA', hex2bin('e0353dc44ccb465a9c980aa6758abcf6'), 10, 0, 0, 0, 0, 0, 'CAN', 0, 0, $this->now(), null],
                [hex2bin('e5cbe4b2105843c3bdef2e9c03eccaae'), $this->defaultId, 'LU', hex2bin('dde2e7c598144e73ba03b093107ce5cf'), 10, 0, 0, 0, 0, 0, 'LUX', 0, 0, $this->now(), null],
                [$this->defaultId, $this->defaultId, 'DE', hex2bin('5cff02b1029741a4891c430bcd9e3603'), 1, 0, 0, 0, 0, 1, 'DEU', 0, 0,  $this->now(), null],
                [hex2bin('9deee5660fd1474fbecdf6fc1809add3'), $this->defaultId, 'NA', hex2bin('e0353dc44ccb465a9c980aa6758abcf6'), 10, 0, 0, 0, 0, 0, 'NAM', 0, 0, $this->now(), null],
                [hex2bin('04ed51ccbb2341bc9b352d78e64213fb'), $this->defaultId, 'NL', hex2bin('dde2e7c598144e73ba03b093107ce5cf'), 10, 0, 0, 0, 0, 1, 'NLD', 0, 0, $this->now(), null],
                [hex2bin('e216449bd67646cc9176a1d57a2f8094'), $this->defaultId, 'NO', hex2bin('dde2e7c598144e73ba03b093107ce5cf'), 10, 0, 0, 0, 0, 0, 'NOR', 0, 0, $this->now(), null],
                [hex2bin('c650574d63d34834b836d8e7f0339ca8'), $this->defaultId, 'AT', hex2bin('dde2e7c598144e73ba03b093107ce5cf'), 1, 0, 0, 0, 0, 1, 'AUT', 0, 0,  $this->now(), null],
                [hex2bin('a40ed5b07bca4b06995a0a56b1170155'), $this->defaultId, 'PT', hex2bin('dde2e7c598144e73ba03b093107ce5cf'), 10, 0, 0, 0, 0, 0, 'PRT', 0, 0, $this->now(), null],
                [hex2bin('f7b0810e24234ae397c769b260a02474'), $this->defaultId, 'SE', hex2bin('dde2e7c598144e73ba03b093107ce5cf'), 10, 0, 0, 0, 0, 0, 'SWE', 0, 0, $this->now(), null],
                [hex2bin('4f52e121f6724b968c00d05829f9a38d'), $this->defaultId, 'CH', hex2bin('dde2e7c598144e73ba03b093107ce5cf'), 10, 0, 1, 0, 0, 1, 'CHE', 0, 0, $this->now(), null],
                [hex2bin('2aba3f2990e044c78bf53b87fb6c3af3'), $this->defaultId, 'ES', hex2bin('dde2e7c598144e73ba03b093107ce5cf'), 10, 0, 0, 0, 0, 1, 'ESP', 0, 0, $this->now(), null],
                [hex2bin('bdcb207c87ab4648b5acde9138f48894'), $this->defaultId, 'US', hex2bin('e0353dc44ccb465a9c980aa6758abcf6'), 10, 0, 0, 0, 0, 0, 'USA', 0, 0, $this->now(), null],
                [hex2bin('e163778197a24b61bd2ae72d006a6d3c'), $this->defaultId, 'LI', hex2bin('dde2e7c598144e73ba03b093107ce5cf'), 10, 0, 0, 0, 0, 0, 'LIE', 0, 0, $this->now(), null],
                [hex2bin('448744a58b9f44e58c40804fef6520f8'), $this->defaultId, 'AE', hex2bin('e0353dc44ccb465a9c980aa6758abcf6'), 10, 0, 0, 0, 0, 1, 'ARE', 0, 0, $this->now(), null],
                [hex2bin('259f7c2be0b44eb6a273a70ea6dd8029'), $this->defaultId, 'PL', hex2bin('dde2e7c598144e73ba03b093107ce5cf'), 10, 0, 0, 0, 0, 0, 'POL', 0, 0, $this->now(), null],
                [hex2bin('d99834aefa4941b490dae37d0027f6bc'), $this->defaultId, 'HU', hex2bin('dde2e7c598144e73ba03b093107ce5cf'), 10, 0, 0, 0, 0, 0, 'HUN', 0, 0, $this->now(), null],
                [hex2bin('11cf2cdd303c41d7bf66808bfe7769a5'), $this->defaultId, 'TR', hex2bin('e0353dc44ccb465a9c980aa6758abcf6'), 10, 0, 0, 0, 0, 0, 'TUR', 0, 0, $this->now(), null],
                [hex2bin('b240408078894b0491634af5963f0c04'), $this->defaultId, 'CZ', hex2bin('dde2e7c598144e73ba03b093107ce5cf'), 10, 0, 0, 0, 0, 0, 'CZE', 0, 0, $this->now(), null],
                [hex2bin('8021ae3dd9ec4675920c16152473e5cc'), $this->defaultId, 'SK', hex2bin('dde2e7c598144e73ba03b093107ce5cf'), 10, 0, 0, 0, 0, 0, 'SVK', 0, 0, $this->now(), null],
                [hex2bin('1d56b07f6a5e4ee0a4e23abc06ba9b1e'), $this->defaultId, 'RO', hex2bin('dde2e7c598144e73ba03b093107ce5cf'), 10, 0, 0, 0, 0, 0, 'ROU', 0, 0, $this->now(), null],
                [hex2bin('68fea9f12c9c46748382b1f48a32014f'), $this->defaultId, 'BR', hex2bin('e0353dc44ccb465a9c980aa6758abcf6'), 10, 0, 0, 0, 0, 0, 'BRA', 0, 0, $this->now(), null],
                [hex2bin('1c91bf01a6a547a78497abf7b8e4e5db'), $this->defaultId, 'IL', hex2bin('e0353dc44ccb465a9c980aa6758abcf6'), 10, 0, 0, 0, 0, 0, 'ISR', 0, 0, $this->now(), null],
                [hex2bin('5a4aa22452e04acca23185d4d21bb3bf'), $this->defaultId, 'AU', hex2bin('e0353dc44ccb465a9c980aa6758abcf6'), 10, 0, 0, 0, 0, 1, 'AUS', 0, 0, $this->now(), null],
                [hex2bin('2e54611a053b4b19afccca547f50bf56'), $this->defaultId, 'BE', hex2bin('dde2e7c598144e73ba03b093107ce5cf'), 10, 0, 0, 0, 0, 1, 'BEL', 0, 0, $this->now(), null],
                [hex2bin('1d7911d918714c3ea9f0d7339afd3d43'), $this->defaultId, 'DK', hex2bin('dde2e7c598144e73ba03b093107ce5cf'), 10, 0, 0, 0, 0, 1, 'DNK', 0, 0, $this->now(), null],
                [hex2bin('9d8661d69c10416c858dbf408ec2500a'), $this->defaultId, 'FI', hex2bin('dde2e7c598144e73ba03b093107ce5cf'), 10, 0, 0, 0, 0, 1, 'FIN', 0, 0, $this->now(), null],
                [hex2bin('e85c25e1cdfc4cd4af49d54c34aa3d25'), $this->defaultId, 'FR', hex2bin('dde2e7c598144e73ba03b093107ce5cf'), 10, 0, 0, 0, 0, 0, 'FRA', 0, 0, $this->now(), null],
            ]
        );

        $this->importTable(
            'country_translation',
            ['country_id', 'country_version_id', 'language_id', 'name', 'created_at'],
            ['country_tenant_id', 'language_tenant_id'],
            [
                [hex2bin('ffe61e1c99154f9597014a310ab5482d'), $this->defaultId, $this->defaultId, 'Griechenland', $this->now()],
                [hex2bin('6c72828ec5e240588a35114cf1d4d5ef'), $this->defaultId, $this->defaultId, 'Großbritannien', $this->now()],
                [hex2bin('584c3ff22f5644789705383bde891fc9'), $this->defaultId, $this->defaultId, 'Irland', $this->now()],
                [hex2bin('b72b9b7cd26b4a40a36f2e76a1bf42c1'), $this->defaultId, $this->defaultId, 'Island', $this->now()],
                [hex2bin('92ca022e9d28492e9ea173f279fa6755'), $this->defaultId, $this->defaultId, 'Italien', $this->now()],
                [hex2bin('e130d974fd6c438485972fe00b5cd609'), $this->defaultId, $this->defaultId, 'Japan', $this->now()],
                [hex2bin('a453634acb414768b2542ae9a57639b5'), $this->defaultId, $this->defaultId, 'Kanada', $this->now()],
                [hex2bin('e5cbe4b2105843c3bdef2e9c03eccaae'), $this->defaultId, $this->defaultId, 'Luxemburg', $this->now()],
                [$this->defaultId, $this->defaultId, $this->defaultId, 'Deutschland', $this->now()],
                [hex2bin('9deee5660fd1474fbecdf6fc1809add3'), $this->defaultId, $this->defaultId, 'Namibia', $this->now()],
                [hex2bin('04ed51ccbb2341bc9b352d78e64213fb'), $this->defaultId, $this->defaultId, 'Niederlande', $this->now()],
                [hex2bin('e216449bd67646cc9176a1d57a2f8094'), $this->defaultId, $this->defaultId, 'Norwegen', $this->now()],
                [hex2bin('c650574d63d34834b836d8e7f0339ca8'), $this->defaultId, $this->defaultId, 'Österreich', $this->now()],
                [hex2bin('a40ed5b07bca4b06995a0a56b1170155'), $this->defaultId, $this->defaultId, 'Portugal', $this->now()],
                [hex2bin('f7b0810e24234ae397c769b260a02474'), $this->defaultId, $this->defaultId, 'Schweden', $this->now()],
                [hex2bin('4f52e121f6724b968c00d05829f9a38d'), $this->defaultId, $this->defaultId, 'Schweiz', $this->now()],
                [hex2bin('2aba3f2990e044c78bf53b87fb6c3af3'), $this->defaultId, $this->defaultId, 'Spanien', $this->now()],
                [hex2bin('bdcb207c87ab4648b5acde9138f48894'), $this->defaultId, $this->defaultId, 'USA', $this->now()],
                [hex2bin('e163778197a24b61bd2ae72d006a6d3c'), $this->defaultId, $this->defaultId, 'Liechtenstein', $this->now()],
                [hex2bin('448744a58b9f44e58c40804fef6520f8'), $this->defaultId, $this->defaultId, 'Arabische Emirate', $this->now()],
                [hex2bin('259f7c2be0b44eb6a273a70ea6dd8029'), $this->defaultId, $this->defaultId, 'Polen', $this->now()],
                [hex2bin('d99834aefa4941b490dae37d0027f6bc'), $this->defaultId, $this->defaultId, 'Ungarn', $this->now()],
                [hex2bin('11cf2cdd303c41d7bf66808bfe7769a5'), $this->defaultId, $this->defaultId, 'Türkei', $this->now()],
                [hex2bin('b240408078894b0491634af5963f0c04'), $this->defaultId, $this->defaultId, 'Tschechien', $this->now()],
                [hex2bin('8021ae3dd9ec4675920c16152473e5cc'), $this->defaultId, $this->defaultId, 'Slowakei', $this->now()],
                [hex2bin('1d56b07f6a5e4ee0a4e23abc06ba9b1e'), $this->defaultId, $this->defaultId, 'Rumänien', $this->now()],
                [hex2bin('68fea9f12c9c46748382b1f48a32014f'), $this->defaultId, $this->defaultId, 'Brasilien', $this->now()],
                [hex2bin('1c91bf01a6a547a78497abf7b8e4e5db'), $this->defaultId, $this->defaultId, 'Israel', $this->now()],
                [hex2bin('5a4aa22452e04acca23185d4d21bb3bf'), $this->defaultId, $this->defaultId, 'Australien', $this->now()],
                [hex2bin('2e54611a053b4b19afccca547f50bf56'), $this->defaultId, $this->defaultId, 'Belgien', $this->now()],
                [hex2bin('1d7911d918714c3ea9f0d7339afd3d43'), $this->defaultId, $this->defaultId, 'Dänemark', $this->now()],
                [hex2bin('9d8661d69c10416c858dbf408ec2500a'), $this->defaultId, $this->defaultId, 'Finnland', $this->now()],
                [hex2bin('e85c25e1cdfc4cd4af49d54c34aa3d25'), $this->defaultId, $this->defaultId, 'Frankreich', $this->now()],
            ]
        );
    }

    private function createOrderState(): void
    {
        $states = [
            [hex2bin('1194A493806742C9B85E61F1F2CF9BE8'), $this->defaultId, 1, 0, $this->now(), null],
            [Uuid::uuid4()->getBytes(), $this->defaultId, 2, 0, $this->now(), null],
            [Uuid::uuid4()->getBytes(), $this->defaultId, 3, 0, $this->now(), null],
            [Uuid::uuid4()->getBytes(), $this->defaultId, 4, 0, $this->now(), null],
            [Uuid::uuid4()->getBytes(), $this->defaultId, 5, 0, $this->now(), null],
            [Uuid::uuid4()->getBytes(), $this->defaultId, 6, 0, $this->now(), null],
            [Uuid::uuid4()->getBytes(), $this->defaultId, 7, 0, $this->now(), null],
            [Uuid::uuid4()->getBytes(), $this->defaultId, 8, 0, $this->now(), null],
            [Uuid::uuid4()->getBytes(), $this->defaultId, 9, 0, $this->now(), null],
            [Uuid::uuid4()->getBytes(), $this->defaultId, 10, 0, $this->now(), null],
        ];

        $this->importTable(
            'order_state',
            ['id', 'version_id', 'position', 'has_mail', 'created_at', 'updated_at'],
            ['tenant_id'],
            $states
        );

        $this->importTable(
            'order_state_translation',
            ['order_state_id', 'order_state_version_id', 'language_id', 'description', 'created_at'],
            ['order_state_tenant_id', 'language_tenant_id'],
            [
                [$states[0][0], $this->defaultId, $this->defaultId, 'Open', $this->now()],
                [$states[1][0], $this->defaultId, $this->defaultId, 'Completed', $this->now()],
                [$states[2][0], $this->defaultId, $this->defaultId, 'Cancelled', $this->now()],
                [$states[3][0], $this->defaultId, $this->defaultId, 'In progress', $this->now()],
                [$states[4][0], $this->defaultId, $this->defaultId, 'Partially completed', $this->now()],
                [$states[5][0], $this->defaultId, $this->defaultId, 'Cancelled (rejected)', $this->now()],
                [$states[6][0], $this->defaultId, $this->defaultId, 'Ready for delivery', $this->now()],
                [$states[7][0], $this->defaultId, $this->defaultId, 'Partially delivered', $this->now()],
                [$states[8][0], $this->defaultId, $this->defaultId, 'Completely delivered', $this->now()],
                [$states[9][0], $this->defaultId, $this->defaultId, 'Clarification required', $this->now()],
            ]
        );
    }

    private function createOrderTransactionState(): void
    {
        $states = [
            [hex2bin('60025B03849340BA8D1ABF7E58AA2B9F'), $this->defaultId, 1, 0, $this->now(), null],
            [hex2bin('B64BFC7F379144829365A6994A3B56E6'), $this->defaultId, 2, 0, $this->now(), null],
            [hex2bin('099E79DBFA9F43E4876B172FF58359F2'), $this->defaultId, 3, 0, $this->now(), null],

            [Uuid::uuid4()->getBytes(), $this->defaultId, 4, 0, $this->now(), null],
            [Uuid::uuid4()->getBytes(), $this->defaultId, 5, 0, $this->now(), null],
            [Uuid::uuid4()->getBytes(), $this->defaultId, 6, 0, $this->now(), null],
            [Uuid::uuid4()->getBytes(), $this->defaultId, 7, 0, $this->now(), null],
            [Uuid::uuid4()->getBytes(), $this->defaultId, 8, 0, $this->now(), null],
            [Uuid::uuid4()->getBytes(), $this->defaultId, 9, 0, $this->now(), null],
            [Uuid::uuid4()->getBytes(), $this->defaultId, 10, 0, $this->now(), null],
            [Uuid::uuid4()->getBytes(), $this->defaultId, 11, 0, $this->now(), null],
            [Uuid::uuid4()->getBytes(), $this->defaultId, 12, 0, $this->now(), null],
            [Uuid::uuid4()->getBytes(), $this->defaultId, 13, 0, $this->now(), null],
            [Uuid::uuid4()->getBytes(), $this->defaultId, 14, 0, $this->now(), null],
            [Uuid::uuid4()->getBytes(), $this->defaultId, 15, 0, $this->now(), null],
            [Uuid::uuid4()->getBytes(), $this->defaultId, 16, 0, $this->now(), null],
            [Uuid::uuid4()->getBytes(), $this->defaultId, 17, 0, $this->now(), null],
            [Uuid::uuid4()->getBytes(), $this->defaultId, 18, 0, $this->now(), null],
            [Uuid::uuid4()->getBytes(), $this->defaultId, 19, 0, $this->now(), null],
            [Uuid::uuid4()->getBytes(), $this->defaultId, 20, 0, $this->now(), null],
        ];

        $this->importTable(
            'order_transaction_state',
            ['id', 'version_id', 'position', 'has_mail', 'created_at', 'updated_at'],
            ['tenant_id'],
            $states
        );

        $this->importTable(
            'order_transaction_state_translation',
            ['order_transaction_state_id', 'order_transaction_state_version_id', 'language_id', 'description', 'created_at'],
            ['order_transaction_state_tenant_id', 'language_tenant_id'],
            [
                [$states[0][0], $this->defaultId, $this->defaultId, 'Completed', $this->now()],
                [$states[1][0], $this->defaultId, $this->defaultId, 'Cancelled', $this->now()],
                [$states[2][0], $this->defaultId, $this->defaultId, 'Open', $this->now()],

                [$states[3][0], $this->defaultId, $this->defaultId, 'Partially invoiced', $this->now()],
                [$states[4][0], $this->defaultId, $this->defaultId, 'Completely invoiced', $this->now()],
                [$states[5][0], $this->defaultId, $this->defaultId, 'Partially paid', $this->now()],
                [$states[6][0], $this->defaultId, $this->defaultId, 'Completely paid', $this->now()],
                [$states[7][0], $this->defaultId, $this->defaultId, '1st reminder', $this->now()],
                [$states[8][0], $this->defaultId, $this->defaultId, '2nd reminder', $this->now()],
                [$states[9][0], $this->defaultId, $this->defaultId, '3rd reminder', $this->now()],
                [$states[10][0], $this->defaultId, $this->defaultId, 'Encashment', $this->now()],
                [$states[11][0], $this->defaultId, $this->defaultId, 'Reserved', $this->now()],
                [$states[12][0], $this->defaultId, $this->defaultId, 'Delayed', $this->now()],
                [$states[13][0], $this->defaultId, $this->defaultId, 'Re-crediting', $this->now()],
                [$states[14][0], $this->defaultId, $this->defaultId, 'Review necessary', $this->now()],
                [$states[15][0], $this->defaultId, $this->defaultId, 'No credit approved', $this->now()],
                [$states[16][0], $this->defaultId, $this->defaultId, 'Credit preliminarily accepted', $this->now()],
                [$states[17][0], $this->defaultId, $this->defaultId, 'Credit accepted', $this->now()],
                [$states[18][0], $this->defaultId, $this->defaultId, 'Payment ordered', $this->now()],
                [$states[19][0], $this->defaultId, $this->defaultId, 'Time extension registered', $this->now()],
            ]
        );
    }

    private function createCurrency(): void
    {
        $currencies = [
            [$this->defaultId, $this->defaultId, 1, 1, '€', 0, 0, $this->now(), null],
            [Uuid::uuid4()->getBytes(), $this->defaultId, 0, 1.17085, '$', 0, 0, $this->now(), null],
            [Uuid::uuid4()->getBytes(), $this->defaultId, 0, 0.89157, '£', 0, 0, $this->now(), null],
        ];

        $this->importTable(
            'currency',
            ['id', 'version_id', 'is_default', 'factor', 'symbol', 'placed_in_front', 'position', 'created_at', 'updated_at'],
            ['tenant_id'],
            $currencies
        );

        $this->importTable(
            'currency_translation',
            ['currency_id', 'currency_version_id', 'language_id', 'short_name', 'name', 'created_at'],
            ['currency_tenant_id', 'language_tenant_id'],
            [
                [$currencies[0][0], $this->defaultId, $this->defaultId, 'EUR', 'Euro', $this->now()],
                [$currencies[1][0], $this->defaultId, $this->defaultId, 'USD', 'US-Dollar', $this->now()],
                [$currencies[2][0], $this->defaultId, $this->defaultId, 'GBP', 'Pound', $this->now()],
            ]
        );
    }

    private function createCustomerGroup(): void
    {
        $this->importTable(
            'customer_group',
            ['id', 'version_id', 'display_gross', 'input_gross', 'has_global_discount', 'percentage_global_discount', 'minimum_order_amount', 'minimum_order_amount_surcharge', 'created_at', 'updated_at'],
            ['tenant_id'],
            [
                [$this->defaultId, $this->defaultId, 1, 1, 0, 0, 0, 0, $this->now(), null],
            ]
        );

        $this->importTable(
            'customer_group_translation',
            ['customer_group_id', 'customer_group_version_id', 'language_id', 'name', 'created_at'],
            ['customer_group_tenant_id', 'language_tenant_id'],
            [
                [$this->defaultId, $this->defaultId, $this->defaultId, 'Standard customer group', $this->now()],
            ]
        );
    }

    private function createLocale(): void
    {
        $locales = [
            [$this->defaultId, $this->defaultId, 'en_GB', $this->now(), null],
            [hex2bin('2f3663edb7614308a60188c21c7963d5'), $this->defaultId, 'de_DE', $this->now(), null],
        ];

        $this->importTable(
            'locale',
            ['id', 'version_id', 'code', 'created_at', 'updated_at'],
            ['tenant_id'],
            $locales
        );

        $this->importTable(
            'locale_translation',
            ['locale_id', 'locale_version_id', 'language_id', 'name', 'territory', 'created_at'],
            ['locale_tenant_id', 'language_tenant_id'],
            [
                [$locales[0][0], $this->defaultId, $this->defaultId, 'English', 'United Kingdom', $this->now()],
                [$locales[1][0], $this->defaultId, $this->defaultId, 'German', 'Germany', $this->now()],
            ]
        );
    }

    private function createPaymentMethod(): void
    {
        $paymentMethods = [
            [hex2bin('e84976ace9ab4928a3dcc387b66dbaa6'), $this->defaultId, 'debit', 'debit.tpl', 'Shopware\\Core\\Checkout\\Payment\\Cart\\PaymentHandler\\DebitPayment', '', 0, -10, null, '', 4, 0, 0, '', 0, '', null, null, 0, null, $this->now(), null],
            [hex2bin('19d144ffe15f4772860d59fca7f207c1'), $this->defaultId, 'invoice', 'invoice.tpl', 'Shopware\\Core\\Checkout\\Payment\\Cart\\PaymentHandler\\InvoicePayment', '', 0, null, 5, '', 3, 1, 1, '', 0, '', null, null, 0, null, $this->now(), null],
            [hex2bin('a6ddadce4cb441f3976a32505049f037'), $this->defaultId, 'sepa', '@Checkout/frontend/sepa.html.twig', 'Shopware\\Core\\Checkout\\Payment\\Cart\\PaymentHandler\\SEPAPayment', '', 0, null, null, '', 5, 1, 1, '', 0, '', null, null, 0, null, $this->now(), null],
        ];

        $this->importTable(
            'payment_method',
            ['id', 'version_id', 'technical_name', 'template', 'class', '`table`', 'hide', 'percentage_surcharge', 'absolute_surcharge', 'surcharge_string', 'position', 'active', 'allow_esd', 'used_iframe', 'hide_prospect', 'action', 'plugin_id', 'source', 'mobile_inactive', 'risk_rules', 'created_at', 'updated_at'],
            ['tenant_id'],
            $paymentMethods
        );

        $this->importTable(
            'payment_method_translation',
            ['payment_method_id', 'payment_method_version_id', 'language_id', 'name', 'additional_description', 'created_at'],
            ['payment_method_tenant_id', 'language_tenant_id'],
            [
                [$paymentMethods[0][0], $this->defaultId, $this->defaultId, 'Direct Debit', 'Additional text', $this->now()],
                [$paymentMethods[1][0], $this->defaultId, $this->defaultId, 'Invoice', 'Payment by invoice. Shopware provides automatic invoicing for all customers on orders after the first, in order to avoid defaults on payment.', $this->now()],
                [$paymentMethods[2][0], $this->defaultId, $this->defaultId, 'SEPA direct debit', '', $this->now()],
            ]
        );
    }

    private function createShippingMethod(): void
    {
        $shippingMethods = [
            [$this->defaultId, $this->defaultId, 0, 1, 1, 1, 0, 0, null, 0, null, null, null, 0, null, null, null, 1.000, null, null, null, '', null, $this->now(), null],
            [Uuid::uuid4()->getBytes(), $this->defaultId, 0, 1, 2, 1, 5, 0, null, 0, null, null, null, 0, null, null, null, 1.000, null, null, null, '', null, $this->now(), null],
        ];

        $this->importTable(
            'shipping_method',
            ['id', 'version_id', 'type', 'active', 'position', 'calculation', 'surcharge_calculation', 'tax_calculation', 'shipping_free', 'bind_shippingfree', 'bind_time_from', 'bind_time_to', 'bind_instock', 'bind_laststock', 'bind_weekday_from', 'bind_weekday_to', 'bind_weight_from', 'bind_weight_to', 'bind_price_from', 'bind_price_to', 'bind_sql', 'status_link', 'calculation_sql', 'created_at', 'updated_at'],
            ['tenant_id'],
            $shippingMethods
        );

        $this->importTable(
            'shipping_method_translation',
            ['shipping_method_id', 'shipping_method_version_id', 'language_id', 'name', 'description', 'comment', 'created_at'],
            ['shipping_method_tenant_id', 'language_tenant_id'],
            [
                [$shippingMethods[0][0], $this->defaultId, $this->defaultId, 'Standard', '', '', $this->now()],
                [$shippingMethods[1][0], $this->defaultId, $this->defaultId, 'Express', '', '', $this->now()],
            ]
        );
    }

    private function createTax(): void
    {
        $this->importTable(
            'tax',
            ['id', 'version_id', 'tax_rate', 'name', 'created_at', 'updated_at'],
            ['tenant_id'],
            [
                [Uuid::uuid4()->getBytes(), $this->defaultId, '19', '19%', $this->now(), null],
                [Uuid::uuid4()->getBytes(), $this->defaultId, '7', '7%',   $this->now(), null],
                [Uuid::uuid4()->getBytes(), $this->defaultId, '20', '20%',   $this->now(), null],
                [Uuid::uuid4()->getBytes(), $this->defaultId, '5', '5%',   $this->now(), null],
                [Uuid::uuid4()->getBytes(), $this->defaultId, '0', '0%',   $this->now(), null],
            ]
        );
    }

    private function now(): string
    {
        return date(Defaults::DATE_FORMAT);
    }

    private function createSalesChannelTypes(): void
    {
        $this->importTable(
            'sales_channel_type',
            ['id', 'icon_name', 'created_at'],
            ['tenant_id'],
            [
                [hex2bin(Defaults::SALES_CHANNEL_STOREFRONT), 'default-building-shop', $this->now()],
                [hex2bin(Defaults::SALES_CHANNEL_STOREFRONT_API), 'default-shopping-basket', $this->now()],
            ]
        );

        $this->importTable(
            'sales_channel_type_translation',
            ['sales_channel_type_id', 'language_id', 'name', 'manufacturer', 'description', 'description_long', 'created_at', 'updated_at'],
            ['sales_channel_type_tenant_id', 'language_tenant_id'],
            [
                [hex2bin(Defaults::SALES_CHANNEL_STOREFRONT), $this->defaultId, 'Storefront', 'Shopware AG', 'Default storefront sales channel', '', $this->now(), null],
                [hex2bin(Defaults::SALES_CHANNEL_STOREFRONT_API), $this->defaultId, 'Storefront API', 'Shopware AG', 'Default Storefront-API', '', $this->now(), null],
            ]
        );
    }

    private function createProductManufacturer(): void
    {
        $id = Uuid::uuid4()->getBytes();

        $this->importTable(
            'product_manufacturer',
            ['id', 'created_at', 'version_id', 'catalog_id'],
            ['tenant_id', 'catalog_tenant_id'],
            [
                [$id, $this->now(), $this->defaultId, $this->defaultId],
            ]
        );

        $this->importTable(
            'product_manufacturer_translation',
            ['product_manufacturer_id', 'product_manufacturer_version_id', 'catalog_id', 'language_id', 'name', 'created_at'],
            ['product_manufacturer_tenant_id', 'catalog_tenant_id', 'language_tenant_id'],
            [
                [$id, $this->defaultId, $this->defaultId, $this->defaultId, 'shopware AG', $this->now()],
            ]
        );
    }

    /**
     * @throws InvalidUuidException
     */
    private function createSalesChannel(): void
    {
        $key = AccessKeyHelper::generateAccessKey('sales-channel');

        $salesChannels = [
            [Uuid::uuid4()->getBytes(), Uuid::fromHexToBytes(Defaults::SALES_CHANNEL_STOREFRONT_API), $key, $this->defaultId, $this->defaultId, $this->defaultId, UUid::fromHexToBytes(Defaults::PAYMENT_METHOD_INVOICE), $this->defaultId, $this->defaultId, $this->defaultId, $this->defaultId, $this->defaultId, 1, 'vertical', $this->now()],
        ];

        $this->importTable(
            'sales_channel',
            ['id', 'type_id', 'access_key', 'language_id', 'currency_id', 'currency_version_id', 'payment_method_id', 'payment_method_version_id', 'shipping_method_version_id', 'shipping_method_id', 'country_id', 'country_version_id', 'active', 'tax_calculation_type', 'created_at'],
            ['type_tenant_id', 'tenant_id', 'language_tenant_id', 'currency_tenant_id', 'payment_method_tenant_id', 'shipping_method_tenant_id', 'country_tenant_id'],
            $salesChannels
        );

        $salesChannelCatalogs = [
            [$salesChannels[0][0], Uuid::fromHexToBytes(Defaults::CATALOG), $this->now()],
        ];

        $this->importTable(
            'sales_channel_translation',
            ['sales_channel_id', 'language_id', 'name', 'created_at'],
            ['sales_channel_tenant_id', 'language_tenant_id'],
            [
                [$salesChannels[0][0], $this->defaultId, 'Storefront API', $this->now()],
            ]
        );

        $this->importTable(
            'sales_channel_catalog',
            ['sales_channel_id', 'catalog_id', 'created_at'],
            ['sales_channel_tenant_id', 'catalog_tenant_id'],
            $salesChannelCatalogs
        );

        $salesChannelCountries = [
            [$salesChannels[0][0], $this->defaultId, $this->defaultId, $this->now()],
            [$salesChannels[0][0], Uuid::fromHexToBytes('6c72828ec5e240588a35114cf1d4d5ef'), $this->defaultId, $this->now()],
        ];
        $this->importTable(
            'sales_channel_country',
            ['sales_channel_id', 'country_id', 'country_version_id', 'created_at'],
            ['sales_channel_tenant_id', 'country_tenant_id'],
            $salesChannelCountries
        );

        $salesChannelCurrency = $this->connection->executeQuery('SELECT id FROM currency')->fetchAll();
        foreach ($salesChannelCurrency as &$currency) {
            $currency = [$salesChannels[0][0], $currency['id'], $this->defaultId, $this->now()];
        }
        $this->importTable(
            'sales_channel_currency',
            ['sales_channel_id', 'currency_id', 'currency_version_id', 'created_at'],
            ['sales_channel_tenant_id', 'currency_tenant_id'],
            $salesChannelCurrency
        );

        $salesChannelLanguages = $this->connection->executeQuery('SELECT id FROM language')->fetchAll();
        foreach ($salesChannelLanguages as &$language) {
            $language = [$salesChannels[0][0], $language['id'], $this->now()];
        }
        $this->importTable(
            'sales_channel_language',
            ['sales_channel_id', 'language_id', 'created_at'],
            ['sales_channel_tenant_id', 'language_tenant_id'],
            $salesChannelLanguages
        );

        $salesChannelShippingMethods = $this->connection->executeQuery('SELECT id FROM shipping_method')->fetchAll();
        foreach ($salesChannelShippingMethods as &$shippingMethod) {
            $shippingMethod = [$salesChannels[0][0], $shippingMethod['id'], $this->defaultId, $this->now()];
        }
        $this->importTable(
            'sales_channel_shipping_method',
            ['sales_channel_id', 'shipping_method_id', 'shipping_method_version_id', 'created_at'],
            ['sales_channel_tenant_id', 'shipping_method_tenant_id'],
            $salesChannelShippingMethods
        );

        $salesChannelPaymentMethods = $this->connection->executeQuery('SELECT id FROM payment_method')->fetchAll();
        foreach ($salesChannelPaymentMethods as &$paymentMethod) {
            $paymentMethod = [$salesChannels[0][0], $paymentMethod['id'], $this->defaultId, $this->now()];
        }
        $this->importTable(
            'sales_channel_payment_method',
            ['sales_channel_id', 'payment_method_id', 'payment_method_version_id', 'created_at'],
            ['sales_channel_tenant_id', 'payment_method_tenant_id'],
            $salesChannelPaymentMethods
        );
    }
}

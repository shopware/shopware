<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_4\Migration1620376945AddCompanyTaxAndCustomerTaxToCountry;

class Migration1620376945AddCompanyTaxAndCustomerTaxToCountryTest extends TestCase
{
    use KernelTestBehaviour;
    use DatabaseTransactionBehaviour;

    /**
     * @var Connection
     */
    private $connection;

    public function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->connection->rollBack();

        $this->connection->executeStatement('DROP TRIGGER IF EXISTS country_tax_free_insert;');
        $this->connection->executeStatement('DROP TRIGGER IF EXISTS country_tax_free_update;');
        $this->connection->executeStatement(
            'ALTER TABLE `country`
            DROP COLUMN `customer_tax`,
            DROP COLUMN `company_tax`;'
        );

        $migration = new Migration1620376945AddCompanyTaxAndCustomerTaxToCountry();
        $migration->update($this->connection);

        $this->connection->beginTransaction();
    }

    public function testMigrateDataFromTaxFreeAndCompanyTaxFreeToNewFieldsShouldBeCorrect(): void
    {
        $countries = $this->connection->fetchAllAssociative('SELECT `tax_free`, `company_tax_free`, `customer_tax`, `company_tax` FROM `country`');

        foreach ($countries as $country) {
            $customerTaxFree = json_decode($country['customer_tax'], true);
            $companyTaxFree = json_decode($country['company_tax'], true);
            static::assertSame((int) $country['tax_free'], $customerTaxFree['enabled']);
            static::assertSame((int) $country['company_tax_free'], $companyTaxFree['enabled']);
        }
    }

    public function dataProvider(): array
    {
        return [
            'Write old value' => [
                [
                    'tax_free' => 1,
                    'company_tax_free' => 1,
                ],
                ['enabled' => 1, 'currencyId' => Defaults::CURRENCY, 'amount' => 0],
                ['enabled' => 1, 'currencyId' => Defaults::CURRENCY, 'amount' => 0],
            ],
            'Write old and new value at the same time' => [
                [
                    'special_case' => true,
                    'tax_free' => 1,
                    'company_tax_free' => 1,
                    'customer_tax' => json_encode(['enabled' => 0, 'currencyId' => Defaults::CURRENCY, 'amount' => 0]),
                    'company_tax' => json_encode(['enabled' => 0, 'currencyId' => Defaults::CURRENCY, 'amount' => 0]),
                ],
                ['enabled' => 1, 'currencyId' => Defaults::CURRENCY, 'amount' => 0],
                ['enabled' => 1, 'currencyId' => Defaults::CURRENCY, 'amount' => 0],
            ],
            'Write new value' => [
                [
                    'customer_tax' => json_encode(['enabled' => 1, 'currencyId' => Defaults::CURRENCY, 'amount' => 0]),
                    'company_tax' => json_encode(['enabled' => 1, 'currencyId' => Defaults::CURRENCY, 'amount' => 0]),
                ],
                ['enabled' => 1, 'currencyId' => Defaults::CURRENCY, 'amount' => 0],
                ['enabled' => 1, 'currencyId' => Defaults::CURRENCY, 'amount' => 0],
            ],
            'Write other new value' => [
                [
                    'customer_tax' => json_encode(['enabled' => 0, 'currencyId' => Defaults::CURRENCY, 'amount' => 0]),
                    'company_tax' => json_encode(['enabled' => 0, 'currencyId' => Defaults::CURRENCY, 'amount' => 0]),
                ],
                ['enabled' => 0, 'currencyId' => Defaults::CURRENCY, 'amount' => 0],
                ['enabled' => 0, 'currencyId' => Defaults::CURRENCY, 'amount' => 0],
            ],
        ];
    }

    /**
     * @dataProvider dataProvider
     */
    public function testCountryCreateTrigger(
        array $payload,
        array $customerTaxExpected,
        array $companyTaxExpected
    ): void {
        $id = Uuid::randomBytes();

        $data = [
            'id' => $id,
            'active' => 1,
            'iso' => 'TC',
            'iso3' => 'TCT',
            'created_at' => (new \DateTime())->format('Y-m-d'),
        ];

        // this key use for testCountryUpdateTrigger
        if (\array_key_exists('special_case', $payload)) {
            unset($payload['special_case']);
        }

        $data = array_merge($data, $payload);

        $this->connection->insert('country', $data);

        $country = $this->connection->fetchAssociative(
            'SELECT tax_free, company_tax_free, customer_tax, company_tax FROM country WHERE id = :id',
            ['id' => $id]
        );

        $countryCustomerTax = json_decode($country['customer_tax'], true);
        $countryCompanyTax = json_decode($country['company_tax'], true);

        ksort($customerTaxExpected);
        ksort($companyTaxExpected);
        ksort($countryCustomerTax);
        ksort($countryCompanyTax);

        static::assertSame((int) $country['tax_free'], (int) $countryCustomerTax['enabled']);
        static::assertSame((int) $country['company_tax_free'], (int) $countryCompanyTax['enabled']);
        static::assertSame($customerTaxExpected, $countryCustomerTax);
        static::assertSame($companyTaxExpected, $countryCompanyTax);
    }

    /**
     * @dataProvider dataProvider
     */
    public function testCountryUpdateTrigger(
        array $payload,
        array $customerTaxExpected,
        array $companyTaxExpected
    ): void {
        $id = Uuid::randomBytes();

        $data = [
            'id' => $id,
            'active' => 1,
            'tax_free' => 0,
            'company_tax_free' => 0,
            'iso' => 'TC',
            'iso3' => 'TCT',
            'created_at' => (new \DateTime())->format('Y-m-d'),
        ];

        $this->connection->insert('country', $data);

        $data = [
            'id' => $id,
        ];

        if (\array_key_exists('special_case', $payload)) {
            unset($payload['special_case']);
            $data = array_merge($data, $payload);
            $this->connection->executeStatement(
                'UPDATE country SET
                    tax_free = :tax_free,
                    company_tax_free = :company_tax_free,
                    customer_tax = :customer_tax,
                    company_tax = :company_tax
                    WHERE id = :id',
                $data
            );
        } else {
            if (\array_key_exists('tax_free', $payload)) {
                $data['taxFree'] = $payload['tax_free'];
                $this->connection->executeStatement(
                    'UPDATE country SET tax_free = :taxFree WHERE id = :id',
                    $data
                );
            }

            if (\array_key_exists('company_tax_free', $payload)) {
                $data['companyTaxFree'] = $payload['company_tax_free'];
                $this->connection->executeStatement(
                    'UPDATE country SET company_tax_free = :companyTaxFree WHERE id = :id',
                    $data
                );
            }

            if (\array_key_exists('customer_tax', $payload)) {
                $data['customerTax'] = $payload['customer_tax'];
                $this->connection->executeStatement(
                    'UPDATE country SET customer_tax = :customerTax WHERE id = :id',
                    $data
                );
            }

            if (\array_key_exists('company_tax', $payload)) {
                $data['companyTax'] = $payload['company_tax'];
                $this->connection->executeStatement(
                    'UPDATE country SET company_tax = :companyTax WHERE id = :id',
                    $data
                );
            }
        }

        $country = $this->connection->fetchAssociative(
            'SELECT tax_free, company_tax_free, customer_tax, company_tax FROM country WHERE id = :id',
            ['id' => $id]
        );

        $countryCustomerTax = json_decode($country['customer_tax'], true);
        $countryCompanyTax = json_decode($country['company_tax'], true);

        ksort($customerTaxExpected);
        ksort($companyTaxExpected);
        ksort($countryCustomerTax);
        ksort($countryCompanyTax);

        static::assertSame((int) $country['tax_free'], (int) $countryCustomerTax['enabled']);
        static::assertSame((int) $country['company_tax_free'], (int) $countryCompanyTax['enabled']);
        static::assertSame($customerTaxExpected, $countryCustomerTax);
        static::assertSame($companyTaxExpected, $countryCompanyTax);
    }
}

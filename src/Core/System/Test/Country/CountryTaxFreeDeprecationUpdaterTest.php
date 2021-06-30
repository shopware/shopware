<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\Country;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\CountryEntity;

class CountryTaxFreeDeprecationUpdaterTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function tearDown(): void
    {
        $this->setBlueGreen(true);
    }

    public function dataCreate(): array
    {
        return [
            'Write old value' => [
                [
                    'taxFree' => true,
                    'companyTaxFree' => true,
                ],
                ['enabled' => false, 'currencyId' => Defaults::CURRENCY, 'amount' => 0.0],
                ['enabled' => false, 'currencyId' => Defaults::CURRENCY, 'amount' => 0.0],
            ],
            'Write old and new value at the same time' => [
                [
                    'taxFree' => true,
                    'companyTaxFree' => true,
                    'customerTax' => ['enabled' => false, 'currencyId' => Defaults::CURRENCY, 'amount' => 0.0],
                    'companyTax' => ['enabled' => false, 'currencyId' => Defaults::CURRENCY, 'amount' => 0.0],
                ],
                ['enabled' => false, 'currencyId' => Defaults::CURRENCY, 'amount' => 0.0],
                ['enabled' => false, 'currencyId' => Defaults::CURRENCY, 'amount' => 0.0],
            ],
            'Write new value' => [
                [
                    'customerTax' => ['enabled' => true, 'currencyId' => Defaults::CURRENCY, 'amount' => 0.0],
                    'companyTax' => ['enabled' => true, 'currencyId' => Defaults::CURRENCY, 'amount' => 0.0],
                ],
                ['enabled' => true, 'currencyId' => Defaults::CURRENCY, 'amount' => 0.0],
                ['enabled' => true, 'currencyId' => Defaults::CURRENCY, 'amount' => 0.0],
            ],
            'Write other new value' => [
                [
                    'customerTax' => ['enabled' => false, 'currencyId' => Defaults::CURRENCY, 'amount' => 0.0],
                    'companyTax' => ['enabled' => false, 'currencyId' => Defaults::CURRENCY, 'amount' => 0.0],
                ],
                ['enabled' => false, 'currencyId' => Defaults::CURRENCY, 'amount' => 0.0],
                ['enabled' => false, 'currencyId' => Defaults::CURRENCY, 'amount' => 0.0],
            ],
        ];
    }

    /**
     * @dataProvider dataCreate
     */
    public function testCreate(
        array $payload,
        array $customerTaxExpected,
        array $companyTaxExpected
    ): void {
        Feature::skipTestIfInActive('FEATURE_NEXT_14114', $this);

        $this->setBlueGreen(false);

        $id = Uuid::randomHex();

        $data = [
            'id' => $id,
            'active' => true,
            'iso' => 'TC',
            'iso3' => 'TCT',
            'name' => 'Test Country',
        ];

        $data = array_merge($data, $payload);

        $this->getContainer()
            ->get('country.repository')
            ->create([$data], Context::createDefaultContext());

        /** @var CountryEntity $country */
        $country = $this->getContainer()
            ->get('country.repository')
            ->search(new Criteria([$id]), Context::createDefaultContext())
            ->first();

        static::assertInstanceOf(CountryEntity::class, $country);

        static::assertSame($country->getTaxFree(), $country->getCustomerTax()->getEnabled());
        static::assertSame($customerTaxExpected['enabled'], $country->getCustomerTax()->getEnabled());
        static::assertSame($customerTaxExpected['currencyId'], $country->getCustomerTax()->getCurrencyId());
        static::assertSame($customerTaxExpected['amount'], $country->getCustomerTax()->getAmount());

        static::assertSame($country->getCompanyTaxFree(), $country->getCompanyTax()->getEnabled());
        static::assertSame($companyTaxExpected['enabled'], $country->getCompanyTax()->getEnabled());
        static::assertSame($companyTaxExpected['currencyId'], $country->getCompanyTax()->getCurrencyId());
        static::assertSame($companyTaxExpected['amount'], $country->getCompanyTax()->getAmount());
    }

    public function dataUpdate(): array
    {
        return [
            'Write old value' => [
                [
                    'taxFree' => true,
                    'companyTaxFree' => true,
                ],
                ['enabled' => true, 'currencyId' => Defaults::CURRENCY, 'amount' => 0.0],
                ['enabled' => true, 'currencyId' => Defaults::CURRENCY, 'amount' => 0.0],
            ],
            'Write old and new value at the same time' => [
                [
                    'taxFree' => true,
                    'companyTaxFree' => true,
                    'customerTax' => ['enabled' => false, 'currencyId' => Defaults::CURRENCY, 'amount' => 0.0],
                    'companyTax' => ['enabled' => false, 'currencyId' => Defaults::CURRENCY, 'amount' => 0.0],
                ],
                ['enabled' => false, 'currencyId' => Defaults::CURRENCY, 'amount' => 0.0],
                ['enabled' => false, 'currencyId' => Defaults::CURRENCY, 'amount' => 0.0],
            ],
            'Write new value' => [
                [
                    'customerTax' => ['enabled' => true, 'currencyId' => Defaults::CURRENCY, 'amount' => 0.0],
                    'companyTax' => ['enabled' => true, 'currencyId' => Defaults::CURRENCY, 'amount' => 0.0],
                ],
                ['enabled' => true, 'currencyId' => Defaults::CURRENCY, 'amount' => 0.0],
                ['enabled' => true, 'currencyId' => Defaults::CURRENCY, 'amount' => 0.0],
            ],
            'Write other new value' => [
                [
                    'customerTax' => ['enabled' => false, 'currencyId' => Defaults::CURRENCY, 'amount' => 0.0],
                    'companyTax' => ['enabled' => false, 'currencyId' => Defaults::CURRENCY, 'amount' => 0.0],
                ],
                ['enabled' => false, 'currencyId' => Defaults::CURRENCY, 'amount' => 0.0],
                ['enabled' => false, 'currencyId' => Defaults::CURRENCY, 'amount' => 0.0],
            ],
        ];
    }

    /**
     * @dataProvider dataUpdate
     */
    public function testUpdate(
        array $payload,
        array $customerTaxExpected,
        array $companyTaxExpected
    ): void {
        Feature::skipTestIfInActive('FEATURE_NEXT_14114', $this);

        $this->setBlueGreen(false);

        $id = Uuid::randomHex();

        $data = [
            'id' => $id,
            'active' => true,
            'iso' => 'TC',
            'iso3' => 'TCT',
            'name' => 'Test Country',
        ];

        $this->getContainer()
            ->get('country.repository')
            ->create([$data], Context::createDefaultContext());

        $data = [
            'id' => $id,
            'active' => true,
        ];

        $data = array_merge($data, $payload);

        $this->getContainer()
            ->get('country.repository')
            ->update([$data], Context::createDefaultContext());

        /** @var CountryEntity $country */
        $country = $this->getContainer()
            ->get('country.repository')
            ->search(new Criteria([$id]), Context::createDefaultContext())
            ->first();

        static::assertInstanceOf(CountryEntity::class, $country);

        static::assertSame($country->getTaxFree(), $country->getCustomerTax()->getEnabled());
        static::assertSame($customerTaxExpected['enabled'], $country->getCustomerTax()->getEnabled());
        static::assertSame($customerTaxExpected['currencyId'], $country->getCustomerTax()->getCurrencyId());
        static::assertSame($customerTaxExpected['amount'], $country->getCustomerTax()->getAmount());

        static::assertSame($country->getCompanyTaxFree(), $country->getCompanyTax()->getEnabled());
        static::assertSame($companyTaxExpected['enabled'], $country->getCompanyTax()->getEnabled());
        static::assertSame($companyTaxExpected['currencyId'], $country->getCompanyTax()->getCurrencyId());
        static::assertSame($companyTaxExpected['amount'], $country->getCompanyTax()->getAmount());
    }

    private function setBlueGreen(?bool $enabled): void
    {
        $this->getContainer()->get(Connection::class)->rollBack();

        if ($enabled === null) {
            unset($_ENV['BLUE_GREEN_DEPLOYMENT']);
        } else {
            $_ENV['BLUE_GREEN_DEPLOYMENT'] = $enabled ? '1' : '0';
        }

        // reload env
        KernelLifecycleManager::bootKernel();

        $this->getContainer()->get(Connection::class)->beginTransaction();
        if ($enabled !== null) {
            $this->getContainer()->get(Connection::class)->executeStatement('SET @TRIGGER_DISABLED = ' . ($enabled ? '0' : '1'));
        }
    }
}

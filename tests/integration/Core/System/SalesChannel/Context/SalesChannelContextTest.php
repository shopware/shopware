<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\SalesChannel\Context;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\Tax\Aggregate\TaxRuleType\TaxRuleTypeCollection;
use Shopware\Core\System\Tax\Aggregate\TaxRuleType\TaxRuleTypeEntity;
use Shopware\Core\System\Tax\TaxRuleType\EntireCountryRuleTypeFilter;
use Shopware\Core\System\Tax\TaxRuleType\IndividualStatesRuleTypeFilter;
use Shopware\Core\System\Tax\TaxRuleType\ZipCodeRangeRuleTypeFilter;
use Shopware\Core\System\Tax\TaxRuleType\ZipCodeRuleTypeFilter;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('discovery')]
class SalesChannelContextTest extends TestCase
{
    use IntegrationTestBehaviour;

    private TaxRuleTypeCollection $taxRuleTypes;

    protected function setUp(): void
    {
        $this->taxRuleTypes = $this->loadTaxRuleTypes();
    }

    public function testGetTaxRuleCollectionWithoutRulesReturnsDefault(): void
    {
        $taxId = Uuid::randomHex();
        $taxData = [
            'id' => $taxId,
            'taxRate' => 15,
            'name' => Uuid::randomHex(),
        ];
        $taxRuleCollection = $this->createSalesChannelContext([$taxData])->buildTaxRules($taxId);

        static::assertCount(1, $taxRuleCollection);
        static::assertSame(15.0, $taxRuleCollection->first()?->getTaxRate());
        static::assertSame(100.0, $taxRuleCollection->first()->getPercentage());
    }

    public function testGetTaxRuleCollectionCustomerShippingCountry(): void
    {
        $customerId = Uuid::randomHex();
        $shippingCountryId = Uuid::randomHex();
        $this->createCustomer($customerId, $shippingCountryId, Uuid::randomHex());
        $taxId = Uuid::randomHex();
        $entireCountryRuleType = $this->getRuleType(EntireCountryRuleTypeFilter::TECHNICAL_NAME);
        $taxData = [
            'id' => $taxId,
            'taxRate' => 15,
            'name' => Uuid::randomHex(),
            'rules' => [
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $shippingCountryId,
                    'taxRate' => 10,
                    'activeFrom' => (new \DateTime())->modify('-3 days'),
                    'taxRuleTypeId' => $entireCountryRuleType->getId(),
                ], [
                    'id' => Uuid::randomHex(),
                    'countryId' => $shippingCountryId,
                    'taxRate' => 9,
                    'activeFrom' => (new \DateTime())->modify('-2 days'),
                    'taxRuleTypeId' => $entireCountryRuleType->getId(),
                ], [
                    'id' => Uuid::randomHex(),
                    'countryId' => $shippingCountryId,
                    'taxRate' => 8,
                    'activeFrom' => (new \DateTime())->modify('+3 days'),
                    'taxRuleTypeId' => $entireCountryRuleType->getId(),
                ],
            ],
        ];
        $salesChannelContext = $this->createSalesChannelContext([$taxData], [SalesChannelContextService::CUSTOMER_ID => $customerId]);
        $taxRuleCollection = $salesChannelContext->buildTaxRules($taxData['id']);

        static::assertCount(1, $taxRuleCollection);
        static::assertSame(9.0, $taxRuleCollection->first()?->getTaxRate());
        static::assertSame(100.0, $taxRuleCollection->first()->getPercentage());
    }

    public function testGetTaxRuleCollectionCustomerShippingWithMultipleRule(): void
    {
        $customerId = Uuid::randomHex();
        $randomCountryId = $this->getValidCountryId();
        $billingCountryId = Uuid::randomHex();
        $shippingCountryId = Uuid::randomHex();
        $this->createCustomer($customerId, $shippingCountryId, $billingCountryId);
        $entireCountryRuleType = $this->getRuleType(EntireCountryRuleTypeFilter::TECHNICAL_NAME);
        $taxData = [
            'id' => Uuid::randomHex(),
            'taxRate' => 15,
            'name' => Uuid::randomHex(),
            'rules' => [
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $billingCountryId,
                    'taxRate' => 10,
                    'taxRuleTypeId' => $entireCountryRuleType->getId(),
                ],
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $shippingCountryId,
                    'taxRate' => 9,
                    'taxRuleTypeId' => $entireCountryRuleType->getId(),
                ],
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $randomCountryId,
                    'taxRate' => 8,
                    'taxRuleTypeId' => $entireCountryRuleType->getId(),
                ],
            ],
        ];
        $salesChannelContext = $this->createSalesChannelContext([$taxData], [SalesChannelContextService::CUSTOMER_ID => $customerId]);
        $taxRuleCollection = $salesChannelContext->buildTaxRules($taxData['id']);

        static::assertCount(1, $taxRuleCollection);
        static::assertSame(9.0, $taxRuleCollection->first()?->getTaxRate());
        static::assertSame(100.0, $taxRuleCollection->first()->getPercentage());
    }

    public function testGetTaxRuleCollectionCustomerShippingTypeIndividualStatesOverridesEntireCountry(): void
    {
        $customerId = Uuid::randomHex();
        $shippingCountryId = Uuid::randomHex();
        $countryStateId = Uuid::randomHex();
        $countryState = ['id' => $countryStateId, 'countryId' => $shippingCountryId, 'shortCode' => Uuid::randomHex(), 'name' => Uuid::randomHex()];
        $this->createCustomer($customerId, $shippingCountryId, Uuid::randomHex(), $countryState);
        $taxData = [
            'id' => Uuid::randomHex(),
            'taxRate' => 15,
            'name' => Uuid::randomHex(),
            'rules' => [
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $shippingCountryId,
                    'taxRate' => 10,
                    'taxRuleTypeId' => $this->getRuleType(EntireCountryRuleTypeFilter::TECHNICAL_NAME)->getId(),
                ],
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $shippingCountryId,
                    'taxRate' => 9,
                    'taxRuleTypeId' => $this->getRuleType(IndividualStatesRuleTypeFilter::TECHNICAL_NAME)->getId(),
                    'data' => [
                        'states' => [$countryStateId],
                    ],
                ],
            ],
        ];
        $salesChannelContext = $this->createSalesChannelContext([$taxData], [SalesChannelContextService::CUSTOMER_ID => $customerId]);
        $taxRuleCollection = $salesChannelContext->buildTaxRules($taxData['id']);

        static::assertCount(1, $taxRuleCollection);
        static::assertSame(9.0, $taxRuleCollection->first()?->getTaxRate());
        static::assertSame(100.0, $taxRuleCollection->first()->getPercentage());
    }

    public function testGetTaxRuleCollectionCustomerShippingTypeZipCodeRangeOverridesIndividualStates(): void
    {
        $customerId = Uuid::randomHex();
        $shippingCountryId = Uuid::randomHex();
        $countryStateId = Uuid::randomHex();
        $countryState = ['id' => $countryStateId, 'countryId' => $shippingCountryId, 'shortCode' => Uuid::randomHex(), 'name' => Uuid::randomHex()];
        $this->createCustomer($customerId, $shippingCountryId, Uuid::randomHex(), $countryState);
        $taxData = [
            'id' => Uuid::randomHex(),
            'taxRate' => 15,
            'name' => Uuid::randomHex(),
            'rules' => [
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $shippingCountryId,
                    'taxRate' => 9,
                    'activeFrom' => (new \DateTime())->modify('-2 days'),
                    'taxRuleTypeId' => $this->getRuleType(IndividualStatesRuleTypeFilter::TECHNICAL_NAME)->getId(),
                    'data' => [
                        'states' => [$countryStateId],
                    ],
                ],
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $shippingCountryId,
                    'taxRate' => 8,
                    'activeFrom' => (new \DateTime())->modify('-3 days'),
                    'taxRuleTypeId' => $this->getRuleType(ZipCodeRangeRuleTypeFilter::TECHNICAL_NAME)->getId(),
                    'data' => [
                        'fromZipCode' => '12000',
                        'toZipCode' => '12999',
                    ],
                ],
            ],
        ];
        $salesChannelContext = $this->createSalesChannelContext([$taxData], [SalesChannelContextService::CUSTOMER_ID => $customerId]);
        $taxRuleCollection = $salesChannelContext->buildTaxRules($taxData['id']);

        static::assertCount(1, $taxRuleCollection);
        static::assertSame(8.0, $taxRuleCollection->first()?->getTaxRate());
        static::assertSame(100.0, $taxRuleCollection->first()->getPercentage());
    }

    public function testGetTaxRuleCollectionCustomerShippingTypeZipCodeOverridesTypeZipCodeRange(): void
    {
        $customerId = Uuid::randomHex();
        $shippingCountryId = Uuid::randomHex();
        $countryStateId = Uuid::randomHex();
        $countryState = ['id' => $countryStateId, 'countryId' => $shippingCountryId, 'shortCode' => Uuid::randomHex(), 'name' => Uuid::randomHex()];
        $this->createCustomer($customerId, $shippingCountryId, Uuid::randomHex(), $countryState);
        $taxData = [
            'id' => Uuid::randomHex(),
            'taxRate' => 15,
            'name' => Uuid::randomHex(),
            'rules' => [
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $shippingCountryId,
                    'taxRate' => 8,
                    'taxRuleTypeId' => $this->getRuleType(ZipCodeRangeRuleTypeFilter::TECHNICAL_NAME)->getId(),
                    'data' => [
                        'fromZipCode' => '12000',
                        'toZipCode' => '12999',
                    ],
                ],
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $shippingCountryId,
                    'taxRate' => 7,
                    'taxRuleTypeId' => $this->getRuleType(ZipCodeRuleTypeFilter::TECHNICAL_NAME)->getId(),
                    'data' => [
                        'zipCode' => '12345',
                    ],
                ],
            ],
        ];
        $salesChannelContext = $this->createSalesChannelContext([$taxData], [SalesChannelContextService::CUSTOMER_ID => $customerId]);
        $taxRuleCollection = $salesChannelContext->buildTaxRules($taxData['id']);

        static::assertCount(1, $taxRuleCollection);
        static::assertSame(7.0, $taxRuleCollection->first()?->getTaxRate());
        static::assertSame(100.0, $taxRuleCollection->first()->getPercentage());
    }

    public function testGetTaxRuleCollectionCustomerShippingTypeZipCodeOverridesOthers(): void
    {
        $customerId = Uuid::randomHex();
        $shippingCountryId = Uuid::randomHex();
        $countryStateId = Uuid::randomHex();
        $countryState = ['id' => $countryStateId, 'countryId' => $shippingCountryId, 'shortCode' => Uuid::randomHex(), 'name' => Uuid::randomHex()];
        $this->createCustomer($customerId, $shippingCountryId, Uuid::randomHex(), $countryState);
        $taxData = $this->getTaxData($shippingCountryId, $countryStateId);
        $salesChannelContext = $this->createSalesChannelContext([$taxData], [SalesChannelContextService::CUSTOMER_ID => $customerId]);
        $taxRuleCollection = $salesChannelContext->buildTaxRules($taxData['id']);

        static::assertCount(1, $taxRuleCollection);
        static::assertSame(7.0, $taxRuleCollection->first()?->getTaxRate());
        static::assertSame(100.0, $taxRuleCollection->first()->getPercentage());
    }

    public function testGetTaxRuleCollectionDefaultOnWrongCountry(): void
    {
        $customerId = Uuid::randomHex();
        $shippingCountryId = $this->getValidCountryId();
        $countryStateId = Uuid::randomHex();
        $countryState = ['id' => $countryStateId, 'countryId' => $shippingCountryId, 'shortCode' => Uuid::randomHex(), 'name' => Uuid::randomHex()];
        $this->createCustomer($customerId, Uuid::randomHex(), Uuid::randomHex(), $countryState);
        $taxData = $this->getTaxData($shippingCountryId, $countryStateId);
        $salesChannelContext = $this->createSalesChannelContext([$taxData], [SalesChannelContextService::CUSTOMER_ID => $customerId]);
        $taxRuleCollection = $salesChannelContext->buildTaxRules($taxData['id']);

        static::assertCount(1, $taxRuleCollection);
        static::assertSame(15.0, $taxRuleCollection->first()?->getTaxRate());
        static::assertSame(100.0, $taxRuleCollection->first()->getPercentage());
    }

    public function testGetTaxRuleCollectionNoCustomerDefaultShippingLocationCountry(): void
    {
        $shippingCountryId = $this->getValidCountryId();
        $taxId = Uuid::randomHex();
        $taxData = [
            'id' => $taxId,
            'taxRate' => 15,
            'name' => Uuid::randomHex(),
            'rules' => [
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $shippingCountryId,
                    'taxRate' => 10,
                    'taxRuleTypeId' => $this->getRuleType(EntireCountryRuleTypeFilter::TECHNICAL_NAME)->getId(),
                ],
            ],
        ];
        $salesChannelContext = $this->createSalesChannelContext([$taxData], [SalesChannelContextService::COUNTRY_ID => $shippingCountryId]);
        $taxRuleCollection = $salesChannelContext->buildTaxRules($taxData['id']);

        static::assertCount(1, $taxRuleCollection);
        static::assertSame(10.0, $taxRuleCollection->first()?->getTaxRate());
        static::assertSame(100.0, $taxRuleCollection->first()->getPercentage());
    }

    public function testGetTaxRuleCollectionNoCustomerDefaultShippingLocationWithMultipleRule(): void
    {
        $countryIds = $this->getValidCountryIds(3);
        $shippingCountryId = $countryIds[0];
        $entireCountryRuleType = $this->getRuleType(EntireCountryRuleTypeFilter::TECHNICAL_NAME);
        $taxData = [
            'id' => Uuid::randomHex(),
            'taxRate' => 15,
            'name' => Uuid::randomHex(),
            'rules' => [
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $shippingCountryId,
                    'taxRate' => 10,
                    'taxRuleTypeId' => $entireCountryRuleType->getId(),
                ],
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $countryIds[1],
                    'taxRate' => 9,
                    'taxRuleTypeId' => $entireCountryRuleType->getId(),
                ],
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $countryIds[2],
                    'taxRate' => 8,
                    'taxRuleTypeId' => $entireCountryRuleType->getId(),
                ],
            ],
        ];
        $salesChannelContext = $this->createSalesChannelContext([$taxData], [SalesChannelContextService::COUNTRY_ID => $shippingCountryId]);
        $taxRuleCollection = $salesChannelContext->buildTaxRules($taxData['id']);

        static::assertCount(1, $taxRuleCollection);
        static::assertSame(10.0, $taxRuleCollection->first()?->getTaxRate());
        static::assertSame(100.0, $taxRuleCollection->first()->getPercentage());
    }

    public function testGetTaxRuleCollectionNoCustomerDefaultShippingLocationTypeIndividualStatesOverridesEntireCountry(): void
    {
        $shippingCountryId = $this->getValidCountryId();
        $countryStateId = $this->createCountryState($shippingCountryId);
        $taxData = [
            'id' => Uuid::randomHex(),
            'taxRate' => 15,
            'name' => Uuid::randomHex(),
            'rules' => [
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $shippingCountryId,
                    'taxRate' => 10,
                    'taxRuleTypeId' => $this->getRuleType(EntireCountryRuleTypeFilter::TECHNICAL_NAME)->getId(),
                ],
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $shippingCountryId,
                    'taxRate' => 9,
                    'taxRuleTypeId' => $this->getRuleType(IndividualStatesRuleTypeFilter::TECHNICAL_NAME)->getId(),
                    'data' => [
                        'states' => [$countryStateId],
                    ],
                ],
            ],
        ];
        $salesChannelContext = $this->createSalesChannelContext([$taxData], [SalesChannelContextService::COUNTRY_ID => $shippingCountryId, SalesChannelContextService::COUNTRY_STATE_ID => $countryStateId]);
        $taxRuleCollection = $salesChannelContext->buildTaxRules($taxData['id']);

        static::assertCount(1, $taxRuleCollection);
        static::assertSame(9.0, $taxRuleCollection->first()?->getTaxRate());
        static::assertSame(100.0, $taxRuleCollection->first()->getPercentage());
    }

    public function testGetTaxRuleCollectionNoCustomerDefaultShippingLocationTypeZipCodeRangeDoesNotMatch(): void
    {
        $shippingCountryId = $this->getValidCountryId();
        $zipCodeRangeRuleType = $this->getRuleType(ZipCodeRangeRuleTypeFilter::TECHNICAL_NAME);
        $taxData = [
            'id' => Uuid::randomHex(),
            'taxRate' => 15,
            'name' => Uuid::randomHex(),
            'rules' => [
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $shippingCountryId,
                    'taxRate' => 8,
                    'taxRuleTypeId' => $zipCodeRangeRuleType->getId(),
                    'data' => [
                        'fromZipCode' => '12000',
                        'toZipCode' => '12999',
                    ],
                ],
            ],
        ];
        $salesChannelContext = $this->createSalesChannelContext([$taxData], [SalesChannelContextService::COUNTRY_ID => $shippingCountryId]);
        $taxRuleCollection = $salesChannelContext->buildTaxRules($taxData['id']);

        static::assertCount(1, $taxRuleCollection);
        static::assertSame(15.0, $taxRuleCollection->first()?->getTaxRate());
        static::assertSame(100.0, $taxRuleCollection->first()->getPercentage());
    }

    public function testGetTaxRuleCollectionNoCustomerDefaultShippingLocationTypeZipCodeDoesNotMatch(): void
    {
        $shippingCountryId = $this->getValidCountryId();
        $taxData = [
            'id' => Uuid::randomHex(),
            'taxRate' => 15,
            'name' => Uuid::randomHex(),
            'rules' => [
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $shippingCountryId,
                    'taxRate' => 8,
                    'taxRuleTypeId' => $this->getRuleType(ZipCodeRuleTypeFilter::TECHNICAL_NAME)->getId(),
                    'data' => [
                        'zipCode' => '12345',
                    ],
                ],
            ],
        ];
        $salesChannelContext = $this->createSalesChannelContext([$taxData], [SalesChannelContextService::COUNTRY_ID => $shippingCountryId]);
        $taxRuleCollection = $salesChannelContext->buildTaxRules($taxData['id']);

        static::assertCount(1, $taxRuleCollection);
        static::assertSame(15.0, $taxRuleCollection->first()?->getTaxRate());
        static::assertSame(100.0, $taxRuleCollection->first()->getPercentage());
    }

    public function testGetTaxRuleCollectionNoCustomerDefaultShippingLocationTypeIndividualStatesOverridesOthers(): void
    {
        $shippingCountryId = $this->getValidCountryId();
        $countryStateId = $this->createCountryState($shippingCountryId);
        $taxData = $this->getTaxData($shippingCountryId, $countryStateId);
        $salesChannelContext = $this->createSalesChannelContext([$taxData], [SalesChannelContextService::COUNTRY_ID => $shippingCountryId, SalesChannelContextService::COUNTRY_STATE_ID => $countryStateId]);
        $taxRuleCollection = $salesChannelContext->buildTaxRules($taxData['id']);

        static::assertCount(1, $taxRuleCollection);
        static::assertSame(9.0, $taxRuleCollection->first()?->getTaxRate());
        static::assertSame(100.0, $taxRuleCollection->first()->getPercentage());
    }

    public function testGetTaxRuleCollectionNoCustomerDefaultShippingLocationDefaultOnWrongCountry(): void
    {
        $countryIds = $this->getValidCountryIds(2);
        $shippingCountryId = $countryIds[0];
        $countryStateId = $this->createCountryState($shippingCountryId);
        $taxData = $this->getTaxData($countryIds[1], $countryStateId);
        $salesChannelContext = $this->createSalesChannelContext([$taxData], [SalesChannelContextService::COUNTRY_ID => $shippingCountryId, SalesChannelContextService::COUNTRY_STATE_ID => $countryStateId]);
        $taxRuleCollection = $salesChannelContext->buildTaxRules($taxData['id']);

        static::assertCount(1, $taxRuleCollection);
        static::assertSame(15.0, $taxRuleCollection->first()?->getTaxRate());
        static::assertSame(100.0, $taxRuleCollection->first()->getPercentage());
    }

    public function testGetShippingMethodNoFound(): void
    {
        $shippingMethodIdNoExits = '25c5b40b1cb643288ae8e703c2997666';

        $salesChannelContext = $this->createSalesChannelContext([], [SalesChannelContextService::SHIPPING_METHOD_ID => $shippingMethodIdNoExits]);

        /** @var EntityRepository<SalesChannelCollection> $repository */
        $repository = static::getContainer()->get('sales_channel.repository');
        $salesChannel = $repository->search(new Criteria([$salesChannelContext->getSalesChannelId()]), $salesChannelContext->getContext())->first();
        static::assertNotNull($salesChannel);

        static::assertSame($salesChannel->getShippingMethodId(), $salesChannelContext->getSalesChannel()->getShippingMethodId());
        static::assertNotSame($shippingMethodIdNoExits, $salesChannelContext->getSalesChannel()->getShippingMethodId());
    }

    #[DataProvider('ensureLoginProvider')]
    public function testEnsureLogin(bool $login, bool $isGuest, bool $allowGuest, bool $shouldThrow): void
    {
        $options = [];

        if ($login) {
            $customerId = Uuid::randomHex();
            $this->createCustomer($customerId, null, null, [], $isGuest);

            $options[SalesChannelContextService::CUSTOMER_ID] = $customerId;
        }

        $salesChannelContext = $this->createSalesChannelContext([], $options);

        if ($shouldThrow) {
            $this->expectException(CustomerNotLoggedInException::class);
        }
        $salesChannelContext->ensureLoggedIn($allowGuest);
    }

    public static function ensureLoginProvider(): \Generator
    {
        yield 'Not logged in' => [
            false,
            false,
            false,
            true,
        ];

        yield 'Logged in as guest, but guest not allowed' => [
            true,
            true,
            false,
            true,
        ];

        yield 'Logged in as guest and guest is allowed' => [
            true,
            true,
            true,
            false,
        ];

        yield 'Logged in and guest is allowed' => [
            true,
            false,
            true,
            false,
        ];

        yield 'Logged in and guest is not allowed' => [
            true,
            false,
            false,
            false,
        ];
    }

    /**
     * @return list<string>
     */
    protected function getValidCountryIds(int $limit): array
    {
        $criteria = (new Criteria())->setLimit($limit);

        $ids = static::getContainer()->get('country.repository')->searchIds($criteria, Context::createDefaultContext())->getIds();
        static::assertContainsOnlyString($ids);

        return $ids;
    }

    protected function createCountryState(string $countryId): string
    {
        $id = Uuid::randomHex();

        static::getContainer()->get('country_state.repository')->create(
            [['id' => $id, 'countryId' => $countryId, 'shortCode' => Uuid::randomHex(), 'name' => Uuid::randomHex()]],
            Context::createDefaultContext()
        );

        return $id;
    }

    /**
     * @param array<int, array<string, mixed>> $taxData
     * @param array<string, mixed> $options
     */
    private function createSalesChannelContext(array $taxData = [], array $options = []): SalesChannelContext
    {
        if ($taxData !== []) {
            static::getContainer()->get('tax.repository')->create($taxData, Context::createDefaultContext());
        }

        $salesChannelContextFactory = static::getContainer()->get(SalesChannelContextFactory::class);

        $token = Uuid::randomHex();

        return $salesChannelContextFactory->create($token, TestDefaults::SALES_CHANNEL, $options);
    }

    private function loadTaxRuleTypes(): TaxRuleTypeCollection
    {
        /** @var EntityRepository<TaxRuleTypeCollection> $taxRuleTypeRepository */
        $taxRuleTypeRepository = static::getContainer()->get('tax_rule_type.repository');

        return $taxRuleTypeRepository->search(new Criteria(), Context::createDefaultContext())->getEntities();
    }

    /**
     * @param array<string, mixed>|null $countryState
     */
    private function createCustomer(
        string $customerId,
        ?string $shippingCountryId = null,
        ?string $billingCountryId = null,
        ?array $countryState = null,
        bool $isGuest = false
    ): void {
        $customerRepository = static::getContainer()->get('customer.repository');
        $salutationId = $this->getValidSalutationId();

        $billingAddress = [
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'street' => 'Musterstraße 1',
            'city' => 'Schöppingen',
            'zipcode' => '12345',
            'salutationId' => $salutationId,
            'country' => ['id' => $billingCountryId ?? Uuid::randomHex(), 'name' => 'Germany'],
        ];

        $shippingAddress = [
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'street' => 'Musterstraße 1',
            'city' => 'Schöppingen',
            'zipcode' => '12345',
            'salutationId' => $salutationId,
            'country' => ['id' => $shippingCountryId ?? Uuid::randomHex(), 'name' => 'Germany'],
        ];

        if ($countryState) {
            $billingAddress['countryState'] = $countryState;
            $shippingAddress['countryState'] = $countryState;
        }

        $customer = [
            'id' => $customerId,
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'defaultShippingAddress' => $shippingAddress,
            'defaultBillingAddress' => $billingAddress,
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'email' => Uuid::randomHex() . '@example.com',
            'guest' => $isGuest,
            'password' => '$password',
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'salutationId' => $salutationId,
            'customerNumber' => '12345',
        ];

        $customerRepository->create([$customer], Context::createDefaultContext());
    }

    private function getRuleType(string $technicalName): TaxRuleTypeEntity
    {
        $ruleType = $this->taxRuleTypes->getByTechnicalName($technicalName);
        static::assertNotNull($ruleType);

        return $ruleType;
    }

    /**
     * @return array{id: string, taxRate: float, name: string, rules: list<array{id: string, countryId: string, taxRate: float, taxRuleTypeId: string, data?: array<string, mixed>}>}
     */
    private function getTaxData(string $shippingCountryId, string $countryStateId): array
    {
        return [
            'id' => Uuid::randomHex(),
            'taxRate' => 15,
            'name' => Uuid::randomHex(),
            'rules' => [
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $shippingCountryId,
                    'taxRate' => 10,
                    'taxRuleTypeId' => $this->getRuleType(EntireCountryRuleTypeFilter::TECHNICAL_NAME)->getId(),
                ],
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $shippingCountryId,
                    'taxRate' => 9,
                    'taxRuleTypeId' => $this->getRuleType(IndividualStatesRuleTypeFilter::TECHNICAL_NAME)->getId(),
                    'data' => [
                        'states' => [$countryStateId],
                    ],
                ],
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $shippingCountryId,
                    'taxRate' => 8,
                    'taxRuleTypeId' => $this->getRuleType(ZipCodeRangeRuleTypeFilter::TECHNICAL_NAME)->getId(),
                    'data' => [
                        'fromZipCode' => '12000',
                        'toZipCode' => '12999',
                    ],
                ],
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $shippingCountryId,
                    'taxRate' => 7,
                    'taxRuleTypeId' => $this->getRuleType(ZipCodeRuleTypeFilter::TECHNICAL_NAME)->getId(),
                    'data' => [
                        'zipCode' => '12345',
                    ],
                ],
            ],
        ];
    }
}

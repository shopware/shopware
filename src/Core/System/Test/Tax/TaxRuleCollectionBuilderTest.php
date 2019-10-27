<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\Tax;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\Tax\Aggregate\TaxAreaRule\TaxAreaRuleCollection;
use Shopware\Core\System\Tax\Aggregate\TaxAreaRule\TaxAreaRuleEntity;
use Shopware\Core\System\Tax\Aggregate\TaxAreaRuleType\TaxAreaRuleTypeCollection;
use Shopware\Core\System\Tax\Builder\TaxRuleCollectionBuilder;
use Shopware\Core\System\Tax\Builder\TaxRuleCollectionBuilderInterface;
use Shopware\Core\System\Tax\TaxAreaRuleType\EntireCountryRuleTypeFilter;
use Shopware\Core\System\Tax\TaxAreaRuleType\IndividualStatesRuleTypeFilter;
use Shopware\Core\System\Tax\TaxAreaRuleType\ZipCodeRangeRuleTypeFilter;
use Shopware\Core\System\Tax\TaxAreaRuleType\ZipCodeRuleTypeFilter;
use Shopware\Core\System\Tax\TaxEntity;

class TaxRuleCollectionBuilderTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var TaxRuleCollectionBuilder
     */
    private $taxRuleCollectionBuilder;

    /**
     * @var TaxAreaRuleTypeCollection
     */
    private $taxAreaRuleTypes;

    public function setUp(): void
    {
        $this->taxRuleCollectionBuilder = $this->getContainer()->get(TaxRuleCollectionBuilderInterface::class);
        $this->taxAreaRuleTypes = $this->loadTaxAreaRuleTypes();
    }

    public function testWithoutAreaRulesReturnsDefault(): void
    {
        $taxEntity = (new TaxEntity())->assign(['id' => Uuid::randomHex(), 'taxRate' => 15]);
        $taxRuleCollection = $this->taxRuleCollectionBuilder->buildTaxRuleCollection($taxEntity, $this->createSalesChannelContext());

        static::assertCount(1, $taxRuleCollection);
        static::assertSame(15.0, $taxRuleCollection->first()->getTaxRate());
        static::assertSame(100.0, $taxRuleCollection->first()->getPercentage());
    }

    public function testFallbackAreaRulesFromEntity(): void
    {
        $salesChannelContext = $this->createSalesChannelContext();
        $taxEntity = (new TaxEntity())->assign([
            'id' => Uuid::randomHex(),
            'taxRate' => 15,
            'taxAreaRules' => (new TaxAreaRuleCollection([
                (new TaxAreaRuleEntity())->assign([
                    'id' => Uuid::randomHex(),
                    'countryId' => $salesChannelContext->getShippingLocation()->getCountry()->getId(),
                    'taxRate' => 10,
                    'taxAreaRuleType' => $this->taxAreaRuleTypes->getByTechnicalName(EntireCountryRuleTypeFilter::TECHNICAL_NAME),
                ]),
            ])),
        ]);
        $taxRuleCollection = $this->taxRuleCollectionBuilder->buildTaxRuleCollection($taxEntity, $this->createSalesChannelContext());

        static::assertCount(1, $taxRuleCollection);
        static::assertSame(10.0, $taxRuleCollection->first()->getTaxRate());
        static::assertSame(100.0, $taxRuleCollection->first()->getPercentage());
    }

    public function testCustomerBillingCountry(): void
    {
        $customerId = Uuid::randomHex();
        $billingCountryId = Uuid::randomHex();
        $this->createCustomer($customerId, Uuid::randomHex(), $billingCountryId);
        $taxId = Uuid::randomHex();
        $taxData = [
            'id' => $taxId,
            'taxRate' => 15,
            'name' => Uuid::randomHex(),
            'taxAreaRules' => [
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $billingCountryId,
                    'taxRate' => 10,
                    'taxAreaRuleTypeId' => $this->taxAreaRuleTypes->getByTechnicalName(EntireCountryRuleTypeFilter::TECHNICAL_NAME)->getId(),
                ],
            ],
        ];

        $salesChannelContext = $this->createSalesChannelContext([$taxData], [SalesChannelContextService::CUSTOMER_ID => $customerId]);
        $taxRuleCollection = $this->taxRuleCollectionBuilder->buildTaxRuleCollection((new TaxEntity())->assign(['id' => $taxId, 'taxRate' => 15]), $salesChannelContext);
        static::assertCount(1, $taxRuleCollection);
        static::assertSame(10.0, $taxRuleCollection->first()->getTaxRate());
        static::assertSame(100.0, $taxRuleCollection->first()->getPercentage());
    }

    public function testCustomerBillingWithMultipleAreaRule(): void
    {
        $customerId = Uuid::randomHex();
        $randomCountryId = $this->getValidCountryId();
        $billingCountryId = Uuid::randomHex();
        $shippingCountryId = Uuid::randomHex();
        $this->createCustomer($customerId, $shippingCountryId, $billingCountryId);
        $taxData = [
            'id' => Uuid::randomHex(),
            'taxRate' => 15,
            'name' => Uuid::randomHex(),
            'taxAreaRules' => [
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $billingCountryId,
                    'taxRate' => 10,
                    'taxAreaRuleTypeId' => $this->taxAreaRuleTypes->getByTechnicalName(EntireCountryRuleTypeFilter::TECHNICAL_NAME)->getId(),
                ],
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $shippingCountryId,
                    'taxRate' => 9,
                    'taxAreaRuleTypeId' => $this->taxAreaRuleTypes->getByTechnicalName(EntireCountryRuleTypeFilter::TECHNICAL_NAME)->getId(),
                ],
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $randomCountryId,
                    'taxRate' => 8,
                    'taxAreaRuleTypeId' => $this->taxAreaRuleTypes->getByTechnicalName(EntireCountryRuleTypeFilter::TECHNICAL_NAME)->getId(),
                ],
            ],
        ];
        $salesChannelContext = $this->createSalesChannelContext([$taxData], [SalesChannelContextService::CUSTOMER_ID => $customerId]);
        $taxRuleCollection = $this->taxRuleCollectionBuilder->buildTaxRuleCollection((new TaxEntity())->assign(['id' => $taxData['id'], 'taxRate' => $taxData['taxRate']]), $salesChannelContext);

        static::assertCount(1, $taxRuleCollection);
        static::assertSame(10.0, $taxRuleCollection->first()->getTaxRate());
        static::assertSame(100.0, $taxRuleCollection->first()->getPercentage());
    }

    public function testCustomerBillingTypeIndividualStatesOverridesEntireCountry(): void
    {
        $customerId = Uuid::randomHex();
        $billingCountryId = Uuid::randomHex();
        $countryStateId = Uuid::randomHex();
        $countryState = ['id' => $countryStateId, 'countryId' => $billingCountryId, 'shortCode' => Uuid::randomHex(), 'name' => Uuid::randomHex()];
        $this->createCustomer($customerId, Uuid::randomHex(), $billingCountryId, $countryState);
        $taxData = [
            'id' => Uuid::randomHex(),
            'taxRate' => 15,
            'name' => Uuid::randomHex(),
            'taxAreaRules' => [
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $billingCountryId,
                    'taxRate' => 10,
                    'taxAreaRuleTypeId' => $this->taxAreaRuleTypes->getByTechnicalName(EntireCountryRuleTypeFilter::TECHNICAL_NAME)->getId(),
                ],
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $billingCountryId,
                    'taxRate' => 9,
                    'taxAreaRuleTypeId' => $this->taxAreaRuleTypes->getByTechnicalName(IndividualStatesRuleTypeFilter::TECHNICAL_NAME)->getId(),
                    'data' => [
                        'states' => [$countryStateId],
                    ],
                ],
            ],
        ];
        $salesChannelContext = $this->createSalesChannelContext([$taxData], [SalesChannelContextService::CUSTOMER_ID => $customerId]);
        $taxRuleCollection = $this->taxRuleCollectionBuilder->buildTaxRuleCollection((new TaxEntity())->assign(['id' => $taxData['id'], 'taxRate' => $taxData['taxRate']]), $salesChannelContext);

        static::assertCount(1, $taxRuleCollection);
        static::assertSame(9.0, $taxRuleCollection->first()->getTaxRate());
        static::assertSame(100.0, $taxRuleCollection->first()->getPercentage());
    }

    public function testCustomerBillingTypeZipCodeRangeOverridesIndividualStates(): void
    {
        $customerId = Uuid::randomHex();
        $billingCountryId = Uuid::randomHex();
        $countryStateId = Uuid::randomHex();
        $countryState = ['id' => $countryStateId, 'countryId' => $billingCountryId, 'shortCode' => Uuid::randomHex(), 'name' => Uuid::randomHex()];
        $this->createCustomer($customerId, Uuid::randomHex(), $billingCountryId, $countryState);
        $taxData = [
            'id' => Uuid::randomHex(),
            'taxRate' => 15,
            'name' => Uuid::randomHex(),
            'taxAreaRules' => [
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $billingCountryId,
                    'taxRate' => 9,
                    'taxAreaRuleTypeId' => $this->taxAreaRuleTypes->getByTechnicalName(IndividualStatesRuleTypeFilter::TECHNICAL_NAME)->getId(),
                    'data' => [
                        'states' => [$countryStateId],
                    ],
                ],
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $billingCountryId,
                    'taxRate' => 8,
                    'taxAreaRuleTypeId' => $this->taxAreaRuleTypes->getByTechnicalName(ZipCodeRangeRuleTypeFilter::TECHNICAL_NAME)->getId(),
                    'data' => [
                        'fromZipCode' => '12000',
                        'toZipCode' => '12999',
                    ],
                ],
            ],
        ];
        $salesChannelContext = $this->createSalesChannelContext([$taxData], [SalesChannelContextService::CUSTOMER_ID => $customerId]);
        $taxRuleCollection = $this->taxRuleCollectionBuilder->buildTaxRuleCollection((new TaxEntity())->assign(['id' => $taxData['id'], 'taxRate' => $taxData['taxRate']]), $salesChannelContext);

        static::assertCount(1, $taxRuleCollection);
        static::assertSame(8.0, $taxRuleCollection->first()->getTaxRate());
        static::assertSame(100.0, $taxRuleCollection->first()->getPercentage());
    }

    public function testCustomerBillingTypeZipCodeOverridesTypeZipCodeRange(): void
    {
        $customerId = Uuid::randomHex();
        $billingCountryId = Uuid::randomHex();
        $countryStateId = Uuid::randomHex();
        $countryState = ['id' => $countryStateId, 'countryId' => $billingCountryId, 'shortCode' => Uuid::randomHex(), 'name' => Uuid::randomHex()];
        $this->createCustomer($customerId, Uuid::randomHex(), $billingCountryId, $countryState);
        $taxData = [
            'id' => Uuid::randomHex(),
            'taxRate' => 15,
            'name' => Uuid::randomHex(),
            'taxAreaRules' => [
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $billingCountryId,
                    'taxRate' => 8,
                    'taxAreaRuleTypeId' => $this->taxAreaRuleTypes->getByTechnicalName(ZipCodeRangeRuleTypeFilter::TECHNICAL_NAME)->getId(),
                    'data' => [
                        'fromZipCode' => '12000',
                        'toZipCode' => '12999',
                    ],
                ],
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $billingCountryId,
                    'taxRate' => 7,
                    'taxAreaRuleTypeId' => $this->taxAreaRuleTypes->getByTechnicalName(ZipCodeRuleTypeFilter::TECHNICAL_NAME)->getId(),
                    'data' => [
                        'zipCode' => '12345',
                    ],
                ],
            ],
        ];
        $salesChannelContext = $this->createSalesChannelContext([$taxData], [SalesChannelContextService::CUSTOMER_ID => $customerId]);
        $taxRuleCollection = $this->taxRuleCollectionBuilder->buildTaxRuleCollection((new TaxEntity())->assign(['id' => $taxData['id'], 'taxRate' => $taxData['taxRate']]), $salesChannelContext);

        static::assertCount(1, $taxRuleCollection);
        static::assertSame(7.0, $taxRuleCollection->first()->getTaxRate());
        static::assertSame(100.0, $taxRuleCollection->first()->getPercentage());
    }

    public function testCustomerBillingTypeZipCodeOverridesOthers(): void
    {
        $customerId = Uuid::randomHex();
        $billingCountryId = Uuid::randomHex();
        $countryStateId = Uuid::randomHex();
        $countryState = ['id' => $countryStateId, 'countryId' => $billingCountryId, 'shortCode' => Uuid::randomHex(), 'name' => Uuid::randomHex()];
        $this->createCustomer($customerId, Uuid::randomHex(), $billingCountryId, $countryState);
        $taxData = [
            'id' => Uuid::randomHex(),
            'taxRate' => 15,
            'name' => Uuid::randomHex(),
            'taxAreaRules' => [
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $billingCountryId,
                    'taxRate' => 10,
                    'taxAreaRuleTypeId' => $this->taxAreaRuleTypes->getByTechnicalName(EntireCountryRuleTypeFilter::TECHNICAL_NAME)->getId(),
                ],
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $billingCountryId,
                    'taxRate' => 9,
                    'taxAreaRuleTypeId' => $this->taxAreaRuleTypes->getByTechnicalName(IndividualStatesRuleTypeFilter::TECHNICAL_NAME)->getId(),
                    'data' => [
                        'states' => [$countryStateId],
                    ],
                ],
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $billingCountryId,
                    'taxRate' => 8,
                    'taxAreaRuleTypeId' => $this->taxAreaRuleTypes->getByTechnicalName(ZipCodeRangeRuleTypeFilter::TECHNICAL_NAME)->getId(),
                    'data' => [
                        'fromZipCode' => '12000',
                        'toZipCode' => '12999',
                    ],
                ],
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $billingCountryId,
                    'taxRate' => 7,
                    'taxAreaRuleTypeId' => $this->taxAreaRuleTypes->getByTechnicalName(ZipCodeRuleTypeFilter::TECHNICAL_NAME)->getId(),
                    'data' => [
                        'zipCode' => '12345',
                    ],
                ],
            ],
        ];
        $salesChannelContext = $this->createSalesChannelContext([$taxData], [SalesChannelContextService::CUSTOMER_ID => $customerId]);
        $taxRuleCollection = $this->taxRuleCollectionBuilder->buildTaxRuleCollection((new TaxEntity())->assign(['id' => $taxData['id'], 'taxRate' => $taxData['taxRate']]), $salesChannelContext);

        static::assertCount(1, $taxRuleCollection);
        static::assertSame(7.0, $taxRuleCollection->first()->getTaxRate());
        static::assertSame(100.0, $taxRuleCollection->first()->getPercentage());
    }

    public function testDefaultOnWrongCountry(): void
    {
        $customerId = Uuid::randomHex();
        $billingCountryId = $this->getValidCountryId();
        $countryStateId = Uuid::randomHex();
        $countryState = ['id' => $countryStateId, 'countryId' => $billingCountryId, 'shortCode' => Uuid::randomHex(), 'name' => Uuid::randomHex()];
        $this->createCustomer($customerId, Uuid::randomHex(), Uuid::randomHex(), $countryState);
        $taxData = [
            'id' => Uuid::randomHex(),
            'taxRate' => 15,
            'name' => Uuid::randomHex(),
            'taxAreaRules' => [
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $billingCountryId,
                    'taxRate' => 10,
                    'taxAreaRuleTypeId' => $this->taxAreaRuleTypes->getByTechnicalName(EntireCountryRuleTypeFilter::TECHNICAL_NAME)->getId(),
                ],
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $billingCountryId,
                    'taxRate' => 9,
                    'taxAreaRuleTypeId' => $this->taxAreaRuleTypes->getByTechnicalName(IndividualStatesRuleTypeFilter::TECHNICAL_NAME)->getId(),
                    'data' => [
                        'states' => [$countryStateId],
                    ],
                ],
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $billingCountryId,
                    'taxRate' => 8,
                    'taxAreaRuleTypeId' => $this->taxAreaRuleTypes->getByTechnicalName(ZipCodeRangeRuleTypeFilter::TECHNICAL_NAME)->getId(),
                    'data' => [
                        'fromZipCode' => '12000',
                        'toZipCode' => '12999',
                    ],
                ],
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $billingCountryId,
                    'taxRate' => 7,
                    'taxAreaRuleTypeId' => $this->taxAreaRuleTypes->getByTechnicalName(ZipCodeRuleTypeFilter::TECHNICAL_NAME)->getId(),
                    'data' => [
                        'zipCode' => '12345',
                    ],
                ],
            ],
        ];
        $salesChannelContext = $this->createSalesChannelContext([$taxData], [SalesChannelContextService::CUSTOMER_ID => $customerId]);
        $taxRuleCollection = $this->taxRuleCollectionBuilder->buildTaxRuleCollection((new TaxEntity())->assign(['id' => $taxData['id'], 'taxRate' => $taxData['taxRate']]), $salesChannelContext);

        static::assertCount(1, $taxRuleCollection);
        static::assertSame(15.0, $taxRuleCollection->first()->getTaxRate());
        static::assertSame(100.0, $taxRuleCollection->first()->getPercentage());
    }

    public function testNoCustomerDefaultShippingLocationCountry(): void
    {
        $shippingCountryId = $this->getValidCountryId();
        $taxId = Uuid::randomHex();
        $taxData = [
            'id' => $taxId,
            'taxRate' => 15,
            'name' => Uuid::randomHex(),
            'taxAreaRules' => [
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $shippingCountryId,
                    'taxRate' => 10,
                    'taxAreaRuleTypeId' => $this->taxAreaRuleTypes->getByTechnicalName(EntireCountryRuleTypeFilter::TECHNICAL_NAME)->getId(),
                ],
            ],
        ];

        $salesChannelContext = $this->createSalesChannelContext([$taxData], [SalesChannelContextService::COUNTRY_ID => $shippingCountryId]);
        $taxRuleCollection = $this->taxRuleCollectionBuilder->buildTaxRuleCollection((new TaxEntity())->assign(['id' => $taxId, 'taxRate' => 15]), $salesChannelContext);
        static::assertCount(1, $taxRuleCollection);
        static::assertSame(10.0, $taxRuleCollection->first()->getTaxRate());
        static::assertSame(100.0, $taxRuleCollection->first()->getPercentage());
    }

    public function testNoCustomerDefaultShippingLocationWithMultipleAreaRule(): void
    {
        $countryIds = $this->getValidCountryIds(3);
        $shippingCountryId = $countryIds[0];
        $taxData = [
            'id' => Uuid::randomHex(),
            'taxRate' => 15,
            'name' => Uuid::randomHex(),
            'taxAreaRules' => [
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $shippingCountryId,
                    'taxRate' => 10,
                    'taxAreaRuleTypeId' => $this->taxAreaRuleTypes->getByTechnicalName(EntireCountryRuleTypeFilter::TECHNICAL_NAME)->getId(),
                ],
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $countryIds[1],
                    'taxRate' => 9,
                    'taxAreaRuleTypeId' => $this->taxAreaRuleTypes->getByTechnicalName(EntireCountryRuleTypeFilter::TECHNICAL_NAME)->getId(),
                ],
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $countryIds[2],
                    'taxRate' => 8,
                    'taxAreaRuleTypeId' => $this->taxAreaRuleTypes->getByTechnicalName(EntireCountryRuleTypeFilter::TECHNICAL_NAME)->getId(),
                ],
            ],
        ];
        $salesChannelContext = $this->createSalesChannelContext([$taxData], [SalesChannelContextService::COUNTRY_ID => $shippingCountryId]);
        $taxRuleCollection = $this->taxRuleCollectionBuilder->buildTaxRuleCollection((new TaxEntity())->assign(['id' => $taxData['id'], 'taxRate' => $taxData['taxRate']]), $salesChannelContext);

        static::assertCount(1, $taxRuleCollection);
        static::assertSame(10.0, $taxRuleCollection->first()->getTaxRate());
        static::assertSame(100.0, $taxRuleCollection->first()->getPercentage());
    }

    public function testNoCustomerDefaultShippingLocationTypeIndividualStatesOverridesEntireCountry(): void
    {
        $shippingCountryId = $this->getValidCountryId();
        $countryStateId = $this->createCountryState($shippingCountryId);
        $taxData = [
            'id' => Uuid::randomHex(),
            'taxRate' => 15,
            'name' => Uuid::randomHex(),
            'taxAreaRules' => [
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $shippingCountryId,
                    'taxRate' => 10,
                    'taxAreaRuleTypeId' => $this->taxAreaRuleTypes->getByTechnicalName(EntireCountryRuleTypeFilter::TECHNICAL_NAME)->getId(),
                ],
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $shippingCountryId,
                    'taxRate' => 9,
                    'taxAreaRuleTypeId' => $this->taxAreaRuleTypes->getByTechnicalName(IndividualStatesRuleTypeFilter::TECHNICAL_NAME)->getId(),
                    'data' => [
                        'states' => [$countryStateId],
                    ],
                ],
            ],
        ];
        $salesChannelContext = $this->createSalesChannelContext([$taxData], [SalesChannelContextService::COUNTRY_ID => $shippingCountryId, SalesChannelContextService::COUNTRY_STATE_ID => $countryStateId]);
        $taxRuleCollection = $this->taxRuleCollectionBuilder->buildTaxRuleCollection((new TaxEntity())->assign(['id' => $taxData['id'], 'taxRate' => $taxData['taxRate']]), $salesChannelContext);

        static::assertCount(1, $taxRuleCollection);
        static::assertSame(9.0, $taxRuleCollection->first()->getTaxRate());
        static::assertSame(100.0, $taxRuleCollection->first()->getPercentage());
    }

    public function testNoCustomerDefaultShippingLocationTypeZipCodeRangeDoesNotMatch(): void
    {
        $shippingCountryId = $this->getValidCountryId();
        $taxData = [
            'id' => Uuid::randomHex(),
            'taxRate' => 15,
            'name' => Uuid::randomHex(),
            'taxAreaRules' => [
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $shippingCountryId,
                    'taxRate' => 8,
                    'taxAreaRuleTypeId' => $this->taxAreaRuleTypes->getByTechnicalName(ZipCodeRangeRuleTypeFilter::TECHNICAL_NAME)->getId(),
                    'data' => [
                        'fromZipCode' => '12000',
                        'toZipCode' => '12999',
                    ],
                ],
            ],
        ];
        $salesChannelContext = $this->createSalesChannelContext([$taxData], [SalesChannelContextService::COUNTRY_ID => $shippingCountryId]);
        $taxRuleCollection = $this->taxRuleCollectionBuilder->buildTaxRuleCollection((new TaxEntity())->assign(['id' => $taxData['id'], 'taxRate' => $taxData['taxRate']]), $salesChannelContext);

        static::assertCount(1, $taxRuleCollection);
        static::assertSame(15.0, $taxRuleCollection->first()->getTaxRate());
        static::assertSame(100.0, $taxRuleCollection->first()->getPercentage());
    }

    public function testNoCustomerDefaultShippingLocationTypeZipCodeDoesNotMatch(): void
    {
        $shippingCountryId = $this->getValidCountryId();
        $taxData = [
            'id' => Uuid::randomHex(),
            'taxRate' => 15,
            'name' => Uuid::randomHex(),
            'taxAreaRules' => [
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $shippingCountryId,
                    'taxRate' => 8,
                    'taxAreaRuleTypeId' => $this->taxAreaRuleTypes->getByTechnicalName(ZipCodeRuleTypeFilter::TECHNICAL_NAME)->getId(),
                    'data' => [
                        'zipCode' => '12345',
                    ],
                ],
            ],
        ];
        $salesChannelContext = $this->createSalesChannelContext([$taxData], [SalesChannelContextService::COUNTRY_ID => $shippingCountryId]);
        $taxRuleCollection = $this->taxRuleCollectionBuilder->buildTaxRuleCollection((new TaxEntity())->assign(['id' => $taxData['id'], 'taxRate' => $taxData['taxRate']]), $salesChannelContext);

        static::assertCount(1, $taxRuleCollection);
        static::assertSame(15.0, $taxRuleCollection->first()->getTaxRate());
        static::assertSame(100.0, $taxRuleCollection->first()->getPercentage());
    }

    public function testNoCustomerDefaultShippingLocationTypeIndividualStatesOverridesOthers(): void
    {
        $shippingCountryId = $this->getValidCountryId();
        $countryStateId = $this->createCountryState($shippingCountryId);
        $taxData = [
            'id' => Uuid::randomHex(),
            'taxRate' => 15,
            'name' => Uuid::randomHex(),
            'taxAreaRules' => [
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $shippingCountryId,
                    'taxRate' => 10,
                    'taxAreaRuleTypeId' => $this->taxAreaRuleTypes->getByTechnicalName(EntireCountryRuleTypeFilter::TECHNICAL_NAME)->getId(),
                ],
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $shippingCountryId,
                    'taxRate' => 9,
                    'taxAreaRuleTypeId' => $this->taxAreaRuleTypes->getByTechnicalName(IndividualStatesRuleTypeFilter::TECHNICAL_NAME)->getId(),
                    'data' => [
                        'states' => [$countryStateId],
                    ],
                ],
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $shippingCountryId,
                    'taxRate' => 8,
                    'taxAreaRuleTypeId' => $this->taxAreaRuleTypes->getByTechnicalName(ZipCodeRangeRuleTypeFilter::TECHNICAL_NAME)->getId(),
                    'data' => [
                        'fromZipCode' => '12000',
                        'toZipCode' => '12999',
                    ],
                ],
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $shippingCountryId,
                    'taxRate' => 7,
                    'taxAreaRuleTypeId' => $this->taxAreaRuleTypes->getByTechnicalName(ZipCodeRuleTypeFilter::TECHNICAL_NAME)->getId(),
                    'data' => [
                        'zipCode' => '12345',
                    ],
                ],
            ],
        ];
        $salesChannelContext = $this->createSalesChannelContext([$taxData], [SalesChannelContextService::COUNTRY_ID => $shippingCountryId, SalesChannelContextService::COUNTRY_STATE_ID => $countryStateId]);
        $taxRuleCollection = $this->taxRuleCollectionBuilder->buildTaxRuleCollection((new TaxEntity())->assign(['id' => $taxData['id'], 'taxRate' => $taxData['taxRate']]), $salesChannelContext);

        static::assertCount(1, $taxRuleCollection);
        static::assertSame(9.0, $taxRuleCollection->first()->getTaxRate());
        static::assertSame(100.0, $taxRuleCollection->first()->getPercentage());
    }

    public function testNoCustomerDefaultShippingLocationDefaultOnWrongCountry(): void
    {
        $countryIds = $this->getValidCountryIds(2);
        $shippingCountryId = $countryIds[0];
        $countryStateId = $this->createCountryState($shippingCountryId);
        $taxData = [
            'id' => Uuid::randomHex(),
            'taxRate' => 15,
            'name' => Uuid::randomHex(),
            'taxAreaRules' => [
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $countryIds[1],
                    'taxRate' => 10,
                    'taxAreaRuleTypeId' => $this->taxAreaRuleTypes->getByTechnicalName(EntireCountryRuleTypeFilter::TECHNICAL_NAME)->getId(),
                ],
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $countryIds[1],
                    'taxRate' => 9,
                    'taxAreaRuleTypeId' => $this->taxAreaRuleTypes->getByTechnicalName(IndividualStatesRuleTypeFilter::TECHNICAL_NAME)->getId(),
                    'data' => [
                        'states' => [$countryStateId],
                    ],
                ],
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $countryIds[1],
                    'taxRate' => 8,
                    'taxAreaRuleTypeId' => $this->taxAreaRuleTypes->getByTechnicalName(ZipCodeRangeRuleTypeFilter::TECHNICAL_NAME)->getId(),
                    'data' => [
                        'fromZipCode' => '12000',
                        'toZipCode' => '12999',
                    ],
                ],
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $countryIds[1],
                    'taxRate' => 7,
                    'taxAreaRuleTypeId' => $this->taxAreaRuleTypes->getByTechnicalName(ZipCodeRuleTypeFilter::TECHNICAL_NAME)->getId(),
                    'data' => [
                        'zipCode' => '12345',
                    ],
                ],
            ],
        ];
        $salesChannelContext = $this->createSalesChannelContext([$taxData], [SalesChannelContextService::COUNTRY_ID => $shippingCountryId, SalesChannelContextService::COUNTRY_STATE_ID => $countryStateId]);
        $taxRuleCollection = $this->taxRuleCollectionBuilder->buildTaxRuleCollection((new TaxEntity())->assign(['id' => $taxData['id'], 'taxRate' => $taxData['taxRate']]), $salesChannelContext);

        static::assertCount(1, $taxRuleCollection);
        static::assertSame(15.0, $taxRuleCollection->first()->getTaxRate());
        static::assertSame(100.0, $taxRuleCollection->first()->getPercentage());
    }

    protected function getValidCountryIds(int $limit): array
    {
        /** @var EntityRepositoryInterface $repository */
        $repository = $this->getContainer()->get('country.repository');

        $criteria = (new Criteria())->setLimit($limit)->addFilter(new EqualsFilter('taxFree', 0));

        return $repository->searchIds($criteria, Context::createDefaultContext())->getIds();
    }

    protected function createCountryState(string $countryId): string
    {
        /** @var EntityRepositoryInterface $repository */
        $repository = $this->getContainer()->get('country_state.repository');
        $id = Uuid::randomHex();

        $repository->create(
            [['id' => $id, 'countryId' => $countryId, 'shortCode' => Uuid::randomHex(), 'name' => Uuid::randomHex()]],
            Context::createDefaultContext()
        );

        return $id;
    }

    private function createSalesChannelContext(array $taxData = [], array $options = []): SalesChannelContext
    {
        if ($taxData) {
            $this->getContainer()->get('tax.repository')->create($taxData, Context::createDefaultContext());
        }

        $salesChannelContextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);

        $token = Uuid::randomHex();

        return $salesChannelContextFactory->create($token, Defaults::SALES_CHANNEL, $options);
    }

    private function loadTaxAreaRuleTypes(): EntityCollection
    {
        return $this->getContainer()->get('tax_area_rule_type.repository')->search(new Criteria(), Context::createDefaultContext())->getEntities();
    }

    private function createCustomer(
        string $customerId,
        string $shippingCountryId,
        string $billingCountryId,
        ?array $countryState = null
    ): void {
        $customerRepository = $this->getContainer()->get('customer.repository');
        $salutationId = $this->getValidSalutationId();
        $billingAddress = [
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'street' => 'Musterstraße 1',
            'city' => 'Schöppingen',
            'zipcode' => '12345',
            'salutationId' => $salutationId,
            'country' => ['id' => $billingCountryId, 'name' => 'Germany'],
        ];

        if ($countryState) {
            $billingAddress['countryState'] = $countryState;
        }

        $customer = [
            'id' => $customerId,
            'salesChannelId' => Defaults::SALES_CHANNEL,
            'defaultShippingAddress' => [
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'street' => 'Musterstraße 1',
                'city' => 'Schöppingen',
                'zipcode' => '54321',
                'salutationId' => $salutationId,
                'country' => ['id' => $shippingCountryId, 'name' => 'Germany'],
            ],
            'defaultBillingAddress' => $billingAddress,
            'defaultPaymentMethodId' => $this->getAvailablePaymentMethod()->getId(),
            'groupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
            'email' => Uuid::randomHex() . '@example.com',
            'password' => '$password',
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'salutationId' => $salutationId,
            'customerNumber' => '12345',
        ];

        $customerRepository->create([$customer], Context::createDefaultContext());
    }
}

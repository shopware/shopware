<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\SalesChannel\Context;

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
use Shopware\Core\System\Tax\Aggregate\TaxRuleType\TaxRuleTypeCollection;
use Shopware\Core\System\Tax\TaxRuleType\EntireCountryRuleTypeFilter;
use Shopware\Core\System\Tax\TaxRuleType\IndividualStatesRuleTypeFilter;
use Shopware\Core\System\Tax\TaxRuleType\ZipCodeRangeRuleTypeFilter;
use Shopware\Core\System\Tax\TaxRuleType\ZipCodeRuleTypeFilter;

class SalesChannelContextTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var TaxRuleTypeCollection
     */
    private $taxRuleTypes;

    public function setUp(): void
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
        $salesChannelContext = $this->createSalesChannelContext([$taxData], []);
        $taxRuleCollection = $salesChannelContext->buildTaxRules($taxId);

        static::assertCount(1, $taxRuleCollection);
        static::assertSame(15.0, $taxRuleCollection->first()->getTaxRate());
        static::assertSame(100.0, $taxRuleCollection->first()->getPercentage());
    }

    public function testGetTaxRuleCollectionCustomerBillingCountry(): void
    {
        $customerId = Uuid::randomHex();
        $billingCountryId = Uuid::randomHex();
        $this->createCustomer($customerId, Uuid::randomHex(), $billingCountryId);
        $taxId = Uuid::randomHex();
        $taxData = [
            'id' => $taxId,
            'taxRate' => 15,
            'name' => Uuid::randomHex(),
            'rules' => [
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $billingCountryId,
                    'taxRate' => 10,
                    'taxRuleTypeId' => $this->taxRuleTypes->getByTechnicalName(EntireCountryRuleTypeFilter::TECHNICAL_NAME)->getId(),
                ],
            ],
        ];

        $salesChannelContext = $this->createSalesChannelContext([$taxData], [SalesChannelContextService::CUSTOMER_ID => $customerId]);
        $taxRuleCollection = $salesChannelContext->buildTaxRules($taxData['id']);
        static::assertCount(1, $taxRuleCollection);
        static::assertSame(10.0, $taxRuleCollection->first()->getTaxRate());
        static::assertSame(100.0, $taxRuleCollection->first()->getPercentage());
    }

    public function testGetTaxRuleCollectionCustomerBillingWithMultipleRule(): void
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
            'rules' => [
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $billingCountryId,
                    'taxRate' => 10,
                    'taxRuleTypeId' => $this->taxRuleTypes->getByTechnicalName(EntireCountryRuleTypeFilter::TECHNICAL_NAME)->getId(),
                ],
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $shippingCountryId,
                    'taxRate' => 9,
                    'taxRuleTypeId' => $this->taxRuleTypes->getByTechnicalName(EntireCountryRuleTypeFilter::TECHNICAL_NAME)->getId(),
                ],
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $randomCountryId,
                    'taxRate' => 8,
                    'taxRuleTypeId' => $this->taxRuleTypes->getByTechnicalName(EntireCountryRuleTypeFilter::TECHNICAL_NAME)->getId(),
                ],
            ],
        ];
        $salesChannelContext = $this->createSalesChannelContext([$taxData], [SalesChannelContextService::CUSTOMER_ID => $customerId]);
        $taxRuleCollection = $salesChannelContext->buildTaxRules($taxData['id']);

        static::assertCount(1, $taxRuleCollection);
        static::assertSame(10.0, $taxRuleCollection->first()->getTaxRate());
        static::assertSame(100.0, $taxRuleCollection->first()->getPercentage());
    }

    public function testGetTaxRuleCollectionCustomerBillingTypeIndividualStatesOverridesEntireCountry(): void
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
            'rules' => [
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $billingCountryId,
                    'taxRate' => 10,
                    'taxRuleTypeId' => $this->taxRuleTypes->getByTechnicalName(EntireCountryRuleTypeFilter::TECHNICAL_NAME)->getId(),
                ],
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $billingCountryId,
                    'taxRate' => 9,
                    'taxRuleTypeId' => $this->taxRuleTypes->getByTechnicalName(IndividualStatesRuleTypeFilter::TECHNICAL_NAME)->getId(),
                    'data' => [
                        'states' => [$countryStateId],
                    ],
                ],
            ],
        ];
        $salesChannelContext = $this->createSalesChannelContext([$taxData], [SalesChannelContextService::CUSTOMER_ID => $customerId]);
        $taxRuleCollection = $salesChannelContext->buildTaxRules($taxData['id']);

        static::assertCount(1, $taxRuleCollection);
        static::assertSame(9.0, $taxRuleCollection->first()->getTaxRate());
        static::assertSame(100.0, $taxRuleCollection->first()->getPercentage());
    }

    public function testGetTaxRuleCollectionCustomerBillingTypeZipCodeRangeOverridesIndividualStates(): void
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
            'rules' => [
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $billingCountryId,
                    'taxRate' => 9,
                    'taxRuleTypeId' => $this->taxRuleTypes->getByTechnicalName(IndividualStatesRuleTypeFilter::TECHNICAL_NAME)->getId(),
                    'data' => [
                        'states' => [$countryStateId],
                    ],
                ],
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $billingCountryId,
                    'taxRate' => 8,
                    'taxRuleTypeId' => $this->taxRuleTypes->getByTechnicalName(ZipCodeRangeRuleTypeFilter::TECHNICAL_NAME)->getId(),
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
        static::assertSame(8.0, $taxRuleCollection->first()->getTaxRate());
        static::assertSame(100.0, $taxRuleCollection->first()->getPercentage());
    }

    public function testGetTaxRuleCollectionCustomerBillingTypeZipCodeOverridesTypeZipCodeRange(): void
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
            'rules' => [
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $billingCountryId,
                    'taxRate' => 8,
                    'taxRuleTypeId' => $this->taxRuleTypes->getByTechnicalName(ZipCodeRangeRuleTypeFilter::TECHNICAL_NAME)->getId(),
                    'data' => [
                        'fromZipCode' => '12000',
                        'toZipCode' => '12999',
                    ],
                ],
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $billingCountryId,
                    'taxRate' => 7,
                    'taxRuleTypeId' => $this->taxRuleTypes->getByTechnicalName(ZipCodeRuleTypeFilter::TECHNICAL_NAME)->getId(),
                    'data' => [
                        'zipCode' => '12345',
                    ],
                ],
            ],
        ];
        $salesChannelContext = $this->createSalesChannelContext([$taxData], [SalesChannelContextService::CUSTOMER_ID => $customerId]);
        $taxRuleCollection = $salesChannelContext->buildTaxRules($taxData['id']);

        static::assertCount(1, $taxRuleCollection);
        static::assertSame(7.0, $taxRuleCollection->first()->getTaxRate());
        static::assertSame(100.0, $taxRuleCollection->first()->getPercentage());
    }

    public function testGetTaxRuleCollectionCustomerBillingTypeZipCodeOverridesOthers(): void
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
            'rules' => [
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $billingCountryId,
                    'taxRate' => 10,
                    'taxRuleTypeId' => $this->taxRuleTypes->getByTechnicalName(EntireCountryRuleTypeFilter::TECHNICAL_NAME)->getId(),
                ],
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $billingCountryId,
                    'taxRate' => 9,
                    'taxRuleTypeId' => $this->taxRuleTypes->getByTechnicalName(IndividualStatesRuleTypeFilter::TECHNICAL_NAME)->getId(),
                    'data' => [
                        'states' => [$countryStateId],
                    ],
                ],
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $billingCountryId,
                    'taxRate' => 8,
                    'taxRuleTypeId' => $this->taxRuleTypes->getByTechnicalName(ZipCodeRangeRuleTypeFilter::TECHNICAL_NAME)->getId(),
                    'data' => [
                        'fromZipCode' => '12000',
                        'toZipCode' => '12999',
                    ],
                ],
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $billingCountryId,
                    'taxRate' => 7,
                    'taxRuleTypeId' => $this->taxRuleTypes->getByTechnicalName(ZipCodeRuleTypeFilter::TECHNICAL_NAME)->getId(),
                    'data' => [
                        'zipCode' => '12345',
                    ],
                ],
            ],
        ];
        $salesChannelContext = $this->createSalesChannelContext([$taxData], [SalesChannelContextService::CUSTOMER_ID => $customerId]);
        $taxRuleCollection = $salesChannelContext->buildTaxRules($taxData['id']);

        static::assertCount(1, $taxRuleCollection);
        static::assertSame(7.0, $taxRuleCollection->first()->getTaxRate());
        static::assertSame(100.0, $taxRuleCollection->first()->getPercentage());
    }

    public function testGetTaxRuleCollectionDefaultOnWrongCountry(): void
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
            'rules' => [
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $billingCountryId,
                    'taxRate' => 10,
                    'taxRuleTypeId' => $this->taxRuleTypes->getByTechnicalName(EntireCountryRuleTypeFilter::TECHNICAL_NAME)->getId(),
                ],
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $billingCountryId,
                    'taxRate' => 9,
                    'taxRuleTypeId' => $this->taxRuleTypes->getByTechnicalName(IndividualStatesRuleTypeFilter::TECHNICAL_NAME)->getId(),
                    'data' => [
                        'states' => [$countryStateId],
                    ],
                ],
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $billingCountryId,
                    'taxRate' => 8,
                    'taxRuleTypeId' => $this->taxRuleTypes->getByTechnicalName(ZipCodeRangeRuleTypeFilter::TECHNICAL_NAME)->getId(),
                    'data' => [
                        'fromZipCode' => '12000',
                        'toZipCode' => '12999',
                    ],
                ],
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $billingCountryId,
                    'taxRate' => 7,
                    'taxRuleTypeId' => $this->taxRuleTypes->getByTechnicalName(ZipCodeRuleTypeFilter::TECHNICAL_NAME)->getId(),
                    'data' => [
                        'zipCode' => '12345',
                    ],
                ],
            ],
        ];
        $salesChannelContext = $this->createSalesChannelContext([$taxData], [SalesChannelContextService::CUSTOMER_ID => $customerId]);
        $taxRuleCollection = $salesChannelContext->buildTaxRules($taxData['id']);

        static::assertCount(1, $taxRuleCollection);
        static::assertSame(15.0, $taxRuleCollection->first()->getTaxRate());
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
                    'taxRuleTypeId' => $this->taxRuleTypes->getByTechnicalName(EntireCountryRuleTypeFilter::TECHNICAL_NAME)->getId(),
                ],
            ],
        ];

        $salesChannelContext = $this->createSalesChannelContext([$taxData], [SalesChannelContextService::COUNTRY_ID => $shippingCountryId]);
        $taxRuleCollection = $salesChannelContext->buildTaxRules($taxData['id']);
        static::assertCount(1, $taxRuleCollection);
        static::assertSame(10.0, $taxRuleCollection->first()->getTaxRate());
        static::assertSame(100.0, $taxRuleCollection->first()->getPercentage());
    }

    public function testGetTaxRuleCollectionNoCustomerDefaultShippingLocationWithMultipleRule(): void
    {
        $countryIds = $this->getValidCountryIds(3);
        $shippingCountryId = $countryIds[0];
        $taxData = [
            'id' => Uuid::randomHex(),
            'taxRate' => 15,
            'name' => Uuid::randomHex(),
            'rules' => [
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $shippingCountryId,
                    'taxRate' => 10,
                    'taxRuleTypeId' => $this->taxRuleTypes->getByTechnicalName(EntireCountryRuleTypeFilter::TECHNICAL_NAME)->getId(),
                ],
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $countryIds[1],
                    'taxRate' => 9,
                    'taxRuleTypeId' => $this->taxRuleTypes->getByTechnicalName(EntireCountryRuleTypeFilter::TECHNICAL_NAME)->getId(),
                ],
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $countryIds[2],
                    'taxRate' => 8,
                    'taxRuleTypeId' => $this->taxRuleTypes->getByTechnicalName(EntireCountryRuleTypeFilter::TECHNICAL_NAME)->getId(),
                ],
            ],
        ];
        $salesChannelContext = $this->createSalesChannelContext([$taxData], [SalesChannelContextService::COUNTRY_ID => $shippingCountryId]);
        $taxRuleCollection = $salesChannelContext->buildTaxRules($taxData['id']);

        static::assertCount(1, $taxRuleCollection);
        static::assertSame(10.0, $taxRuleCollection->first()->getTaxRate());
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
                    'taxRuleTypeId' => $this->taxRuleTypes->getByTechnicalName(EntireCountryRuleTypeFilter::TECHNICAL_NAME)->getId(),
                ],
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $shippingCountryId,
                    'taxRate' => 9,
                    'taxRuleTypeId' => $this->taxRuleTypes->getByTechnicalName(IndividualStatesRuleTypeFilter::TECHNICAL_NAME)->getId(),
                    'data' => [
                        'states' => [$countryStateId],
                    ],
                ],
            ],
        ];
        $salesChannelContext = $this->createSalesChannelContext([$taxData], [SalesChannelContextService::COUNTRY_ID => $shippingCountryId, SalesChannelContextService::COUNTRY_STATE_ID => $countryStateId]);
        $taxRuleCollection = $salesChannelContext->buildTaxRules($taxData['id']);

        static::assertCount(1, $taxRuleCollection);
        static::assertSame(9.0, $taxRuleCollection->first()->getTaxRate());
        static::assertSame(100.0, $taxRuleCollection->first()->getPercentage());
    }

    public function testGetTaxRuleCollectionNoCustomerDefaultShippingLocationTypeZipCodeRangeDoesNotMatch(): void
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
                    'taxRuleTypeId' => $this->taxRuleTypes->getByTechnicalName(ZipCodeRangeRuleTypeFilter::TECHNICAL_NAME)->getId(),
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
        static::assertSame(15.0, $taxRuleCollection->first()->getTaxRate());
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
                    'taxRuleTypeId' => $this->taxRuleTypes->getByTechnicalName(ZipCodeRuleTypeFilter::TECHNICAL_NAME)->getId(),
                    'data' => [
                        'zipCode' => '12345',
                    ],
                ],
            ],
        ];
        $salesChannelContext = $this->createSalesChannelContext([$taxData], [SalesChannelContextService::COUNTRY_ID => $shippingCountryId]);
        $taxRuleCollection = $salesChannelContext->buildTaxRules($taxData['id']);

        static::assertCount(1, $taxRuleCollection);
        static::assertSame(15.0, $taxRuleCollection->first()->getTaxRate());
        static::assertSame(100.0, $taxRuleCollection->first()->getPercentage());
    }

    public function testGetTaxRuleCollectionNoCustomerDefaultShippingLocationTypeIndividualStatesOverridesOthers(): void
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
                    'taxRuleTypeId' => $this->taxRuleTypes->getByTechnicalName(EntireCountryRuleTypeFilter::TECHNICAL_NAME)->getId(),
                ],
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $shippingCountryId,
                    'taxRate' => 9,
                    'taxRuleTypeId' => $this->taxRuleTypes->getByTechnicalName(IndividualStatesRuleTypeFilter::TECHNICAL_NAME)->getId(),
                    'data' => [
                        'states' => [$countryStateId],
                    ],
                ],
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $shippingCountryId,
                    'taxRate' => 8,
                    'taxRuleTypeId' => $this->taxRuleTypes->getByTechnicalName(ZipCodeRangeRuleTypeFilter::TECHNICAL_NAME)->getId(),
                    'data' => [
                        'fromZipCode' => '12000',
                        'toZipCode' => '12999',
                    ],
                ],
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $shippingCountryId,
                    'taxRate' => 7,
                    'taxRuleTypeId' => $this->taxRuleTypes->getByTechnicalName(ZipCodeRuleTypeFilter::TECHNICAL_NAME)->getId(),
                    'data' => [
                        'zipCode' => '12345',
                    ],
                ],
            ],
        ];
        $salesChannelContext = $this->createSalesChannelContext([$taxData], [SalesChannelContextService::COUNTRY_ID => $shippingCountryId, SalesChannelContextService::COUNTRY_STATE_ID => $countryStateId]);
        $taxRuleCollection = $salesChannelContext->buildTaxRules($taxData['id']);

        static::assertCount(1, $taxRuleCollection);
        static::assertSame(9.0, $taxRuleCollection->first()->getTaxRate());
        static::assertSame(100.0, $taxRuleCollection->first()->getPercentage());
    }

    public function testGetTaxRuleCollectionNoCustomerDefaultShippingLocationDefaultOnWrongCountry(): void
    {
        $countryIds = $this->getValidCountryIds(2);
        $shippingCountryId = $countryIds[0];
        $countryStateId = $this->createCountryState($shippingCountryId);
        $taxData = [
            'id' => Uuid::randomHex(),
            'taxRate' => 15,
            'name' => Uuid::randomHex(),
            'rules' => [
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $countryIds[1],
                    'taxRate' => 10,
                    'taxRuleTypeId' => $this->taxRuleTypes->getByTechnicalName(EntireCountryRuleTypeFilter::TECHNICAL_NAME)->getId(),
                ],
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $countryIds[1],
                    'taxRate' => 9,
                    'taxRuleTypeId' => $this->taxRuleTypes->getByTechnicalName(IndividualStatesRuleTypeFilter::TECHNICAL_NAME)->getId(),
                    'data' => [
                        'states' => [$countryStateId],
                    ],
                ],
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $countryIds[1],
                    'taxRate' => 8,
                    'taxRuleTypeId' => $this->taxRuleTypes->getByTechnicalName(ZipCodeRangeRuleTypeFilter::TECHNICAL_NAME)->getId(),
                    'data' => [
                        'fromZipCode' => '12000',
                        'toZipCode' => '12999',
                    ],
                ],
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $countryIds[1],
                    'taxRate' => 7,
                    'taxRuleTypeId' => $this->taxRuleTypes->getByTechnicalName(ZipCodeRuleTypeFilter::TECHNICAL_NAME)->getId(),
                    'data' => [
                        'zipCode' => '12345',
                    ],
                ],
            ],
        ];
        $salesChannelContext = $this->createSalesChannelContext([$taxData], [SalesChannelContextService::COUNTRY_ID => $shippingCountryId, SalesChannelContextService::COUNTRY_STATE_ID => $countryStateId]);
        $taxRuleCollection = $salesChannelContext->buildTaxRules($taxData['id']);

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

    private function loadTaxRuleTypes(): EntityCollection
    {
        return $this->getContainer()->get('tax_rule_type.repository')->search(new Criteria(), Context::createDefaultContext())->getEntities();
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

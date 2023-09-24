<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\Context;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupDefinition;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodDefinition;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodDefinition;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateCollection;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateDefinition;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Core\System\Country\CountryDefinition;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\Currency\Aggregate\CurrencyCountryRounding\CurrencyCountryRoundingCollection;
use Shopware\Core\System\Currency\Aggregate\CurrencyCountryRounding\CurrencyCountryRoundingDefinition;
use Shopware\Core\System\Currency\CurrencyCollection;
use Shopware\Core\System\Currency\CurrencyDefinition;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\SalesChannel\Context\BaseContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\Tax\TaxCollection;
use Shopware\Core\System\Tax\TaxDefinition;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 *
 * @covers \Shopware\Core\System\SalesChannel\Context\BaseContextFactory
 */
#[Package('buyers-experience')]
class BaseContextFactoryTest extends TestCase
{
    /**
     * @dataProvider factoryCreationDataProvider
     *
     * @param array<string, mixed> $options
     * @param array<string, array<mixed>> $entitySearchResult
     * @param false|array<string, mixed> $fetchDataResult
     */
    public function testCreate(
        array $options,
        false|array $fetchDataResult,
        false|string $fetchParentLanguageResult,
        array $entitySearchResult,
        ?string $exceptionMessage = null
    ): void {
        if ($exceptionMessage !== null) {
            static::expectExceptionMessage($exceptionMessage);
        }

        $currencyRepository = new StaticEntityRepository([new CurrencyCollection($entitySearchResult[CurrencyDefinition::ENTITY_NAME] ?? [])]);
        $customerGroupRepository = new StaticEntityRepository([new CustomerGroupCollection($entitySearchResult[CustomerGroupDefinition::ENTITY_NAME] ?? [])]);
        $countryRepository = new StaticEntityRepository([new CountryCollection($entitySearchResult[CountryDefinition::ENTITY_NAME] ?? [])]);
        $taxRepository = new StaticEntityRepository([new TaxCollection($entitySearchResult[TaxDefinition::ENTITY_NAME] ?? [])]);
        $paymentMethodRepository = new StaticEntityRepository([new PaymentMethodCollection($entitySearchResult[PaymentMethodDefinition::ENTITY_NAME] ?? [])]);
        $shippingMethodRepository = new StaticEntityRepository([new ShippingMethodCollection($entitySearchResult[ShippingMethodDefinition::ENTITY_NAME] ?? [])]);
        $salesChannelRepository = new StaticEntityRepository([new SalesChannelCollection($entitySearchResult[SalesChannelDefinition::ENTITY_NAME] ?? [])]);
        $countryStateRepository = new StaticEntityRepository([new CountryStateCollection($entitySearchResult[CountryStateDefinition::ENTITY_NAME] ?? [])]);
        $currencyCountryRepository = new StaticEntityRepository([new CurrencyCountryRoundingCollection($entitySearchResult[CurrencyCountryRoundingDefinition::ENTITY_NAME] ?? [])]);

        $connection = $this->createMock(Connection::class);
        $connection->expects(static::once())->method('fetchAssociative')->willReturn($fetchDataResult);

        if ($fetchDataResult === false) {
            $connection->expects(static::never())->method('createQueryBuilder');
        }

        if ($fetchParentLanguageResult !== false) {
            $result = $this->createMock(Result::class);
            $result->expects(static::once())->method('fetchOne')->willReturn($fetchParentLanguageResult);
            $connection->expects(static::once())->method('executeQuery')->willReturn($result);
            $connection->expects(static::atMost(1))->method('createQueryBuilder')->willReturn(new QueryBuilder($connection));
        } else {
            $result = $this->createMock(Result::class);
            $result->expects(static::atMost(1))->method('fetchOne')->willReturn(false);
            $connection->expects(static::atMost(1))->method('executeQuery')->willReturn($result);
            $connection->expects(static::atMost(1))->method('createQueryBuilder')->willReturn(new QueryBuilder($connection));
        }

        $factory = new BaseContextFactory(
            $salesChannelRepository,
            $currencyRepository,
            $customerGroupRepository,
            $countryRepository,
            $taxRepository,
            $paymentMethodRepository,
            $shippingMethodRepository,
            $connection,
            $countryStateRepository,
            $currencyCountryRepository
        );

        $factory->create(TestDefaults::SALES_CHANNEL, $options);
    }

    /**
     * @return iterable<string, array<string, mixed>>
     */
    public static function factoryCreationDataProvider(): iterable
    {
        $invalidSalesChannelId = Uuid::randomHex();
        $paymentMethodId = Uuid::randomHex();
        $customerGroupId = Uuid::randomHex();
        $shippingMethodId = Uuid::randomHex();
        $currencyId = Uuid::randomHex();
        $countryStateId = Uuid::randomHex();
        $countryId = Uuid::randomHex();

        $salesChannelEntity = new SalesChannelEntity();
        $salesChannelEntity->setUniqueIdentifier(TestDefaults::SALES_CHANNEL);
        $salesChannelEntity->setCustomerGroupId($customerGroupId);
        $salesChannelEntity->setPaymentMethodId($paymentMethodId);
        $salesChannelEntity->setShippingMethodId($shippingMethodId);

        $currency = new CurrencyEntity();
        $rounding = new CashRoundingConfig(1, 1, true);
        $currency->setUniqueIdentifier($currencyId);
        $currency->setTotalRounding($rounding);
        $currency->setItemRounding($rounding);
        $currency->setId($currencyId);
        $currency->setFactor(1);

        $country = new CountryEntity();
        $country->setUniqueIdentifier($countryId);
        $country->setId($countryId);

        $paymentMethod = new PaymentMethodEntity();
        $paymentMethod->setUniqueIdentifier($paymentMethodId);

        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setUniqueIdentifier($shippingMethodId);
        $salesChannelEntity->setShippingMethod($shippingMethod);

        $customerGroup = new CustomerGroupEntity();
        $customerGroup->setUniqueIdentifier($customerGroupId);

        yield 'no context data' => [
            'options' => [],
            'fetchDataResult' => false,
            'fetchParentLanguageResult' => false,
            'entitySearchResult' => [],
            'exceptionMessage' => sprintf('No context data found for SalesChannel "%s"', TestDefaults::SALES_CHANNEL),
        ];

        yield 'provided language not available' => [
            'options' => [
                SalesChannelContextService::LANGUAGE_ID => $invalidSalesChannelId,
            ],
            'fetchDataResult' => [
                'sales_channel_default_language_id' => Uuid::randomBytes(),
                'sales_channel_language_ids' => Defaults::LANGUAGE_SYSTEM,
            ],
            'fetchParentLanguageResult' => false,
            'entitySearchResult' => [],
            'exceptionMessage' => sprintf('Provided language "%s" is not in list of available languages: %s', $invalidSalesChannelId, Defaults::LANGUAGE_SYSTEM),
        ];

        yield 'language id is not uuid' => [
            'options' => [
                SalesChannelContextService::LANGUAGE_ID => 'not-an-uuid',
            ],
            'fetchDataResult' => [
                'sales_channel_default_language_id' => Uuid::randomBytes(),
                'sales_channel_language_ids' => '3ebb5fe2e29a4d70aa5854ce7ce3e20b,' . Defaults::LANGUAGE_SYSTEM,
            ],
            'fetchParentLanguageResult' => false,
            'entitySearchResult' => [],
            'exceptionMessage' => 'Provided languageId is not a valid uuid',
        ];

        yield 'language id not found' => [
            'options' => [
                SalesChannelContextService::LANGUAGE_ID => '3ebb5fe2e29a4d70aa5854ce7ce3e20b',
            ],
            'fetchDataResult' => [
                'sales_channel_default_language_id' => Uuid::randomBytes(),
                'sales_channel_language_ids' => '3ebb5fe2e29a4d70aa5854ce7ce3e20b,' . Defaults::LANGUAGE_SYSTEM,
            ],
            'fetchParentLanguageResult' => false,
            'entitySearchResult' => [],
            'exceptionMessage' => 'The language "3ebb5fe2e29a4d70aa5854ce7ce3e20b" was not found.',
        ];

        yield 'sales channel not found' => [
            'options' => [
                SalesChannelContextService::LANGUAGE_ID => '3ebb5fe2e29a4d70aa5854ce7ce3e20b',
            ],
            'fetchDataResult' => [
                'sales_channel_default_language_id' => Uuid::randomBytes(),
                'sales_channel_currency_factor' => 1,
                'sales_channel_currency_id' => Uuid::randomBytes(),
                'sales_channel_language_ids' => '3ebb5fe2e29a4d70aa5854ce7ce3e20b,' . Defaults::LANGUAGE_SYSTEM,
            ],
            'fetchParentLanguageResult' => Uuid::randomHex(),
            'entitySearchResult' => [],
            'exceptionMessage' => sprintf('Sales channel with id "%s" not found or not valid!.', TestDefaults::SALES_CHANNEL),
        ];

        yield 'currency not found' => [
            'options' => [
                SalesChannelContextService::LANGUAGE_ID => Defaults::LANGUAGE_SYSTEM,
                SalesChannelContextService::CURRENCY_ID => '3ebb5fe2e29a4d70aa5854ce7ce3e20b',
            ],
            'fetchDataResult' => [
                'sales_channel_default_language_id' => Uuid::randomBytes(),
                'sales_channel_currency_factor' => 1,
                'sales_channel_currency_id' => Uuid::randomBytes(),
                'sales_channel_language_ids' => Defaults::LANGUAGE_SYSTEM,
            ],
            'fetchParentLanguageResult' => false,
            'entitySearchResult' => [
                SalesChannelDefinition::ENTITY_NAME => [
                    TestDefaults::SALES_CHANNEL => $salesChannelEntity,
                ],
            ],
            'exceptionMessage' => 'Currency with id "3ebb5fe2e29a4d70aa5854ce7ce3e20b" not found!.',
        ];

        yield 'country state not found' => [
            'options' => [
                SalesChannelContextService::LANGUAGE_ID => Defaults::LANGUAGE_SYSTEM,
                SalesChannelContextService::CURRENCY_ID => $currencyId,
                SalesChannelContextService::COUNTRY_STATE_ID => $countryStateId,
            ],
            'fetchDataResult' => [
                'sales_channel_default_language_id' => Uuid::randomBytes(),
                'sales_channel_currency_factor' => 1,
                'sales_channel_currency_id' => Uuid::randomBytes(),
                'sales_channel_language_ids' => Defaults::LANGUAGE_SYSTEM,
            ],
            'fetchParentLanguageResult' => false,
            'entitySearchResult' => [
                SalesChannelDefinition::ENTITY_NAME => [
                    TestDefaults::SALES_CHANNEL => $salesChannelEntity,
                ],
                CurrencyDefinition::ENTITY_NAME => [
                    $currencyId => $currency,
                ],
            ],
            'exceptionMessage' => sprintf('Country state with id "%s" not found!.', $countryStateId),
        ];

        yield 'country not found' => [
            'options' => [
                SalesChannelContextService::LANGUAGE_ID => Defaults::LANGUAGE_SYSTEM,
                SalesChannelContextService::CURRENCY_ID => $currencyId,
                SalesChannelContextService::COUNTRY_ID => $countryId,
            ],
            'fetchDataResult' => [
                'sales_channel_default_language_id' => Uuid::randomBytes(),
                'sales_channel_currency_factor' => 1,
                'sales_channel_currency_id' => Uuid::randomBytes(),
                'sales_channel_language_ids' => Defaults::LANGUAGE_SYSTEM,
            ],
            'fetchParentLanguageResult' => false,
            'entitySearchResult' => [
                SalesChannelDefinition::ENTITY_NAME => [
                    TestDefaults::SALES_CHANNEL => $salesChannelEntity,
                ],
                CurrencyDefinition::ENTITY_NAME => [
                    $currencyId => $currency,
                ],
            ],
            'exceptionMessage' => sprintf('Country with id "%s" not found!.', $countryId),
        ];

        yield 'payment method not found' => [
            'options' => [
                SalesChannelContextService::LANGUAGE_ID => Defaults::LANGUAGE_SYSTEM,
                SalesChannelContextService::CURRENCY_ID => $currencyId,
                SalesChannelContextService::COUNTRY_ID => $countryId,
            ],
            'fetchDataResult' => [
                'sales_channel_default_language_id' => Uuid::randomBytes(),
                'sales_channel_currency_factor' => 1,
                'sales_channel_currency_id' => Uuid::randomBytes(),
                'sales_channel_language_ids' => Defaults::LANGUAGE_SYSTEM,
            ],
            'fetchParentLanguageResult' => false,
            'entitySearchResult' => [
                SalesChannelDefinition::ENTITY_NAME => [
                    TestDefaults::SALES_CHANNEL => $salesChannelEntity,
                ],
                CurrencyDefinition::ENTITY_NAME => [
                    $currencyId => $currency,
                ],
                CountryDefinition::ENTITY_NAME => [
                    $countryId => $country,
                ],
            ],
            'exceptionMessage' => sprintf('The payment method %s could not be found.', $paymentMethodId),
        ];

        yield 'create base context successfully' => [
            'options' => [
                SalesChannelContextService::LANGUAGE_ID => Defaults::LANGUAGE_SYSTEM,
                SalesChannelContextService::CURRENCY_ID => $currencyId,
                SalesChannelContextService::COUNTRY_ID => $countryId,
            ],
            'fetchDataResult' => [
                'sales_channel_default_language_id' => Uuid::randomBytes(),
                'sales_channel_currency_factor' => 1,
                'sales_channel_currency_id' => Uuid::randomBytes(),
                'sales_channel_language_ids' => Defaults::LANGUAGE_SYSTEM,
            ],
            'fetchParentLanguageResult' => false,
            'entitySearchResult' => [
                SalesChannelDefinition::ENTITY_NAME => [
                    TestDefaults::SALES_CHANNEL => $salesChannelEntity,
                ],
                CurrencyDefinition::ENTITY_NAME => [
                    $currencyId => $currency,
                ],
                CountryDefinition::ENTITY_NAME => [
                    $countryId => $country,
                ],
                PaymentMethodDefinition::ENTITY_NAME => [
                    $paymentMethodId => $paymentMethod,
                ],
                ShippingMethodDefinition::ENTITY_NAME => [
                    $shippingMethodId => $shippingMethod,
                ],
                CustomerGroupDefinition::ENTITY_NAME => [
                    $customerGroupId => $customerGroup,
                ],
            ],
            'exceptionMessage' => null,
        ];
    }
}

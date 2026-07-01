<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Provider;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigCollection;
use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigDefinition;
use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigEntity;
use Shopware\Core\Checkout\DocumentV2\Config\DocumentConfigLoader;
use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Checkout\DocumentV2\DocumentType;
use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentGenerationRequest;
use Shopware\Core\Checkout\DocumentV2\Provider\InvoiceDataProvider;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\TaxFreeConfig;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Core\System\Country\CountryDefinition;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(InvoiceDataProvider::class)]
class InvoiceDataProviderTest extends TestCase
{
    private const COMPANY_COUNTRY_ID = '0190a3f5cafa70f5b6e7e5b8f0c0c0c0';

    public function testGetDocumentTypes(): void
    {
        $provider = $this->createProvider();

        static::assertSame('invoice', $provider->getKey());
        static::assertSame([DocumentType::INVOICE->value], $provider->getDocumentTypes());
    }

    public function testEnrichOrderCriteria(): void
    {
        $provider = $this->createProvider();
        $criteria = new Criteria();

        $provider->enrichOrderCriteria($criteria);

        static::assertSame(
            [
                'currency',
                'language',
                'addresses',
                'orderCustomer',
                'lineItems',
                'deliveries',
                'primaryOrderTransaction',
                'primaryOrderDelivery',
                'transactions',
            ],
            \array_keys($criteria->getAssociations()),
        );

        $lineItemsSorting = $criteria->getAssociation('lineItems')->getSorting();
        static::assertCount(1, $lineItemsSorting);
        static::assertSame('position', $lineItemsSorting[0]->getField());

        $deliveriesSorting = $criteria->getAssociation('deliveries')->getSorting();
        static::assertCount(1, $deliveriesSorting);
        static::assertSame('createdAt', $deliveriesSorting[0]->getField());

        $transactions = $criteria->getAssociation('transactions');
        static::assertArrayHasKey('paymentMethod', $transactions->getAssociations());

        $transactionSorting = $transactions->getSorting();
        static::assertCount(1, $transactionSorting);
        static::assertSame('createdAt', $transactionSorting[0]->getField());

        static::assertArrayHasKey(
            'paymentMethod',
            $criteria->getAssociation('primaryOrderTransaction')->getAssociations(),
        );
        static::assertArrayHasKey(
            'shippingOrderAddress',
            $criteria->getAssociation('primaryOrderDelivery')->getAssociations(),
        );
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('provideRenderingData')]
    public function testProvideRenderingData(
        array $config,
        OrderEntity $order,
        bool $expectedIntraCommunityDelivery,
        int $vatViolationCount = 0,
    ): void {
        $provider = $this->createProvider(
            $config,
            $this->createValidatorWithViolations($vatViolationCount)
        );

        $request = new DocumentGenerationRequest(
            $order->getId(),
            $order->getVersionId() ?? Uuid::randomHex(),
            DocumentType::INVOICE,
            [DocumentFormat::PDF],
            '12345',
            documentDate: '2026-05-05T12:00:00+00:00',
        );

        $result = $provider->provideRenderingData(
            $order,
            $request,
            Context::createDefaultContext()
        );

        static::assertSame('2026-05-05T12:00:00+00:00', $result->documentDate);
        static::assertSame('12345', $result->documentNumber);
        static::assertSame('12345', $result->custom['invoiceNumber']);
        static::assertSame($expectedIntraCommunityDelivery, $result->intraCommunityDelivery);
    }

    public function testProvideRenderingDataDerivesPaymentDueDateFromDocumentDate(): void
    {
        $provider = $this->createProvider(['paymentDueDate' => '+30 days']);

        $order = self::createOrder();
        $request = new DocumentGenerationRequest(
            $order->getId(),
            $order->getVersionId() ?? Uuid::randomHex(),
            DocumentType::INVOICE,
            [DocumentFormat::PDF],
            '12345',
            documentDate: '2026-05-05T12:00:00+00:00',
        );

        $result = $provider->provideRenderingData($order, $request, Context::createDefaultContext());

        static::assertEquals(
            new \DateTimeImmutable('2026-06-04T12:00:00+00:00'),
            $result->paymentDueDate,
        );
    }

    public function testProvideRenderingDataReturnsNullPaymentDueDateForInvalidDocumentDate(): void
    {
        $provider = $this->createProvider(['paymentDueDate' => '+30 days']);

        $order = self::createOrder();
        $request = new DocumentGenerationRequest(
            $order->getId(),
            $order->getVersionId() ?? Uuid::randomHex(),
            DocumentType::INVOICE,
            [DocumentFormat::PDF],
            '12345',
            documentDate: 'not-a-date',
        );

        $result = $provider->provideRenderingData($order, $request, Context::createDefaultContext());

        static::assertNull($result->paymentDueDate);
    }

    public function testProvideRenderingDataThrowsWhenDocumentNumberMissing(): void
    {
        $provider = $this->createProvider();
        $order = self::createOrder();
        $request = new DocumentGenerationRequest(
            $order->getId(),
            $order->getVersionId() ?? Uuid::randomHex(),
            DocumentType::INVOICE,
            [DocumentFormat::PDF],
            documentNumber: null,
            documentDate: '2026-05-05T12:00:00+00:00',
        );

        static::expectExceptionObject(DocumentV2Exception::missingDocumentNumber(DocumentType::INVOICE->value));

        $provider->provideRenderingData($order, $request, Context::createDefaultContext());
    }

    public function testProvideRenderingDataResolvesDeliveryDateFromDeliveriesWhenV68IsInactive(): void
    {
        if (Feature::isActive('v6.8.0.0')) {
            static::markTestSkipped('v6.7 fallback branch is only exercised when v6.8.0.0 is inactive.');
        }

        $provider = $this->createProvider();
        $order = self::createOrder(
            country: self::createCountry(companyTaxEnabled: true, isEu: true),
            skipPrimaryDelivery: true,
        );
        $request = new DocumentGenerationRequest(
            $order->getId(),
            $order->getVersionId() ?? Uuid::randomHex(),
            DocumentType::INVOICE,
            [DocumentFormat::PDF],
            '12345',
            documentDate: '2026-05-05T12:00:00+00:00',
        );

        $result = $provider->provideRenderingData($order, $request, Context::createDefaultContext());

        static::assertEquals(new \DateTimeImmutable('2026-05-15'), $result->deliveryDate);
    }

    public function testProvideRenderingDataConvertsMutableShippingDateToImmutable(): void
    {
        $provider = $this->createProvider();
        $order = self::createOrder(
            country: self::createCountry(companyTaxEnabled: true, isEu: true),
            shippingDateLatest: new \DateTime('2026-05-15'),
        );
        $request = new DocumentGenerationRequest(
            $order->getId(),
            $order->getVersionId() ?? Uuid::randomHex(),
            DocumentType::INVOICE,
            [DocumentFormat::PDF],
            '12345',
            documentDate: '2026-05-05T12:00:00+00:00',
        );

        $result = $provider->provideRenderingData($order, $request, Context::createDefaultContext());

        static::assertInstanceOf(\DateTimeImmutable::class, $result->deliveryDate);
        static::assertEquals(new \DateTimeImmutable('2026-05-15'), $result->deliveryDate);
    }

    public static function provideRenderingData(): iterable
    {
        $flag = ['displayAdditionalNoteDelivery' => true];
        $business = CustomerEntity::ACCOUNT_TYPE_BUSINESS;

        $validEuCountry = self::createCountry(
            companyTaxEnabled: true,
            isEu: true
        );

        $validEuCountryNoPatternCheck = self::createCountry(
            companyTaxEnabled: true,
            isEu: true,
            checkVatIdPattern: false
        );

        yield 'intra false - displayAdditionalNoteDelivery flag not set' => [
            'config' => [],
            'order' => self::createOrder(),
            'expectedIntraCommunityDelivery' => false,
        ];

        yield 'intra false - displayAdditionalNoteDelivery flag explicitly false' => [
            'config' => ['displayAdditionalNoteDelivery' => false],
            'order' => self::createOrder(
                accountType: $business,
                country: $validEuCountry,
                vatIds: ['DE123456789']
            ),
            'expectedIntraCommunityDelivery' => false,
        ];

        yield 'intra false - flag set, customer is not business' => [
            'config' => $flag,
            'order' => self::createOrder(),
            'expectedIntraCommunityDelivery' => false,
        ];

        yield 'intra false - business customer, order has no deliveries' => [
            'config' => $flag,
            'order' => self::createOrder(accountType: $business),
            'expectedIntraCommunityDelivery' => false,
        ];

        yield 'intra false - delivery exists but shipping address has no country' => [
            'config' => $flag,
            'order' => self::createOrder(
                accountType: $business,
                deliveryWithoutCountry: true
            ),
            'expectedIntraCommunityDelivery' => false,
        ];

        yield 'intra false - country is non-EU' => [
            'config' => $flag,
            'order' => self::createOrder(
                accountType: $business,
                country: self::createCountry(
                    companyTaxEnabled: true,
                    isEu: false
                ),
            ),
            'expectedIntraCommunityDelivery' => false,
        ];

        yield 'intra false - EU country but companyTax disabled' => [
            'config' => $flag,
            'order' => self::createOrder(
                accountType: $business,
                country: self::createCountry(
                    companyTaxEnabled: false,
                    isEu: true
                ),
            ),
            'expectedIntraCommunityDelivery' => false,
        ];

        yield 'intra true - country has checkVatIdPattern disabled, validator skipped' => [
            'config' => $flag,
            'order' => self::createOrder(
                accountType: $business,
                country: $validEuCountryNoPatternCheck,
            ),
            'expectedIntraCommunityDelivery' => true,
        ];

        yield 'intra false - all preconditions met but customer has no vatIds' => [
            'config' => $flag,
            'order' => self::createOrder(
                accountType: $business,
                country: $validEuCountry
            ),
            'expectedIntraCommunityDelivery' => false,
        ];

        yield 'intra false - vatIds present but validator finds violations' => [
            'config' => $flag,
            'order' => self::createOrder(
                accountType: $business,
                country: $validEuCountry,
                vatIds: ['INVALID']
            ),
            'expectedIntraCommunityDelivery' => false,
            'vatViolationCount' => 1,
        ];

        yield 'intra true - all preconditions met and vatId validates cleanly' => [
            'config' => $flag,
            'order' => self::createOrder(
                accountType: $business,
                country: $validEuCountry,
                vatIds: ['DE123456789']
            ),
            'expectedIntraCommunityDelivery' => true,
            'vatViolationCount' => 0,
        ];
    }

    /**
     * @param array<string, mixed> $config
     */
    private function createProvider(
        array $config = [],
        ?ValidatorInterface $validator = null
    ): InvoiceDataProvider {
        $companyCountry = new CountryEntity();
        $companyCountry->setUniqueIdentifier(self::COMPANY_COUNTRY_ID);
        $companyCountry->setId(self::COMPANY_COUNTRY_ID);

        /** @var StaticEntityRepository<CountryCollection> $countryRepository */
        $countryRepository = new StaticEntityRepository(
            [new CountryCollection([$companyCountry])],
            new CountryDefinition(),
        );

        /** @var StaticEntityRepository<DocumentBaseConfigCollection> $documentConfigRepository */
        $documentConfigRepository = new StaticEntityRepository(
            [new DocumentBaseConfigCollection([
                $this->createBaseConfig($config),
            ])],
            new DocumentBaseConfigDefinition(),
        );

        $configLoader = new DocumentConfigLoader(
            $documentConfigRepository,
            $countryRepository,
        );

        return new InvoiceDataProvider(
            $configLoader,
            $validator ?? $this->createMock(ValidatorInterface::class),
        );
    }

    /**
     * @param array<string, mixed> $config
     */
    private function createBaseConfig(array $config): DocumentBaseConfigEntity
    {
        $entity = new DocumentBaseConfigEntity();
        $entity->setUniqueIdentifier(Uuid::randomHex());
        $entity->setId(Uuid::randomHex());
        $entity->setGlobal(true);
        $entity->setPageSize('A4');
        $entity->setPageOrientation('portrait');
        $entity->setItemsPerPage(10);
        $entity->setConfig([
            'companyName' => 'Example',
            'companyStreet' => 'Example Street 1',
            'companyZipcode' => '12345',
            'companyCity' => 'Example City',
            'companyCountryId' => self::COMPANY_COUNTRY_ID,
            ...$config,
        ]);

        return $entity;
    }

    private function createValidatorWithViolations(int $count): ValidatorInterface
    {
        $violations = [];

        for ($i = 0; $i < $count; ++$i) {
            $violations[] = new ConstraintViolation(
                'invalid vat id',
                null,
                [],
                null,
                null,
                null
            );
        }

        $validator = $this->createMock(ValidatorInterface::class);
        $validator->method('validate')
            ->willReturn(new ConstraintViolationList($violations));

        return $validator;
    }

    /**
     * @param list<string>|null $vatIds
     */
    private static function createOrder(
        ?string $accountType = null,
        ?CountryEntity $country = null,
        ?array $vatIds = null,
        bool $deliveryWithoutCountry = false,
        ?\DateTimeInterface $shippingDateLatest = null,
        bool $skipPrimaryDelivery = false,
    ): OrderEntity {
        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());
        $order->setVersionId(Uuid::randomHex());
        $order->setSalesChannelId(Uuid::randomHex());
        $billingAddressId = Uuid::randomHex();
        $order->setBillingAddressId($billingAddressId);
        $order->setLineItems(new OrderLineItemCollection());
        $order->setPrice(new CartPrice(0.0, 0.0, 0.0, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_NET));
        $order->setAmountTotal(0.0);
        $order->setAmountNet(0.0);

        $currency = new CurrencyEntity();
        $currency->setUniqueIdentifier(Uuid::randomHex());
        $currency->setIsoCode('EUR');
        $order->setCurrency($currency);

        $billingCountry = new CountryEntity();
        $billingCountry->setUniqueIdentifier(Uuid::randomHex());
        $billingCountry->setIso('DE');

        $billingAddress = new OrderAddressEntity();
        $billingAddress->setUniqueIdentifier($billingAddressId);
        $billingAddress->setId($billingAddressId);
        $billingAddress->setCountry($billingCountry);
        $billingAddress->setStreet('');
        $billingAddress->setZipcode('');
        $billingAddress->setCity('');
        $order->setBillingAddress($billingAddress);

        $orderCustomer = new OrderCustomerEntity();
        $orderCustomer->setUniqueIdentifier(Uuid::randomHex());
        $orderCustomer->setFirstName('Max');
        $orderCustomer->setLastName('Mustermann');
        $orderCustomer->setEmail('');
        $orderCustomer->setCustomerNumber('');

        if ($accountType !== null) {
            $customer = new CustomerEntity();
            $customer->setAccountType($accountType);
            $orderCustomer->setCustomer($customer);
        }

        if ($vatIds !== null) {
            $orderCustomer->setVatIds($vatIds);
        }

        $order->setOrderCustomer($orderCustomer);

        if ($country !== null || $deliveryWithoutCountry) {
            $address = new OrderAddressEntity();
            $address->setUniqueIdentifier(Uuid::randomHex());

            if ($country !== null) {
                $address->setCountry($country);
            }

            $delivery = new OrderDeliveryEntity();
            $delivery->setUniqueIdentifier(Uuid::randomHex());
            $delivery->setShippingOrderAddress($address);
            $delivery->setShippingDateLatest($shippingDateLatest ?? new \DateTimeImmutable('2026-05-15'));
            $delivery->setShippingCosts(new CalculatedPrice(0.0, 0.0, new CalculatedTaxCollection(), new TaxRuleCollection()));

            $order->setDeliveries(new OrderDeliveryCollection([$delivery]));

            if (!$skipPrimaryDelivery) {
                $order->setPrimaryOrderDelivery($delivery);
            }
        }

        return $order;
    }

    private static function createCountry(
        bool $companyTaxEnabled,
        bool $isEu,
        bool $checkVatIdPattern = true,
    ): CountryEntity {
        $country = new CountryEntity();
        $country->setId(Uuid::randomHex());
        $country->setIsEu($isEu);
        $country->setCheckVatIdPattern($checkVatIdPattern);
        $country->setCompanyTax(new TaxFreeConfig(enabled: $companyTaxEnabled));

        return $country;
    }
}

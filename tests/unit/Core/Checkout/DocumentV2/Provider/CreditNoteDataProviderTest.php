<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Provider;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigCollection;
use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigDefinition;
use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigEntity;
use Shopware\Core\Checkout\DocumentV2\Config\DocumentConfigLoader;
use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Checkout\DocumentV2\DocumentType;
use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentGenerationRequest;
use Shopware\Core\Checkout\DocumentV2\Provider\CreditNoteDataProvider;
use Shopware\Core\Checkout\DocumentV2\Service\CreditItemResolver;
use Shopware\Core\Checkout\DocumentV2\Service\InvoiceRenderDataFactory;
use Shopware\Core\Checkout\DocumentV2\Service\ReferenceInvoiceLoader;
use Shopware\Core\Checkout\DocumentV2\Template\Enum\TypeCode;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Core\System\Country\CountryDefinition;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\Doctrine\FakeQueryBuilder;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(CreditNoteDataProvider::class)]
class CreditNoteDataProviderTest extends TestCase
{
    private const COMPANY_COUNTRY_ID = '0190a3f5cafa70f5b6e7e5b8f0c0c0c0';

    public function testKeyIsCreditNote(): void
    {
        static::assertSame('credit_note', $this->createProvider()->getKey());
    }

    #[DataProvider('supportsProvider')]
    public function testSupportsOnlyCreditNote(string $documentType, bool $expected): void
    {
        static::assertSame($expected, $this->createProvider()->supports($documentType));
    }

    /**
     * @return \Generator<string, array{string, bool}>
     */
    public static function supportsProvider(): \Generator
    {
        yield 'credit note is supported' => [DocumentType::CREDIT_NOTE->value, true];
        yield 'other core type is not supported' => [DocumentType::INVOICE->value, false];
        yield 'plugin-defined type is not supported' => ['my_plugin_document', false];
    }

    public function testEnrichOrderCriteriaDelegatesToTheInvoiceFactory(): void
    {
        $creditNoteCriteria = new Criteria();
        $this->createProvider()->enrichOrderCriteria($creditNoteCriteria);

        $invoiceCriteria = new Criteria();
        $this->createInvoiceRenderDataFactory()->enrichOrderCriteria($invoiceCriteria);

        static::assertSame(
            \array_keys($invoiceCriteria->getAssociations()),
            \array_keys($creditNoteCriteria->getAssociations()),
        );
    }

    public function testProvideRenderingDataBuildsACreditNoteReferencingTheInvoice(): void
    {
        $invoiceId = Uuid::randomHex();
        $provider = $this->createProvider(rows: [$this->invoiceRow($invoiceId, documentNumber: '1000')]);

        $order = self::createOrder();
        $data = $provider->provideRenderingData($order, $this->buildRequest($order), Context::createDefaultContext());

        static::assertSame(TypeCode::CREDIT_NOTE, $data->typeCode);
        static::assertSame('3000', $data->custom['creditNoteNumber']);
        static::assertSame('1000', $data->custom['invoiceNumber']);
        static::assertSame($invoiceId, $data->getReferencedDocumentId());

        static::assertCount(1, $data->lineItems);
        static::assertGreaterThan(0, $data->monetarySummation->grandTotal);
    }

    public function testThrowsWhenNoReferenceInvoiceExists(): void
    {
        $provider = $this->createProvider();
        $order = self::createOrder();

        $this->expectExceptionObject(DocumentV2Exception::referencedInvoiceNotFound($order->getId()));

        $provider->provideRenderingData($order, $this->buildRequest($order), Context::createDefaultContext());
    }

    public function testThrowsWhenTheOrderHasNoCreditItems(): void
    {
        $provider = $this->createProvider(rows: [$this->invoiceRow(Uuid::randomHex(), documentNumber: '1000')]);
        $order = self::createOrder(withCreditItem: false);

        $this->expectExceptionObject(DocumentV2Exception::noCreditItems($order->getId()));

        $provider->provideRenderingData($order, $this->buildRequest($order), Context::createDefaultContext());
    }

    /**
     * @param list<array<string, string>> $rows
     */
    private function createProvider(array $rows = []): CreditNoteDataProvider
    {
        return new CreditNoteDataProvider(
            $this->createInvoiceRenderDataFactory(),
            $this->createReferenceInvoiceLoader($rows),
            $this->createCreditItemResolver(),
        );
    }

    private function createCreditItemResolver(): CreditItemResolver
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchFirstColumn')->willReturn([]);

        return new CreditItemResolver($connection);
    }

    private function createInvoiceRenderDataFactory(): InvoiceRenderDataFactory
    {
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
            [new DocumentBaseConfigCollection([$this->createBaseConfig()])],
            new DocumentBaseConfigDefinition(),
        );

        /** @var StaticEntityRepository<MediaCollection> $mediaRepository */
        $mediaRepository = new StaticEntityRepository([new MediaCollection()], new MediaDefinition());

        $configLoader = new DocumentConfigLoader(
            $documentConfigRepository,
            $countryRepository,
            $mediaRepository,
            static::createStub(SystemConfigService::class),
        );

        return new InvoiceRenderDataFactory(
            $configLoader,
            static::createStub(ValidatorInterface::class),
        );
    }

    /**
     * @param list<array<string, string>> $rows
     */
    private function createReferenceInvoiceLoader(array $rows): ReferenceInvoiceLoader
    {
        $connection = static::createStub(Connection::class);
        $connection->method('createQueryBuilder')->willReturn(new FakeQueryBuilder($connection, $rows));

        return new ReferenceInvoiceLoader($connection);
    }

    /**
     * @return array<string, string>
     */
    private function invoiceRow(string $id, string $documentNumber, string $config = '{}'): array
    {
        return [
            'id' => $id,
            'orderId' => Uuid::randomHex(),
            'orderVersionId' => Defaults::LIVE_VERSION,
            'versionId' => Defaults::LIVE_VERSION,
            'deepLinkCode' => '',
            'config' => $config,
            'documentNumber' => $documentNumber,
        ];
    }

    private function buildRequest(OrderEntity $order): DocumentGenerationRequest
    {
        return new DocumentGenerationRequest(
            orderId: $order->getId(),
            orderVersionId: $order->getVersionId() ?? Uuid::randomHex(),
            documentType: DocumentType::CREDIT_NOTE,
            requestedFormats: [DocumentFormat::ZUGFERD_XML],
            documentNumber: '3000',
            documentDate: '2026-05-05T12:00:00+00:00',
        );
    }

    private function createBaseConfig(): DocumentBaseConfigEntity
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
        ]);

        return $entity;
    }

    private static function createOrder(bool $withCreditItem = true): OrderEntity
    {
        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());
        $order->setVersionId(Uuid::randomHex());
        $order->setSalesChannelId(Uuid::randomHex());

        $billingAddressId = Uuid::randomHex();
        $order->setBillingAddressId($billingAddressId);
        $order->setAmountTotal(119.0);
        $order->setAmountNet(100.0);
        $order->setShippingTotal(0.0);
        $order->setPrice(new CartPrice(
            100.0,
            119.0,
            100.0,
            new CalculatedTaxCollection([new CalculatedTax(19.0, 19.0, 100.0)]),
            new TaxRuleCollection(),
            CartPrice::TAX_STATE_NET,
        ));

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
        $order->setOrderCustomer($orderCustomer);

        $lineItems = [self::createProductItem()];

        if ($withCreditItem) {
            $lineItems[] = self::createCreditItem();
        }

        $order->setLineItems(new OrderLineItemCollection($lineItems));

        return $order;
    }

    private static function createProductItem(): OrderLineItemEntity
    {
        $lineItem = new OrderLineItemEntity();
        $lineItem->setUniqueIdentifier(Uuid::randomHex());
        $lineItem->setId(Uuid::randomHex());
        $lineItem->setType(LineItem::PRODUCT_LINE_ITEM_TYPE);
        $lineItem->setIdentifier('product-1');
        $lineItem->setLabel('Product 1');
        $lineItem->setPosition(1);
        $lineItem->setQuantity(2);
        $lineItem->setUnitPrice(50.0);
        $lineItem->setTotalPrice(100.0);
        $lineItem->setPrice(new CalculatedPrice(
            50.0,
            100.0,
            new CalculatedTaxCollection([new CalculatedTax(19.0, 19.0, 100.0)]),
            new TaxRuleCollection(),
            2,
        ));

        return $lineItem;
    }

    private static function createCreditItem(): OrderLineItemEntity
    {
        $lineItem = new OrderLineItemEntity();
        $lineItem->setUniqueIdentifier(Uuid::randomHex());
        $lineItem->setId(Uuid::randomHex());
        $lineItem->setType(LineItem::CREDIT_LINE_ITEM_TYPE);
        $lineItem->setIdentifier('credit-1');
        $lineItem->setLabel('Voucher');
        $lineItem->setPosition(2);
        $lineItem->setQuantity(1);
        $lineItem->setUnitPrice(-10.0);
        $lineItem->setTotalPrice(-10.0);
        $lineItem->setPrice(new CalculatedPrice(
            -10.0,
            -10.0,
            new CalculatedTaxCollection([new CalculatedTax(-1.9, 19.0, -10.0)]),
            new TaxRuleCollection(),
            1,
        ));

        return $lineItem;
    }
}

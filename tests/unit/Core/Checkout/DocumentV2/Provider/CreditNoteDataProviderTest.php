<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Provider;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
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
use Shopware\Core\Checkout\DocumentV2\Provider\InvoiceDataProvider;
use Shopware\Core\Checkout\DocumentV2\Service\CreditItemResolver;
use Shopware\Core\Checkout\DocumentV2\Struct\ProviderInput;
use Shopware\Core\Checkout\DocumentV2\Struct\ReferencedDocument;
use Shopware\Core\Checkout\DocumentV2\Template\Enum\TypeCode;
use Shopware\Core\Checkout\DocumentV2\Type\CreditNoteDocumentType;
use Shopware\Core\Checkout\DocumentV2\Type\DocumentTypeRegistry;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Framework\App\Feature\AppFeatureStorage;
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

    public function testSupportsCreditNote(): void
    {
        static::assertTrue($this->createProvider()->supports(DocumentType::CREDIT_NOTE->value));
    }

    public function testEnrichOrderCriteriaDelegatesToInvoiceProvider(): void
    {
        $creditNoteCriteria = new Criteria();
        $this->createProvider()->enrichOrderCriteria($creditNoteCriteria);

        $invoiceCriteria = new Criteria();
        $this->createInvoiceDataProvider()->enrichOrderCriteria($invoiceCriteria);

        static::assertSame(
            \array_keys($invoiceCriteria->getAssociations()),
            \array_keys($creditNoteCriteria->getAssociations()),
        );
    }

    public function testProvideRenderingDataBillsTheCreditItemsAsPositivePositions(): void
    {
        $order = self::createOrder(withCredit: true);
        $input = new ProviderInput(
            $order,
            $this->buildRequest($order),
            new ReferencedDocument(
                id: Uuid::randomHex(),
                documentNumber: '1000',
                orderVersionId: Uuid::randomHex(),
            ),
        );

        $data = $this->createProvider()->provideRenderingData($input, Context::createDefaultContext());

        static::assertSame(TypeCode::CREDIT_NOTE, $data->typeCode);
        static::assertSame('2000', $data->custom['creditNoteNumber']);
        static::assertSame('1000', $data->custom['invoiceNumber']);

        static::assertCount(1, $data->lineItems);
        static::assertGreaterThan(0, $data->lineItems[0]->lineTotal);
        static::assertGreaterThan(0, $data->monetarySummation->grandTotal);
    }

    public function testProvideRenderingDataDropsTheInvoiceAllowanceChargesToAvoidDoubleCounting(): void
    {
        $order = self::createOrder(withCredit: true);
        $input = new ProviderInput(
            $order,
            $this->buildRequest($order),
            new ReferencedDocument(
                id: Uuid::randomHex(),
                documentNumber: '1000',
                orderVersionId: Uuid::randomHex(),
            ),
        );

        $data = $this->createProvider()->provideRenderingData($input, Context::createDefaultContext());

        static::assertSame([], $data->allowanceCharges);
    }

    public function testProvideRenderingDataThrowsWhenTheOrderHasNoCreditItems(): void
    {
        $order = self::createOrder(withCredit: false);
        $input = new ProviderInput(
            $order,
            $this->buildRequest($order),
            new ReferencedDocument(
                id: Uuid::randomHex(),
                documentNumber: '1000',
                orderVersionId: Uuid::randomHex(),
            ),
        );

        $this->expectExceptionObject(DocumentV2Exception::noCreditLineItems($order->getId()));

        $this->createProvider()->provideRenderingData($input, Context::createDefaultContext());
    }

    private function createProvider(): CreditNoteDataProvider
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchFirstColumn')->willReturn([]);

        return new CreditNoteDataProvider(
            $this->createInvoiceDataProvider(),
            new CreditItemResolver($connection),
        );
    }

    private function createInvoiceDataProvider(): InvoiceDataProvider
    {
        $companyCountry = new CountryEntity();
        $companyCountry->setUniqueIdentifier(self::COMPANY_COUNTRY_ID);
        $companyCountry->setId(self::COMPANY_COUNTRY_ID);

        $countryRepository = new StaticEntityRepository(
            [new CountryCollection([$companyCountry])],
            new CountryDefinition(),
        );

        $documentConfigRepository = new StaticEntityRepository(
            [new DocumentBaseConfigCollection([$this->createBaseConfig()])],
            new DocumentBaseConfigDefinition(),
        );

        $mediaRepository = new StaticEntityRepository([new MediaCollection()], new MediaDefinition());

        $storage = static::createStub(AppFeatureStorage::class);
        $storage->method('forActiveApps')->willReturn([]);
        $documentTypeRegistry = new DocumentTypeRegistry([new CreditNoteDocumentType()], $storage);

        $configLoader = new DocumentConfigLoader(
            $documentConfigRepository,
            $countryRepository,
            $mediaRepository,
            static::createStub(SystemConfigService::class),
            $documentTypeRegistry,
        );

        return new InvoiceDataProvider(
            $configLoader,
            $documentTypeRegistry,
            static::createStub(ValidatorInterface::class),
        );
    }

    private function buildRequest(OrderEntity $order): DocumentGenerationRequest
    {
        return new DocumentGenerationRequest(
            $order->getId(),
            DocumentType::CREDIT_NOTE,
            [DocumentFormat::ZUGFERD_XML],
            '2000',
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

    private static function createOrder(bool $withCredit): OrderEntity
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

        $lineItems = [];

        if ($withCredit) {
            $lineItems[] = self::createCreditItem();
        } else {
            $lineItems[] = self::createProductItem();
        }

        $order->setLineItems(new OrderLineItemCollection($lineItems));

        return $order;
    }

    private static function createCreditItem(): OrderLineItemEntity
    {
        $item = new OrderLineItemEntity();
        $item->setUniqueIdentifier(Uuid::randomHex());
        $item->setId(Uuid::randomHex());
        $item->setType(LineItem::CREDIT_LINE_ITEM_TYPE);
        $item->setIdentifier('credit-1');
        $item->setLabel('Refund');
        $item->setPosition(1);
        $item->setQuantity(1);
        $item->setUnitPrice(-30.0);
        $item->setTotalPrice(-30.0);
        $item->setPrice(new CalculatedPrice(
            -30.0,
            -30.0,
            new CalculatedTaxCollection([new CalculatedTax(-5.7, 19.0, -30.0)]),
            new TaxRuleCollection(),
            1,
        ));

        return $item;
    }

    private static function createProductItem(): OrderLineItemEntity
    {
        $item = new OrderLineItemEntity();
        $item->setUniqueIdentifier(Uuid::randomHex());
        $item->setId(Uuid::randomHex());
        $item->setType(LineItem::PRODUCT_LINE_ITEM_TYPE);
        $item->setIdentifier('product-1');
        $item->setLabel('Product 1');
        $item->setPosition(1);
        $item->setQuantity(1);
        $item->setUnitPrice(100.0);
        $item->setTotalPrice(100.0);
        $item->setPrice(new CalculatedPrice(
            100.0,
            100.0,
            new CalculatedTaxCollection([new CalculatedTax(19.0, 19.0, 100.0)]),
            new TaxRuleCollection(),
            1,
        ));

        return $item;
    }
}

<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Provider;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigCollection;
use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigDefinition;
use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigEntity;
use Shopware\Core\Checkout\DocumentV2\Config\DocumentConfigLoader;
use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Checkout\DocumentV2\DocumentType;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentGenerationRequest;
use Shopware\Core\Checkout\DocumentV2\Provider\InvoiceDataProvider;
use Shopware\Core\Checkout\DocumentV2\Service\InvoiceRenderDataFactory;
use Shopware\Core\Checkout\DocumentV2\Template\Enum\TypeCode;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaDefinition;
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
#[CoversClass(InvoiceDataProvider::class)]
class InvoiceDataProviderTest extends TestCase
{
    private const COMPANY_COUNTRY_ID = '0190a3f5cafa70f5b6e7e5b8f0c0c0c0';

    public function testKeyIsInvoice(): void
    {
        static::assertSame('invoice', $this->createProvider()->getKey());
    }

    #[DataProvider('supportsProvider')]
    public function testSupportsOnlyInvoice(string $documentType, bool $expected): void
    {
        static::assertSame($expected, $this->createProvider()->supports($documentType));
    }

    /**
     * @return \Generator<string, array{string, bool}>
     */
    public static function supportsProvider(): \Generator
    {
        yield 'invoice is supported' => [DocumentType::INVOICE->value, true];
        yield 'other core type is not supported' => [DocumentType::CREDIT_NOTE->value, false];
        yield 'plugin-defined type is not supported' => ['my_plugin_document', false];
    }

    public function testEnrichOrderCriteriaDelegatesToTheFactory(): void
    {
        $criteria = new Criteria();
        $this->createProvider()->enrichOrderCriteria($criteria);

        $expected = new Criteria();
        $this->createFactory()->enrichOrderCriteria($expected);

        static::assertSame(
            \array_keys($expected->getAssociations()),
            \array_keys($criteria->getAssociations()),
        );
    }

    public function testProvideRenderingDataDelegatesToTheFactory(): void
    {
        $order = self::createOrder();
        $request = new DocumentGenerationRequest(
            $order->getId(),
            $order->getVersionId() ?? Uuid::randomHex(),
            DocumentType::INVOICE,
            [DocumentFormat::PDF],
            '12345',
            documentDate: '2026-05-05T12:00:00+00:00',
        );

        $result = $this->createProvider()->provideRenderingData($order, $request, Context::createDefaultContext());

        static::assertSame(TypeCode::INVOICE, $result->typeCode);
        static::assertSame('12345', $result->custom['invoiceNumber']);
    }

    private function createProvider(): InvoiceDataProvider
    {
        return new InvoiceDataProvider($this->createFactory());
    }

    private function createFactory(): InvoiceRenderDataFactory
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

    private static function createOrder(): OrderEntity
    {
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
        $order->setOrderCustomer($orderCustomer);

        return $order;
    }
}

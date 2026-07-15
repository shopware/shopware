<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Provider;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigCollection;
use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigDefinition;
use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigEntity;
use Shopware\Core\Checkout\DocumentV2\Config\DocumentConfigLoader;
use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Checkout\DocumentV2\DocumentType;
use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentGenerationRequest;
use Shopware\Core\Checkout\DocumentV2\Provider\DeliveryNoteDataProvider;
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
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(DeliveryNoteDataProvider::class)]
class DeliveryNoteDataProviderTest extends TestCase
{
    private const COMPANY_COUNTRY_ID = '0190a3f5cafa70f5b6e7e5b8f0c0c0c0';

    private const DOCUMENT_DATE = '2026-05-05T12:00:00+00:00';

    private const DELIVERY_DATE = '2026-05-08T09:30:00+00:00';

    public function testGetDocumentTypes(): void
    {
        $provider = $this->createProvider();

        static::assertSame('delivery_note', $provider->getKey());
        static::assertSame([DocumentType::DELIVERY_NOTE->value], $provider->getDocumentTypes());
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
                'transactions',
            ],
            \array_keys($criteria->getAssociations()),
        );

        static::assertArrayHasKey('locale', $criteria->getAssociation('language')->getAssociations());
        static::assertSame(
            ['country', 'salutation', 'countryState'],
            \array_keys($criteria->getAssociation('addresses')->getAssociations()),
        );
        static::assertArrayHasKey('shippingMethod', $criteria->getAssociation('deliveries')->getAssociations());
        static::assertArrayHasKey(
            'paymentMethod',
            $criteria->getAssociation('primaryOrderTransaction')->getAssociations(),
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
    }

    public function testProvideRenderingData(): void
    {
        $provider = $this->createProvider();
        $order = self::createOrder();

        $request = new DocumentGenerationRequest(
            $order->getId(),
            $order->getVersionId() ?? Uuid::randomHex(),
            DocumentType::DELIVERY_NOTE,
            [DocumentFormat::PDF],
            '12345',
            documentComment: 'ship it',
            documentDate: self::DOCUMENT_DATE,
            deliveryDate: self::DELIVERY_DATE,
        );

        $result = $provider->provideRenderingData($order, $request, Context::createDefaultContext());

        static::assertSame(self::DOCUMENT_DATE, $result->documentDate);
        static::assertSame('12345', $result->documentNumber);
        static::assertSame('ship it', $result->documentComment);
    }

    public function testProvideRenderingDataUsesRequestDeliveryDateAndDocumentDateForNoteDate(): void
    {
        $provider = $this->createProvider();
        $order = self::createOrder();

        $request = new DocumentGenerationRequest(
            $order->getId(),
            $order->getVersionId() ?? Uuid::randomHex(),
            DocumentType::DELIVERY_NOTE,
            [DocumentFormat::PDF],
            '12345',
            documentDate: self::DOCUMENT_DATE,
            deliveryDate: self::DELIVERY_DATE,
        );

        $result = $provider->provideRenderingData($order, $request, Context::createDefaultContext());

        static::assertSame('12345', $result->custom['deliveryNoteNumber']);
        static::assertSame(self::DELIVERY_DATE, $result->custom['deliveryDate']);
        static::assertSame(self::DOCUMENT_DATE, $result->custom['deliveryNoteDate']);
    }

    public function testProvideRenderingDataThrowsWhenDocumentNumberMissing(): void
    {
        $provider = $this->createProvider();
        $order = self::createOrder();

        $request = new DocumentGenerationRequest(
            $order->getId(),
            $order->getVersionId() ?? Uuid::randomHex(),
            DocumentType::DELIVERY_NOTE,
            [DocumentFormat::PDF],
            documentNumber: null,
            documentDate: self::DOCUMENT_DATE,
            deliveryDate: self::DELIVERY_DATE,
        );

        $this->expectExceptionObject(
            DocumentV2Exception::missingDocumentNumber(DocumentType::DELIVERY_NOTE->value),
        );

        $provider->provideRenderingData($order, $request, Context::createDefaultContext());
    }

    public function testProvideRenderingDataThrowsWhenDeliveryDateMissing(): void
    {
        $provider = $this->createProvider();
        $order = self::createOrder();

        $request = new DocumentGenerationRequest(
            $order->getId(),
            $order->getVersionId() ?? Uuid::randomHex(),
            DocumentType::DELIVERY_NOTE,
            [DocumentFormat::PDF],
            '12345',
            documentDate: self::DOCUMENT_DATE,
            deliveryDate: null,
        );

        $this->expectExceptionObject(
            DocumentV2Exception::missingDeliveryDate(DocumentType::DELIVERY_NOTE->value),
        );

        $provider->provideRenderingData($order, $request, Context::createDefaultContext());
    }

    private function createProvider(): DeliveryNoteDataProvider
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

        return new DeliveryNoteDataProvider($configLoader);
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

        return $order;
    }
}

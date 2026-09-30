<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Provider;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Checkout\DocumentV2\DocumentType;
use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentGenerationRequest;
use Shopware\Core\Checkout\DocumentV2\Provider\DeliveryNoteDataProvider;
use Shopware\Core\Checkout\DocumentV2\Struct\ProviderInput;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(DeliveryNoteDataProvider::class)]
class DeliveryNoteDataProviderTest extends TestCase
{
    private const DOCUMENT_DATE = '2026-05-05T12:00:00+00:00';

    private const DELIVERY_DATE = '2026-05-08T09:30:00+00:00';

    public function testKeyIsDeliveryNote(): void
    {
        static::assertSame('delivery_note', $this->createProvider()->getKey());
    }

    public function testSupportsOnlyDeliveryNote(): void
    {
        static::assertTrue($this->createProvider()->supports(DocumentType::DELIVERY_NOTE->value));
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

    public function testProvideRenderingDataUsesRequestDeliveryDateAndDocumentDateForNoteDate(): void
    {
        $provider = $this->createProvider();
        $order = self::createOrder();

        $request = new DocumentGenerationRequest(
            $order->getId(),
            DocumentType::DELIVERY_NOTE,
            [DocumentFormat::PDF],
            '12345',
            documentDate: self::DOCUMENT_DATE,
            deliveryDate: self::DELIVERY_DATE,
        );

        $result = $provider->provideRenderingData(new ProviderInput($order, $request), Context::createDefaultContext());

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
            DocumentType::DELIVERY_NOTE,
            [DocumentFormat::PDF],
            documentNumber: null,
            documentDate: self::DOCUMENT_DATE,
            deliveryDate: self::DELIVERY_DATE,
        );

        $this->expectExceptionObject(
            DocumentV2Exception::missingDocumentNumber(DocumentType::DELIVERY_NOTE->value),
        );

        $provider->provideRenderingData(new ProviderInput($order, $request), Context::createDefaultContext());
    }

    public function testProvideRenderingDataThrowsWhenDeliveryDateMissing(): void
    {
        $provider = $this->createProvider();
        $order = self::createOrder();

        $request = new DocumentGenerationRequest(
            $order->getId(),
            DocumentType::DELIVERY_NOTE,
            [DocumentFormat::PDF],
            '12345',
            documentDate: self::DOCUMENT_DATE,
            deliveryDate: null,
        );

        $this->expectExceptionObject(
            DocumentV2Exception::missingDeliveryDate(DocumentType::DELIVERY_NOTE->value),
        );

        $provider->provideRenderingData(new ProviderInput($order, $request), Context::createDefaultContext());
    }

    private function createProvider(): DeliveryNoteDataProvider
    {
        return new DeliveryNoteDataProvider();
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

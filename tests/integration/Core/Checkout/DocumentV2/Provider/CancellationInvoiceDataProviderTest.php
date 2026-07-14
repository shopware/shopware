<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\DocumentV2\Provider;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Checkout\DocumentV2\DocumentType;
use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentGenerationRequest;
use Shopware\Core\Checkout\DocumentV2\Provider\CancellationInvoiceDataProvider;
use Shopware\Core\Checkout\DocumentV2\Template\Enum\TypeCode;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\Test\TestDefaults;
use Shopware\Tests\Integration\Core\Checkout\DocumentV2\DocumentV2Trait;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(CancellationInvoiceDataProvider::class)]
class CancellationInvoiceDataProviderTest extends TestCase
{
    use DocumentV2Trait;

    private CancellationInvoiceDataProvider $provider;

    /**
     * @var EntityRepository<OrderCollection>
     */
    private EntityRepository $orderRepository;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();

        $shippingAddressId = Uuid::randomHex();

        $this->salesChannelContext = static::getContainer()->get(SalesChannelContextFactory::class)->create(
            Uuid::randomHex(),
            TestDefaults::SALES_CHANNEL,
            [
                SalesChannelContextService::CUSTOMER_ID => $this->createCustomer(
                    ['defaultShippingAddressId' => $shippingAddressId],
                    $this->buildDemoShippingAddress($shippingAddressId),
                ),
            ],
        );

        $this->provider = static::getContainer()->get(CancellationInvoiceDataProvider::class);
        $this->orderRepository = static::getContainer()->get('order.repository');
    }

    public function testInvertsAmountsAndReferencesTheOriginalInvoice(): void
    {
        $this->seedDemoBaseConfig('storno');

        $orderId = $this->persistCart($this->applyTenPercentPromotion($this->generateDemoCartWithTaxes([19, 7])));
        $this->enrichOrderForRendering($orderId);

        $this->seedReferenceInvoice($orderId, '1000');

        $order = $this->loadOrder($orderId);
        $data = $this->provider->provideRenderingData($order, $this->buildRequest($orderId, $order), $this->context);

        static::assertSame(TypeCode::CANCELLATION_INVOICE, $data->typeCode);
        static::assertSame('2000', $data->custom['stornoNumber']);
        static::assertSame('1000', $data->custom['invoiceNumber']);

        static::assertLessThan(0, $data->monetarySummation->grandTotal);
        static::assertNotEmpty($data->lineItems);

        foreach ($data->lineItems as $lineItem) {
            static::assertLessThan(0, $lineItem->lineTotal);
            static::assertLessThan(0, $lineItem->quantity);
            static::assertGreaterThan(0, $lineItem->netUnitPrice);
        }
    }

    public function testReferencesTheSelectedInvoiceRatherThanTheLatest(): void
    {
        $this->seedDemoBaseConfig('storno');

        $orderId = $this->persistCart($this->generateDemoCartWithTaxes([19]));
        $this->enrichOrderForRendering($orderId);

        $selectedInvoiceId = $this->seedReferenceInvoice($orderId, '1000');
        $this->seedReferenceInvoice($orderId, '1001');

        $order = $this->loadOrder($orderId);
        $request = $this->buildRequest($orderId, $order, referencedDocumentId: $selectedInvoiceId);

        $data = $this->provider->provideRenderingData($order, $request, $this->context);

        static::assertSame('1000', $data->custom['invoiceNumber']);
    }

    public function testThrowsWhenNoReferenceInvoiceExists(): void
    {
        $orderId = $this->persistCart($this->generateDemoCartWithTaxes([19]));
        $this->enrichOrderForRendering($orderId);

        $order = $this->loadOrder($orderId);

        $this->expectExceptionObject(DocumentV2Exception::referencedInvoiceNotFound($orderId));

        $this->provider->provideRenderingData($order, $this->buildRequest($orderId, $order), $this->context);
    }

    public function testThrowsWhenReferencedInvoiceHasNoNumber(): void
    {
        $orderId = $this->persistCart($this->generateDemoCartWithTaxes([19]));
        $this->enrichOrderForRendering($orderId);
        $this->seedReferenceInvoice($orderId, null);

        $order = $this->loadOrder($orderId);

        $this->expectExceptionObject(DocumentV2Exception::referencedInvoiceNumberMissing($orderId));

        $this->provider->provideRenderingData($order, $this->buildRequest($orderId, $order), $this->context);
    }

    private function loadOrder(string $orderId): OrderEntity
    {
        $criteria = new Criteria([$orderId]);
        $this->provider->enrichOrderCriteria($criteria);

        $order = $this->orderRepository->search($criteria, $this->context)->getEntities()->first();
        static::assertInstanceOf(OrderEntity::class, $order);

        return $order;
    }

    private function buildRequest(
        string $orderId,
        OrderEntity $order,
        ?string $referencedDocumentId = null,
    ): DocumentGenerationRequest {
        return new DocumentGenerationRequest(
            $orderId,
            $order->getVersionId() ?? Uuid::randomHex(),
            DocumentType::CANCELLATION_INVOICE,
            [DocumentFormat::ZUGFERD_XML],
            '2000',
            documentDate: self::DOCUMENT_DATE,
            referencedDocumentId: $referencedDocumentId,
        );
    }
}

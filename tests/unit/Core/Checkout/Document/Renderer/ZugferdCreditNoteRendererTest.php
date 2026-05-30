<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Document\Renderer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Document\DocumentException;
use Shopware\Core\Checkout\Document\FileGenerator\FileTypes;
use Shopware\Core\Checkout\Document\Renderer\DocumentRendererConfig;
use Shopware\Core\Checkout\Document\Renderer\ZugferdCreditNoteRenderer;
use Shopware\Core\Checkout\Document\Service\DocumentConfigLoader;
use Shopware\Core\Checkout\Document\Service\ReferenceInvoiceLoader;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Checkout\Document\Zugferd\ZugferdBuilder;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Shopware\Core\Test\Stub\Doctrine\FakeQueryBuilder;
use Symfony\Component\Clock\NativeClock;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(ZugferdCreditNoteRenderer::class)]
class ZugferdCreditNoteRendererTest extends TestCase
{
    private const ORDER_ID = '0192b305fddb7347be83a311a82f0649';

    private const INVOICE_ID = '01995837666372fc8eb01ca3aa815ee2';

    /**
     * @param array<int, string> $creditItemIds
     * @param array<int, string> $invoicedCreditIds
     * @param array<int, string> $creditNoteCreditIds
     */
    #[DataProvider('creditNoteDataProvider')]
    public function testRender(
        array $creditItemIds,
        array $invoicedCreditIds,
        array $creditNoteCreditIds,
    ): void {
        $context = Context::createDefaultContext();
        $order = $this->addCreditItemsToOrder($this->createOrder(), $creditItemIds);

        $builder = $this->createMock(ZugferdBuilder::class);
        $builder->expects($this->once())
            ->method('buildDocumentWithType')
            ->willReturn('<?xml version="1.0" encoding="UTF-8"?>');

        $renderer = $this->createRenderer(
            $this->createOrderSearchResult($order, $context),
            $invoicedCreditIds,
            $creditNoteCreditIds,
            $builder,
        );

        $operation = new DocumentGenerateOperation(
            self::ORDER_ID,
            FileTypes::XML,
            ['documentNumber' => '1001'],
            Uuid::randomHex(),
        );

        $result = $renderer->render(
            [self::ORDER_ID => $operation],
            $context,
            new DocumentRendererConfig()
        );

        static::assertCount(0, $result->getErrors());

        $rendered = $result->getOrderSuccess(self::ORDER_ID);

        static::assertNotNull($rendered);
        static::assertSame(FileTypes::XML, $rendered->getFileExtension());
        static::assertSame(FileTypes::XML_CONTENT_TYPE, $rendered->getContentType());
        static::assertStringStartsWith('<?xml', $rendered->getContent());
        static::assertSame(self::INVOICE_ID, $operation->getReferencedDocumentId());
    }

    public static function creditNoteDataProvider(): \Generator
    {
        $creditItemId1 = '01995c65601a705fbf9309e7554fdc95';
        $creditItemId2 = '01995c65601a705fbf9309e754dc73b0';
        $creditItemId3 = '01995c81659970f999aa3c8d21a3de3b';

        yield 'filters out no credit items' => [
            'creditItemIds' => [$creditItemId1],
            'invoicedCreditIds' => [],
            'creditNoteCreditIds' => [],
        ];

        yield 'filters out invoiced credit items' => [
            'creditItemIds' => [$creditItemId1, $creditItemId2],
            'invoicedCreditIds' => [$creditItemId1],
            'creditNoteCreditIds' => [],
        ];

        yield 'filters out already credited items in credit notes' => [
            'creditItemIds' => [$creditItemId1, $creditItemId2],
            'invoicedCreditIds' => [],
            'creditNoteCreditIds' => [$creditItemId1],
        ];

        yield 'filters out both invoiced and already credited items' => [
            'creditItemIds' => [$creditItemId1, $creditItemId2, $creditItemId3],
            'invoicedCreditIds' => [$creditItemId3],
            'creditNoteCreditIds' => [$creditItemId1],
        ];
    }

    public function testRenderThrowsErrorWhenNoInvoiceExists(): void
    {
        $context = Context::createDefaultContext();

        $renderer = $this->createRenderer(
            $this->createOrderSearchResult($this->createOrder(), $context),
            hasInvoice: false,
        );

        $result = $renderer->render(
            [self::ORDER_ID => new DocumentGenerateOperation(self::ORDER_ID)],
            $context,
            new DocumentRendererConfig()
        );

        static::assertCount(0, $result->getSuccess());

        $error = $result->getOrderError(self::ORDER_ID);

        static::assertInstanceOf(DocumentException::class, $error);
        static::assertStringContainsString('no invoice document exists', $error->getMessage());
    }

    public function testRenderThrowsErrorWhenNoCreditItemsExist(): void
    {
        $context = Context::createDefaultContext();

        $renderer = $this->createRenderer(
            $this->createOrderSearchResult($this->createOrder(), $context),
        );

        $result = $renderer->render(
            [self::ORDER_ID => new DocumentGenerateOperation(self::ORDER_ID, FileTypes::XML, [], Uuid::randomHex())],
            $context,
            new DocumentRendererConfig()
        );

        static::assertCount(0, $result->getSuccess());

        $error = $result->getOrderError(self::ORDER_ID);

        static::assertInstanceOf(DocumentException::class, $error);
        static::assertStringContainsString('no credit line items exists', $error->getMessage());
    }

    public function testRenderThrowsErrorWhenAllCreditItemsAlreadyProcessed(): void
    {
        $creditItemId1 = '01995c65601a705fbf9309e7554fdc95';
        $creditItemId2 = '01995c65601a705fbf9309e754dc73b0';

        $context = Context::createDefaultContext();

        $order = $this->addCreditItemsToOrder(
            $this->createOrder(),
            [$creditItemId1, $creditItemId2]
        );

        $renderer = $this->createRenderer(
            $this->createOrderSearchResult($order, $context),
            invoicedCreditIds: [$creditItemId1],
            creditNoteCreditIds: [$creditItemId2],
        );

        $result = $renderer->render(
            [self::ORDER_ID => new DocumentGenerateOperation(
                self::ORDER_ID,
                FileTypes::XML,
                [],
                Uuid::randomHex()
            )],
            $context,
            new DocumentRendererConfig()
        );

        static::assertCount(0, $result->getSuccess());

        $error = $result->getOrderError(self::ORDER_ID);

        static::assertInstanceOf(DocumentException::class, $error);
        static::assertStringContainsString('no unprocessed credit line items exists', $error->getMessage());
    }

    private function createOrder(): OrderEntity
    {
        $order = new OrderEntity();
        $order->setId(self::ORDER_ID);
        $order->setVersionId(Uuid::randomHex());
        $order->setSalesChannelId(Uuid::randomHex());
        $order->setAmountNet(100.0);
        $order->setAmountTotal(100.0);
        $order->setShippingTotal(0.0);
        $order->setPrice(new CartPrice(
            100.0,
            100.0,
            100.0,
            new CalculatedTaxCollection(),
            new TaxRuleCollection(),
            CartPrice::TAX_STATE_GROSS,
        ));

        return $order;
    }

    /**
     * @param array<int, string> $creditItemIds
     */
    private function addCreditItemsToOrder(OrderEntity $order, array $creditItemIds): OrderEntity
    {
        $collection = new OrderLineItemCollection();

        foreach ($creditItemIds as $index => $id) {
            $item = new OrderLineItemEntity();
            $item->setId($id);
            $item->setType(LineItem::CREDIT_LINE_ITEM_TYPE);
            $item->setLabel('credit-item-' . ($index + 1));
            $item->setQuantity(1);
            $item->setUnitPrice(10.0);
            $item->setTotalPrice(10.0);
            $item->setPrice(new CalculatedPrice(
                10.0,
                10.0,
                new CalculatedTaxCollection(),
                new TaxRuleCollection()
            ));
            $collection->add($item);
        }

        $order->setLineItems($collection);

        return $order;
    }

    /**
     * @return EntitySearchResult<OrderCollection>
     */
    private function createOrderSearchResult(OrderEntity $order, Context $context): EntitySearchResult
    {
        return new EntitySearchResult(
            OrderDefinition::ENTITY_NAME,
            1,
            new OrderCollection([$order]),
            null,
            new Criteria(),
            $context
        );
    }

    /**
     * @param EntitySearchResult<OrderCollection> $orderSearchResult
     * @param array<int, string> $invoicedCreditIds
     * @param array<int, string> $creditNoteCreditIds
     */
    private function createRenderer(
        EntitySearchResult $orderSearchResult,
        array $invoicedCreditIds = [],
        array $creditNoteCreditIds = [],
        ?ZugferdBuilder $builder = null,
        ?bool $hasInvoice = true,
    ): ZugferdCreditNoteRenderer {
        $invoiceData = [
            'id' => self::INVOICE_ID,
            'orderId' => self::ORDER_ID,
            'orderVersionId' => Defaults::LIVE_VERSION,
            'versionId' => Defaults::LIVE_VERSION,
            'deepLinkCode' => '',
            'config' => '{"documentNumber":"1000"}',
            'documentNumber' => '1000',
        ];

        $orderRepository = $this->createMock(EntityRepository::class);
        $orderRepository->method('search')->willReturn($orderSearchResult);
        $orderRepository->method('createVersion')->willReturn(Uuid::randomHex());

        $referenceInvoiceLoaderConnection = $this->createMock(Connection::class);
        $referenceInvoiceLoaderConnection
            ->method('createQueryBuilder')
            ->willReturn(new FakeQueryBuilder(
                $referenceInvoiceLoaderConnection,
                $hasInvoice ? [$invoiceData] : []
            ));

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            ['language_id' => Defaults::LANGUAGE_SYSTEM, 'ids' => self::ORDER_ID],
        ]);

        $connection->method('fetchFirstColumn')
            ->willReturnCallback(static function (string $sql, array $params) use ($invoicedCreditIds, $creditNoteCreditIds): array {
                if (\count($params) === 2) {
                    return array_map(static fn (string $id) => Uuid::fromHexToBytes($id), $invoicedCreditIds);
                }

                if (\count($params) === 3) {
                    return array_map(static fn (string $id) => Uuid::fromHexToBytes($id), $creditNoteCreditIds);
                }

                return [];
            });

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')->willReturnArgument(0);

        return new ZugferdCreditNoteRenderer(
            $orderRepository,
            new DocumentConfigLoader(
                $this->createMock(EntityRepository::class),
                $this->createMock(EntityRepository::class),
            ),
            $eventDispatcher,
            $this->createMock(NumberRangeValueGeneratorInterface::class),
            new ReferenceInvoiceLoader($referenceInvoiceLoaderConnection),
            $connection,
            $builder ?? $this->createMock(ZugferdBuilder::class),
            new NativeClock()
        );
    }
}

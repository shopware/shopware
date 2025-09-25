<?php

declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Document\Renderer;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Document\DocumentException;
use Shopware\Core\Checkout\Document\Renderer\CreditNoteRenderer;
use Shopware\Core\Checkout\Document\Renderer\DocumentRendererConfig;
use Shopware\Core\Checkout\Document\Service\DocumentConfigLoader;
use Shopware\Core\Checkout\Document\Service\DocumentFileRendererRegistry;
use Shopware\Core\Checkout\Document\Service\HtmlRenderer;
use Shopware\Core\Checkout\Document\Service\ReferenceInvoiceLoader;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
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
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\Locale\LocaleEntity;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(CreditNoteRenderer::class)]
class CreditNoteRendererTest extends TestCase
{
    private const ORDER_ID = '01995837666372fc8eb01ca3aa815ee1';

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
        int $expectedCreditItemsCount,
    ): void {
        $context = Context::createDefaultContext();
        $order = $this->createOrder();
        $orderId = $order->getId();

        $order = $this->addCreditItemsToOrder($order, $creditItemIds);

        $invoiceId = Uuid::randomHex();
        $orderVersionId = Uuid::randomHex();

        $invoiceData = [[
            'id' => $invoiceId,
            'orderId' => $orderId,
            'orderVersionId' => $orderVersionId,
            'versionId' => $orderVersionId,
            'deepLinkCode' => 'deep-link-code',
            'config' => '{"documentNumber": "INVOICE-1"}',
            'documentNumber' => 'INVOICE-1',
        ]];

        $orderSearchResult = new EntitySearchResult(
            OrderDefinition::ENTITY_NAME,
            1,
            new OrderCollection([$order]),
            null,
            new Criteria(),
            $context
        );

        $renderer = $this->createCreditNoteRenderer(
            $orderSearchResult,
            $invoiceData,
            $invoicedCreditIds,
            $creditNoteCreditIds
        );

        $operation = new DocumentGenerateOperation(
            $orderId,
            HtmlRenderer::FILE_EXTENSION,
            [],
            $invoiceId
        );

        $result = $renderer->render(
            [$orderId => $operation],
            $context,
            new DocumentRendererConfig()
        );

        $success = $result->getSuccess();
        static::assertArrayHasKey($orderId, $success);
        static::assertCount(0, $result->getErrors());
        static::assertArrayHasKey($orderId, $success);

        $creditedItems = $success[$orderId]->getParameters()['creditItems'];
        static::assertInstanceOf(OrderLineItemCollection::class, $creditedItems);
        static::assertCount($expectedCreditItemsCount, $creditedItems);

        foreach ($creditedItems as $item) {
            static::assertContains($item->getId(), $creditItemIds);
        }
    }

    #[DataProvider('creditNoteDataProvider')]
    public static function creditNoteDataProvider(): \Generator
    {
        $creditItemId1 = '01995c65601a705fbf9309e7554fdc95';
        $creditItemId2 = '01995c65601a705fbf9309e754dc73b0';
        $creditItemId3 = '01995c81659970f999aa3c8d21a3de3b';

        yield 'filters out no credit items' => [
            'creditItemIds' => [
                $creditItemId1,
            ],
            'invoicedCreditIds' => [],
            'creditNoteCreditIds' => [],
            'expectedCreditItemsCount' => 1,
        ];

        yield 'filters out invoiced credit items' => [
            'creditItemIds' => [
                $creditItemId1,
                $creditItemId2,
            ],
            'invoicedCreditIds' => [
                $creditItemId1,
            ],
            'creditNoteCreditIds' => [],
            'expectedCreditItemsCount' => 1,
        ];

        yield 'filters out already credited credit items in credit notes' => [
            'creditItemIds' => [
                $creditItemId1,
                $creditItemId2,
            ],
            'invoicedCreditIds' => [],
            'creditNoteCreditIds' => [
                $creditItemId1,
            ],
            'expectedCreditItemsCount' => 1,
        ];

        yield 'filters out already credited credit items in credit notes and already invoiced credit items' => [
            'creditItemIds' => [
                $creditItemId1,
                $creditItemId2,
                $creditItemId3,
            ],
            'invoicedCreditIds' => [
                $creditItemId3,
            ],
            'creditNoteCreditIds' => [
                $creditItemId1,
            ],
            'expectedCreditItemsCount' => 1,
        ];
    }

    public function testRenderThrowErrorWhenNoInvoiceExists(): void
    {
        $context = Context::createDefaultContext();
        $order = $this->createOrder();
        $orderId = $order->getId();

        $order = $this->addCreditItemsToOrder($order, ['01995c65601a705fbf9309e7554fdc95']);

        $invoiceData = [];

        $orderSearchResult = new EntitySearchResult(
            OrderDefinition::ENTITY_NAME,
            1,
            new OrderCollection([$order]),
            null,
            new Criteria(),
            $context
        );

        $renderer = $this->createCreditNoteRenderer(
            $orderSearchResult,
            $invoiceData,
            [],
            []
        );

        $operation = new DocumentGenerateOperation(
            $orderId,
        );

        $result = $renderer->render(
            [$orderId => $operation],
            $context,
            new DocumentRendererConfig()
        );

        static::assertCount(0, $result->getSuccess());
        static::assertArrayHasKey($orderId, $result->getErrors());

        $error = $result->getErrors()[$orderId];
        static::assertInstanceOf(DocumentException::class, $error);
        static::assertSame(
            'Unable to generate document. Can not generate credit note document because no invoice document exists. OrderId: ' . self::ORDER_ID,
            $error->getMessage()
        );
    }

    public function testRenderThrowErrorWhenNoCreditItemsExists(): void
    {
        $context = Context::createDefaultContext();
        $order = $this->createOrder();
        $orderId = $order->getId();

        $invoiceId = Uuid::randomHex();
        $orderVersionId = Uuid::randomHex();

        $invoiceData = [[
            'id' => $invoiceId,
            'orderId' => $orderId,
            'orderVersionId' => $orderVersionId,
            'versionId' => $orderVersionId,
            'deepLinkCode' => 'deep-link-code',
            'config' => '{"documentNumber": "INVOICE-1"}',
            'documentNumber' => 'INVOICE-1',
        ]];

        $orderSearchResult = new EntitySearchResult(
            OrderDefinition::ENTITY_NAME,
            1,
            new OrderCollection([$order]),
            null,
            new Criteria(),
            $context
        );

        $renderer = $this->createCreditNoteRenderer(
            $orderSearchResult,
            $invoiceData,
            [],
            []
        );

        $operation = new DocumentGenerateOperation(
            $orderId,
            HtmlRenderer::FILE_EXTENSION,
            [],
            $invoiceId
        );

        $result = $renderer->render(
            [$orderId => $operation],
            $context,
            new DocumentRendererConfig()
        );

        static::assertCount(0, $result->getSuccess());
        static::assertArrayHasKey($orderId, $result->getErrors());

        $error = $result->getErrors()[$orderId];
        static::assertInstanceOf(DocumentException::class, $error);
        static::assertSame(
            'Unable to generate document. Can not generate credit note document because no credit line items exists. OrderId: ' . self::ORDER_ID,
            $error->getMessage()
        );
    }

    public function testRenderThrowErrorWhenAllCreditItemsAreAlreadyProcessed(): void
    {
        $context = Context::createDefaultContext();
        $order = $this->createOrder();
        $orderId = $order->getId();

        $order = $this->addCreditItemsToOrder(
            $order,
            [
                '01995c65601a705fbf9309e7554fdc95',
                '01995c65601a705fbf9309e754dc73b0',
            ]
        );

        $invoiceId = Uuid::randomHex();
        $orderVersionId = Uuid::randomHex();

        $invoiceData = [[
            'id' => $invoiceId,
            'orderId' => $orderId,
            'orderVersionId' => $orderVersionId,
            'versionId' => $orderVersionId,
            'deepLinkCode' => 'deep-link-code',
            'config' => '{"documentNumber": "INVOICE-1"}',
            'documentNumber' => 'INVOICE-1',
        ]];

        $orderSearchResult = new EntitySearchResult(
            OrderDefinition::ENTITY_NAME,
            1,
            new OrderCollection([$order]),
            null,
            new Criteria(),
            $context
        );

        $renderer = $this->createCreditNoteRenderer(
            $orderSearchResult,
            $invoiceData,
            ['01995c65601a705fbf9309e7554fdc95'],
            ['01995c65601a705fbf9309e754dc73b0']
        );

        $operation = new DocumentGenerateOperation(
            $orderId,
            HtmlRenderer::FILE_EXTENSION,
            [],
            $invoiceId
        );

        $result = $renderer->render(
            [$orderId => $operation],
            $context,
            new DocumentRendererConfig()
        );

        $errors = $result->getErrors();

        static::assertCount(0, $result->getSuccess());
        static::assertArrayHasKey($orderId, $errors);

        static::assertInstanceOf(DocumentException::class, $errors[$orderId]);
        static::assertSame(
            'Unable to generate document. Can not generate credit note document because no unprocessed credit line items exists. OrderId: ' . self::ORDER_ID,
            $errors[$orderId]->getMessage()
        );
    }

    private function createOrder(): OrderEntity
    {
        $order = new OrderEntity();
        $order->setId(self::ORDER_ID);
        $order->setSalesChannelId(Uuid::randomHex());
        $order->setVersionId(Defaults::LIVE_VERSION);

        $language = new LanguageEntity();
        $language->setId('language-test-id');
        $localeEntity = new LocaleEntity();
        $localeEntity->setCode('en-GB');
        $language->setLocale($localeEntity);

        $order->setLanguage($language);
        $order->setLanguageId('language-test-id');

        $order->setPrice(new CartPrice(
            100,
            100,
            100,
            new CalculatedTaxCollection(),
            new TaxRuleCollection(),
            CartPrice::TAX_STATE_GROSS
        ));
        $order->setPositionPrice(100);
        $order->setTaxStatus(CartPrice::TAX_STATE_GROSS);

        return $order;
    }

    /**
     * @param array<int, string> $creditItemIds
     */
    private function addCreditItemsToOrder(OrderEntity $order, array $creditItemIds): OrderEntity
    {
        $creditItemsCollection = new OrderLineItemCollection();

        foreach ($creditItemIds as $index => $creditItemId) {
            $creditItemsCollection->add((new OrderLineItemEntity())->assign([
                'id' => $creditItemId,
                'type' => 'credit',
                'label' => 'credit-item-' . ($index + 1),
                'quantity' => 1,
                'unitPrice' => 100.0,
                'totalPrice' => 100.0,
            ]));
        }

        $order->setLineItems($creditItemsCollection);

        return $order;
    }

    /**
     * @param EntitySearchResult<OrderCollection> $orderSearchResult
     * @param array{}|array<int, array<string, string>> $invoiceData
     * @param array<int, string> $invoiceCreditIds
     * @param array<int, string> $creditNoteCreditIds
     */
    private function createCreditNoteRenderer(
        EntitySearchResult $orderSearchResult,
        array $invoiceData,
        array $invoiceCreditIds,
        array $creditNoteCreditIds
    ): CreditNoteRenderer {
        $orderRepository = $this->createMock(EntityRepository::class);
        $orderRepository->method('search')->willReturn($orderSearchResult);
        $orderRepository->method('createVersion')->willReturn('new-order-version-id');

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('from')->willReturnSelf();
        $queryBuilder->method('innerJoin')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('orderBy')->willReturnSelf();
        $queryBuilder->method('addOrderBy')->willReturnSelf();

        $result = $this->createMock(Result::class);
        $result->method('fetchAllAssociative')->willReturn($invoiceData);
        $queryBuilder->method('executeQuery')->willReturn($result);

        $connection = $this->createMock(Connection::class);

        $connection->method('createQueryBuilder')->willReturn($queryBuilder);
        $referenceInvoiceLoader = new ReferenceInvoiceLoader($connection);

        $connection->method('fetchAllAssociative')->willReturn([
            [
                'language_id' => 'language-test-id',
                'ids' => self::ORDER_ID,
            ],
        ]);

        /*
        fetchFirstColumn has to return different results based on the number of parameters
            getCreditIdsOnInvoiceDocument (2 params) > creditItems already part of the invoice
            getPreviouslyCreditedIdsForInvoice (3 params) > credit items already part of other credit notes
        */
        $connection->method('fetchFirstColumn')
            ->willReturnCallback(function ($sql, $params) use ($invoiceCreditIds, $creditNoteCreditIds) {
                if (\count($params) === 2) {
                    return array_map(fn ($hexIds) => Uuid::fromHexToBytes($hexIds), $invoiceCreditIds);
                }
                if (\count($params) === 3) {
                    return array_map(fn ($hexIds) => Uuid::fromHexToBytes($hexIds), $creditNoteCreditIds);
                }

                return [];
            });

        return new CreditNoteRenderer(
            $orderRepository,
            new DocumentConfigLoader(
                $this->createMock(
                    EntityRepository::class
                ),
                $this->createMock(EntityRepository::class)
            ),
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(NumberRangeValueGeneratorInterface::class),
            $referenceInvoiceLoader,
            $connection,
            $this->createMock(DocumentFileRendererRegistry::class),
            $this->createMock(ValidatorInterface::class),
        );
    }
}

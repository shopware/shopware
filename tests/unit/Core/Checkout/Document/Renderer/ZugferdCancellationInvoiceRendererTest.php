<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Document\Renderer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Document\DocumentException;
use Shopware\Core\Checkout\Document\FileGenerator\FileTypes;
use Shopware\Core\Checkout\Document\Renderer\DocumentRendererConfig;
use Shopware\Core\Checkout\Document\Renderer\ZugferdCancellationInvoiceRenderer;
use Shopware\Core\Checkout\Document\Service\DocumentConfigLoader;
use Shopware\Core\Checkout\Document\Service\ReferenceInvoiceLoader;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Checkout\Document\Zugferd\ZugferdBuilder;
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
#[CoversClass(ZugferdCancellationInvoiceRenderer::class)]
class ZugferdCancellationInvoiceRendererTest extends TestCase
{
    private const ORDER_ID = '0192b305fddb7347be83a311a82f0649';

    public function testSupports(): void
    {
        static::assertSame(
            'zugferd_cancellation_invoice',
            $this->createRenderer()->supports()
        );
    }

    public function testRender(): void
    {
        $context = Context::createDefaultContext();
        $order = $this->createOrder();

        $builder = $this->createMock(ZugferdBuilder::class);
        $builder->expects($this->once())
            ->method('buildDocumentWithType')
            ->willReturn('<?xml version="1.0" encoding="UTF-8"?>');

        $renderer = $this->createRenderer(
            $this->createOrderSearchResult($order, $context),
            $builder,
        );

        $operation = new DocumentGenerateOperation(
            self::ORDER_ID,
            FileTypes::XML,
            ['documentNumber' => '1001'],
        );

        $rendered = $renderer->render(
            [self::ORDER_ID => $operation],
            $context,
            new DocumentRendererConfig()
        )->getOrderSuccess(self::ORDER_ID);

        static::assertNotNull($rendered);
        static::assertSame(FileTypes::XML, $rendered->getFileExtension());
        static::assertSame(FileTypes::XML_CONTENT_TYPE, $rendered->getContentType());
        static::assertStringStartsWith('<?xml', $rendered->getContent());
        static::assertSame('invoice-document-id', $operation->getReferencedDocumentId());
        static::assertSame(Defaults::LIVE_VERSION, $operation->getOrderVersionId());
    }

    public function testRenderThrowsErrorWhenNoInvoiceExists(): void
    {
        $renderer = $this->createRenderer(hasInvoice: false);

        $result = $renderer->render(
            [self::ORDER_ID => new DocumentGenerateOperation(self::ORDER_ID)],
            Context::createDefaultContext(),
            new DocumentRendererConfig()
        );

        static::assertNull($result->getOrderSuccess(self::ORDER_ID));

        $error = $result->getOrderError(self::ORDER_ID);

        static::assertInstanceOf(DocumentException::class, $error);
        static::assertSame(
            DocumentException::referencedInvoiceNotFound(ZugferdCancellationInvoiceRenderer::TYPE, self::ORDER_ID)->getMessage(),
            $error->getMessage()
        );
    }

    private function createOrder(): OrderEntity
    {
        $order = new OrderEntity();
        $order->setId(self::ORDER_ID);
        $order->setVersionId(Defaults::LIVE_VERSION);
        $order->setSalesChannelId(Uuid::randomHex());
        $order->setAmountNet(10.0);
        $order->setAmountTotal(10.0);
        $order->setShippingTotal(0.0);
        $order->setPrice(new CartPrice(
            10.0,
            10.0,
            10.0,
            new CalculatedTaxCollection(),
            new TaxRuleCollection(),
            CartPrice::TAX_STATE_GROSS,
            10.0
        ));

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
            $context,
        );
    }

    /**
     * @param EntitySearchResult<OrderCollection>|null $orderSearchResult
     */
    private function createRenderer(
        ?EntitySearchResult $orderSearchResult = null,
        ?ZugferdBuilder $builder = null,
        ?bool $hasInvoice = true,
    ): ZugferdCancellationInvoiceRenderer {
        $invoiceData = [
            'id' => 'invoice-document-id',
            'orderId' => self::ORDER_ID,
            'orderVersionId' => Defaults::LIVE_VERSION,
            'versionId' => Defaults::LIVE_VERSION,
            'deepLinkCode' => '',
            'config' => '{"documentNumber":"1000"}',
            'documentNumber' => '1000',
        ];

        $orderRepository = $this->createMock(EntityRepository::class);

        if ($orderSearchResult !== null) {
            $orderRepository->method('search')->willReturn($orderSearchResult);
        } else {
            $orderRepository->expects($this->never())->method('search');
        }

        $referenceInvoiceLoaderConnection = $this->createMock(Connection::class);
        $referenceInvoiceLoaderConnection
            ->method('createQueryBuilder')
            ->willReturn(
                new FakeQueryBuilder(
                    $referenceInvoiceLoaderConnection,
                    $hasInvoice ? [$invoiceData] : [],
                )
            );

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            ['language_id' => Defaults::LANGUAGE_SYSTEM, 'ids' => self::ORDER_ID],
        ]);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')->willReturnArgument(0);

        return new ZugferdCancellationInvoiceRenderer(
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

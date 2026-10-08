<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ProductExport\ScheduledTask;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ProductExport\ProductExportCollection;
use Shopware\Core\Content\ProductExport\ProductExportEntity;
use Shopware\Core\Content\ProductExport\ScheduledTask\ProductExportPartialGeneration;
use Shopware\Core\Content\ProductExport\ScheduledTask\ProductExportPartialGenerationHandler;
use Shopware\Core\Content\ProductExport\Service\ProductExportFileHandlerInterface;
use Shopware\Core\Content\ProductExport\Service\ProductExportGeneratorInterface;
use Shopware\Core\Content\ProductExport\Service\ProductExportRendererInterface;
use Shopware\Core\Content\ProductExport\Struct\ProductExportResult;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Translation\AbstractTranslator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Locale\LanguageLocaleCodeProvider;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\Test\Stub\MessageBus\CollectingMessageBus;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductExportPartialGenerationHandler::class)]
class ProductExportPartialGenerationHandlerTest extends TestCase
{
    /**
     * @var MockObject&EntityRepository<ProductExportCollection>
     */
    private EntityRepository&MockObject $productExportRepository;

    private Context $context;

    private ProductExportEntity $productExport;

    private SalesChannelContext $salesChannelContext;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
        $this->productExport = $this->createProductExport();
        $this->salesChannelContext = $this->createSalesChannelContext($this->context, $this->productExport->getSalesChannelId());

        $this->productExportRepository = $this->createMock(EntityRepository::class);
        $this->productExportRepository
            ->method('search')
            ->willReturn(new EntitySearchResult(
                'product_export',
                1,
                new ProductExportCollection([$this->productExport]),
                null,
                new Criteria([$this->productExport->getId()]),
                $this->context
            ));
    }

    public function testFirstChunkSchedulesNextRunAndFinalizeKeepsGeneratedAtForCache(): void
    {
        $updateCalls = 0;
        $writeResult = static::createStub(EntityWrittenContainerEvent::class);

        $this->productExportRepository
            ->expects($this->exactly(2))
            ->method('update')
            ->willReturnCallback(function (array $payload, Context $context) use (&$updateCalls, $writeResult): EntityWrittenContainerEvent {
                ++$updateCalls;

                static::assertSame($this->context, $context);
                static::assertCount(1, $payload);
                static::assertSame($this->productExport->getId(), $payload[0]['id']);

                if ($updateCalls === 1) {
                    static::assertTrue($payload[0]['isRunning']);
                    static::assertArrayHasKey('nextGenerationAt', $payload[0]);
                    static::assertInstanceOf(\DateTimeInterface::class, $payload[0]['nextGenerationAt']);
                    static::assertArrayNotHasKey('generatedAt', $payload[0]);

                    return $writeResult;
                }

                static::assertFalse($payload[0]['isRunning']);
                static::assertArrayHasKey('generatedAt', $payload[0]);
                static::assertInstanceOf(\DateTimeInterface::class, $payload[0]['generatedAt']);
                static::assertArrayNotHasKey('nextGenerationAt', $payload[0]);

                return $writeResult;
            });

        $generator = $this->createMock(ProductExportGeneratorInterface::class);
        $generator
            ->expects($this->once())
            ->method('generate')
            ->willReturn(null);

        $fileHandler = $this->createMock(ProductExportFileHandlerInterface::class);
        $fileHandler
            ->expects($this->exactly(2))
            ->method('getFilePath')
            ->willReturnOnConsecutiveCalls('/tmp/export.partial', '/tmp/export.csv');
        $fileHandler
            ->expects($this->once())
            ->method('finalizePartialProductExport')
            ->with('/tmp/export.partial', '/tmp/export.csv', 'header', 'footer')
            ->willReturn(true);

        $renderer = $this->createMock(ProductExportRendererInterface::class);
        $renderer->expects($this->once())->method('renderHeader')->willReturn('header');
        $renderer->expects($this->once())->method('renderFooter')->willReturn('footer');

        $translator = $this->createMock(AbstractTranslator::class);
        $translator->expects($this->once())->method('injectSettings');
        $translator->expects($this->once())->method('resetInjection');

        $salesChannelContextService = $this->createMock(SalesChannelContextServiceInterface::class);
        $salesChannelContextService
            ->expects($this->once())
            ->method('get')
            ->willReturn(static::createConfiguredStub(SalesChannelContext::class, [
                'getContext' => $this->context,
            ]));

        $contextPersister = $this->createMock(SalesChannelContextPersister::class);
        $contextPersister->expects($this->once())->method('save');

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('delete')
            ->with(
                'sales_channel_api_context',
                static::callback(static fn (array $criteria): bool => isset($criteria['token']) && \is_string($criteria['token']) && $criteria['token'] !== '')
            );

        $messageBus = new CollectingMessageBus();

        $this->createHandler(
            $generator,
            $fileHandler,
            $messageBus,
            $renderer,
            $translator,
            $salesChannelContextService,
            $contextPersister,
            $connection,
        )->__invoke(new ProductExportPartialGeneration($this->productExport->getId(), $this->productExport->getSalesChannelId()));

        static::assertCount(0, $messageBus->getMessages());
    }

    public function testFollowUpChunkDoesNotOverwriteSchedulingAndDispatchesNextBatchWithKeysetCursor(): void
    {
        $writeResult = static::createStub(EntityWrittenContainerEvent::class);

        $this->productExportRepository
            ->expects($this->once())
            ->method('update')
            ->willReturnCallback(function (array $payload, Context $context) use ($writeResult): EntityWrittenContainerEvent {
                static::assertSame($this->context, $context);
                static::assertCount(1, $payload);
                static::assertSame($this->productExport->getId(), $payload[0]['id']);
                static::assertTrue($payload[0]['isRunning']);
                static::assertArrayNotHasKey('generatedAt', $payload[0]);
                static::assertArrayNotHasKey('nextGenerationAt', $payload[0]);

                return $writeResult;
            });

        $generator = $this->createMock(ProductExportGeneratorInterface::class);
        $generator
            ->expects($this->once())
            ->method('generate')
            ->willReturn(new ProductExportResult('chunk', [], offset: 250, hasNextBatch: true));

        $fileHandler = $this->createMock(ProductExportFileHandlerInterface::class);
        $fileHandler
            ->expects($this->once())
            ->method('getFilePath')
            ->with($this->productExport, true)
            ->willReturn('/tmp/export.partial');
        $fileHandler
            ->expects($this->once())
            ->method('writeProductExportContent')
            ->with('chunk', '/tmp/export.partial', true);

        $messageBus = new CollectingMessageBus();

        $this->createHandler(
            $generator,
            $fileHandler,
            $messageBus,
            static::createStub(ProductExportRendererInterface::class),
            static::createStub(AbstractTranslator::class),
            static::createStub(SalesChannelContextServiceInterface::class),
            static::createStub(SalesChannelContextPersister::class),
            static::createStub(Connection::class),
        )->__invoke(new ProductExportPartialGeneration($this->productExport->getId(), $this->productExport->getSalesChannelId(), 50));

        // The next batch is dispatched with the keyset cursor carried by the batch result.
        $messages = $messageBus->getMessages();
        static::assertCount(1, $messages);
        $next = $messages[0]->getMessage();
        static::assertInstanceOf(ProductExportPartialGeneration::class, $next);
        static::assertSame(250, $next->getOffset());
        static::assertSame($this->productExport->getId(), $next->getProductExportId());
        static::assertSame($this->productExport->getSalesChannelId(), $next->getSalesChannelId());
    }

    private function createHandler(
        ProductExportGeneratorInterface $generator,
        ProductExportFileHandlerInterface $fileHandler,
        CollectingMessageBus $messageBus,
        ProductExportRendererInterface $renderer,
        AbstractTranslator $translator,
        SalesChannelContextServiceInterface $salesChannelContextService,
        SalesChannelContextPersister $contextPersister,
        Connection $connection,
    ): ProductExportPartialGenerationHandler {
        $salesChannelContextFactory = static::createStub(AbstractSalesChannelContextFactory::class);
        $salesChannelContextFactory
            ->method('create')
            ->willReturn($this->salesChannelContext);

        $languageLocaleProvider = static::createStub(LanguageLocaleCodeProvider::class);
        $languageLocaleProvider
            ->method('getLocaleForLanguageId')
            ->willReturn('en-GB');

        return new ProductExportPartialGenerationHandler(
            $generator,
            $salesChannelContextFactory,
            $this->productExportRepository,
            $fileHandler,
            $messageBus,
            $renderer,
            $translator,
            $salesChannelContextService,
            $contextPersister,
            $connection,
            $languageLocaleProvider,
            new MockClock()
        );
    }

    private function createProductExport(): ProductExportEntity
    {
        $productExport = new ProductExportEntity();
        $productExport->setId('018f6f36d2a0413497b334c53c53cc6d');
        $productExport->setSalesChannelId('018f6f36d2a0413497b334c53c53cc6e');
        $productExport->setStorefrontSalesChannelId('018f6f36d2a0413497b334c53c53cc6e');
        $productExport->setCurrencyId(Defaults::CURRENCY);
        $productExport->setInterval(300);

        $domain = new SalesChannelDomainEntity();
        $domain->setLanguageId(Defaults::LANGUAGE_SYSTEM);
        $domain->setCurrencyId(Defaults::CURRENCY);
        $productExport->setSalesChannelDomain($domain);

        return $productExport;
    }

    private function createSalesChannelContext(Context $context, string $salesChannelId): SalesChannelContext
    {
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId($salesChannelId);
        $salesChannel->setTypeId(Defaults::SALES_CHANNEL_TYPE_STOREFRONT);

        return static::createConfiguredStub(SalesChannelContext::class, [
            'getSalesChannel' => $salesChannel,
            'getContext' => $context,
        ]);
    }
}

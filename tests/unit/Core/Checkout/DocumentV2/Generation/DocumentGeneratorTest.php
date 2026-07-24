<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Generation;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeCollection;
use Shopware\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeDefinition;
use Shopware\Core\Checkout\Document\DocumentCollection;
use Shopware\Core\Checkout\Document\DocumentDefinition;
use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\Document\Service\ReferenceInvoiceLoader;
use Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentFile\DocumentFileCollection;
use Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentFile\DocumentFileDefinition;
use Shopware\Core\Checkout\DocumentV2\Config\DocumentNumberGenerator;
use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Checkout\DocumentV2\DocumentType;
use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentDependencyResolver;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentGenerationRequest;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentGenerator;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentPersister;
use Shopware\Core\Checkout\DocumentV2\Generation\ReferencedDocumentResolver;
use Shopware\Core\Checkout\DocumentV2\Provider\DocumentDataProviderRegistry;
use Shopware\Core\Checkout\DocumentV2\Provider\OrderVersionStrategy;
use Shopware\Core\Checkout\DocumentV2\Renderer\DocumentRendererRegistry;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\Doctrine\FakeQueryBuilder;
use Shopware\Tests\Unit\Core\Checkout\DocumentV2\Fixtures\StaticDocumentDataProvider;
use Shopware\Tests\Unit\Core\Checkout\DocumentV2\Fixtures\StaticDocumentRenderer;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(DocumentGenerator::class)]
class DocumentGeneratorTest extends TestCase
{
    public function testGenerate(): void
    {
        $orderId = Uuid::randomHex();
        $createdOrderVersionId = Uuid::randomHex();
        $salesChannelId = Uuid::randomHex();
        $documentTypeId = Uuid::randomHex();
        $orderLanguageId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $generationRequest = new DocumentGenerationRequest(
            $orderId,
            DocumentType::INVOICE,
            [DocumentFormat::PDF],
        );

        $order = new OrderEntity();
        $order->setId($orderId);
        $order->setVersionId($createdOrderVersionId);
        $order->setSalesChannelId($salesChannelId);
        $order->setLanguageId($orderLanguageId);

        $orderRepository = $this->createMock(EntityRepository::class);
        $orderRepository
            ->expects($this->once())
            ->method('createVersion')
            ->with($orderId, $context, 'document')
            ->willReturn($createdOrderVersionId);

        $orderRepository
            ->expects($this->exactly(2))
            ->method('search')
            ->willReturnCallback(function (
                Criteria $criteria,
                Context $searchContext,
            ) use ($order, $orderId, $createdOrderVersionId): EntitySearchResult {
                static::assertSame([$orderId], $criteria->getIds());
                static::assertSame($createdOrderVersionId, $searchContext->getVersionId());

                return new EntitySearchResult(
                    OrderDefinition::ENTITY_NAME,
                    1,
                    new OrderCollection([$order]),
                    null,
                    $criteria,
                    $searchContext,
                );
            });

        $numberRangeValueGenerator = $this->createMock(NumberRangeValueGeneratorInterface::class);
        $numberRangeValueGenerator
            ->expects($this->once())
            ->method('getValue')
            ->with(
                DocumentNumberGenerator::NUMBER_RANGE_DOCUMENT_TYPE_PREFIX . DocumentType::INVOICE->value,
                $context,
                $salesChannelId,
                false,
            )
            ->willReturn('generated-number');

        $document = new DocumentEntity();

        [$generator, $documentRepository, $documentFileRepository] = $this->createGenerator(
            $orderRepository,
            $numberRangeValueGenerator,
            $documentTypeId,
            $document,
        );

        $result = $generator->generate($generationRequest, $context);

        static::assertSame($document, $result);
        static::assertCount(1, $documentRepository->creates);
        static::assertSame($orderId, $documentRepository->creates[0][0]['orderId']);
        static::assertSame($createdOrderVersionId, $documentRepository->creates[0][0]['orderVersionId']);
        static::assertSame($documentTypeId, $documentRepository->creates[0][0]['documentTypeId']);
        static::assertSame('generated-number', $documentRepository->creates[0][0]['config']['documentNumber']);
        static::assertCount(1, $documentFileRepository->creates);
        static::assertSame(DocumentFormat::PDF->value, $documentFileRepository->creates[0][0]['documentFormat']);
        static::assertIsString($documentFileRepository->creates[0][0]['mediaId']);
        static::assertNotSame('', $documentFileRepository->creates[0][0]['mediaId']);
    }

    public function testPreview(): void
    {
        $orderId = Uuid::randomHex();
        $salesChannelId = Uuid::randomHex();
        $documentTypeId = Uuid::randomHex();
        $orderLanguageId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $generationRequest = new DocumentGenerationRequest(
            $orderId,
            DocumentType::INVOICE,
            [DocumentFormat::PDF],
        );

        $order = new OrderEntity();
        $order->setId($orderId);
        $order->setSalesChannelId($salesChannelId);
        $order->setLanguageId($orderLanguageId);

        $orderRepository = $this->createMock(EntityRepository::class);
        $orderRepository
            ->expects($this->never())
            ->method('createVersion');

        $orderRepository
            ->expects($this->exactly(2))
            ->method('search')
            ->willReturnCallback(function (
                Criteria $criteria,
                Context $searchContext,
            ) use ($order, $orderId): EntitySearchResult {
                static::assertSame([$orderId], $criteria->getIds());
                static::assertSame(Defaults::LIVE_VERSION, $searchContext->getVersionId());

                return new EntitySearchResult(
                    OrderDefinition::ENTITY_NAME,
                    1,
                    new OrderCollection([$order]),
                    null,
                    $criteria,
                    $searchContext,
                );
            });

        $numberRangeValueGenerator = $this->createMock(NumberRangeValueGeneratorInterface::class);
        $numberRangeValueGenerator
            ->expects($this->once())
            ->method('getValue')
            ->with(
                DocumentNumberGenerator::NUMBER_RANGE_DOCUMENT_TYPE_PREFIX . DocumentType::INVOICE->value,
                $context,
                $salesChannelId,
                true,
            )
            ->willReturn('generated-number');

        [$generator, $documentRepository, $documentFileRepository] = $this->createGenerator(
            $orderRepository,
            $numberRangeValueGenerator,
            $documentTypeId,
            new DocumentEntity(),
        );

        $result = $generator->preview($generationRequest, $context);

        static::assertSame('filename.pdf', $result->getName());
        static::assertSame('content', $result->getContent());
        static::assertSame(DocumentFormat::PDF->fileExtension(), $result->getFileExtension());
        static::assertSame(DocumentFormat::PDF->mimeType(), $result->getContentType());
        static::assertCount(0, $documentRepository->creates);
        static::assertCount(0, $documentFileRepository->creates);
    }

    public function testGenerateThrowsExceptionForMissingFormats(): void
    {
        /** @var StaticEntityRepository<OrderCollection> $orderRepository */
        $orderRepository = new StaticEntityRepository([], new OrderDefinition());

        [$generator] = $this->createGenerator(
            $orderRepository,
            static::createStub(NumberRangeValueGeneratorInterface::class),
            Uuid::randomHex(),
            new DocumentEntity(),
        );

        $generationRequest = new DocumentGenerationRequest(
            Uuid::randomHex(),
            DocumentType::INVOICE,
            [],
        );

        $this->expectExceptionObject(DocumentV2Exception::missingFormats());

        $generator->generate($generationRequest, Context::createDefaultContext());
    }

    public function testGenerateThrowsOnConflictingOrderVersionStrategies(): void
    {
        /** @var StaticEntityRepository<OrderCollection> $orderRepository */
        $orderRepository = new StaticEntityRepository([], new OrderDefinition());

        [$generator] = $this->createGenerator(
            $orderRepository,
            static::createStub(NumberRangeValueGeneratorInterface::class),
            Uuid::randomHex(),
            new DocumentEntity(),
            providers: [
                new StaticDocumentDataProvider([DocumentType::INVOICE->value], 'referencing', OrderVersionStrategy::REFERENCED),
                new StaticDocumentDataProvider([DocumentType::INVOICE->value], 'diffing', OrderVersionStrategy::BOTH),
            ],
        );

        $this->expectExceptionObject(DocumentV2Exception::conflictingOrderVersionStrategies(
            DocumentType::INVOICE->value,
            [OrderVersionStrategy::REFERENCED->name, OrderVersionStrategy::BOTH->name],
        ));

        $generator->generate(
            new DocumentGenerationRequest(
                Uuid::randomHex(),
                DocumentType::INVOICE,
                [DocumentFormat::PDF],
            ),
            Context::createDefaultContext(),
        );
    }

    public function testGenerateRejectsReferencedDocumentIdForRequestStrategyType(): void
    {
        $referencedDocumentId = Uuid::randomHex();

        /** @var StaticEntityRepository<OrderCollection> $orderRepository */
        $orderRepository = new StaticEntityRepository([], new OrderDefinition());

        [$generator] = $this->createGenerator(
            $orderRepository,
            static::createStub(NumberRangeValueGeneratorInterface::class),
            Uuid::randomHex(),
            new DocumentEntity(),
        );

        $this->expectExceptionObject(
            DocumentV2Exception::referencedDocumentNotSupported(DocumentType::INVOICE->value, $referencedDocumentId),
        );

        $generator->generate(
            new DocumentGenerationRequest(
                Uuid::randomHex(),
                DocumentType::INVOICE,
                [DocumentFormat::PDF],
                referencedDocumentId: $referencedDocumentId,
            ),
            Context::createDefaultContext(),
        );
    }

    public function testGenerateWithReferencedStrategyRendersAndPersistsTheReferencedSnapshot(): void
    {
        $orderId = Uuid::randomHex();
        $referencedVersionId = Uuid::randomHex();
        $referencedDocumentId = Uuid::randomHex();
        $orderLanguageId = Uuid::randomHex();

        $order = new OrderEntity();
        $order->setId($orderId);
        $order->setVersionId($referencedVersionId);
        $order->setSalesChannelId(Uuid::randomHex());
        $order->setLanguageId($orderLanguageId);

        $orderRepository = $this->createOrderRepository($order, $orderId, $referencedVersionId, $orderLanguageId);

        [$generator, $documentRepository] = $this->createGenerator(
            $orderRepository,
            static::createStub(NumberRangeValueGeneratorInterface::class),
            Uuid::randomHex(),
            new DocumentEntity(),
            providers: [
                new StaticDocumentDataProvider([DocumentType::INVOICE->value], 'referencing', OrderVersionStrategy::REFERENCED),
            ],
            referenceRows: [
                $this->referenceInvoiceRow($orderId, $referencedDocumentId, $referencedVersionId),
            ],
        );

        $generator->generate(
            new DocumentGenerationRequest(
                $orderId,
                DocumentType::INVOICE,
                [DocumentFormat::PDF],
                '2000',
            ),
            Context::createDefaultContext(),
        );

        static::assertCount(1, $documentRepository->creates);
        static::assertSame($referencedVersionId, $documentRepository->creates[0][0]['orderVersionId']);
        static::assertSame($referencedDocumentId, $documentRepository->creates[0][0]['referencedDocumentId']);
    }

    public function testGenerateWithBothStrategyKeepsTheRequestSnapshotAndLoadsTheReferencedOrder(): void
    {
        $orderId = Uuid::randomHex();
        $requestVersionId = Uuid::randomHex();
        $referencedVersionId = Uuid::randomHex();
        $referencedDocumentId = Uuid::randomHex();
        $orderLanguageId = Uuid::randomHex();

        $order = new OrderEntity();
        $order->setId($orderId);
        $order->setVersionId($requestVersionId);
        $order->setSalesChannelId(Uuid::randomHex());
        $order->setLanguageId($orderLanguageId);

        $referencedOrder = new OrderEntity();
        $referencedOrder->setId($orderId);
        $referencedOrder->setVersionId($referencedVersionId);

        /** @var StaticEntityRepository<OrderCollection> $orderRepository */
        $orderRepository = new StaticEntityRepository([
            self::orderSearch($order, $requestVersionId),
            self::orderSearch($order, $requestVersionId),
            function (
                Criteria $criteria,
                Context $searchContext,
            ) use ($referencedOrder, $referencedVersionId): EntitySearchResult {
                static::assertSame('document-v2-generator::load-order', $criteria->getTitle());
                static::assertSame($referencedVersionId, $searchContext->getVersionId());
                static::assertArrayHasKey('lineItems', $criteria->getAssociations());

                return new EntitySearchResult(
                    OrderDefinition::ENTITY_NAME,
                    1,
                    new OrderCollection([$referencedOrder]),
                    null,
                    $criteria,
                    $searchContext,
                );
            },
        ], new OrderDefinition());

        [$generator, $documentRepository] = $this->createGenerator(
            $orderRepository,
            static::createStub(NumberRangeValueGeneratorInterface::class),
            Uuid::randomHex(),
            new DocumentEntity(),
            providers: [
                new StaticDocumentDataProvider([DocumentType::INVOICE->value], 'diffing', OrderVersionStrategy::BOTH),
            ],
            referenceRows: [
                $this->referenceInvoiceRow($orderId, $referencedDocumentId, $referencedVersionId),
            ],
        );

        $generator->generate(
            new DocumentGenerationRequest(
                $orderId,
                DocumentType::INVOICE,
                [DocumentFormat::PDF],
                '2000',
            ),
            Context::createDefaultContext(),
        );

        static::assertCount(1, $documentRepository->creates);
        static::assertSame($requestVersionId, $documentRepository->creates[0][0]['orderVersionId']);
        static::assertSame($referencedDocumentId, $documentRepository->creates[0][0]['referencedDocumentId']);
    }

    /**
     * @param EntityRepository<OrderCollection> $orderRepository
     * @param list<StaticDocumentDataProvider>|null $providers
     * @param list<array<string, string>> $referenceRows
     *
     * @return array{
     *     0: DocumentGenerator,
     *     1: StaticEntityRepository<DocumentCollection>,
     *     2: StaticEntityRepository<DocumentFileCollection>
     * }
     */
    private function createGenerator(
        EntityRepository $orderRepository,
        NumberRangeValueGeneratorInterface $numberRangeValueGenerator,
        string $documentTypeId,
        DocumentEntity $document,
        ?array $providers = null,
        array $referenceRows = [],
    ): array {
        /** @var StaticEntityRepository<DocumentCollection> $documentRepository */
        $documentRepository = new StaticEntityRepository([
            [],
            function (
                Criteria $criteria,
                Context $context,
                StaticEntityRepository $repository,
            ) use ($document): DocumentCollection {
                static::assertCount(1, $repository->creates);
                $document->setId($repository->creates[0][0]['id']);

                return new DocumentCollection([$document]);
            },
        ], new DocumentDefinition());

        /** @var StaticEntityRepository<DocumentFileCollection> $documentFileRepository */
        $documentFileRepository = new StaticEntityRepository([
            new DocumentFileCollection([]),
        ], new DocumentFileDefinition());

        /** @var StaticEntityRepository<DocumentTypeCollection> $documentTypeRepository */
        $documentTypeRepository = new StaticEntityRepository([
            [$documentTypeId],
        ], new DocumentTypeDefinition());

        $providerRegistry = new DocumentDataProviderRegistry(
            $providers ?? [new StaticDocumentDataProvider([DocumentType::INVOICE->value])],
        );

        $rendererRegistry = new DocumentRendererRegistry([
            new StaticDocumentRenderer(
                DocumentFormat::HTML,
                [DocumentType::INVOICE->value],
                []
            ),
            new StaticDocumentRenderer(
                DocumentFormat::PDF,
                [DocumentType::INVOICE->value],
                [DocumentFormat::HTML->value]
            ),
        ]);

        $mediaService = static::createStub(MediaService::class);
        $mediaService->method('saveFile')->willReturn(Uuid::randomHex());

        $connection = static::createStub(Connection::class);
        $connection->method('createQueryBuilder')->willReturn(new FakeQueryBuilder($connection, $referenceRows));

        $generator = new DocumentGenerator(
            $providerRegistry,
            $rendererRegistry,
            new DocumentNumberGenerator($numberRangeValueGenerator),
            new DocumentPersister(
                $documentRepository,
                $documentFileRepository,
                $documentTypeRepository,
                $mediaService,
            ),
            new DocumentDependencyResolver($rendererRegistry),
            new ReferencedDocumentResolver(new ReferenceInvoiceLoader($connection), $connection),
            $orderRepository,
        );

        return [$generator, $documentRepository, $documentFileRepository];
    }

    /**
     * @return StaticEntityRepository<OrderCollection>
     */
    private function createOrderRepository(
        OrderEntity $order,
        string $orderId,
        string $expectedVersionId,
        string $orderLanguageId,
    ): StaticEntityRepository {
        /** @var StaticEntityRepository<OrderCollection> $orderRepository */
        $orderRepository = new StaticEntityRepository([
            function (
                Criteria $criteria,
                Context $searchContext,
            ) use ($order, $orderId, $expectedVersionId): EntitySearchResult {
                static::assertSame([$orderId], $criteria->getIds());
                static::assertSame('document-v2-generator::load-order-language', $criteria->getTitle());
                static::assertSame(['languageId'], $criteria->getFields());
                static::assertSame($expectedVersionId, $searchContext->getVersionId());

                return new EntitySearchResult(
                    OrderDefinition::ENTITY_NAME,
                    1,
                    new OrderCollection([$order]),
                    null,
                    $criteria,
                    $searchContext,
                );
            },
            function (
                Criteria $criteria,
                Context $searchContext,
            ) use ($order, $orderId, $expectedVersionId, $orderLanguageId): EntitySearchResult {
                static::assertSame([$orderId], $criteria->getIds());
                static::assertSame('document-v2-generator::load-order', $criteria->getTitle());
                static::assertSame($expectedVersionId, $searchContext->getVersionId());
                static::assertSame($orderLanguageId, $searchContext->getLanguageIdChain()[0]);

                return new EntitySearchResult(
                    OrderDefinition::ENTITY_NAME,
                    1,
                    new OrderCollection([$order]),
                    null,
                    $criteria,
                    $searchContext,
                );
            },
        ], new OrderDefinition());

        return $orderRepository;
    }

    private static function orderSearch(OrderEntity $order, string $expectedVersionId): \Closure
    {
        return function (
            Criteria $criteria,
            Context $searchContext,
        ) use ($order, $expectedVersionId): EntitySearchResult {
            static::assertSame($expectedVersionId, $searchContext->getVersionId());

            return new EntitySearchResult(
                OrderDefinition::ENTITY_NAME,
                1,
                new OrderCollection([$order]),
                null,
                $criteria,
                $searchContext,
            );
        };
    }

    /**
     * @return array<string, string>
     */
    private function referenceInvoiceRow(
        string $orderId,
        string $documentId,
        string $orderVersionId,
    ): array {
        return [
            'id' => $documentId,
            'orderId' => $orderId,
            'orderVersionId' => $orderVersionId,
            'versionId' => $orderVersionId,
            'deepLinkCode' => '',
            'config' => '{}',
            'documentNumber' => '1000',
        ];
    }
}

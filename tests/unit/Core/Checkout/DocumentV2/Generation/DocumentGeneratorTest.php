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
use Shopware\Core\Checkout\DocumentV2\Renderer\DocumentRendererRegistry;
use Shopware\Core\Checkout\DocumentV2\Struct\ProviderInput;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Media\File\FileNameProvider;
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
use Shopware\Tests\Unit\Core\Checkout\DocumentV2\Fixtures\StaticReferencedSnapshotDocumentDataProvider;
use Shopware\Tests\Unit\Core\Checkout\DocumentV2\Fixtures\StaticReferencingDocumentDataProvider;

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

        /** @var \ArrayObject<int, ProviderInput> $receivedInputs */
        $receivedInputs = new \ArrayObject();

        [$generator, $documentRepository, $documentFileRepository] = $this->createGenerator(
            $orderRepository,
            $numberRangeValueGenerator,
            $documentTypeId,
            $document,
            providers: [
                new StaticDocumentDataProvider([DocumentType::INVOICE->value], receivedInputs: $receivedInputs),
            ],
        );

        $result = $generator->generate($generationRequest, $context);

        $inputs = $receivedInputs->getArrayCopy();
        static::assertCount(1, $inputs);
        static::assertNull($inputs[0]->resolvedReference);

        static::assertSame($document, $result);
        static::assertCount(1, $documentRepository->creates);
        static::assertSame($orderId, $documentRepository->creates[0][0]['orderId']);
        static::assertSame($createdOrderVersionId, $documentRepository->creates[0][0]['orderVersionId']);
        static::assertNull($documentRepository->creates[0][0]['referencedDocumentId']);
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

        /** @var \ArrayObject<int, ProviderInput> $receivedInputs */
        $receivedInputs = new \ArrayObject();

        [$generator, $documentRepository, $documentFileRepository] = $this->createGenerator(
            $orderRepository,
            $numberRangeValueGenerator,
            $documentTypeId,
            new DocumentEntity(),
            providers: [
                new StaticDocumentDataProvider([DocumentType::INVOICE->value], receivedInputs: $receivedInputs),
            ],
        );

        $result = $generator->preview($generationRequest, $context);

        $inputs = $receivedInputs->getArrayCopy();
        static::assertCount(1, $inputs);
        static::assertNull($inputs[0]->resolvedReference);

        static::assertSame('filename.pdf', $result->getName());
        static::assertSame('content', $result->getContent());
        static::assertSame(DocumentFormat::PDF->fileExtension(), $result->getFileExtension());
        static::assertSame(DocumentFormat::PDF->mimeType(), $result->getContentType());
        static::assertCount(0, $documentRepository->creates);
        static::assertCount(0, $documentFileRepository->creates);
    }

    public function testGenerateThrowsExceptionForMissingFormats(): void
    {
        $orderRepository = StaticEntityRepository::of(OrderCollection::class, [], new OrderDefinition());

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

    public function testGenerateRejectsAReferencedDocumentIdForANonReferencingType(): void
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

    public function testGenerateForAReferencingTypeRendersAndPersistsTheReferencedSnapshot(): void
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

        /** @var \ArrayObject<int, ProviderInput> $receivedInputs */
        $receivedInputs = new \ArrayObject();

        [$generator, $documentRepository] = $this->createGenerator(
            $orderRepository,
            static::createStub(NumberRangeValueGeneratorInterface::class),
            Uuid::randomHex(),
            new DocumentEntity(),
            providers: [
                new StaticReferencedSnapshotDocumentDataProvider([DocumentType::INVOICE->value], 'referencing', $receivedInputs),
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

        $inputs = $receivedInputs->getArrayCopy();
        static::assertCount(1, $inputs);
        static::assertSame($referencedDocumentId, $inputs[0]->resolvedReference?->id);

        static::assertCount(1, $documentRepository->creates);
        static::assertSame($referencedVersionId, $documentRepository->creates[0][0]['orderVersionId']);
        static::assertSame($referencedDocumentId, $documentRepository->creates[0][0]['referencedDocumentId']);
    }

    public function testPreviewForAReferencingTypeRendersTheReferencedSnapshot(): void
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

        /** @var \ArrayObject<int, ProviderInput> $receivedInputs */
        $receivedInputs = new \ArrayObject();

        [$generator, $documentRepository, $documentFileRepository] = $this->createGenerator(
            $orderRepository,
            static::createStub(NumberRangeValueGeneratorInterface::class),
            Uuid::randomHex(),
            new DocumentEntity(),
            providers: [
                new StaticReferencedSnapshotDocumentDataProvider([DocumentType::INVOICE->value], 'referencing', $receivedInputs),
            ],
            referenceRows: [
                $this->referenceInvoiceRow($orderId, $referencedDocumentId, $referencedVersionId),
            ],
        );

        $result = $generator->preview(
            new DocumentGenerationRequest(
                $orderId,
                DocumentType::INVOICE,
                [DocumentFormat::PDF],
                '2000',
            ),
            Context::createDefaultContext(),
        );

        $inputs = $receivedInputs->getArrayCopy();
        static::assertCount(1, $inputs);
        static::assertSame($referencedDocumentId, $inputs[0]->resolvedReference?->id);

        static::assertSame('content', $result->getContent());
        static::assertCount(0, $documentRepository->creates);
        static::assertCount(0, $documentFileRepository->creates);
    }

    public function testGenerateForAResolveOnlyReferencingTypeCreatesAVersionAndPersistsTheResolvedReference(): void
    {
        $orderId = Uuid::randomHex();
        $createdOrderVersionId = Uuid::randomHex();
        $referencedVersionId = Uuid::randomHex();
        $referencedDocumentId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $order = new OrderEntity();
        $order->setId($orderId);
        $order->setVersionId($createdOrderVersionId);
        $order->setSalesChannelId(Uuid::randomHex());
        $order->setLanguageId(Uuid::randomHex());

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

        /** @var \ArrayObject<int, ProviderInput> $receivedInputs */
        $receivedInputs = new \ArrayObject();

        [$generator, $documentRepository] = $this->createGenerator(
            $orderRepository,
            static::createStub(NumberRangeValueGeneratorInterface::class),
            Uuid::randomHex(),
            new DocumentEntity(),
            providers: [
                new StaticReferencingDocumentDataProvider([DocumentType::INVOICE->value], 'referencing', $receivedInputs),
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
            $context,
        );

        $inputs = $receivedInputs->getArrayCopy();
        static::assertCount(1, $inputs);
        static::assertSame($referencedDocumentId, $inputs[0]->resolvedReference?->id);

        static::assertCount(1, $documentRepository->creates);
        static::assertSame($createdOrderVersionId, $documentRepository->creates[0][0]['orderVersionId']);
        static::assertSame($referencedDocumentId, $documentRepository->creates[0][0]['referencedDocumentId']);
    }

    public function testPreviewForAResolveOnlyReferencingTypeRendersLiveAndResolvesTheReference(): void
    {
        $orderId = Uuid::randomHex();
        $referencedVersionId = Uuid::randomHex();
        $referencedDocumentId = Uuid::randomHex();
        $orderLanguageId = Uuid::randomHex();

        $order = new OrderEntity();
        $order->setId($orderId);
        $order->setSalesChannelId(Uuid::randomHex());
        $order->setLanguageId($orderLanguageId);

        $orderRepository = $this->createOrderRepository($order, $orderId, Defaults::LIVE_VERSION, $orderLanguageId);

        /** @var \ArrayObject<int, ProviderInput> $receivedInputs */
        $receivedInputs = new \ArrayObject();

        [$generator, $documentRepository, $documentFileRepository] = $this->createGenerator(
            $orderRepository,
            static::createStub(NumberRangeValueGeneratorInterface::class),
            Uuid::randomHex(),
            new DocumentEntity(),
            providers: [
                new StaticReferencingDocumentDataProvider([DocumentType::INVOICE->value], 'referencing', $receivedInputs),
            ],
            referenceRows: [
                $this->referenceInvoiceRow($orderId, $referencedDocumentId, $referencedVersionId),
            ],
        );

        $result = $generator->preview(
            new DocumentGenerationRequest(
                $orderId,
                DocumentType::INVOICE,
                [DocumentFormat::PDF],
                '2000',
            ),
            Context::createDefaultContext(),
        );

        $inputs = $receivedInputs->getArrayCopy();
        static::assertCount(1, $inputs);
        static::assertSame($referencedDocumentId, $inputs[0]->resolvedReference?->id);

        static::assertSame('content', $result->getContent());
        static::assertCount(0, $documentRepository->creates);
        static::assertCount(0, $documentFileRepository->creates);
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
        $documentRepository = StaticEntityRepository::of(DocumentCollection::class, [
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

        $documentFileRepository = StaticEntityRepository::of(DocumentFileCollection::class, [
            new DocumentFileCollection([]),
        ], new DocumentFileDefinition());

        $documentTypeRepository = StaticEntityRepository::of(DocumentTypeCollection::class, [
            [$documentTypeId],
        ], new DocumentTypeDefinition());

        $providerRegistry = new DocumentDataProviderRegistry(
            $providers ?? [new StaticDocumentDataProvider([DocumentType::INVOICE->value])],
        );

        $rendererRegistry = new DocumentRendererRegistry([
            new StaticDocumentRenderer(
                DocumentFormat::HTML,
                []
            ),
            new StaticDocumentRenderer(
                DocumentFormat::PDF,
                [DocumentFormat::HTML->value]
            ),
        ]);

        $mediaService = static::createStub(MediaService::class);
        $mediaService->method('saveFile')->willReturn(Uuid::randomHex());

        $fileNameProvider = static::createStub(FileNameProvider::class);
        $fileNameProvider->method('provide')->willReturnArgument(0);

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
                $fileNameProvider,
            ),
            new DocumentDependencyResolver($rendererRegistry),
            new ReferencedDocumentResolver(new ReferenceInvoiceLoader($connection), $connection),
            $orderRepository,
        );

        return [$generator, $documentRepository, $documentFileRepository];
    }

    /**
     * @return EntityRepository<OrderCollection>
     */
    private function createOrderRepository(
        OrderEntity $order,
        string $orderId,
        string $expectedVersionId,
        string $orderLanguageId,
    ): EntityRepository {
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
            ) use ($order, $orderId, $expectedVersionId, $orderLanguageId): EntitySearchResult {
                static::assertSame([$orderId], $criteria->getIds());
                static::assertSame($expectedVersionId, $searchContext->getVersionId());

                if ($criteria->getTitle() === 'document-v2-generator::load-order-language') {
                    static::assertSame(['languageId'], $criteria->getFields());
                } else {
                    static::assertSame('document-v2-generator::load-order', $criteria->getTitle());
                    static::assertSame($orderLanguageId, $searchContext->getLanguageIdChain()[0]);
                }

                return new EntitySearchResult(
                    OrderDefinition::ENTITY_NAME,
                    1,
                    new OrderCollection([$order]),
                    null,
                    $criteria,
                    $searchContext,
                );
            });

        return $orderRepository;
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

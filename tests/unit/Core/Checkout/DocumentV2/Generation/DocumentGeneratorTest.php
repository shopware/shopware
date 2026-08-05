<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Generation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeCollection;
use Shopware\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeDefinition;
use Shopware\Core\Checkout\Document\DocumentCollection;
use Shopware\Core\Checkout\Document\DocumentDefinition;
use Shopware\Core\Checkout\Document\DocumentEntity;
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
use Shopware\Core\Checkout\DocumentV2\Provider\DocumentDataProviderRegistry;
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
use Shopware\Tests\Unit\Core\Checkout\DocumentV2\Fixtures\StaticDocumentDataProvider;
use Shopware\Tests\Unit\Core\Checkout\DocumentV2\Fixtures\StaticDocumentRenderer;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

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

    /**
     * @param EntityRepository<OrderCollection> $orderRepository
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
                $document->setOrderId($repository->creates[0][0]['orderId']);
                $document->setOrderVersionId($repository->creates[0][0]['orderVersionId']);

                return new DocumentCollection([$document]);
            },
        ], new DocumentDefinition());

        $documentFileRepository = StaticEntityRepository::of(DocumentFileCollection::class, [
            new DocumentFileCollection([]),
        ], new DocumentFileDefinition());

        $documentTypeRepository = StaticEntityRepository::of(DocumentTypeCollection::class, [
            [$documentTypeId],
        ], new DocumentTypeDefinition());

        $providerRegistry = new DocumentDataProviderRegistry([
            new StaticDocumentDataProvider([DocumentType::INVOICE->value]),
        ]);

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

        $generator = new DocumentGenerator(
            $providerRegistry,
            $rendererRegistry,
            new DocumentNumberGenerator($numberRangeValueGenerator),
            new DocumentPersister(
                $documentRepository,
                $documentFileRepository,
                $documentTypeRepository,
                $mediaService,
                static::createStub(EventDispatcherInterface::class),
            ),
            new DocumentDependencyResolver($rendererRegistry),
            $orderRepository,
        );

        return [$generator, $documentRepository, $documentFileRepository];
    }
}

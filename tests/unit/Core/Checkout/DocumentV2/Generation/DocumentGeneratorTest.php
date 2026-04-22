<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Generation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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
use Shopware\Core\Checkout\DocumentV2\Event\DocumentGeneratedEvent;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentDependencyResolver;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentEntityPersister;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentGenerationContext;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentGenerator;
use Shopware\Core\Checkout\DocumentV2\Provider\DocumentDataProviderRegistry;
use Shopware\Core\Checkout\DocumentV2\Renderer\DocumentRendererRegistry;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Tests\Unit\Core\Checkout\DocumentV2\Fixtures\StaticDocumentDataProvider;
use Shopware\Tests\Unit\Core\Checkout\DocumentV2\Fixtures\StaticDocumentRenderer;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

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
        $orderVersionId = Uuid::randomHex();
        $salesChannelId = Uuid::randomHex();
        $documentTypeId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $generationContext = new DocumentGenerationContext(
            $orderId,
            $orderVersionId,
            DocumentType::INVOICE->value,
            [DocumentFormat::PDF->value],
            $context,
        );

        $order = new OrderEntity();
        $order->setId($orderId);
        $order->setSalesChannelId($salesChannelId);
        $order->setLanguageId(Defaults::LANGUAGE_SYSTEM);

        /** @var StaticEntityRepository<OrderCollection> $orderRepository */
        $orderRepository = new StaticEntityRepository([
            function (Criteria $criteria, Context $searchContext) use ($order, $orderId, $orderVersionId): EntitySearchResult {
                static::assertSame([$orderId], $criteria->getIds());
                static::assertSame('document-v2-generator::load-order', $criteria->getTitle());
                static::assertSame($orderVersionId, $searchContext->getVersionId());

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

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with(static::isInstanceOf(DocumentGeneratedEvent::class))
            ->willReturnArgument(0);

        [$generator, $documentRepository, $documentFileRepository] = $this->createGenerator(
            $orderRepository,
            $numberRangeValueGenerator,
            $eventDispatcher,
            $documentTypeId,
            $document,
        );

        $result = $generator->generate($generationContext);

        static::assertSame($document, $result);
        static::assertCount(1, $documentRepository->creates);
        static::assertSame($orderId, $documentRepository->creates[0][0]['orderId']);
        static::assertSame($orderVersionId, $documentRepository->creates[0][0]['orderVersionId']);
        static::assertSame($documentTypeId, $documentRepository->creates[0][0]['documentTypeId']);
        static::assertSame('generated-number', $documentRepository->creates[0][0]['documentNumber']);

        static::assertCount(1, $documentFileRepository->creates);
        static::assertSame(DocumentFormat::PDF->value, $documentFileRepository->creates[0][0]['documentFormat']);
        static::assertIsString($documentFileRepository->creates[0][0]['mediaId']);
        static::assertNotSame('', $documentFileRepository->creates[0][0]['mediaId']);
    }

    #[DataProvider('invalidGenerationContextProvider')]
    public function testGenerateThrowsExceptionOnInvalidGenerationContext(DocumentGenerationContext $generationContext, DocumentV2Exception $exception): void
    {
        /** @var StaticEntityRepository<OrderCollection> $orderRepository */
        $orderRepository = new StaticEntityRepository([], new OrderDefinition());

        [$generator] = $this->createGenerator(
            $orderRepository,
            $this->createMock(NumberRangeValueGeneratorInterface::class),
            $this->createMock(EventDispatcherInterface::class),
            Uuid::randomHex(),
            new DocumentEntity(),
        );

        static::expectExceptionObject($exception);

        $generator->generate($generationContext);
    }

    /**
     * @return iterable<string, array{generationContext: DocumentGenerationContext, exception: DocumentV2Exception}>
     */
    public static function invalidGenerationContextProvider(): iterable
    {
        yield 'missing formats' => [
            'generationContext' => new DocumentGenerationContext(
                Uuid::randomHex(),
                Uuid::randomHex(),
                DocumentType::INVOICE->value,
                [],
                Context::createDefaultContext(),
            ),
            'exception' => DocumentV2Exception::missingFormats(),
        ];

        yield 'live version not allowed' => [
            'generationContext' => new DocumentGenerationContext(
                Uuid::randomHex(),
                Defaults::LIVE_VERSION,
                DocumentType::INVOICE->value,
                [DocumentFormat::PDF->value],
                Context::createDefaultContext(),
            ),
            'exception' => DocumentV2Exception::liveVersionNotAllowed(),
        ];
    }

    /**
     * @param StaticEntityRepository<OrderCollection> $orderRepository
     *
     * @return array{
     *     0: DocumentGenerator,
     *     1: StaticEntityRepository<DocumentCollection>,
     *     2: StaticEntityRepository<DocumentFileCollection>
     * }
     */
    private function createGenerator(
        StaticEntityRepository $orderRepository,
        NumberRangeValueGeneratorInterface $numberRangeValueGenerator,
        EventDispatcherInterface $eventDispatcher,
        string $documentTypeId,
        DocumentEntity $document,
    ): array {
        /** @var StaticEntityRepository<DocumentCollection> $documentRepository */
        $documentRepository = new StaticEntityRepository([
            [],
            function (Criteria $criteria, Context $context, StaticEntityRepository $repository) use ($document): DocumentCollection {
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

        $providerRegistry = new DocumentDataProviderRegistry([
            new StaticDocumentDataProvider([DocumentType::INVOICE->value]),
        ]);

        $rendererRegistry = new DocumentRendererRegistry([
            new StaticDocumentRenderer(DocumentFormat::HTML, [DocumentType::INVOICE->value], []),
            new StaticDocumentRenderer(DocumentFormat::PDF, [DocumentType::INVOICE->value], [DocumentFormat::HTML->value]),
        ]);

        $generator = new DocumentGenerator(
            $providerRegistry,
            $rendererRegistry,
            new DocumentNumberGenerator($numberRangeValueGenerator),
            new DocumentEntityPersister(
                $documentRepository,
                $documentFileRepository,
                $documentTypeRepository,
            ),
            new DocumentDependencyResolver($rendererRegistry),
            $eventDispatcher,
            $orderRepository,
        );

        return [$generator, $documentRepository, $documentFileRepository];
    }
}

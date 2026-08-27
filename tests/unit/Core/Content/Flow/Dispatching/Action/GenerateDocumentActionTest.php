<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Flow\Dispatching\Action;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeCollection;
use Shopware\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeDefinition;
use Shopware\Core\Checkout\Document\DocumentCollection;
use Shopware\Core\Checkout\Document\DocumentDefinition;
use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\Document\FileGenerator\FileTypes;
use Shopware\Core\Checkout\Document\Service\DocumentGenerator;
use Shopware\Core\Checkout\Document\Service\ReferenceInvoiceLoader;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentFile\DocumentFileCollection;
use Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentFile\DocumentFileDefinition;
use Shopware\Core\Checkout\DocumentV2\Config\DocumentNumberGenerator;
use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Checkout\DocumentV2\DocumentType;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentDependencyResolver;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentGenerator as DocumentV2Generator;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentPersister;
use Shopware\Core\Checkout\DocumentV2\Generation\ReferencedDocumentResolver;
use Shopware\Core\Checkout\DocumentV2\Provider\DocumentDataProviderRegistry;
use Shopware\Core\Checkout\DocumentV2\Renderer\DocumentRendererRegistry;
use Shopware\Core\Checkout\DocumentV2\Type\DocumentTypeRegistry;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Flow\Dispatching\Action\GenerateDocumentAction;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\Media\File\FileNameProvider;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Framework\App\Feature\AppFeatureStorage;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Event\OrderAware;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Execution\ScriptExecutor;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Tests\Unit\Core\Checkout\DocumentV2\Fixtures\StaticDocumentDataProvider;
use Shopware\Tests\Unit\Core\Checkout\DocumentV2\Fixtures\StaticDocumentRenderer;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(GenerateDocumentAction::class)]
class GenerateDocumentActionTest extends TestCase
{
    private GenerateDocumentAction $action;

    protected function setUp(): void
    {
        $documentGenerator = static::createStub(DocumentGenerator::class);

        $this->action = $this->createAction($documentGenerator);
    }

    public function testRequirements(): void
    {
        static::assertSame(
            [OrderAware::class],
            $this->action->requirements()
        );
    }

    public function testName(): void
    {
        static::assertSame('action.generate.document', GenerateDocumentAction::getName());
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('actionExecutedProvider')]
    #[DisabledFeatures(['DOCUMENT_GENERATION_REWORK'])]
    public function testActionExecuted(array $config, int $expected): void
    {
        $orderId = Uuid::randomHex();
        $flow = new StorableFlow('foo', Context::createDefaultContext(), [], [
            OrderAware::ORDER_ID => $orderId,
        ]);
        $flow->setConfig($config);

        $documentType = $config['documentTypes'][0]['documentType'] ?? $config['documentType'] ?? null;
        $fileType = $config['documentTypes'][0]['fileType'] ?? $config['fileType'] ?? FileTypes::PDF;
        $conf = $config['documentTypes'][0]['config'] ?? $config['config'] ?? [];
        $static = $config['documentTypes'][0]['static'] ?? $config['static'] ?? false;

        $operation = new DocumentGenerateOperation($orderId, $fileType, $conf, null, $static);

        $documentGenerator = $this->createMock(DocumentGenerator::class);
        $documentGenerator->expects($this->exactly($expected))
            ->method('generate')
            ->with($documentType, [$orderId => $operation], Context::createDefaultContext());

        $this->createAction($documentGenerator)->handleFlow($flow);
    }

    public static function actionExecutedProvider(): \Generator
    {
        yield 'Generate invoice multi' => [
            [
                'documentTypes' => [
                    [
                        'documentType' => 'invoice',
                        'documentRangerType' => 'document_invoice',
                        'custom' => [
                            'invoiceNumber' => '1100',
                        ],
                        'fileType' => 'pdf',
                        'static' => true,
                    ],
                    [
                        'documentType' => 'invoice',
                        'documentRangerType' => 'document_invoice',
                        'custom' => [
                            'invoiceNumber' => '1100',
                        ],
                        'fileType' => 'pdf',
                        'static' => true,
                    ],
                ],
            ],
            2,
        ];

        yield 'Generate invoice single' => [
            [
                'documentType' => 'invoice',
                'documentRangerType' => 'document_invoice',
                'custom' => [
                    'invoiceNumber' => '1100',
                ],
                'fileType' => 'pdf',
                'static' => true,
            ],
            1,
        ];
    }

    public function testActionExecutedForV2WhenReworkIsActive(): void
    {
        $orderId = Uuid::randomHex();
        $createdOrderVersionId = Uuid::randomHex();
        $salesChannelId = Uuid::randomHex();
        $documentTypeId = Uuid::randomHex();
        $orderLanguageId = Uuid::randomHex();
        $context = Context::createDefaultContext();

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

        [$documentV2Generator, $documentRepository] = $this->createDocumentV2Generator($orderRepository, $documentTypeId);

        $flow = new StorableFlow('foo', $context, [], [
            OrderAware::ORDER_ID => $orderId,
        ]);
        $flow->setConfig([
            'documentType' => DocumentType::INVOICE->value,
            'fileFormats' => [DocumentFormat::PDF->value],
        ]);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('error');

        $action = new GenerateDocumentAction(
            static::createStub(DocumentGenerator::class),
            $documentV2Generator,
            $logger,
        );

        Feature::fake(['DOCUMENT_GENERATION_REWORK'], static function () use ($action, $flow): void {
            $action->handleFlow($flow);
        });

        static::assertCount(1, $documentRepository->creates);
        static::assertSame($orderId, $documentRepository->creates[0][0]['orderId']);
        static::assertSame($createdOrderVersionId, $documentRepository->creates[0][0]['orderVersionId']);
    }

    private function createAction(?DocumentGenerator $documentGenerator = null): GenerateDocumentAction
    {
        return new GenerateDocumentAction(
            $documentGenerator ?? static::createStub(DocumentGenerator::class),
            $this->createDocumentV2Generator(static::createStub(EntityRepository::class), Uuid::randomHex())[0],
            static::createStub(LoggerInterface::class),
        );
    }

    /**
     * @param EntityRepository<OrderCollection> $orderRepository
     *
     * @return array{0: DocumentV2Generator, 1: StaticEntityRepository<DocumentCollection>}
     */
    private function createDocumentV2Generator(EntityRepository $orderRepository, string $documentTypeId): array
    {
        $document = new DocumentEntity();
        // The generated event reads these off the persisted document, which the DAL always hydrates.
        $document->setId(Uuid::randomHex());
        $document->setOrderId(Uuid::randomHex());
        $document->setOrderVersionId(Uuid::randomHex());

        /** @var StaticEntityRepository<DocumentCollection> $documentRepository */
        $documentRepository = new StaticEntityRepository([
            [],
            function (
                Criteria $criteria,
                Context $context,
                StaticEntityRepository $repository,
            ) use ($document): DocumentCollection {
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

        $rendererRegistry = new DocumentRendererRegistry([
            new StaticDocumentRenderer(DocumentFormat::PDF),
        ]);

        $mediaService = static::createStub(MediaService::class);
        $mediaService->method('saveFile')->willReturn(Uuid::randomHex());

        $numberRangeValueGenerator = static::createStub(NumberRangeValueGeneratorInterface::class);
        $numberRangeValueGenerator->method('getValue')->willReturn('generated-number');

        $appFeatureStorage = static::createStub(AppFeatureStorage::class);
        $appFeatureStorage->method('forActiveApps')->willReturn([]);
        $documentTypeRegistry = new DocumentTypeRegistry([], $appFeatureStorage);

        $generator = new DocumentV2Generator(
            new DocumentDataProviderRegistry([
                new StaticDocumentDataProvider([DocumentType::INVOICE->value]),
            ]),
            $rendererRegistry,
            new DocumentNumberGenerator($numberRangeValueGenerator),
            new DocumentPersister(
                $documentRepository,
                $documentFileRepository,
                $documentTypeRepository,
                $mediaService,
                $documentTypeRegistry,
                static::createStub(FileNameProvider::class),
                static::createStub(EventDispatcherInterface::class),
            ),
            new DocumentDependencyResolver($rendererRegistry),
            new ReferencedDocumentResolver(
                new ReferenceInvoiceLoader(static::createStub(Connection::class)),
                static::createStub(Connection::class),
            ),
            $orderRepository,
            static::createStub(ScriptExecutor::class),
        );

        return [$generator, $documentRepository];
    }
}

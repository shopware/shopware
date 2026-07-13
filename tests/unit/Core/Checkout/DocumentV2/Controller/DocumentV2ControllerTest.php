<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Controller;

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
use Shopware\Core\Checkout\DocumentV2\Controller\DocumentV2Controller;
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
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Tests\Unit\Core\Checkout\DocumentV2\Fixtures\StaticDocumentDataProvider;
use Shopware\Tests\Unit\Core\Checkout\DocumentV2\Fixtures\StaticDocumentRenderer;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(DocumentV2Controller::class)]
class DocumentV2ControllerTest extends TestCase
{
    public function testAvailableTypesReturnsFormatsForRendererSupportedDocumentTypes(): void
    {
        $rendererRegistry = new DocumentRendererRegistry([
            new StaticDocumentRenderer(DocumentFormat::HTML, [DocumentType::INVOICE->value, 'partial_cancellation']),
            new StaticDocumentRenderer(DocumentFormat::PDF, ['partial_cancellation']),
        ]);

        $controller = new DocumentV2Controller(
            $this->createGenerator($rendererRegistry, Uuid::randomHex()),
            $rendererRegistry,
        );

        $response = $controller->availableTypes();

        static::assertSame(200, $response->getStatusCode());
        static::assertSame(
            [
                'documentTypes' => [
                    DocumentType::INVOICE->value => [
                        'formats' => [
                            DocumentFormat::HTML->value,
                        ],
                    ],
                    'partial_cancellation' => [
                        'formats' => [
                            DocumentFormat::HTML->value,
                            DocumentFormat::PDF->value,
                        ],
                    ],
                ],
            ],
            json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR),
        );
    }

    public function testCreateRejectsUnsupportedFormat(): void
    {
        $rendererRegistry = new DocumentRendererRegistry([
            new StaticDocumentRenderer(DocumentFormat::HTML, [DocumentType::INVOICE->value]),
        ]);

        static::expectExceptionObject(
            DocumentV2Exception::rendererNotFound(DocumentFormat::PDF->value, DocumentType::INVOICE->value)
        );

        $controller = new DocumentV2Controller(
            $this->createGenerator($rendererRegistry, Uuid::randomHex()),
            $rendererRegistry,
        );

        $controller->create(
            new DocumentGenerationRequest('order-id', 'order-version-id', DocumentType::INVOICE, [DocumentFormat::PDF]),
            Context::createDefaultContext(),
        );
    }

    public function testCreateReturnsGeneratedDocumentResponse(): void
    {
        $orderId = Uuid::randomHex();

        $document = new DocumentEntity();

        $rendererRegistry = new DocumentRendererRegistry([
            new StaticDocumentRenderer(DocumentFormat::HTML, [DocumentType::INVOICE->value]),
        ]);

        $controller = new DocumentV2Controller(
            $this->createGenerator($rendererRegistry, $orderId, $document),
            $rendererRegistry,
        );

        $response = $controller->create(
            new DocumentGenerationRequest(
                $orderId,
                Uuid::randomHex(),
                DocumentType::INVOICE,
                [DocumentFormat::HTML],
                '1000',
            ),
            Context::createDefaultContext(),
        );

        static::assertSame(200, $response->getStatusCode());
        static::assertSame(
            [
                'documentDeepLink' => $document->getDeepLinkCode(),
                'documentId' => $document->getId(),
                'fileTypes' => [
                    DocumentFormat::HTML->value,
                ],
            ],
            json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR),
        );
    }

    public function testPreviewRejectsUnsupportedFormat(): void
    {
        $rendererRegistry = new DocumentRendererRegistry([
            new StaticDocumentRenderer(DocumentFormat::HTML, [DocumentType::INVOICE->value]),
        ]);

        static::expectExceptionObject(
            DocumentV2Exception::rendererNotFound(DocumentFormat::PDF->value, DocumentType::INVOICE->value)
        );

        $controller = new DocumentV2Controller(
            $this->createGenerator($rendererRegistry, Uuid::randomHex()),
            $rendererRegistry,
        );

        $controller->preview(
            new DocumentGenerationRequest('order-id', 'order-version-id', DocumentType::INVOICE, [DocumentFormat::PDF]),
            Context::createDefaultContext(),
        );
    }

    public function testPreviewReturnsRenderedDocumentResponse(): void
    {
        $orderId = Uuid::randomHex();

        $rendererRegistry = new DocumentRendererRegistry([
            new StaticDocumentRenderer(DocumentFormat::HTML, [DocumentType::INVOICE->value]),
        ]);

        $controller = new DocumentV2Controller(
            $this->createGenerator($rendererRegistry, $orderId),
            $rendererRegistry,
        );

        $response = $controller->preview(
            new DocumentGenerationRequest(
                $orderId,
                Uuid::randomHex(),
                DocumentType::INVOICE,
                [DocumentFormat::HTML],
                '1000',
            ),
            Context::createDefaultContext(),
        );

        static::assertSame('content', $response->getContent());
        static::assertSame(DocumentFormat::HTML->mimeType(), $response->headers->get('content-type'));
        static::assertStringStartsWith('inline;', (string) $response->headers->get('content-disposition'));
        static::assertStringContainsString('filename.html', (string) $response->headers->get('content-disposition'));
    }

    private function createGenerator(
        DocumentRendererRegistry $rendererRegistry,
        string $orderId,
        ?DocumentEntity $document = null,
    ): DocumentGenerator {
        $order = new OrderEntity();
        $order->setId($orderId);
        $order->setSalesChannelId(Uuid::randomHex());
        $order->setLanguageId(Uuid::randomHex());

        /** @var StaticEntityRepository<OrderCollection> $orderRepository */
        $orderRepository = new StaticEntityRepository([
            new OrderCollection([$order]),
            new OrderCollection([$order]),
        ], new OrderDefinition());

        $document ??= new DocumentEntity();
        $document->setId(Uuid::randomHex());
        $document->setDeepLinkCode(Uuid::randomHex());

        /** @var StaticEntityRepository<DocumentCollection> $documentRepository */
        $documentRepository = new StaticEntityRepository([
            [],
            new DocumentCollection([$document]),
        ], new DocumentDefinition());

        /** @var StaticEntityRepository<DocumentFileCollection> $documentFileRepository */
        $documentFileRepository = new StaticEntityRepository([
            new DocumentFileCollection([]),
        ], new DocumentFileDefinition());

        /** @var StaticEntityRepository<DocumentTypeCollection> $documentTypeRepository */
        $documentTypeRepository = new StaticEntityRepository([
            [Uuid::randomHex()],
        ], new DocumentTypeDefinition());

        $mediaService = static::createStub(MediaService::class);
        $mediaService->method('saveFile')->willReturn(Uuid::randomHex());

        return new DocumentGenerator(
            new DocumentDataProviderRegistry([
                new StaticDocumentDataProvider([DocumentType::INVOICE->value]),
            ]),
            $rendererRegistry,
            new DocumentNumberGenerator(static::createStub(NumberRangeValueGeneratorInterface::class)),
            new DocumentPersister(
                $documentRepository,
                $documentFileRepository,
                $documentTypeRepository,
                $mediaService,
            ),
            new DocumentDependencyResolver($rendererRegistry),
            $orderRepository,
        );
    }
}

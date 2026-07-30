<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\DocumentV2\Renderer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentFile\DocumentFileCollection;
use Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentFile\DocumentFileEntity;
use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Checkout\DocumentV2\DocumentType;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentGenerationRequest;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentGenerator;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\Test\TestDefaults;
use Shopware\Tests\Integration\Core\Checkout\DocumentV2\DocumentV2Trait;

/**
 * @internal
 */
#[Package('after-sales')]
class PdfRendererTest extends TestCase
{
    use DocumentV2Trait;

    private DocumentGenerator $documentGenerator;

    /**
     * @var EntityRepository<DocumentFileCollection>
     */
    private EntityRepository $documentFileRepository;

    private MediaService $mediaService;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();

        $shippingAddressId = Uuid::randomHex();

        $this->salesChannelContext = static::getContainer()->get(SalesChannelContextFactory::class)->create(
            Uuid::randomHex(),
            TestDefaults::SALES_CHANNEL,
            [
                SalesChannelContextService::CUSTOMER_ID => $this->createCustomer(
                    ['defaultShippingAddressId' => $shippingAddressId],
                    $this->buildDemoShippingAddress($shippingAddressId),
                ),
            ],
        );

        $this->documentGenerator = static::getContainer()->get(DocumentGenerator::class);
        $this->documentFileRepository = static::getContainer()->get('document_file.repository');
        $this->mediaService = static::getContainer()->get(MediaService::class);
    }

    #[DataProvider('providePdfDocumentTypes')]
    public function testGeneratesPdf(DocumentType $documentType): void
    {
        $this->seedDemoBaseConfig($documentType->value);

        $cart = $this->generateDemoCartWithTaxes([19, 7]);
        $cart = $this->applyTenPercentPromotion($cart);
        $orderId = $this->persistCart($cart);
        $this->enrichOrderForRendering($orderId);

        $request = new DocumentGenerationRequest(
            orderId: $orderId,
            documentType: $documentType,
            requestedFormats: [DocumentFormat::PDF],
            deliveryDate: '2026-05-08T09:30:00+00:00',
        );

        $document = $this->documentGenerator->generate($request, $this->context);

        $files = $this->loadDocumentFiles($document->getId());
        static::assertCount(1, $files);

        $file = $files->first();
        static::assertInstanceOf(DocumentFileEntity::class, $file);
        static::assertSame(DocumentFormat::PDF->value, $file->getDocumentFormat());

        $bytes = $this->mediaService->loadFile($file->getMediaId(), $this->context);
        static::assertNotEmpty($bytes);
        static::assertStringStartsWith('%PDF-', $bytes);
    }

    /**
     * @return iterable<string, array{DocumentType}>
     */
    public static function providePdfDocumentTypes(): iterable
    {
        yield 'invoice' => [DocumentType::INVOICE];
        yield 'delivery_note' => [DocumentType::DELIVERY_NOTE];
    }

    public function testPreviewsPdfWithoutPersistingDocumentFile(): void
    {
        $this->seedDemoBaseConfig(DocumentType::INVOICE->value);

        $cart = $this->generateDemoCartWithTaxes([19, 7]);
        $cart = $this->applyTenPercentPromotion($cart);
        $orderId = $this->persistCart($cart);
        $this->enrichOrderForRendering($orderId);

        $documentFileCount = $this->documentFileRepository->search(new Criteria(), $this->context)->getEntities()->count();

        $request = new DocumentGenerationRequest(
            orderId: $orderId,
            documentType: DocumentType::INVOICE,
            requestedFormats: [DocumentFormat::PDF],
        );

        $preview = $this->documentGenerator->preview($request, $this->context);

        static::assertSame('application/pdf', $preview->getContentType());
        static::assertSame(DocumentFormat::PDF->fileExtension(), $preview->getFileExtension());
        static::assertStringEndsWith('.pdf', $preview->getName());
        static::assertNotEmpty($preview->getContent());
        static::assertStringStartsWith('%PDF-', $preview->getContent());
        static::assertSame('application/pdf', (new \finfo(\FILEINFO_MIME_TYPE))->buffer($preview->getContent()));
        static::assertCount(
            $documentFileCount,
            $this->documentFileRepository->search(new Criteria(), $this->context)->getEntities(),
        );
    }

    public function testGeneratesCancellationInvoicePdf(): void
    {
        $this->seedDemoBaseConfig('storno');

        $cart = $this->applyTenPercentPromotion($this->generateDemoCartWithTaxes([19, 7]));
        $orderId = $this->persistCart($cart);
        $this->enrichOrderForRendering($orderId);
        $this->seedReferenceInvoice($orderId, '1000');

        $request = new DocumentGenerationRequest(
            orderId: $orderId,
            documentType: DocumentType::CANCELLATION_INVOICE,
            requestedFormats: [DocumentFormat::PDF],
        );

        $document = $this->documentGenerator->generate($request, $this->context);

        $files = $this->loadDocumentFiles($document->getId());
        static::assertCount(1, $files);

        $file = $files->first();
        static::assertInstanceOf(DocumentFileEntity::class, $file);
        static::assertSame(DocumentFormat::PDF->value, $file->getDocumentFormat());

        $bytes = $this->mediaService->loadFile($file->getMediaId(), $this->context);
        static::assertNotEmpty($bytes);
        static::assertStringStartsWith('%PDF-', $bytes);
    }

    private function loadDocumentFiles(string $documentId): DocumentFileCollection
    {
        $criteria = (new Criteria())->addFilter(new EqualsFilter('documentId', $documentId));

        return $this->documentFileRepository->search($criteria, $this->context)->getEntities();
    }
}

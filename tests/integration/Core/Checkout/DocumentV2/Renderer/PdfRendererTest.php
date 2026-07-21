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
use Shopware\Core\Checkout\Order\OrderCollection;
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
     * @var EntityRepository<OrderCollection>
     */
    private EntityRepository $orderRepository;

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
        $this->orderRepository = static::getContainer()->get('order.repository');
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

        $orderVersionId = $this->orderRepository->createVersion($orderId, $this->context, 'DRAFT');

        $request = new DocumentGenerationRequest(
            orderId: $orderId,
            orderVersionId: $orderVersionId,
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

    private function loadDocumentFiles(string $documentId): DocumentFileCollection
    {
        $criteria = (new Criteria())->addFilter(new EqualsFilter('documentId', $documentId));

        return $this->documentFileRepository->search($criteria, $this->context)->getEntities();
    }
}

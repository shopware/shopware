<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Document\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use setasign\Fpdi\FpdiException;
use setasign\Fpdi\Tfpdf\Fpdi;
use Shopware\Core\Checkout\Document\DocumentCollection;
use Shopware\Core\Checkout\Document\DocumentGenerationResult;
use Shopware\Core\Checkout\Document\FileGenerator\FileTypes;
use Shopware\Core\Checkout\Document\Renderer\DeliveryNoteRenderer;
use Shopware\Core\Checkout\Document\Renderer\InvoiceRenderer;
use Shopware\Core\Checkout\Document\Renderer\RenderedDocument;
use Shopware\Core\Checkout\Document\Service\DocumentGenerator;
use Shopware\Core\Checkout\Document\Service\DocumentMerger;
use Shopware\Core\Checkout\Document\Service\PdfRenderer;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\TestDefaults;
use Shopware\Tests\Integration\Core\Checkout\Document\DocumentTrait;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('after-sales')]
#[Group('slow')]
class DocumentMergerTest extends TestCase
{
    use DocumentTrait;

    private SalesChannelContext $salesChannelContext;

    private Context $context;

    private DocumentGenerator $documentGenerator;

    /**
     * @var EntityRepository<DocumentCollection>
     */
    private EntityRepository $documentRepository;

    private DocumentMerger $documentMerger;

    private string $documentTypeId;

    private string $orderId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->context = Context::createDefaultContext();

        $customerId = $this->createCustomer();

        $this->salesChannelContext = static::getContainer()->get(SalesChannelContextFactory::class)->create(
            Uuid::randomHex(),
            TestDefaults::SALES_CHANNEL,
            [
                SalesChannelContextService::CUSTOMER_ID => $customerId,
            ]
        );

        $this->documentGenerator = static::getContainer()->get(DocumentGenerator::class);
        $this->documentRepository = static::getContainer()->get('document.repository');
        $this->documentMerger = static::getContainer()->get(DocumentMerger::class);

        $documentTypeRepository = static::getContainer()->get('document_type.repository');
        $this->documentTypeId = $documentTypeRepository->searchIds(
            (new Criteria())->addFilter(new EqualsFilter('technicalName', InvoiceRenderer::TYPE)),
            Context::createDefaultContext()
        )->firstId() ?? '';

        $cart = $this->generateDemoCart(2);
        $this->orderId = $this->persistCart($cart);
    }

    public function testmergeWithoutDoc(): void
    {
        $mergeResult = $this->documentMerger->merge([Uuid::randomHex()], $this->context);

        static::assertNull($mergeResult);
    }

    public function testMergeNonStaticDocumentsWithoutMedia(): void
    {
        $expectedBlob = 'expected blob';

        $mockFpdi = $this->getMockBuilder(Fpdi::class)->onlyMethods(['Output'])->getMock();
        $mockFpdi->expects($this->once())->method('OutPut')->willReturn($expectedBlob);

        $documentMerger = new DocumentMerger(
            $this->documentRepository,
            static::getContainer()->get(MediaService::class),
            $this->documentGenerator,
            $mockFpdi,
            $this->createMock(Filesystem::class),
        );

        $doc1 = Uuid::randomHex();
        $doc2 = Uuid::randomHex();

        $this->documentRepository->create([[
            'id' => $doc1,
            'documentTypeId' => $this->documentTypeId,
            'fileType' => FileTypes::PDF,
            'orderId' => $this->orderId,
            'static' => false,
            'documentMediaFileId' => null,
            'config' => [],
            'deepLinkCode' => Random::getAlphanumericString(32),
        ], [
            'id' => $doc2,
            'documentTypeId' => $this->documentTypeId,
            'fileType' => FileTypes::PDF,
            'orderId' => $this->orderId,
            'static' => false,
            'documentMediaFileId' => null,
            'config' => [],
            'deepLinkCode' => Random::getAlphanumericString(32),
        ]], $this->context);

        $mergeResult = $documentMerger->merge([$doc1, $doc2], $this->context);

        static::assertInstanceOf(RenderedDocument::class, $mergeResult);
        static::assertSame($mergeResult->getContent(), $expectedBlob);
    }

    public function testMergeWithoutStaticMedia(): void
    {
        $mockGenerator = $this->getMockBuilder(DocumentGenerator::class)->disableOriginalConstructor()->onlyMethods(['generate'])->getMock();
        $mockGenerator->expects($this->once())->method('generate')->willReturn(new DocumentGenerationResult());

        $documentMerger = new DocumentMerger(
            $this->documentRepository,
            static::getContainer()->get(MediaService::class),
            $mockGenerator,
            static::getContainer()->get('pdf.merger'),
            $this->createMock(Filesystem::class),
        );

        $documentId = Uuid::randomHex();

        $this->documentRepository->create([[
            'id' => $documentId,
            'documentTypeId' => $this->documentTypeId,
            'fileType' => FileTypes::PDF,
            'orderId' => $this->orderId,
            'static' => false,
            'documentMediaFileId' => null,
            'config' => [],
            'deepLinkCode' => Random::getAlphanumericString(32),
        ]], $this->context);

        $mergeResult = $documentMerger->merge([$documentId], $this->context);

        static::assertNull($mergeResult);
    }

    #[DataProvider('documentMergeDataProvider')]
    public function testMerge(int $numDocs, bool $static, bool $withMedia, \Closure $assertionCallback): void
    {
        $docIds = [];

        for ($i = 0; $i < $numDocs; ++$i) {
            $deliveryOperation = new DocumentGenerateOperation($this->orderId, FileTypes::PDF, [], null, $static);
            $result = $this->documentGenerator->generate(DeliveryNoteRenderer::TYPE, [$this->orderId => $deliveryOperation], $this->context)->getSuccess()->first();
            static::assertNotNull($result);
            $docIds[] = $result->getId();

            if ($static && $withMedia) {
                $staticFileContent = 'this is some content';

                $uploadFileRequest = new Request([
                    'extension' => FileTypes::PDF,
                    'fileName' => Uuid::randomHex(),
                ], [], [], [], [], [
                    'HTTP_CONTENT_LENGTH' => \strlen($staticFileContent),
                    'HTTP_CONTENT_TYPE' => 'application/pdf',
                ], $staticFileContent);

                $this->documentGenerator->upload($result->getId(), $this->context, $uploadFileRequest);
            }
        }

        $expectedBlob = 'Dummy output';

        $mockFpdi = $this->getMockBuilder(Fpdi::class)->onlyMethods(['Output', 'setSourceFile', 'importPage'])->getMock();

        $mockFpdi->expects($this->any())->method('setSourceFile')->willReturn($numDocs);
        $mockFpdi->expects($this->any())->method('importPage')->willReturn('');

        // Only use merge when merging more than 1 documents
        if ($numDocs > 1 && $withMedia) {
            $mockFpdi->expects($this->once())->method('OutPut')->willReturn($expectedBlob);
        } else {
            $mockFpdi->expects($this->exactly(0))->method('OutPut')->willReturn($expectedBlob);
        }

        $documentMerger = new DocumentMerger(
            $this->documentRepository,
            static::getContainer()->get(MediaService::class),
            $this->documentGenerator,
            $mockFpdi,
            $this->createMock(Filesystem::class),
        );

        $result = $documentMerger->merge($docIds, $this->context);
        $assertionCallback($result);
    }

    public static function documentMergeDataProvider(): \Generator
    {
        yield 'merge 0 documents' => [
            0,
            true,
            true,
            function (?RenderedDocument $mergeResult): void {
                static::assertNull($mergeResult);
            },
        ];

        yield 'merge 1 document' => [
            1,
            false,
            true,
            function (?RenderedDocument $mergeResult): void {
                static::assertInstanceOf(RenderedDocument::class, $mergeResult);
            },
        ];

        yield 'merge 1 document without media' => [
            1,
            true,
            false,
            function (?RenderedDocument $mergeResult): void {
                static::assertNull($mergeResult);
            },
        ];

        yield 'merge non static documents' => [
            2,
            false,
            true,
            function (?RenderedDocument $mergeResult): void {
                static::assertInstanceOf(RenderedDocument::class, $mergeResult);
                static::assertSame('Dummy output', $mergeResult->getContent());
                static::assertSame(PdfRenderer::FILE_CONTENT_TYPE, $mergeResult->getContentType());
            },
        ];

        yield 'merge static documents without media' => [
            2,
            true,
            false,
            function (?RenderedDocument $mergeResult): void {
                static::assertNull($mergeResult);
            },
        ];

        yield 'merge static documents with media' => [
            2,
            true,
            true,
            function (?RenderedDocument $mergeResult): void {
                static::assertInstanceOf(RenderedDocument::class, $mergeResult);
                static::assertSame('Dummy output', $mergeResult->getContent());
                static::assertSame(PdfRenderer::FILE_CONTENT_TYPE, $mergeResult->getContentType());
            },
        ];
    }

    public function testMergeWithFpdiFallbackCreatesZipWithCorrectContent(): void
    {
        $filesystem = static::getContainer()->get('filesystem');

        $docIds = [];
        $documentNumbers = ['1001', '1002'];

        // create static documents with media
        for ($i = 0; $i < 2; ++$i) {
            $deliveryOperation = new DocumentGenerateOperation(
                $this->orderId,
                FileTypes::PDF,
                [
                    'documentNumber' => $documentNumbers[$i],
                ],
                null,
                true
            );

            $result = $this->documentGenerator->generate(
                DeliveryNoteRenderer::TYPE,
                [$this->orderId => $deliveryOperation],
                $this->context
            )->getSuccess()->first();

            static::assertNotNull($result);
            $docIds[] = $result->getId();

            $staticFileContent = 'PDF content for document ' . $i;
            $uploadFileRequest = new Request(
                [
                    'extension' => FileTypes::PDF,
                    'fileName' => 'document' . $i . '.pdf',
                ],
                [],
                [],
                [],
                [],
                [
                    'HTTP_CONTENT_LENGTH' => \strlen($staticFileContent),
                    'HTTP_CONTENT_TYPE' => 'application/pdf',
                ],
                $staticFileContent
            );

            $this->documentGenerator->upload($result->getId(), $this->context, $uploadFileRequest);
        }

        // force zip creation
        $mockFpdi = $this->createMock(Fpdi::class);
        $mockFpdi->method('setSourceFile')
            ->willThrowException(new FpdiException('PDF merge failed'));

        $documentMerger = new DocumentMerger(
            $this->documentRepository,
            static::getContainer()->get(MediaService::class),
            $this->documentGenerator,
            $mockFpdi,
            $filesystem,
        );

        $result = $documentMerger->merge($docIds, $this->context);

        static::assertNotNull($result);
        static::assertSame('zip', $result->getFileExtension());
        static::assertSame('application/zip', $result->getContentType());

        // save content to a temporary zip file
        $filesystem = static::getContainer()->get('filesystem');
        $tempFile = tempnam(sys_get_temp_dir(), 'test_zip');
        $filesystem->dumpFile($tempFile, $result->getContent());

        $zip = new \ZipArchive();
        static::assertTrue($zip->open($tempFile), 'failed to open zip file');
        static::assertSame(2, $zip->numFiles, 'zip should contain exactly 2 files');

        $order = static::getContainer()
            ->get('order.repository')
            ->search(new Criteria([$this->orderId]), $this->context)
            ->first();
        static::assertNotNull($order);
        static::assertInstanceOf(OrderEntity::class, $order);
        $orderNumber = $order->getOrderNumber();

        for ($i = 0; $i < $zip->numFiles; ++$i) {
            $documentName = $orderNumber . '_' . DeliveryNoteRenderer::TYPE . '_' . $documentNumbers[$i] . '.' . FileTypes::PDF;
            static::assertIsInt($zip->locateName($documentName), 'zip should contain file with name: ' . $documentName);
        }

        $zip->close();
        $filesystem->remove($tempFile);
        static::assertFalse($filesystem->exists($tempFile), 'temporary zip file should have been deleted');
    }
}

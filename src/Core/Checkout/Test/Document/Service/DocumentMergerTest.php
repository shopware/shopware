<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Document\Service;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use setasign\Fpdi\Tcpdf\Fpdi;
use Shopware\Core\Checkout\Document\FileGenerator\FileTypes;
use Shopware\Core\Checkout\Document\Renderer\DeliveryNoteRenderer;
use Shopware\Core\Checkout\Document\Renderer\InvoiceRenderer;
use Shopware\Core\Checkout\Document\Renderer\RenderedDocument;
use Shopware\Core\Checkout\Document\Service\DocumentGenerator;
use Shopware\Core\Checkout\Document\Service\DocumentMerger;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Checkout\Test\Document\DocumentTrait;
use Shopware\Core\Content\Media\File\FileLoader;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\TestDefaults;

/**
 * @group slow
 */
class DocumentMergerTest extends TestCase
{
    use DocumentTrait;

    private SalesChannelContext $salesChannelContext;

    private Context $context;

    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = $this->getContainer()->get(Connection::class);

        $this->context = Context::createDefaultContext();

        $customerId = $this->createCustomer();

        $this->salesChannelContext = $this->getContainer()->get(SalesChannelContextFactory::class)->create(
            Uuid::randomHex(),
            TestDefaults::SALES_CHANNEL,
            [
                SalesChannelContextService::CUSTOMER_ID => $customerId,
            ]
        );
    }

    public function testMergeWithoutDoc(): void
    {
        $merger = $this->getContainer()->get(DocumentMerger::class);

        $mergeResult = $merger->merge([Uuid::randomHex()], $this->context);

        static::assertNull($mergeResult);
    }

    public function testMergeWithOneDoc(): void
    {
        $merger = $this->getContainer()->get(DocumentMerger::class);
        $documentGenerator = $this->getContainer()->get(DocumentGenerator::class);

        $mergeResult = $merger->merge([Uuid::randomHex()], $this->context);

        static::assertNull($mergeResult);

        $cart = $this->generateDemoCart(2);
        $orderId = $this->persistCart($cart);

        $operation = new DocumentGenerateOperation($orderId, FileTypes::PDF);

        $invoiceStruct = $documentGenerator->generate(InvoiceRenderer::TYPE, [$orderId => $operation], $this->context)->first();

        $mergeResult = $merger->merge([$invoiceStruct->getId()], $this->context);

        static::assertInstanceOf(RenderedDocument::class, $mergeResult);
        static::assertNotNull($mergeResult->getContent());
        static::assertNotNull($mergeResult->getName(), 'invoice_1000.pdf');
    }

    public function testMergeWithoutMedia(): void
    {
        $expectedBlob = 'expected blob';

        $mockFpdi = $this->getMockBuilder(Fpdi::class)->onlyMethods(['Output'])->getMock();
        $mockFpdi->expects(static::once())->method('OutPut')->willReturn($expectedBlob);

        $documentRepository = $this->getContainer()->get('document.repository');

        $documentMerger = new DocumentMerger(
            $documentRepository,
            $this->getContainer()->get(MediaService::class),
            $this->getContainer()->get(DocumentGenerator::class),
            $mockFpdi,
        );

        $cart = $this->generateDemoCart(2);
        $orderId = $this->persistCart($cart);

        $documentTypeRepository = $this->getContainer()->get('document_type.repository');
        $documentTypeId = $documentTypeRepository->searchIds(
            (new Criteria())->addFilter(new EqualsFilter('technicalName', InvoiceRenderer::TYPE)),
            Context::createDefaultContext()
        )->firstId();

        $doc1 = Uuid::randomHex();
        $doc2 = Uuid::randomHex();

        $documentRepository->create([[
            'id' => $doc1,
            'documentTypeId' => $documentTypeId,
            'fileType' => FileTypes::PDF,
            'orderId' => $orderId,
            'static' => false,
            'documentMediaFileId' => null,
            'config' => [],
            'deepLinkCode' => Random::getAlphanumericString(32),
        ], [
            'id' => $doc2,
            'documentTypeId' => $documentTypeId,
            'fileType' => FileTypes::PDF,
            'orderId' => $orderId,
            'static' => false,
            'documentMediaFileId' => null,
            'config' => [],
            'deepLinkCode' => Random::getAlphanumericString(32),
        ]], $this->context);

        $mergeResult = $documentMerger->merge([$doc1, $doc2], $this->context);

        static::assertInstanceOf(RenderedDocument::class, $mergeResult);
        static::assertEquals($mergeResult->getContent(), $expectedBlob);

        $criteria = new Criteria([$doc1, $doc2]);
        $criteria->addFilter(new NotFilter('AND', [new EqualsFilter('documentMediaId', null)]));

        $documents = $documentRepository->search(new Criteria([$doc1, $doc2]), $this->context);

        static::assertCount(2, $documents);
    }

    public function testMerge(): void
    {
        $expectedBlob = 'expected blob';

        $mockFpdi = $this->getMockBuilder(Fpdi::class)->onlyMethods(['Output'])->getMock();
        $mockFpdi->expects(static::once())->method('OutPut')->willReturn($expectedBlob);

        $documentMerger = new DocumentMerger(
            $this->getContainer()->get('document.repository'),
            $this->getContainer()->get(MediaService::class),
            $this->getContainer()->get(DocumentGenerator::class),
            $mockFpdi,
        );

        $documentGenerator = $this->getContainer()->get(DocumentGenerator::class);

        $cart = $this->generateDemoCart(2);
        $orderId = $this->persistCart($cart);

        $deliveryOperation = new DocumentGenerateOperation($orderId);
        $invoiceOperation = new DocumentGenerateOperation($orderId);

        $invoiceDoc = $documentGenerator->generate(InvoiceRenderer::TYPE, [$orderId => $invoiceOperation], $this->context)->first();
        $deliveryDoc = $documentGenerator->generate(DeliveryNoteRenderer::TYPE, [$orderId => $deliveryOperation], $this->context)->first();

        static::assertNotNull($invoiceDoc);
        static::assertNotNull($deliveryDoc);

        $mergeResult = $documentMerger->merge([$invoiceDoc->getId(), $deliveryDoc->getId()], $this->context);

        static::assertInstanceOf(RenderedDocument::class, $mergeResult);
        static::assertEquals($mergeResult->getContent(), $expectedBlob);
    }
}

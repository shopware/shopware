<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\DocumentV2\Generation;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentFile\DocumentFileCollection;
use Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentFile\DocumentFileEntity;
use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Checkout\DocumentV2\DocumentType;
use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentGenerationRequest;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentGenerator;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Translation\Translator;
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
class DocumentGeneratorTest extends TestCase
{
    use DocumentV2Trait;

    private const CANCELLATION_INVOICE_NUMBER = '2000';

    private const EDITED_TOTAL = 999.99;

    private DocumentGenerator $documentGenerator;

    /**
     * @var EntityRepository<OrderCollection>
     */
    private EntityRepository $orderRepository;

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
    }

    protected function tearDown(): void
    {
        static::getContainer()->get(Translator::class)->reset();

        parent::tearDown();
    }

    public function testCancellationInvoiceRendersTheOrderSnapshotOfTheReferencedInvoice(): void
    {
        $orderId = $this->createDemoOrder();

        $invoice = $this->generateDocument(DocumentType::INVOICE, $orderId, self::DOCUMENT_NUMBER);

        $invoiceVersionId = $invoice->getOrderVersionId();
        static::assertNotSame(Defaults::LIVE_VERSION, $invoiceVersionId);

        $invoicedTotal = $this->loadOrderTotal($orderId, $invoiceVersionId);
        static::assertNotSame(self::EDITED_TOTAL, $invoicedTotal);

        $this->changeOrderTotal($orderId, self::EDITED_TOTAL);
        static::assertSame(self::EDITED_TOTAL, $this->loadOrderTotal($orderId, Defaults::LIVE_VERSION));

        $cancellationInvoice = $this->generateDocument(
            DocumentType::CANCELLATION_INVOICE,
            $orderId,
            self::CANCELLATION_INVOICE_NUMBER,
        );

        static::assertSame($invoiceVersionId, $cancellationInvoice->getOrderVersionId());
        static::assertSame($invoice->getId(), $cancellationInvoice->getReferencedDocumentId());

        $html = $this->loadDocumentContent($cancellationInvoice);
        static::assertStringNotContainsString(number_format(self::EDITED_TOTAL, 2, '.', ','), $html);
    }

    public function testCancellationInvoiceGeneratesForAnInvoiceStoredAtTheLiveVersion(): void
    {
        $orderId = $this->createDemoOrder();

        $invoiceId = $this->seedReferenceInvoice($orderId, self::DOCUMENT_NUMBER, Defaults::LIVE_VERSION);

        $cancellationInvoice = $this->generateDocument(
            DocumentType::CANCELLATION_INVOICE,
            $orderId,
            self::CANCELLATION_INVOICE_NUMBER,
        );

        static::assertSame(Defaults::LIVE_VERSION, $cancellationInvoice->getOrderVersionId());
        static::assertSame($invoiceId, $cancellationInvoice->getReferencedDocumentId());
    }

    public function testCancellationInvoiceThrowsWhenTheReferencedSnapshotNoLongerExists(): void
    {
        $orderId = $this->createDemoOrder();

        $invoiceId = $this->seedReferenceInvoice($orderId);
        $this->simulateLegacySnapshotLoss($invoiceId);

        $this->expectExceptionObject(DocumentV2Exception::referencedOrderVersionNotFound($orderId));

        $this->generateDocument(
            DocumentType::CANCELLATION_INVOICE,
            $orderId,
            self::CANCELLATION_INVOICE_NUMBER,
        );
    }

    public function testCancellationInvoiceUsesTheExplicitlyReferencedInvoice(): void
    {
        $orderId = $this->createDemoOrder();

        $olderInvoiceId = $this->seedReferenceInvoice($orderId, self::DOCUMENT_NUMBER);
        $newerInvoiceId = $this->seedReferenceInvoice($orderId, '1001');
        $this->markDocumentSent($newerInvoiceId);

        $olderInvoiceVersionId = $this->loadDocument($olderInvoiceId)->getOrderVersionId();
        static::assertNotSame($this->loadDocument($newerInvoiceId)->getOrderVersionId(), $olderInvoiceVersionId);

        $cancellationInvoice = $this->generateDocument(
            DocumentType::CANCELLATION_INVOICE,
            $orderId,
            self::CANCELLATION_INVOICE_NUMBER,
            $olderInvoiceId,
        );

        static::assertSame($olderInvoiceId, $cancellationInvoice->getReferencedDocumentId());
        static::assertSame($olderInvoiceVersionId, $cancellationInvoice->getOrderVersionId());
    }

    public function testCancellationInvoiceRejectsAReferenceToAnotherOrdersInvoice(): void
    {
        $orderId = $this->createDemoOrder();
        $this->seedReferenceInvoice($orderId);

        $foreignOrderId = $this->persistCart($this->generateDemoCartWithTaxes(['foreign' => 19]));
        $foreignInvoiceId = $this->seedReferenceInvoice($foreignOrderId, '1001');

        $this->expectExceptionObject(DocumentV2Exception::referencedInvoiceNotFound($orderId));

        $this->generateDocument(
            DocumentType::CANCELLATION_INVOICE,
            $orderId,
            self::CANCELLATION_INVOICE_NUMBER,
            $foreignInvoiceId,
        );
    }

    private function createDemoOrder(): string
    {
        $cart = $this->generateDemoCartWithTaxes([19]);
        $orderId = $this->persistCart($cart);
        $this->enrichOrderForRendering($orderId);

        $this->seedDemoBaseConfig(DocumentType::INVOICE->value);
        $this->seedDemoBaseConfig(DocumentType::CANCELLATION_INVOICE->value);

        return $orderId;
    }

    private function generateDocument(
        DocumentType $documentType,
        string $orderId,
        string $documentNumber,
        ?string $referencedDocumentId = null,
    ): DocumentEntity {
        return $this->documentGenerator->generate(
            new DocumentGenerationRequest(
                $orderId,
                $documentType,
                [DocumentFormat::HTML],
                $documentNumber,
                documentDate: self::DOCUMENT_DATE,
                referencedDocumentId: $referencedDocumentId,
            ),
            $this->context,
        );
    }

    private function changeOrderTotal(string $orderId, float $total): void
    {
        $net = round($total / 1.19, 2);

        $this->orderRepository->update([
            [
                'id' => $orderId,
                'price' => new CartPrice(
                    $net,
                    $total,
                    $total,
                    new CalculatedTaxCollection([new CalculatedTax($total - $net, 19.0, $total)]),
                    new TaxRuleCollection([new TaxRule(19.0)]),
                    CartPrice::TAX_STATE_GROSS,
                ),
            ],
        ], $this->context);
    }

    private function loadOrderTotal(string $orderId, string $orderVersionId): float
    {
        $order = $this->orderRepository->search(
            new Criteria([$orderId]),
            $this->context->createWithVersionId($orderVersionId),
        )->getEntities()->first();

        static::assertInstanceOf(OrderEntity::class, $order);
        static::assertIsFloat($order->getAmountTotal());

        return $order->getAmountTotal();
    }

    private function simulateLegacySnapshotLoss(string $documentId): void
    {
        $connection = static::getContainer()->get(Connection::class);

        $connection->executeStatement('SET FOREIGN_KEY_CHECKS=0');

        try {
            $connection->executeStatement(
                'UPDATE `document` SET `order_version_id` = :orderVersionId WHERE `id` = :id',
                [
                    'orderVersionId' => Uuid::randomBytes(),
                    'id' => Uuid::fromHexToBytes($documentId),
                ],
            );
        } finally {
            $connection->executeStatement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    private function markDocumentSent(string $documentId): void
    {
        static::getContainer()->get('document.repository')->update([
            [
                'id' => $documentId,
                'sent' => true,
            ],
        ], $this->context);
    }

    private function loadDocument(string $documentId): DocumentEntity
    {
        $document = static::getContainer()->get('document.repository')
            ->search(new Criteria([$documentId]), $this->context)->getEntities()->first();

        static::assertInstanceOf(DocumentEntity::class, $document);

        return $document;
    }

    private function loadDocumentContent(DocumentEntity $document): string
    {
        /** @var EntityRepository<DocumentFileCollection> $documentFileRepository */
        $documentFileRepository = static::getContainer()->get('document_file.repository');

        $file = $documentFileRepository->search(
            (new Criteria())->addFilter(new EqualsFilter('documentId', $document->getId())),
            $this->context,
        )->getEntities()->first();

        static::assertInstanceOf(DocumentFileEntity::class, $file);

        return static::getContainer()->get(MediaService::class)->loadFile($file->getMediaId(), $this->context);
    }
}

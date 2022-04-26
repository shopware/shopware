<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Document\Renderer;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\DocumentConfiguration;
use Shopware\Core\Checkout\Document\Event\DocumentGeneratorCriteriaEvent;
use Shopware\Core\Checkout\Document\FileGenerator\FileTypes;
use Shopware\Core\Checkout\Document\Renderer\RenderedDocument;
use Shopware\Core\Checkout\Document\Renderer\StornoRenderer;
use Shopware\Core\Checkout\Document\Service\DocumentGenerator;
use Shopware\Core\Checkout\Document\Service\PdfRenderer;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Checkout\Test\Customer\Rule\OrderFixture;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

class StornoRendererTest extends TestCase
{
    use IntegrationTestBehaviour;
    use OrderFixture;

    private Context $context;

    private EntityRepositoryInterface $orderRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->context = Context::createDefaultContext();
        $this->orderRepository = $this->getContainer()->get('order.repository');
    }

    public function testRender(): void
    {
        $stornoRenderer = $this->getContainer()->get(StornoRenderer::class);
        $pdfGenerator = $this->getContainer()->get(PdfRenderer::class);
        $orderId = Uuid::randomHex();
        $orderData = $this->getOrderData($orderId, $this->context);
        $this->orderRepository->create($orderData, $this->context);

        $invoiceNumber = '1000';
        $stornoNumber = '2000';

        $operation = new DocumentGenerateOperation($orderId, FileTypes::PDF, [
            'displayLineItems' => true,
            'itemsPerPage' => 10,
            'displayFooter' => true,
            'displayHeader' => true,
            'documentNumber' => $stornoNumber,
            'custom' => [
                'invoiceNumber' => $invoiceNumber,
                'stornoNumber' => $stornoNumber,
            ],
        ]);

        $caughtEvent = null;

        $this->getContainer()->get('event_dispatcher')
            ->addListener(DocumentGeneratorCriteriaEvent::class, function (DocumentGeneratorCriteriaEvent $event) use (&$caughtEvent): void {
                $caughtEvent = $event;
            });

        $processedTemplate = $stornoRenderer->render(
            [$orderId => $operation],
            $this->context
        );

        static::assertInstanceOf(DocumentGeneratorCriteriaEvent::class, $caughtEvent);
        static::assertEquals($caughtEvent->getDocumentType(), 'storno');
        static::assertCount(1, $caughtEvent->getOperations());
        static::assertInstanceOf(RenderedDocument::class, $processedTemplate[$orderId]);
        $rendered = $processedTemplate[$orderId];
        $html = $rendered->getHtml();

        static::assertStringContainsString('<html>', $html);
        static::assertStringContainsString('</html>', $html);

        static::assertStringContainsString('Cancellation number ' . $stornoNumber, $html);
        static::assertStringContainsString(sprintf('Cancellation %s for invoice %s ', $stornoNumber, $invoiceNumber), $html);

        $generatorOutput = $pdfGenerator->render($rendered);
        static::assertNotEmpty($generatorOutput);

        $finfo = new \finfo(\FILEINFO_MIME_TYPE);
        static::assertEquals('application/pdf', $finfo->buffer($generatorOutput));
    }

    public function testRenderStornoWithInvoiceNumber(): void
    {
        $stornoRenderer = $this->getContainer()->get(StornoRenderer::class);
        $orderId = Uuid::randomHex();
        $orderData = $this->getOrderData($orderId, $this->context);
        $this->orderRepository->create($orderData, $this->context);

        $documentService = $this->getContainer()->get(DocumentGenerator::class);

        $invoiceConfig = new DocumentConfiguration();
        $invoiceConfig->setDocumentNumber('1001');

        $operationInvoice = new DocumentGenerateOperation($orderId, FileTypes::PDF, $invoiceConfig->jsonSerialize());
        $invoice = $documentService->generate('invoice', [$orderId => $operationInvoice], $this->context)->first();

        static::assertNotNull($invoice);

        $stornoNumber = 'STORNO_NUMBER_001';

        $stornoOperation = new DocumentGenerateOperation($orderId, FileTypes::PDF, [
            'documentNumber' => $stornoNumber,
        ]);

        $rendered = $stornoRenderer->render([$orderId => $stornoOperation], $this->context);

        static::assertEquals('STORNO_NUMBER_001', $rendered[$orderId]->getNumber());
        static::assertEquals($stornoOperation->getReferencedDocumentId(), $invoice->getId());
    }

    public function testGenerateCustomConfigWithoutInvoiceNumber(): void
    {
        $stornoRenderer = $this->getContainer()->get(StornoRenderer::class);
        $orderId = Uuid::randomHex();
        $orderData = $this->getOrderData($orderId, $this->context);
        $this->orderRepository->create($orderData, $this->context);

        $documentService = $this->getContainer()->get(DocumentGenerator::class);

        $invoiceConfig = new DocumentConfiguration();
        $invoiceConfig->setDocumentNumber('1001');

        $operationInvoice = new DocumentGenerateOperation($orderId, FileTypes::PDF, $invoiceConfig->jsonSerialize());
        $invoice = $documentService->generate('invoice', [$orderId => $operationInvoice], $this->context)->first();

        static::assertNotNull($invoice);

        $stornoNumber = 'STORNO_NUMBER_001';

        $operation = new DocumentGenerateOperation($orderId, FileTypes::PDF, [
            'documentNumber' => $stornoNumber,
        ]);

        $rendered = $stornoRenderer->render([$orderId => $operation], $this->context);

        static::assertEquals($stornoNumber, $rendered[$orderId]->getNumber());
        static::assertEquals($operation->getReferencedDocumentId(), $invoice->getId());
    }
}

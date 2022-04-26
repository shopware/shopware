<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Document\Renderer;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\Event\DocumentGeneratorCriteriaEvent;
use Shopware\Core\Checkout\Document\FileGenerator\FileTypes;
use Shopware\Core\Checkout\Document\Renderer\DeliveryNoteRenderer;
use Shopware\Core\Checkout\Document\Renderer\RenderedDocument;
use Shopware\Core\Checkout\Document\Service\PdfRenderer;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Checkout\Test\Customer\Rule\OrderFixture;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

class DeliveryNoteRendererTest extends TestCase
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
        $deliveryNoteRenderer = $this->getContainer()->get(DeliveryNoteRenderer::class);
        $pdfGenerator = $this->getContainer()->get(PdfRenderer::class);
        $orderId = Uuid::randomHex();
        $orderData = $this->getOrderData($orderId, $this->context);
        $this->orderRepository->create($orderData, $this->context);

        $deliveryNoteNumber = '2000';

        $operation = new DocumentGenerateOperation($orderId, FileTypes::PDF, [
            'documentNumber' => $deliveryNoteNumber,
        ]);

        $caughtEvent = null;

        $this->getContainer()->get('event_dispatcher')
            ->addListener(DocumentGeneratorCriteriaEvent::class, function (DocumentGeneratorCriteriaEvent $event) use (&$caughtEvent): void {
                $caughtEvent = $event;
            });

        $processedTemplate = $deliveryNoteRenderer->render(
            [$orderId => $operation],
            $this->context
        );

        static::assertInstanceOf(DocumentGeneratorCriteriaEvent::class, $caughtEvent);
        static::assertEquals($caughtEvent->getDocumentType(), 'delivery_note');
        static::assertCount(1, $caughtEvent->getOperations());
        static::assertInstanceOf(RenderedDocument::class, $processedTemplate[$orderId]);

        $html = $processedTemplate[$orderId]->getHtml();
        static::assertStringContainsString('<html>', $html);
        static::assertStringContainsString('</html>', $html);

        static::assertStringContainsString('Delivery note ' . $deliveryNoteNumber, $html);
        static::assertStringContainsString(sprintf('Delivery note %s for order %s ', $deliveryNoteNumber, $orderData[0]['orderNumber']), $html);

        $generatorOutput = $pdfGenerator->render($processedTemplate[$orderId]);
        static::assertNotEmpty($generatorOutput);

        $finfo = new \finfo(\FILEINFO_MIME_TYPE);
        static::assertEquals('application/pdf', $finfo->buffer($generatorOutput));
    }
}

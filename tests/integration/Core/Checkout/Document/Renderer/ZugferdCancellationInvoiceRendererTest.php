<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Document\Renderer;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\FileGenerator\FileTypes;
use Shopware\Core\Checkout\Document\Renderer\DocumentRendererConfig;
use Shopware\Core\Checkout\Document\Renderer\InvoiceRenderer;
use Shopware\Core\Checkout\Document\Renderer\RenderedDocument;
use Shopware\Core\Checkout\Document\Renderer\ZugferdCancellationInvoiceRenderer;
use Shopware\Core\Checkout\Document\Service\DocumentGenerator;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\TestDefaults;
use Shopware\Tests\Integration\Core\Checkout\Document\DocumentTrait;

/**
 * @internal
 */
#[Package('after-sales')]
class ZugferdCancellationInvoiceRendererTest extends TestCase
{
    use DocumentTrait;

    private SalesChannelContext $salesChannelContext;

    private Context $context;

    private ZugferdCancellationInvoiceRenderer $renderer;

    private DocumentGenerator $documentGenerator;

    private string $customerId;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();

        $priceRuleId = Uuid::randomHex();
        $this->customerId = $this->createCustomer(['email' => 'test@example.com']);
        $this->salesChannelContext = $this->createSalesChannelContext($this->createShippingMethod(), [$priceRuleId]);

        $this->renderer = static::getContainer()->get(ZugferdCancellationInvoiceRenderer::class);
        $this->documentGenerator = static::getContainer()->get(DocumentGenerator::class);
    }

    public function testDocumentSnapshot(): void
    {
        $cart = $this->generateDemoCartWithTaxes([7]);
        $orderId = $this->persistCart($cart);

        $invoiceOperation = new DocumentGenerateOperation(
            $orderId,
            FileTypes::PDF,
            [
                'documentNumber' => '1001',
                'documentDate' => '2023-11-24T12:00:00+00:00',
            ]
        );

        $invoice = $this->documentGenerator->generate(
            InvoiceRenderer::TYPE,
            [$orderId => $invoiceOperation],
            $this->context
        )->getSuccess()->first();

        static::assertNotNull($invoice);

        $processedTemplate = $this->renderer->render(
            [$orderId => new DocumentGenerateOperation($orderId, FileTypes::XML, $this->createDocumentConfig(), $invoice->getId())],
            $this->context,
            new DocumentRendererConfig(),
        );

        $renderedDocument = $processedTemplate->getSuccess()[$orderId];
        static::assertInstanceOf(RenderedDocument::class, $renderedDocument);

        $content = $renderedDocument->getContent();
        static::assertIsString($content);

        $this->assertXmlSnapshot('zugferd_cancellation_invoice_document_default', $content);
    }

    public function testDocumentOmitsDeliveryChargeForZeroShippingCosts(): void
    {
        $this->salesChannelContext = $this->createSalesChannelContext(
            $this->createShippingMethod(0.0),
            $this->salesChannelContext->getRuleIds()
        );

        $cart = $this->generateDemoCartWithTaxes([7]);
        $orderId = $this->persistCart($cart);

        $invoice = $this->documentGenerator->generate(
            InvoiceRenderer::TYPE,
            [$orderId => new DocumentGenerateOperation($orderId, FileTypes::PDF, ['documentNumber' => '1001'])],
            $this->context
        )->getSuccess()->first();

        static::assertNotNull($invoice);

        $processedTemplate = $this->renderer->render(
            [$orderId => new DocumentGenerateOperation($orderId, FileTypes::XML, $this->createDocumentConfig(), $invoice->getId())],
            $this->context,
            new DocumentRendererConfig(),
        );

        $renderedDocument = $processedTemplate->getSuccess()[$orderId];
        static::assertInstanceOf(RenderedDocument::class, $renderedDocument);

        $content = $renderedDocument->getContent();
        static::assertIsString($content);
        static::assertStringNotContainsString('<ram:SpecifiedTradeAllowanceCharge>', $content);
    }

    /**
     * @param array<string> $ruleIds
     */
    private function createSalesChannelContext(string $shippingMethodId, array $ruleIds): SalesChannelContext
    {
        $salesChannelContext = static::getContainer()->get(SalesChannelContextFactory::class)->create(
            Uuid::randomHex(),
            TestDefaults::SALES_CHANNEL,
            [
                SalesChannelContextService::CUSTOMER_ID => $this->customerId,
                SalesChannelContextService::SHIPPING_METHOD_ID => $shippingMethodId,
            ]
        );
        $salesChannelContext->setRuleIds($ruleIds);

        return $salesChannelContext;
    }

    /**
     * @return array<string, string>
     */
    private function createDocumentConfig(): array
    {
        return [
            'vatId' => 'DE123456789',
            'bankBic' => 'DEUTDEDBFRA',
            'bankIban' => 'DE89370400440532013000',
            'bankName' => 'Deutsche Bank',
            'taxNumber' => '123/456/7890',
            'taxOffice' => 'Finanzamt Musterstadt',
            'companyUrl' => 'https://www.example.com',
            'companyName' => 'Example Company',
            'companyEmail' => 'mail@example.com',
            'companyPhone' => '+49 123 4567890',
            'paymentDueDate' => '+30 days',
            'executiveDirector' => 'Max Mustermann',
            'placeOfFulfillment' => 'Musterstadt',
            'placeOfJurisdiction' => 'Musterstadt',
            'documentDate' => '2023-11-24T12:00:00+00:00',
        ];
    }

    private function assertXmlSnapshot(string $expectedSnapshotName, string $actual, string $message = ''): void
    {
        $baseline = file_get_contents(__DIR__ . '/_snapshots/' . $expectedSnapshotName . '/snapshot.xml');
        static::assertIsString($baseline);
        static::assertSame($this->normalizeXmlSnapshotContent($baseline), $this->normalizeXmlSnapshotContent($actual), $message);
    }

    private function normalizeXmlSnapshotContent(string $content): string
    {
        return (string) preg_replace(
            '/<(?:udt|qdt):DateTimeString format="102">[0-9]{8}<\/(?:udt|qdt):DateTimeString>/',
            '<udt:DateTimeString format="102">[date]</udt:DateTimeString>',
            str_replace('<qdt:DateTimeString format="102">', '<udt:DateTimeString format="102">', $content)
        );
    }
}

<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Document\Renderer;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\DocumentConfiguration;
use Shopware\Core\Checkout\Document\FileGenerator\FileTypes;
use Shopware\Core\Checkout\Document\Renderer\DocumentRendererConfig;
use Shopware\Core\Checkout\Document\Renderer\InvoiceRenderer;
use Shopware\Core\Checkout\Document\Renderer\RenderedDocument;
use Shopware\Core\Checkout\Document\Renderer\StornoRenderer;
use Shopware\Core\Checkout\Document\Renderer\ZugferdCancellationInvoiceRenderer;
use Shopware\Core\Checkout\Document\Service\DocumentGenerator;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Integration\Traits\SnapshotTesting;
use Shopware\Core\Test\TestDefaults;
use Shopware\Tests\Integration\Core\Checkout\Document\DocumentTrait;

/**
 * @internal
 */
#[Package('after-sales')]
class ZugferdCancellationInvoiceRendererTest extends TestCase
{
    use DocumentTrait;
    use SnapshotTesting;

    private SalesChannelContext $salesChannelContext;

    private Context $context;

    private ZugferdCancellationInvoiceRenderer $renderer;

    private DocumentGenerator $documentGenerator;

    private string $customerId;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();

        $priceRuleId = Uuid::randomHex();
        $shippingAddressId = Uuid::randomHex();

        $customerOptions = [
            'defaultShippingAddressId' => $shippingAddressId,
        ];

        $additionalShippingAddress = [
            'id' => $shippingAddressId,
            'countryId' => $this->getValidCountryId(),
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'Maximilian',
            'lastName' => 'Musterfrau',
            'street' => 'Ebbinghoff 10a',
            'zipcode' => '48624',
            'city' => 'Schöppingen',
        ];

        $this->customerId = $this->createCustomer($customerOptions, $additionalShippingAddress);
        $this->salesChannelContext = $this->createSalesChannelContext($this->createShippingMethod(), [$priceRuleId]);

        $this->renderer = static::getContainer()->get(ZugferdCancellationInvoiceRenderer::class);
        $this->documentGenerator = static::getContainer()->get(DocumentGenerator::class);

        $this->upsertDocumentSellerAddress(StornoRenderer::TYPE);
    }

    public function testDocumentSnapshot(): void
    {
        $cart = $this->generateDemoCartWithTaxes([7]);
        $orderId = $this->persistCart($cart);

        $config = $this->createDocumentConfig();

        $invoiceConfig = new DocumentConfiguration();
        $invoiceConfig->setDocumentNumber('1001');

        $invoiceOperation = new DocumentGenerateOperation(
            $orderId,
            FileTypes::PDF,
            $invoiceConfig->jsonSerialize()
        );

        $invoice = $this->documentGenerator->generate(
            InvoiceRenderer::TYPE,
            [$orderId => $invoiceOperation],
            $this->context
        )->getSuccess()->first();

        static::assertNotNull($invoice);

        $invoiceId = $invoice->getId();

        $operation = new DocumentGenerateOperation(
            $orderId,
            FileTypes::XML,
            $config,
            $invoiceId
        );

        $processedTemplate = $this->renderer->render(
            [$orderId => $operation],
            $this->context,
            new DocumentRendererConfig(),
        );

        $renderedDocument = $processedTemplate->getSuccess()[$orderId];
        static::assertInstanceOf(RenderedDocument::class, $renderedDocument);

        $content = $renderedDocument->getContent();
        static::assertIsString($content);

        $this->assertSnapshot('zugferd_cancellation_invoice_document_default', [
            [
                'type' => self::TYPE_XML,
                'actual' => $content,
            ],
        ]);
    }

    public function testDocumentOmitsDeliveryChargeForZeroShippingCosts(): void
    {
        $this->salesChannelContext = $this->createSalesChannelContext(
            $this->createShippingMethod(0.0),
            $this->salesChannelContext->getRuleIds()
        );

        $cart = $this->generateDemoCartWithTaxes([7]);
        $orderId = $this->persistCart($cart);

        $invoiceConfig = new DocumentConfiguration();
        $invoiceConfig->setDocumentNumber('1001');

        $invoice = $this->documentGenerator->generate(
            InvoiceRenderer::TYPE,
            [$orderId => new DocumentGenerateOperation($orderId, FileTypes::PDF, $invoiceConfig->jsonSerialize())],
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
}

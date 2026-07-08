<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Document\Renderer;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\Renderer\DocumentRendererConfig;
use Shopware\Core\Checkout\Document\Renderer\RenderedDocument;
use Shopware\Core\Checkout\Document\Renderer\ZugferdRenderer;
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
class ZugferdRendererTest extends TestCase
{
    use DocumentTrait;

    private SalesChannelContext $salesChannelContext;

    private Context $context;

    private ZugferdRenderer $renderer;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();

        $priceRuleId = Uuid::randomHex();
        $shippingMethodId = $this->createShippingMethod();

        $this->salesChannelContext = static::getContainer()->get(SalesChannelContextFactory::class)->create(
            Uuid::randomHex(),
            TestDefaults::SALES_CHANNEL,
            [
                SalesChannelContextService::CUSTOMER_ID => $this->createCustomer(),
                SalesChannelContextService::SHIPPING_METHOD_ID => $shippingMethodId,
            ]
        );
        $this->salesChannelContext->setRuleIds([$priceRuleId]);

        $this->renderer = static::getContainer()->get(ZugferdRenderer::class);
    }

    public function testDocumentSnapshot(): void
    {
        $cart = $this->generateDemoCartWithTaxes([7]);
        $orderId = $this->persistCart($cart);

        $config = [
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

        $operation = new DocumentGenerateOperation(
            $orderId,
            ZugferdRenderer::FILE_EXTENSION,
            $config
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

        $this->assertXmlSnapshot('zugferd_invoice_document_default', $content);
    }

    private function assertXmlSnapshot(string $expectedSnapshotName, string $actual, string $message = ''): void
    {
        $baseline = file_get_contents(__DIR__ . '/_snapshots/' . $expectedSnapshotName . '/snapshot.xml');
        static::assertIsString($baseline);
        static::assertSame($baseline, $actual, $message);
    }
}

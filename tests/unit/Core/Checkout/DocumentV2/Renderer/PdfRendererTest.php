<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Renderer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\DocumentV2\Config\DocumentCompanyInfo;
use Shopware\Core\Checkout\DocumentV2\Config\DocumentConfig;
use Shopware\Core\Checkout\DocumentV2\Config\DocumentDisplayOptions;
use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Checkout\DocumentV2\DocumentType;
use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Checkout\DocumentV2\Provider\InvoiceDataProvider;
use Shopware\Core\Checkout\DocumentV2\Provider\RenderData\InvoiceRenderData;
use Shopware\Core\Checkout\DocumentV2\Renderer\PdfRenderer;
use Shopware\Core\Checkout\DocumentV2\Struct\AbstractRenderData;
use Shopware\Core\Checkout\DocumentV2\Struct\RenderInput;
use Shopware\Core\Checkout\DocumentV2\Struct\RenderResult;
use Shopware\Core\Checkout\DocumentV2\Struct\RenderState;
use Shopware\Core\Checkout\DocumentV2\Template\Enum\TypeCode;
use Shopware\Core\Checkout\DocumentV2\Template\View\MonetarySummationView;
use Shopware\Core\Checkout\DocumentV2\Template\View\TradePartyView;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\CountryEntity;
use Smalot\PdfParser\Parser;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(PdfRenderer::class)]
class PdfRendererTest extends TestCase
{
    private const DOMPDF_OPTIONS = [
        'isRemoteEnabled' => false,
        'isHtml5ParserEnabled' => true,
    ];

    public function testConfig(): void
    {
        $renderer = new PdfRenderer(self::DOMPDF_OPTIONS);

        static::assertSame(DocumentFormat::PDF->value, $renderer->getFormat());
        static::assertSame([DocumentFormat::HTML->value], $renderer->getDependencies());
        static::assertSame([
            DocumentType::INVOICE->value,
            DocumentType::DELIVERY_NOTE->value,
        ], $renderer->getDocumentTypes());
    }

    public function testRenderToString(): void
    {
        $renderer = new PdfRenderer(self::DOMPDF_OPTIONS);
        $html = $this->htmlResult('<html><body><p>invoice body</p></body></html>');

        $state = new RenderState();
        $state->add($html);

        $renderData = $this->createRenderData(filenamePrefix: 'invoice_');

        $result = $renderer->renderToString(
            $this->createInput($renderData),
            $state,
            Context::createDefaultContext(),
        );

        static::assertSame(DocumentFormat::PDF->value, $result->format);
        static::assertSame('pdf', $result->fileExtension);
        static::assertSame('application/pdf', $result->mimeType);
        static::assertSame('invoice_12345', $result->fileName);
        static::assertStringStartsWith('%PDF-', $result->content);
        static::assertSame('application/pdf', (new \finfo(\FILEINFO_MIME_TYPE))->buffer($result->content));
    }

    public function testThrowsWhenHtmlDependencyMissing(): void
    {
        $renderer = new PdfRenderer(self::DOMPDF_OPTIONS);

        static::expectExceptionObject(
            DocumentV2Exception::unknownRenderResult(DocumentFormat::HTML->value),
        );

        $renderer->renderToString(
            $this->createInput($this->createRenderData()),
            new RenderState(),
            Context::createDefaultContext(),
        );
    }

    public function testThrowsWhenRenderDataMissing(): void
    {
        $renderer = new PdfRenderer(self::DOMPDF_OPTIONS);
        $html = $this->htmlResult('<html><body><p>invoice body</p></body></html>');

        $state = new RenderState();
        $state->add($html);

        $input = new RenderInput(
            DocumentType::INVOICE->value,
            '12345',
            $this->createOrder(),
            [],
        );

        $this->expectExceptionObject(
            DocumentV2Exception::unknownRenderData(DocumentType::INVOICE->value, AbstractRenderData::class),
        );

        $renderer->renderToString($input, $state, Context::createDefaultContext());
    }

    public function testPageCountInjected(): void
    {
        $renderer = new PdfRenderer(self::DOMPDF_OPTIONS);

        $raw = <<<'HTML'
            <html><head><style>body { font-family: DejaVu Sans; }</style></head><body>
                <p>Page 1 / DOMPDF_PAGE_COUNT_PLACEHOLDER</p>
            </body></html>
            HTML;

        $html = $this->htmlResult($raw);

        $state = new RenderState();
        $state->add($html);

        $result = $renderer->renderToString(
            $this->createInput($this->createRenderData()),
            $state,
            Context::createDefaultContext(),
        );

        $text = (new Parser())->parseContent($result->content)->getText();

        static::assertStringNotContainsString('DOMPDF_PAGE_COUNT_PLACEHOLDER', $text);
        static::assertStringContainsString('Page 1 / 1', $text);
    }

    public function testScreenHiddenContentRemainsVisibleInPdf(): void
    {
        $renderer = new PdfRenderer(self::DOMPDF_OPTIONS);

        $raw = <<<'HTML'
            <html><head>
                <style media="screen">p { display: none; }</style>
            </head><body>
                <p>hidden on screen</p>
            </body></html>
            HTML;

        $state = new RenderState();
        $state->add($this->htmlResult($raw));

        $result = $renderer->renderToString(
            $this->createInput($this->createRenderData()),
            $state,
            Context::createDefaultContext(),
        );

        $text = (new Parser())->parseContent($result->content)->getText();

        static::assertStringContainsString('hidden on screen', $text);
    }

    private function htmlResult(string $content): RenderResult
    {
        return new RenderResult(
            format: DocumentFormat::HTML->value,
            content: $content,
            fileName: 'invoice_12345',
            fileExtension: DocumentFormat::HTML->fileExtension(),
            mimeType: DocumentFormat::HTML->mimeType(),
        );
    }

    private function createInput(InvoiceRenderData $data): RenderInput
    {
        return new RenderInput(
            DocumentType::INVOICE->value,
            $data->documentNumber,
            $this->createOrder(),
            [InvoiceDataProvider::KEY => $data],
        );
    }

    private function createOrder(): OrderEntity
    {
        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());
        $order->setSalesChannelId(Uuid::randomHex());
        $order->setLanguageId(Uuid::randomHex());

        return $order;
    }

    private function createRenderData(?string $filenamePrefix = null): InvoiceRenderData
    {
        return new InvoiceRenderData(
            config: new DocumentConfig(
                pageSize: 'a4',
                pageOrientation: 'portrait',
                itemsPerPage: 10,
                filenamePrefix: $filenamePrefix,
            ),
            company: new DocumentCompanyInfo(
                'company',
                'street',
                '12345',
                'city',
                new CountryEntity(),
            ),
            display: new DocumentDisplayOptions(),
            documentDate: 'date',
            documentNumber: '12345',
            documentComment: null,
            templatePaths: [
                DocumentFormat::HTML->value => '@Framework/documents/invoice.html.twig',
            ],
            typeCode: TypeCode::INVOICE,
            buyerReference: '10000',
            buyer: new TradePartyView(
                id: null,
                name: '',
                street: null,
                additionalAddressLine1: null,
                additionalAddressLine2: null,
                zipcode: null,
                city: null,
                countrySubdivision: null,
                countryIso: null,
                email: null,
            ),
            deliveryDate: null,
            lineItems: [],
            allowanceCharges: [],
            taxBreakdown: [],
            monetarySummation: new MonetarySummationView(
                0,
                0,
                0,
                0,
                0,
                'EUR',
                0,
                0,
                0,
                0
            ),
            paymentMeans: null,
            paymentDueDate: null,
            intraCommunityDelivery: false,
        );
    }
}

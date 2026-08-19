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
use Shopware\Core\Checkout\DocumentV2\Provider\DocumentMetaProvider;
use Shopware\Core\Checkout\DocumentV2\Provider\RenderData\DocumentMetaRenderData;
use Shopware\Core\Checkout\DocumentV2\Renderer\PdfRenderer;
use Shopware\Core\Checkout\DocumentV2\Struct\RenderInput;
use Shopware\Core\Checkout\DocumentV2\Struct\RenderResult;
use Shopware\Core\Checkout\DocumentV2\Struct\RenderState;
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
    }

    public function testRenderToString(): void
    {
        $renderer = new PdfRenderer(self::DOMPDF_OPTIONS);
        $html = $this->htmlResult('<html><body><p>invoice body</p></body></html>');

        $state = new RenderState();
        $state->add($html);

        $meta = $this->createMeta(filenamePrefix: 'invoice_');

        $result = $renderer->renderToString(
            $this->createInput($meta),
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

    public function testRenderToStringUsesConfiguredInfix(): void
    {
        $renderer = new PdfRenderer(self::DOMPDF_OPTIONS);
        $html = $this->htmlResult('<html><body><p>invoice body</p></body></html>');

        $state = new RenderState();
        $state->add($html);

        $meta = $this->createMeta(filenamePrefix: 'invoice_', filenameInfixes: ['pdf' => '_custom']);

        $result = $renderer->renderToString(
            $this->createInput($meta),
            $state,
            Context::createDefaultContext(),
        );

        static::assertSame('invoice_12345_custom', $result->fileName);
    }

    public function testThrowsWhenHtmlDependencyMissing(): void
    {
        $renderer = new PdfRenderer(self::DOMPDF_OPTIONS);

        $this->expectExceptionObject(
            DocumentV2Exception::unknownRenderResult(DocumentFormat::HTML->value),
        );

        $renderer->renderToString(
            $this->createInput($this->createMeta()),
            new RenderState(),
            Context::createDefaultContext(),
        );
    }

    public function testThrowsWhenMetaRenderDataMissing(): void
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
            DocumentV2Exception::unknownRenderData(DocumentMetaProvider::KEY, DocumentMetaRenderData::class),
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
            $this->createInput($this->createMeta()),
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
            $this->createInput($this->createMeta()),
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

    private function createInput(DocumentMetaRenderData $meta): RenderInput
    {
        return new RenderInput(
            DocumentType::INVOICE->value,
            $meta->documentNumber,
            $this->createOrder(),
            [DocumentMetaProvider::KEY => $meta],
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

    /**
     * @param array<string, string> $filenameInfixes
     */
    private function createMeta(?string $filenamePrefix = null, array $filenameInfixes = []): DocumentMetaRenderData
    {
        return new DocumentMetaRenderData(
            config: new DocumentConfig(
                pageSize: 'a4',
                pageOrientation: 'portrait',
                itemsPerPage: 10,
                filenamePrefix: $filenamePrefix,
                filenameInfixes: $filenameInfixes,
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
        );
    }
}

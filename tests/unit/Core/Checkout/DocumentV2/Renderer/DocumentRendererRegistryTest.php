<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Renderer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Checkout\DocumentV2\DocumentType;
use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Checkout\DocumentV2\Renderer\DocumentRendererRegistry;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Unit\Core\Checkout\DocumentV2\Fixtures\StaticDocumentRenderer;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(DocumentRendererRegistry::class)]
class DocumentRendererRegistryTest extends TestCase
{
    #[DataProvider('getRendererProvider')]
    public function testGetRenderer(bool $throw, DocumentFormat $format, DocumentType $type): void
    {
        $registry = self::createRegistry();

        if ($throw) {
            static::expectExceptionObject(DocumentV2Exception::rendererNotFound($format->value, $type->value));
        }

        $renderer = $registry->getRenderer($format->value, $type->value);

        static::assertSame($format->value, $renderer->getFormat());
        static::assertTrue($renderer->supports($type->value));
    }

    /**
     * @return iterable<string, array{throw: bool, format: DocumentFormat, type: DocumentType}>
     */
    public static function getRendererProvider(): iterable
    {
        yield 'type mismatch, format mismatch' => [
            'throw' => true,
            'format' => DocumentFormat::ZUGFERD_EMBEDDED_PDF,
            'type' => DocumentType::DELIVERY_NOTE,
        ];

        yield 'type match, format mismatch' => [
            'throw' => true,
            'format' => DocumentFormat::ZUGFERD_EMBEDDED_PDF,
            'type' => DocumentType::INVOICE,
        ];

        yield 'type mismatch, format match' => [
            'throw' => true,
            'format' => DocumentFormat::HTML,
            'type' => DocumentType::DELIVERY_NOTE,
        ];

        yield 'type match, format match' => [
            'throw' => false,
            'format' => DocumentFormat::HTML,
            'type' => DocumentType::INVOICE,
        ];
    }

    public function testMapRenderersByFormat(): void
    {
        $registry = self::createRegistry();

        static::assertSame([], $registry->mapRenderersByFormat(DocumentType::DELIVERY_NOTE->value));

        $invoiceRenderers = $registry->mapRenderersByFormat(DocumentType::INVOICE->value);

        static::assertCount(1, $invoiceRenderers);
        static::assertArrayHasKey(DocumentFormat::HTML->value, $invoiceRenderers);
        static::assertSame(DocumentFormat::HTML->value, $invoiceRenderers[DocumentFormat::HTML->value]->getFormat());

        $creditNoteRenderers = $registry->mapRenderersByFormat(DocumentType::CREDIT_NOTE->value);

        static::assertCount(1, $creditNoteRenderers);
        static::assertArrayHasKey(DocumentFormat::PDF->value, $creditNoteRenderers);
        static::assertSame(DocumentFormat::PDF->value, $creditNoteRenderers[DocumentFormat::PDF->value]->getFormat());
    }

    public function testGetRendererThrowsOnDuplicateRendererRegistration(): void
    {
        static::expectExceptionObject(
            DocumentV2Exception::duplicateRenderer(DocumentFormat::HTML->value, DocumentType::INVOICE->value)
        );

        new DocumentRendererRegistry([
            new StaticDocumentRenderer(DocumentFormat::HTML, [DocumentType::INVOICE->value]),
            new StaticDocumentRenderer(DocumentFormat::HTML, [DocumentType::INVOICE->value]),
        ]);
    }

    public function testMapRenderersByFormatThrowsOnDuplicateRendererRegistration(): void
    {
        static::expectExceptionObject(
            DocumentV2Exception::duplicateRenderer(DocumentFormat::HTML->value, DocumentType::INVOICE->value)
        );

        new DocumentRendererRegistry([
            new StaticDocumentRenderer(DocumentFormat::HTML, [DocumentType::INVOICE->value]),
            new StaticDocumentRenderer(DocumentFormat::HTML, [DocumentType::INVOICE->value]),
        ]);
    }

    public function testMapRenderersByFormatCanBeCalledMultipleTimesWithGeneratorInput(): void
    {
        $registry = new DocumentRendererRegistry(self::createRendererGenerator());

        static::assertCount(1, $registry->mapRenderersByFormat(DocumentType::INVOICE->value));
        static::assertCount(1, $registry->mapRenderersByFormat(DocumentType::INVOICE->value));
    }

    private static function createRegistry(): DocumentRendererRegistry
    {
        return new DocumentRendererRegistry([
            new StaticDocumentRenderer(DocumentFormat::HTML, [DocumentType::INVOICE->value]),
            new StaticDocumentRenderer(DocumentFormat::PDF, [DocumentType::CREDIT_NOTE->value]),
        ]);
    }

    /**
     * @return \Generator<StaticDocumentRenderer>
     */
    private static function createRendererGenerator(): \Generator
    {
        yield new StaticDocumentRenderer(DocumentFormat::HTML, [DocumentType::INVOICE->value]);

        yield new StaticDocumentRenderer(DocumentFormat::PDF, [DocumentType::CREDIT_NOTE->value]);
    }
}

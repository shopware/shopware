<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Renderer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
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
    public function testGetRendererReturnsEngineForRegisteredFormat(): void
    {
        $registry = new DocumentRendererRegistry([
            new StaticDocumentRenderer(DocumentFormat::HTML),
            new StaticDocumentRenderer(DocumentFormat::PDF),
        ]);

        $renderer = $registry->getRenderer(DocumentFormat::PDF->value);

        static::assertSame(DocumentFormat::PDF->value, $renderer->getFormat());
    }

    public function testGetRendererThrowsForUnknownFormat(): void
    {
        $registry = new DocumentRendererRegistry([
            new StaticDocumentRenderer(DocumentFormat::HTML),
        ]);

        $this->expectExceptionObject(DocumentV2Exception::rendererNotFound(DocumentFormat::PDF->value));

        $registry->getRenderer(DocumentFormat::PDF->value);
    }

    public function testFirstRendererPerFormatWins(): void
    {
        $registry = new DocumentRendererRegistry([
            new StaticDocumentRenderer(DocumentFormat::PDF, fileExtension: 'first'),
            new StaticDocumentRenderer(DocumentFormat::PDF, fileExtension: 'second'),
        ]);

        static::assertSame('first', $registry->getRenderer(DocumentFormat::PDF->value)->getFileExtension());
    }

    public function testGetRenderersReturnsFormatKeyedMap(): void
    {
        $html = new StaticDocumentRenderer(DocumentFormat::HTML);
        $pdf = new StaticDocumentRenderer(DocumentFormat::PDF);

        $registry = new DocumentRendererRegistry([$html, $pdf]);

        static::assertSame(
            [
                DocumentFormat::HTML->value => $html,
                DocumentFormat::PDF->value => $pdf,
            ],
            $registry->getRenderers(),
        );
    }

    public function testAcceptsGeneratorInput(): void
    {
        $registry = new DocumentRendererRegistry(self::createRendererGenerator());

        static::assertSame(DocumentFormat::HTML->value, $registry->getRenderer(DocumentFormat::HTML->value)->getFormat());
        static::assertSame(DocumentFormat::PDF->value, $registry->getRenderer(DocumentFormat::PDF->value)->getFormat());
    }

    public function testGetFileExtensionReturnsEngineExtensionOrNull(): void
    {
        $registry = new DocumentRendererRegistry([
            new StaticDocumentRenderer(DocumentFormat::PDF),
            new StaticDocumentRenderer('custom_format', fileExtension: 'custom'),
        ]);

        static::assertSame(DocumentFormat::PDF->fileExtension(), $registry->getFileExtension(DocumentFormat::PDF->value));
        static::assertSame('custom', $registry->getFileExtension('custom_format'));
        static::assertNull($registry->getFileExtension('unknown_format'));
    }

    /**
     * @return \Generator<StaticDocumentRenderer>
     */
    private static function createRendererGenerator(): \Generator
    {
        yield new StaticDocumentRenderer(DocumentFormat::HTML);

        yield new StaticDocumentRenderer(DocumentFormat::PDF);
    }
}

<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Generation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Checkout\DocumentV2\DocumentType;
use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentDependencyResolver;
use Shopware\Core\Checkout\DocumentV2\Renderer\DocumentRendererRegistry;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Unit\Core\Checkout\DocumentV2\Fixtures\StaticDocumentRenderer;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(DocumentDependencyResolver::class)]
class DocumentDependencyResolverTest extends TestCase
{
    /**
     * @param list<string> $requested
     * @param list<string> $expected
     */
    #[DataProvider('resolveProvider')]
    public function testResolve(array $requested, array $expected): void
    {
        $resolver = $this->createResolver();

        $result = $resolver->resolve(DocumentType::INVOICE->value, $requested);
        static::assertSame($expected, $result);
    }

    /**
     * @return iterable<string, array{requested: list<string>, expected: list<string>}>
     */
    public static function resolveProvider(): iterable
    {
        yield 'single format without dependencies' => [
            'requested' => [DocumentFormat::HTML->value],
            'expected' => [DocumentFormat::HTML->value],
        ];

        yield 'single format with one dependencies' => [
            'requested' => [
                DocumentFormat::PDF->value,
            ],
            'expected' => [
                DocumentFormat::HTML->value,
                DocumentFormat::PDF->value,
            ],
        ];

        yield 'single format with transitive dependencies' => [
            'requested' => [
                DocumentFormat::ZUGFERD_EMBEDDED_PDF->value,
            ],
            'expected' => [
                DocumentFormat::HTML->value,
                DocumentFormat::ZUGFERD_XML->value,
                DocumentFormat::PDF->value,
                DocumentFormat::ZUGFERD_EMBEDDED_PDF->value,
            ],
        ];

        yield 'multiple requested formats with shared dependencies' => [
            'requested' => [
                DocumentFormat::PDF->value,
                DocumentFormat::ZUGFERD_EMBEDDED_PDF->value,
            ],
            'expected' => [
                DocumentFormat::HTML->value,
                DocumentFormat::ZUGFERD_XML->value,
                DocumentFormat::PDF->value,
                DocumentFormat::ZUGFERD_EMBEDDED_PDF->value,
            ],
        ];

        yield 'duplicated requested formats' => [
            'requested' => [
                DocumentFormat::PDF->value,
                DocumentFormat::PDF->value,
            ],
            'expected' => [
                DocumentFormat::HTML->value,
                DocumentFormat::PDF->value,
            ],
        ];
    }

    /**
     * @param list<string> $requested
     * @param list<StaticDocumentRenderer> $renderers
     */
    #[DataProvider('resolveExceptionProvider')]
    public function testResolveThrowsException(array $requested, array $renderers, DocumentV2Exception $exception): void
    {
        $resolver = $this->createResolver($renderers);

        static::expectExceptionObject($exception);

        $resolver->resolve(DocumentType::INVOICE->value, $requested);
    }

    /**
     * @return iterable<string, array{
     *     requested: list<string>,
     *     renderers: list<StaticDocumentRenderer>,
     *     exception: DocumentV2Exception
     * }>
     */
    public static function resolveExceptionProvider(): iterable
    {
        yield 'requested format has no renderer' => [
            'requested' => ['something'],
            'renderers' => self::createDefaultRenderers(),
            'exception' => DocumentV2Exception::rendererNotFound('something', DocumentType::INVOICE->value),
        ];

        yield 'dependency has no renderer' => [
            'requested' => [DocumentFormat::PDF->value],
            'renderers' => [
                new StaticDocumentRenderer(
                    DocumentFormat::PDF,
                    [DocumentType::INVOICE->value],
                    [DocumentFormat::HTML->value]
                ),
            ],
            'exception' => DocumentV2Exception::rendererNotFound(
                DocumentFormat::HTML->value,
                DocumentType::INVOICE->value,
            ),
        ];

        yield 'circular dependency between formats' => [
            'requested' => [DocumentFormat::PDF->value],
            'renderers' => [
                new StaticDocumentRenderer(
                    DocumentFormat::PDF,
                    [DocumentType::INVOICE->value],
                    [DocumentFormat::HTML->value]
                ),
                new StaticDocumentRenderer(
                    DocumentFormat::HTML,
                    [DocumentType::INVOICE->value],
                    [DocumentFormat::PDF->value]
                ),
            ],
            'exception' => DocumentV2Exception::circularRenderDependency([
                DocumentFormat::PDF->value,
                DocumentFormat::HTML->value,
            ]),
        ];

        yield 'self dependency' => [
            'requested' => [DocumentFormat::PDF->value],
            'renderers' => [
                new StaticDocumentRenderer(
                    DocumentFormat::PDF,
                    [DocumentType::INVOICE->value],
                    [DocumentFormat::PDF->value]
                ),
            ],
            'exception' => DocumentV2Exception::circularRenderDependency([
                DocumentFormat::PDF->value,
            ]),
        ];
    }

    /**
     * @param list<StaticDocumentRenderer>|null $renderers
     */
    private function createResolver(?array $renderers = null): DocumentDependencyResolver
    {
        $registry = new DocumentRendererRegistry($renderers ?? self::createDefaultRenderers());

        return new DocumentDependencyResolver($registry);
    }

    /**
     * @return list<StaticDocumentRenderer>
     */
    private static function createDefaultRenderers(): array
    {
        return [
            new StaticDocumentRenderer(
                DocumentFormat::HTML,
                [DocumentType::INVOICE->value],
                []
            ),
            new StaticDocumentRenderer(
                DocumentFormat::PDF,
                [DocumentType::INVOICE->value],
                [DocumentFormat::HTML->value]
            ),
            new StaticDocumentRenderer(
                DocumentFormat::ZUGFERD_XML,
                [DocumentType::INVOICE->value],
                []
            ),
            new StaticDocumentRenderer(
                DocumentFormat::ZUGFERD_EMBEDDED_PDF,
                [DocumentType::INVOICE->value],
                [DocumentFormat::PDF->value, DocumentFormat::ZUGFERD_XML->value]
            ),
        ];
    }
}

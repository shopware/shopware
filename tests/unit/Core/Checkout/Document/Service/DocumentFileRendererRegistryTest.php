<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Document\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\DocumentException;
use Shopware\Core\Checkout\Document\Renderer\RenderedDocument;
use Shopware\Core\Checkout\Document\Service\DocumentFileRendererRegistry;
use Shopware\Core\Checkout\Document\Service\HtmlRenderer;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\Locale\LocaleEntity;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(DocumentFileRendererRegistry::class)]
class DocumentFileRendererRegistryTest extends TestCase
{
    #[DataProvider('documentTypeRendererProvider')]
    public function testRender(RenderedDocument $document, \Closure $expectsClosure): void
    {
        $registry = $this->createMock(DocumentFileRendererRegistry::class);
        $registry->expects(static::exactly(1))
            ->method('render')->willReturn($document->getHtml());

        $locale = new LocaleEntity();
        $locale->setId(Uuid::randomHex());
        $locale->setCode('en-GB');

        $language = new LanguageEntity();
        $language->setId(Uuid::randomHex());
        $language->setLocale($locale);

        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());
        $order->setSalesChannelId(Uuid::randomHex());
        $order->setLanguageId($language->getId());
        $order->setLanguage($language);

        $document->setOrder($order);
        $document->setContext(Context::createDefaultContext());

        $content = $registry->render($document);

        $expectsClosure($content);
    }

    public function testThrowException(): void
    {
        static::expectException(DocumentException::class);
        static::expectExceptionMessage('File extension not supported: xml');

        $registry = new DocumentFileRendererRegistry([]);

        $registry->render(new RenderedDocument(
            '',
            '1001',
            'invoice',
            'xml',
            [],
            'application/xml'
        ));
    }

    public static function documentTypeRendererProvider(): \Generator
    {
        yield 'PDF renderer' => [
            new RenderedDocument(
                'pdf',
                '1001',
                'invoice',
            ),

            function (string $rendered): void {
                static::assertSame($rendered, 'pdf');
            },
        ];

        yield 'HTML renderer' => [
            new RenderedDocument(
                'html',
                '1001',
                'invoice',
                HtmlRenderer::FILE_EXTENSION,
                [],
                HtmlRenderer::FILE_CONTENT_TYPE
            ),

            function (string $rendered): void {
                static::assertSame($rendered, 'html');
            },
        ];
    }
}

<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Checkout\DocumentV2\Struct\RenderedDocument;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(RenderedDocument::class)]
class RenderedDocumentTest extends TestCase
{
    public function testDefaultsToPdf(): void
    {
        $document = new RenderedDocument();

        static::assertSame(DocumentFormat::PDF->fileExtension(), $document->getFileExtension());
        static::assertSame(DocumentFormat::PDF->mimeType(), $document->getContentType());
    }

    public function testContentTypeFallsBackToPdfWhenUnset(): void
    {
        $document = new RenderedDocument(contentType: DocumentFormat::ZUGFERD_XML->mimeType());

        $document->setContentType(null);

        static::assertSame(DocumentFormat::PDF->mimeType(), $document->getContentType());
    }

    public function testPageLayoutFallsBackToPortraitA4(): void
    {
        $document = new RenderedDocument();

        static::assertSame('portrait', $document->getPageOrientation());
        static::assertSame('a4', $document->getPageSize());
    }

    public function testConstructorArgumentsAreExposed(): void
    {
        $document = new RenderedDocument(
            number: '1000',
            name: 'invoice.xml',
            fileExtension: DocumentFormat::ZUGFERD_XML->fileExtension(),
            config: ['pageOrientation' => 'landscape', 'pageSize' => 'a5'],
            contentType: DocumentFormat::ZUGFERD_XML->mimeType(),
            content: '<invoice/>',
        );

        static::assertSame('1000', $document->getNumber());
        static::assertSame('invoice.xml', $document->getName());
        static::assertSame(DocumentFormat::ZUGFERD_XML->fileExtension(), $document->getFileExtension());
        static::assertSame(DocumentFormat::ZUGFERD_XML->mimeType(), $document->getContentType());
        static::assertSame('<invoice/>', $document->getContent());
        static::assertSame(['pageOrientation' => 'landscape', 'pageSize' => 'a5'], $document->getConfig());
        static::assertSame('landscape', $document->getPageOrientation());
        static::assertSame('a5', $document->getPageSize());
    }

    public function testParametersAreCollected(): void
    {
        $document = new RenderedDocument();

        $document->setParameters(['order' => 'data']);
        $document->addParameter('intraCommunityDelivery', true);

        static::assertSame(['order' => 'data', 'intraCommunityDelivery' => true], $document->getParameters());
    }
}

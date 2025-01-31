<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Document\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\Renderer\RenderedDocument;
use Shopware\Core\Checkout\Document\Service\XmlRenderer;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Annotation\DisabledFeatures;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(XmlRenderer::class)]
class XmlRendererTest extends TestCase
{
    #[DisabledFeatures(['v6.7.0.0'])]
    public function testGetContentType(): void
    {
        $htmlRenderer = new XmlRenderer();

        static::assertEquals('application/xml', $htmlRenderer->getContentType());
    }

    #[DisabledFeatures(['v6.7.0.0'])]
    public function testRender(): void
    {
        $htmlRenderer = new XmlRenderer();
        $document = new RenderedDocument();
        $document->setContent('test123');

        static::assertEquals('test123', $htmlRenderer->render($document));
    }
}

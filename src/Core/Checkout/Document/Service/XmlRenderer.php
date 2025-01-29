<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Service;

use Shopware\Core\Checkout\Document\Renderer\RenderedDocument;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

#[Package('after-sales')]
class XmlRenderer extends AbstractDocumentTypeRenderer
{
    public const FILE_EXTENSION = 'xml';

    public const FILE_CONTENT_TYPE = 'application/xml';

    public function getContentType(): string
    {
        return self::FILE_CONTENT_TYPE;
    }

    public function render(RenderedDocument $document): string
    {
        return $document->getContent();
    }

    public function getDecorated(): AbstractDocumentTypeRenderer
    {
        throw new DecorationPatternException(self::class);
    }
}

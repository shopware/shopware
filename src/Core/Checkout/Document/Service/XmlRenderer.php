<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Service;

use Shopware\Core\Checkout\Document\Renderer\RenderedDocument;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

/**
 * @deprecated tag:v6.7.0 - Will be removed. DocumentFileRendererRegistry will not be called every time
 */
#[Package('after-sales')]
class XmlRenderer extends AbstractDocumentTypeRenderer
{
    public const FILE_EXTENSION = 'xml';

    public const FILE_CONTENT_TYPE = 'application/xml';

    public function getContentType(): string
    {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', 'Class will be removed without replacement.');

        return self::FILE_CONTENT_TYPE;
    }

    public function render(RenderedDocument $document): string
    {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', 'Class will be removed without replacement.');

        return $document->getContent();
    }

    public function getDecorated(): AbstractDocumentTypeRenderer
    {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', 'Class will be removed without replacement.');

        throw new DecorationPatternException(self::class);
    }
}

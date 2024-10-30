<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Extension;

use Shopware\Core\Checkout\Document\Renderer\RenderedDocument;
use Shopware\Core\Framework\Extensions\Extension;

/**
 * @experimental stableVersion:v6.7.0 feature:EXTENSION_SYSTEM
 *
 * @extends Extension<string>
 */
final class PdfRendererExtension extends Extension
{
    public const NAME = 'pdf-renderer';

    /**
     * @internal shopware owns the __constructor, but the properties are public API
     */
    public function __construct(public readonly RenderedDocument $document) {}
}

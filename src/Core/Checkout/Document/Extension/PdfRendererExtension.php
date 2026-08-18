<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Extension;

use Shopware\Core\Checkout\Document\Renderer\RenderedDocument;
use Shopware\Core\Framework\Extensions\Extension;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

/**
 * @public this class is used as type-hint for all event listeners, so the class string is "public consumable" API
 *
 * @title Rendering of the PDF document
 *
 * @description This event allows manipulation of the input and output when rendering PDF documents.
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<string>
 *
 * @deprecated tag:v6.9.0 - Will be removed.
 */
#[Package('after-sales')]
final class PdfRendererExtension extends Extension
{
    public const NAME = 'pdf-renderer';

    /**
     * @internal shopware owns the __constructor, but the properties are public API
     */
    public function __construct(public readonly RenderedDocument $document)
    {
        Feature::triggerDeprecationOrThrow('v6.9.0.0', 'PdfRendererExtension is deprecated and will be removed with document generation v1.');
    }
}

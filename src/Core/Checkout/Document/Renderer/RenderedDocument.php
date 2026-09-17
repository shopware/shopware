<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Renderer;

use Shopware\Core\Framework\Log\Package;

if (!class_exists(\Shopware\Core\Checkout\DocumentV2\Struct\RenderedDocument::class)) {
    /**
     * @deprecated tag:v6.9.0 - compatibility alias, this file is deleted together with document generation v1.
     * Use \Shopware\Core\Checkout\DocumentV2\Struct\RenderedDocument instead.
     */
    #[Package('after-sales')]
    class RenderedDocument extends \Shopware\Core\Checkout\DocumentV2\Struct\RenderedDocument
    {
    }
}

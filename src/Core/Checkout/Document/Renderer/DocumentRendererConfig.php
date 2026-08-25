<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Renderer;

use Shopware\Core\Framework\Log\Package;

/**
 * @deprecated tag:v6.9.0 reason:experimental-replacement - Will be removed. Use {@link \Shopware\Core\Checkout\DocumentV2\Struct\RenderInput} instead.
 */
#[Package('after-sales')]
final class DocumentRendererConfig
{
    public string $deepLinkCode = '';
}

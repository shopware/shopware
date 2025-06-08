<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Renderer;

use Shopware\Core\Framework\Log\Package;

#[Package('after-sales')]
final class DocumentRendererConfig
{
    public string $deepLinkCode = '';
}

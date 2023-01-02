<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Renderer;

use Shopware\Core\Framework\Log\Package;
/**
 * @package customer-order
 */
#[Package('customer-order')]
final class DocumentRendererConfig
{
    public string $deepLinkCode = '';
}

<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Exception;

use Shopware\Core\Framework\Adapter\Twig\Exception\StringTemplateRenderingException;
use Shopware\Core\Framework\Log\Package;

/**
 * @deprecated tag:v6.6.0 - Exception will be removed
 */
#[Package('sales-channel')]
class RenderProductException extends StringTemplateRenderingException
{
}

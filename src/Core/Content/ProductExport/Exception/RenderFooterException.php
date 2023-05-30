<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Exception;

use Shopware\Core\Framework\Adapter\Twig\Exception\StringTemplateRenderingException;
use Shopware\Core\Framework\Log\Package;

#[Package('sales-channel')]
class RenderFooterException extends StringTemplateRenderingException
{
}

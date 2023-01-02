<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Adapter\Twig\Exception\StringTemplateRenderingException;

/**
 * @package inventory
 */
#[Package('inventory')]
class RenderHeaderException extends StringTemplateRenderingException
{
}

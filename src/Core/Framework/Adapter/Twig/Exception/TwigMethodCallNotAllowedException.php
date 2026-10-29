<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\Exception;

use Shopware\Core\Framework\Adapter\AdapterException;
use Shopware\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
class TwigMethodCallNotAllowedException extends AdapterException
{
}

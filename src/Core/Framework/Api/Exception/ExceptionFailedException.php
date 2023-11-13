<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Exception;

use Shopware\Core\Framework\Log\Package;

/**
 * @deprecated tag:v6.6.0 - Will be removed, use Shopware\Core\Framework\Api\Exception\ExpectationFailedException instead
 */
#[Package('core')]
class ExceptionFailedException extends ExpectationFailedException
{
}

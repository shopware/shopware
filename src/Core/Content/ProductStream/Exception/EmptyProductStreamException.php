<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductStream\Exception;

use Shopware\Core\Framework\Log\Package;

/**
 * Thrown when a product stream is valid but has no filters left (api_filter is []).
 *
 * Extends {@see NoFilterException} on purpose so existing `catch (NoFilterException)` handlers
 * keep working, while callers can catch this narrower type to treat an emptied stream as "no filtering".
 *
 * @codeCoverageIgnore
 */
#[Package('inventory')]
class EmptyProductStreamException extends NoFilterException
{
}

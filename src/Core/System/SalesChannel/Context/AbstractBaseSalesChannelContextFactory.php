<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Context;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\BaseSalesChannelContext;

/**
 * Loads customer-independent information for a sales channel, which could be cached separately.
 *
 * @internal
 */
#[Package('framework')]
abstract class AbstractBaseSalesChannelContextFactory
{
    /**
     * @param array<string, mixed> $options
     */
    abstract public function create(string $salesChannelId, array $options = []): BaseSalesChannelContext;
}

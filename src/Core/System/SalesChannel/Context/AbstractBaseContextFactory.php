<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Context;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\BaseContext;

/**
 * @internal
 */
#[Package('core')]
abstract class AbstractBaseContextFactory
{
    /**
     * @param array<string, mixed> $options
     */
    abstract public function create(string $salesChannelId, array $options = []): BaseContext;
}

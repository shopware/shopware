<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Deployment;

use Shopware\Core\Framework\FrameworkException;
use Shopware\Core\Framework\Log\Package;

/**
 * Operator-facing deployment mode that forbids HTTP to Shopware-operated SaaS and content hosts.
 *
 * @internal
 */
#[Package('framework')]
final class AirGappedMode
{
    public function __construct(
        private readonly bool $enabled,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * @throws FrameworkException
     */
    public function denyShopwareOperatedHttp(): void
    {
        if ($this->enabled) {
            throw FrameworkException::airGapped();
        }
    }
}

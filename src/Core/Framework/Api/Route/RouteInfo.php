<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Route;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('core')]
readonly class RouteInfo
{
    /**
     * @param string[] $methods
     */
    public function __construct(
        public string $path,
        public array $methods,
    ) {
    }
}

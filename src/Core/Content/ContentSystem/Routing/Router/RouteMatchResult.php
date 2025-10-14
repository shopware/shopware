<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Routing\Router;

use Shopware\Core\Content\ContentSystem\Routing\Entity\ContentRouteEntity;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
final readonly class RouteMatchResult
{
    /**
     * @param array<string, mixed> $parameters
     */
    public function __construct(
        public ContentRouteEntity $route,
        public array $parameters
    ) {
    }
}

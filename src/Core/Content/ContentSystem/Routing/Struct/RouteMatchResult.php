<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Routing\Struct;

use Shopware\Core\Content\ContentSystem\Routing\Entity\ContentRouteEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @final
 */
#[Package('discovery')]
class RouteMatchResult extends Struct
{
    /**
     * @param array<string, mixed> $parameters
     */
    public function __construct(
        protected ContentRouteEntity $route,
        protected array $parameters
    ) {
    }

    public function getRoute(): ContentRouteEntity
    {
        return $this->route;
    }

    /**
     * @return array<string, mixed>
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function getParameter(string $key): mixed
    {
        return $this->parameters[$key] ?? null;
    }

    public function getApiAlias(): string
    {
        return 'content_route_match_result';
    }
}

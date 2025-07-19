<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('inventory')]
class HreflangLoaderParameter
{
    protected string $route;

    /**
     * @var array<string, mixed>
     */
    protected array $routeParameters;

    protected SalesChannelContext $salesChannelContext;

    /**
     * @param array<string, mixed> $routeParameters
     */
    public function __construct(
        string $route,
        array $routeParameters,
        SalesChannelContext $salesChannelContext
    ) {
        $this->route = $route;
        $this->routeParameters = $routeParameters;
        $this->salesChannelContext = $salesChannelContext;
    }

    public function getRoute(): string
    {
        return $this->route;
    }

    /**
     * @return array<string, mixed>
     */
    public function getRouteParameters(): array
    {
        return $this->routeParameters;
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }
}

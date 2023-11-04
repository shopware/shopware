<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('sales-channel')]
class HreflangLoaderParameter
{
    /**
     * @var string
     */
    protected $route;

    /**
     * @var array
     */
    protected $routeParameters;

    /**
     * @var SalesChannelContext
     */
    protected $salesChannelContext;

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

    public function getRouteParameters(): array
    {
        return $this->routeParameters;
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }
}

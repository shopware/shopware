<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Navigation;

use Shopware\Core\Framework\Struct\Struct;

class NavigationPageletRequest extends Struct
{
    /**
     * @var string|null
     */
    protected $navigationId;

    /**
     * @var string
     */
    protected $route;

    /**
     * @var array
     */
    protected $routeParams;

    /**
     * @return string
     */
    public function getRoute(): string
    {
        return $this->route;
    }

    /**
     * @param string $route
     */
    public function setRoute(string $route): void
    {
        $this->route = $route;
    }

    /**
     * @return array
     */
    public function getRouteParams(): array
    {
        return $this->routeParams;
    }

    /**
     * @param array $routeParams
     */
    public function setRouteParams(array $routeParams): void
    {
        $this->routeParams = $routeParams;
    }

    /**
     * @return string|null
     */
    public function getNavigationId(): ?string
    {
        return $this->navigationId;
    }

    /**
     * @param string|null $navigationId
     */
    public function setNavigationId(?string $navigationId): void
    {
        $this->navigationId = $navigationId;
    }
}

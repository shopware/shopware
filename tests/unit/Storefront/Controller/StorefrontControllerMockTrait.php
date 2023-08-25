<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Controller;

use Shopware\Core\Framework\Script\Execution\Hook;
use Symfony\Component\HttpFoundation\Response;

trait StorefrontControllerMockTrait
{
    public string $renderStorefrontView;

    /**
     * @var array<mixed>
     */
    public array $renderStorefrontParameters;

    public Hook $calledHook;

    public string $forwardToRoute;

    /**
     * @var array<string, mixed>
     */
    public array $forwardToRouteAttributes;

    /**
     * @var array<string, mixed>
     */
    public array $forwardToRouteParameters;

    /**
     * @param array<string, mixed> $parameters
     */
    protected function renderStorefront(string $view, array $parameters = []): Response
    {
        $this->renderStorefrontView = $view;
        $this->renderStorefrontParameters = $parameters;

        return new Response();
    }

    /**
     * @param array<string, mixed> $attributes
     * @param array<string, mixed> $routeParameters
     */
    protected function forwardToRoute(string $routeName, array $attributes = [], array $routeParameters = []): Response
    {
        $this->forwardToRoute = $routeName;
        $this->forwardToRouteAttributes = $attributes;
        $this->forwardToRouteParameters = $routeParameters;

        return new Response();
    }

    protected function hook(Hook $hook): void
    {
        $this->calledHook = $hook;
    }
}

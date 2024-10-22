<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Controller;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Framework\Script\Execution\Hook;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @deprecated tag:v6.7.0 - reason:becomes-internal - Will be internal in v6.7.0
 */
trait StorefrontControllerMockTrait
{
    public string $renderStorefrontView;

    /**
     * @var array<string, mixed>
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
     * @var array<string, array<int, array{parameters: array<string, mixed>, status: int}>>
     */
    public array $redirected = [];

    /**
     * @var array<string, array<int, mixed>>
     */
    public array $flashBag = [];

    public function reset(): void
    {
        $this->flashBag = [];
        $this->redirected = [];
    }

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

        return new Response('forward to ' . $routeName);
    }

    /**
     * @param array<string, mixed> $parameters
     */
    protected function redirectToRoute(string $route, array $parameters = [], int $status = Response::HTTP_FOUND): RedirectResponse
    {
        $this->redirected[$route][] = [
            'parameters' => $parameters,
            'status' => $status,
        ];

        return new RedirectResponse($route, $status);
    }

    protected function hook(Hook $hook): void
    {
        $this->calledHook = $hook;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    protected function trans(string $snippet, array $parameters = []): string
    {
        return $snippet;
    }

    protected function addFlash(string $type, mixed $message): void
    {
        $this->flashBag[$type][] = $message;
    }

    protected function addCartErrors(Cart $cart, ?\Closure $filter = null): void
    {
        // nothing
    }

    /**
     * @param array<string|int, mixed> $parameters
     */
    protected function generateUrl(string $route, array $parameters = [], int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH): string
    {
        return 'url:' . $route;
    }
}

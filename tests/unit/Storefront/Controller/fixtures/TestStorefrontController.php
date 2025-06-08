<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Controller\fixtures;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Execution\Hook;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
class TestStorefrontController extends StorefrontController
{
    /**
     * @param array<string, mixed> $parameters
     */
    public function testRenderStorefront(string $view, array $parameters = []): Response
    {
        return $this->renderStorefront($view, $parameters);
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function testTrans(string $snippet, array $parameters = []): string
    {
        return $this->trans($snippet, $parameters);
    }

    public function testCreateActionResponse(Request $request): Response
    {
        return $this->createActionResponse($request);
    }

    /**
     * @param array<string, mixed> $attributes
     * @param array<string, mixed> $routeParameters
     */
    public function testForwardToRoute(string $routeName, array $attributes = [], array $routeParameters = []): Response
    {
        return $this->forwardToRoute($routeName, $attributes, $routeParameters);
    }

    /**
     * @return array<string, mixed>
     */
    public function testDecodeParam(Request $request, string $param): array
    {
        return $this->decodeParam($request, $param);
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function testRedirectToRoute(string $route, array $parameters = [], int $status = Response::HTTP_FOUND): RedirectResponse
    {
        return $this->redirectToRoute($route, $parameters, $status);
    }

    public function testAddCartErrors(Cart $cart, ?\Closure $filter = null): void
    {
        $this->addCartErrors($cart, $filter);
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function testRenderView(string $view, array $parameters = []): string
    {
        return $this->renderView($view, $parameters);
    }

    public function testHook(Hook $hook): void
    {
        $this->hook($hook);
    }
}

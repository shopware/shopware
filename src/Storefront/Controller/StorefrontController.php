<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Event\StorefrontRenderEvent;
use Shopware\Storefront\Framework\Routing\Router;
use Shopware\Storefront\Theme\Twig\ThemeTemplateFinder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

abstract class StorefrontController extends AbstractController
{
    protected function renderStorefront(string $view, array $parameters = [], ?Response $response = null): Response
    {
        $request = $this->get('request_stack')->getCurrentRequest();

        $master = $this->get('request_stack')->getMasterRequest();

        $context = $master->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT);

        $activeThemeName = $request->attributes->get(SalesChannelRequest::ATTRIBUTE_THEME_NAME);
        $activeThemeBaseName = $request->attributes->get(SalesChannelRequest::ATTRIBUTE_THEME_BASE_NAME);

        $view = $this->resolveView($view, $activeThemeName, $activeThemeBaseName);

        $event = new StorefrontRenderEvent($view, $parameters, $request, $context);

        $this->get('event_dispatcher')->dispatch($event);

        return $this->render($view, $event->getParameters(), $response);
    }

    protected function trans(string $snippet, array $parameters = []): string
    {
        return $this->container
            ->get('translator')
            ->trans($snippet, $parameters);
    }

    protected function createActionResponse(Request $request): Response
    {
        if ($request->get('redirectTo')) {
            $params = $this->decodeParam($request, 'redirectParameters');

            return $this->redirectToRoute($request->get('redirectTo'), $params);
        }

        if ($request->get('forwardTo')) {
            $params = $this->decodeParam($request, 'forwardParameters');

            return $this->forwardToRoute($request->get('forwardTo'), $params);
        }

        return new Response();
    }

    protected function forwardToRoute(string $routeName, array $parameters = [], array $routeParameters = []): Response
    {
        $router = $this->container->get('router');

        $url = $this->generateUrl($routeName, $routeParameters, Router::PATH_INFO);

        // for the route matching the request method is set to "GET" because
        // this method is not ought to be used as a post passthrough
        // rather it shall return templates or redirects to display results of the request ahead
        $method = $router->getContext()->getMethod();
        $router->getContext()->setMethod(Request::METHOD_GET);

        $route = $router->match($url);
        $router->getContext()->setMethod($method);

        $request = $this->container->get('request_stack')->getCurrentRequest();
        $parameters = array_merge($request->attributes->all(), $parameters);

        return $this->forward($route['_controller'], $parameters);
    }

    protected function resolveView(string $view, ?string $activeThemeName, ?string $activeThemeBaseName): string
    {
        /** @var ThemeTemplateFinder $templateFinder */
        $templateFinder = $this->get(ThemeTemplateFinder::class);

        return $templateFinder->find($view, false, null, $activeThemeName, $activeThemeBaseName);
    }

    /**
     * @throws CustomerNotLoggedInException
     */
    protected function denyAccessUnlessLoggedIn(): void
    {
        /** @var RequestStack $requestStack */
        $requestStack = $this->get('request_stack');
        $request = $requestStack->getMasterRequest();

        if (!$request) {
            return;
        }

        /** @var SalesChannelContext|null $context */
        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT);

        if ($context && $context->getCustomer() && $context->getCustomer()->getGuest() === false) {
            return;
        }

        throw new CustomerNotLoggedInException();
    }

    protected function decodeParam(Request $request, string $param): array
    {
        $params = $request->get($param);

        if (is_string($params)) {
            $params = json_decode($params, true);
        }

        if (empty($params)) {
            $params = [];
        }

        return $params;
    }
}

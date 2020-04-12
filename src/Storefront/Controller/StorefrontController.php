<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\Framework\Adapter\Twig\TemplateFinder;
use Shopware\Core\Framework\Routing\RequestTransformerInterface;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Event\StorefrontRenderEvent;
use Shopware\Storefront\Framework\Routing\RequestTransformer;
use Shopware\Storefront\Framework\Routing\Router;
use Shopware\Storefront\Framework\Routing\StorefrontResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\EventListener\AbstractSessionListener;

abstract class StorefrontController extends AbstractController
{
    protected function renderStorefront(string $view, array $parameters = []): Response
    {
        $request = $this->get('request_stack')->getCurrentRequest();

        $salesChannelContext = $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT);

        $view = $this->get(TemplateFinder::class)->find($view, false, null);

        $event = new StorefrontRenderEvent($view, $parameters, $request, $salesChannelContext);
        $this->get('event_dispatcher')->dispatch($event);

        $response = $this->render($view, $event->getParameters(), new StorefrontResponse());

        if (!$response instanceof StorefrontResponse) {
            throw new \RuntimeException('Symfony render implementation changed. Providing a response is no longer supported');
        }

        $host = $request->attributes->get(RequestTransformer::SALES_CHANNEL_ABSOLUTE_BASE_URL)
            . $request->attributes->get(RequestTransformer::SALES_CHANNEL_BASE_URL);

        /** @var SeoUrlPlaceholderHandlerInterface $seoUrlReplacer */
        $seoUrlReplacer = $this->container->get(SeoUrlPlaceholderHandlerInterface::class);
        $response->setContent(
            $seoUrlReplacer->replace($response->getContent(), $host, $salesChannelContext)
        );

        /* @var StorefrontResponse $response */
        $response->setData($parameters);
        $response->setContext($salesChannelContext);
        $response->headers->set(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER, '1');
        $response->headers->set('Content-Type', 'text/html');

        return $response;
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

            return $this->forwardToRoute($request->get('forwardTo'), [], $params);
        }

        return new Response();
    }

    protected function forwardToRoute(string $routeName, array $attributes = [], array $routeParameters = []): Response
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

        $attributes = array_merge(
            $this->get(RequestTransformerInterface::class)->extractInheritableAttributes($request),
            $route,
            $attributes
        );

        return $this->forward($route['_controller'], $attributes, $routeParameters);
    }

    /**
     * @throws CustomerNotLoggedInException
     */
    protected function denyAccessUnlessLoggedIn(bool $allowGuest = false): void
    {
        /** @var RequestStack $requestStack */
        $requestStack = $this->get('request_stack');
        $request = $requestStack->getCurrentRequest();

        if (!$request) {
            throw new CustomerNotLoggedInException();
        }

        /** @var SalesChannelContext|null $context */
        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT);

        if (
            $context
            && $context->getCustomer()
            && (
                $allowGuest === true
                || $context->getCustomer()->getGuest() === false
            )
        ) {
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

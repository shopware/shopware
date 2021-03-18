<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\Framework\Adapter\Twig\TemplateFinder;
use Shopware\Core\Framework\Routing\RequestTransformerInterface;
use Shopware\Core\PlatformRequest;
use Shopware\Storefront\Event\StorefrontRenderEvent;
use Shopware\Storefront\Framework\Routing\RequestTransformer;
use Shopware\Storefront\Framework\Routing\Router;
use Shopware\Storefront\Framework\Routing\StorefrontResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\EventListener\AbstractSessionListener;

abstract class StorefrontController extends AbstractController
{
    public const SUCCESS = 'success';
    public const DANGER = 'danger';
    public const INFO = 'info';
    public const WARNING = 'warning';

    protected function renderStorefront(string $view, array $parameters = []): Response
    {
        $request = $this->get('request_stack')->getCurrentRequest();

        $salesChannelContext = $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT);

        $view = $this->get(TemplateFinder::class)->find($view, false, null);

        $event = new StorefrontRenderEvent($view, $parameters, $request, $salesChannelContext);
        $this->get('event_dispatcher')->dispatch($event);

        $response = $this->render($event->getView(), $event->getParameters(), new StorefrontResponse());

        if (!$response instanceof StorefrontResponse) {
            throw new \RuntimeException('Symfony render implementation changed. Providing a response is no longer supported');
        }

        $host = $request->attributes->get(RequestTransformer::STOREFRONT_URL);

        $seoUrlReplacer = $this->get(SeoUrlPlaceholderHandlerInterface::class);
        $content = $response->getContent();
        if ($content !== false) {
            $response->setContent(
                $seoUrlReplacer->replace($content, $host, $salesChannelContext)
            );
        }

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
        $router = $this->get('router');

        $url = $this->generateUrl($routeName, $routeParameters, Router::PATH_INFO);

        // for the route matching the request method is set to "GET" because
        // this method is not ought to be used as a post passthrough
        // rather it shall return templates or redirects to display results of the request ahead
        $method = $router->getContext()->getMethod();
        $router->getContext()->setMethod(Request::METHOD_GET);

        $route = $router->match($url);
        $router->getContext()->setMethod($method);

        $request = $this->get('request_stack')->getCurrentRequest();

        $attributes = array_merge(
            $this->get(RequestTransformerInterface::class)->extractInheritableAttributes($request),
            $route,
            $attributes,
            ['_route_params' => $routeParameters]
        );

        return $this->forward($route['_controller'], $attributes, $routeParameters);
    }

    protected function decodeParam(Request $request, string $param): array
    {
        $params = $request->get($param);

        if (\is_string($params)) {
            $params = json_decode($params, true);
        }

        if (empty($params)) {
            $params = [];
        }

        return $params;
    }

    protected function addCartErrors(Cart $cart): void
    {
        $groups = [
            'info' => $cart->getErrors()->getNotices(),
            'warning' => $cart->getErrors()->getWarnings(),
            'danger' => $cart->getErrors()->getErrors(),
        ];

        foreach ($groups as $type => $errors) {
            foreach ($errors as $error) {
                $parameters = [];
                foreach ($error->getParameters() as $key => $value) {
                    $parameters['%' . $key . '%'] = $value;
                }

                $message = $this->trans('checkout.' . $error->getMessageKey(), $parameters);

                $this->addFlash($type, $message);
            }
        }
    }
}

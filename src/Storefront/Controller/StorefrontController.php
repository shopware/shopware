<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Checkout\Cart\Error\ErrorRoute;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\Framework\Adapter\Twig\TemplateFinder;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Routing\RequestTransformerInterface;
use Shopware\Core\Framework\Script\Execution\Hook;
use Shopware\Core\Framework\Script\Execution\ScriptExecutor;
use Shopware\Core\PlatformRequest;
use Shopware\Core\Profiling\Profiler;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Event\StorefrontRenderEvent;
use Shopware\Storefront\Framework\Routing\RequestTransformer;
use Shopware\Storefront\Framework\Routing\Router;
use Shopware\Storefront\Framework\Routing\StorefrontResponse;
use Shopware\Storefront\Framework\Twig\Extension\IconCacheTwigFilter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\EventListener\AbstractSessionListener;
use Twig\Environment;

abstract class StorefrontController extends AbstractController
{
    public const SUCCESS = 'success';
    public const DANGER = 'danger';
    public const INFO = 'info';
    public const WARNING = 'warning';

    private Environment $twig;

    public function setTwig(Environment $twig): void
    {
        $this->twig = $twig;
    }

    protected function renderStorefront(string $view, array $parameters = []): Response
    {
        $request = $this->container->get('request_stack')->getCurrentRequest();

        if ($request === null) {
            $request = new Request();
        }

        $salesChannelContext = $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT);

        /* @feature-deprecated $view will be original template in StorefrontRenderEvent from 6.5.0.0 */
        if (Feature::isActive('FEATURE_NEXT_17275')) {
            $event = new StorefrontRenderEvent($view, $parameters, $request, $salesChannelContext);
        } else {
            $inheritedView = $this->getTemplateFinder()->find($view);

            $event = new StorefrontRenderEvent($inheritedView, $parameters, $request, $salesChannelContext);
        }
        $this->container->get('event_dispatcher')->dispatch($event);

        $iconCacheEnabled = $this->getSystemConfigService()->get('core.storefrontSettings.iconCache');

        /** @deprecated tag:v6.5.0 - icon cache will be true by default. */
        if ($iconCacheEnabled || (Feature::isActive('v6.5.0.0') && $iconCacheEnabled === null)) {
            IconCacheTwigFilter::enable();
        }

        $response = Profiler::trace('twig-rendering', function () use ($view, $event) {
            return $this->render($view, $event->getParameters(), new StorefrontResponse());
        });

        /** @deprecated tag:v6.5.0 - icon cache will be true by default. */
        if ($iconCacheEnabled || (Feature::isActive('v6.5.0.0') && $iconCacheEnabled === null)) {
            IconCacheTwigFilter::disable();
        }

        if (!$response instanceof StorefrontResponse) {
            throw new \RuntimeException('Symfony render implementation changed. Providing a response is no longer supported');
        }

        $host = $request->attributes->get(RequestTransformer::STOREFRONT_URL);

        $seoUrlReplacer = $this->container->get(SeoUrlPlaceholderHandlerInterface::class);
        $content = $response->getContent();
        if ($content !== false) {
            $response->setContent(
                $seoUrlReplacer->replace($content, $host, $salesChannelContext)
            );
        }

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
        if ($request->get('redirectTo') || $request->get('redirectTo') === '') {
            $params = $this->decodeParam($request, 'redirectParameters');

            $redirectTo = $request->get('redirectTo');

            if ($redirectTo) {
                return $this->redirectToRoute($redirectTo, $params);
            }

            return $this->redirectToRoute('frontend.home.page', $params);
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

        if ($request === null) {
            $request = new Request();
        }

        $attributes = array_merge(
            $this->container->get(RequestTransformerInterface::class)->extractInheritableAttributes($request),
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

    protected function addCartErrors(Cart $cart, ?\Closure $filter = null): void
    {
        $errors = $cart->getErrors();
        if ($filter !== null) {
            $errors = $errors->filter($filter);
        }

        $groups = [
            'info' => $errors->getNotices(),
            'warning' => $errors->getWarnings(),
            'danger' => $errors->getErrors(),
        ];

        $request = $this->container->get('request_stack')->getMainRequest();
        $exists = [];

        if ($request && $request->hasSession() && method_exists($session = $request->getSession(), 'getFlashBag')) {
            $exists = $session->getFlashBag()->peekAll();
        }

        $flat = [];
        foreach ($exists as $messages) {
            $flat = array_merge($flat, $messages);
        }

        /** @var array<string, Error[]> $groups */
        foreach ($groups as $type => $errors) {
            foreach ($errors as $error) {
                $parameters = [];

                foreach ($error->getParameters() as $key => $value) {
                    $parameters['%' . $key . '%'] = $value;
                }

                if ($error->getRoute() instanceof ErrorRoute) {
                    $parameters['%url%'] = $this->generateUrl(
                        $error->getRoute()->getKey(),
                        $error->getRoute()->getParams()
                    );
                }

                $message = $this->trans('checkout.' . $error->getMessageKey(), $parameters);

                if (\in_array($message, $flat, true)) {
                    continue;
                }

                $this->addFlash($type, $message);
            }
        }
    }

    protected function renderView(string $view, array $parameters = []): string
    {
        $view = $this->getTemplateFinder()->find($view);

        if (isset($this->twig)) {
            return $this->twig->render($view, $parameters);
        }

        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            sprintf('Class %s does not have twig injected. Add to your service definition a method call to setTwig with the twig instance', static::class)
        );

        return parent::renderView($view, $parameters);
    }

    protected function getTemplateFinder(): TemplateFinder
    {
        return $this->container->get(TemplateFinder::class);
    }

    protected function hook(Hook $hook): void
    {
        $this->container->get(ScriptExecutor::class)->execute($hook);
    }

    protected function getSystemConfigService(): SystemConfigService
    {
        return $this->container->get(SystemConfigService::class);
    }
}

<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Controller;

use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Framework\Twig\TemplateFinder;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

abstract class StorefrontController extends AbstractController
{
    /**
     * {@inheritdoc}
     */
    protected function renderStorefront($view, array $parameters = [], ?Response $response = null): Response
    {
        $view = $this->resolveView($view);

        return $this->render($view, $parameters, $response);
    }

    protected function createActionResponse(Request $request): Response
    {
        if ($request->get('redirectTo')) {
            return $this->redirectToRoute($request->get('redirectTo'));
        }

        if ($request->get('forwardTo')) {
            $router = $this->container->get('router');

            $url = $this->generateUrl($request->get('forwardTo'));

            $route = $router->match($url);

            return $this->forward($route['_controller']);
        }

        return new Response();
    }

    protected function resolveView(string $view): string
    {
        //remove static template inheritance prefix
        if (strpos($view, '@') === 0) {
            $viewParts = explode('/', $view);
            array_shift($viewParts);
            $view = implode('/', $viewParts);
        }

        /** @var TemplateFinder $templateFinder */
        $templateFinder = $this->get(TemplateFinder::class);

        return $templateFinder->find($view);
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

    protected function redirectToRouteAndReturn(string $route, Request $request, array $parameters = [], $status = 302): RedirectResponse
    {
        $default = [
            'redirectTo' => urlencode($request->getRequestUri()),
        ];
        $parameters = array_merge($default, $parameters);

        return $this->redirectToRoute($route, $parameters, $status);
    }

    protected function handleRedirectTo(string $url): RedirectResponse
    {
        $parsedUrl = parse_url(urldecode($url));
        $redirectUrl = $parsedUrl['path'];

        if (array_key_exists('query', $parsedUrl)) {
            $redirectUrl .= '?' . $parsedUrl['query'];
        }

        if (array_key_exists('fragment', $parsedUrl)) {
            $redirectUrl .= '#' . $parsedUrl['query'];
        }

        if (array_key_exists('host', $parsedUrl)) {
            throw new \RuntimeException('Absolute URLs are prohibited for the redirectTo parameter.');
        }

        return $this->redirect($redirectUrl);
    }
}

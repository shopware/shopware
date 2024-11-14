<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannel\AbstractContextSwitchRoute;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Routing\RequestTransformer;
use Shopware\Storefront\Framework\Routing\Router;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 * Do not use direct or indirect repository calls in a controller. Always use a store-api route to get or put data
 */
#[Route(defaults: ['_routeScope' => ['storefront']])]
#[Package('storefront')]
class ContextController extends StorefrontController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractContextSwitchRoute $contextSwitchRoute,
        private readonly RequestStack $requestStack,
        private readonly RouterInterface $router
    ) {
    }

    #[Route(path: '/checkout/configure', name: 'frontend.checkout.configure', options: ['seo' => false], defaults: ['XmlHttpRequest' => true], methods: ['POST'])]
    public function configure(Request $request, RequestDataBag $data, SalesChannelContext $context): Response
    {
        $this->contextSwitchRoute->switchContext($data, $context);

        return $this->createActionResponse($request);
    }

    #[Route(path: '/checkout/language', name: 'frontend.checkout.switch-language', methods: ['POST'])]
    public function switchLanguage(Request $request, SalesChannelContext $context): RedirectResponse
    {
        $languageId = $request->request->get('languageId');
        if (!$languageId) {
            throw RoutingException::missingRequestParameter('languageId');
        }

        if (!\is_string($languageId) || !Uuid::isValid($languageId)) {
            throw RoutingException::invalidRequestParameter('languageId');
        }

        try {
            $newTokenResponse = $this->contextSwitchRoute->switchContext(
                new RequestDataBag([SalesChannelContextService::LANGUAGE_ID => $languageId]),
                $context
            );
        } catch (ConstraintViolationException) {
            throw RoutingException::languageNotFound($languageId);
        }

        $params = $request->get('redirectParameters', '[]');
        if (\is_string($params)) {
            $params = json_decode($params, true);
        }

        $languageCode = $request->request->get('languageCode_' . $languageId);
        if ($languageCode) {
            $params['_locale'] = $languageCode;
        }

        $route = (string) $request->request->get('redirectTo', 'frontend.home.page');
        if (empty($route) || $this->routeTargetExists($route, $params) === false) {
            $route = 'frontend.home.page';
            $params = [];
        }

        if ($newTokenResponse->getRedirectUrl() === null) {
            return $this->redirectToRoute($route, $params);
        }

        /*
         * possible domains
         *
         * http://shopware.de/de
         * http://shopware.de/en
         * http://shopware.de/fr
         *
         * http://shopware.fr
         * http://shopware.com
         * http://shopware.de
         *
         * http://color.com
         * http://farben.de
         * http://couleurs.fr
         *
         * http://localhost/development/public/de
         * http://localhost/development/public/en
         * http://localhost/development/public/fr
         * http://localhost/development/public/de-DE
         *
         * http://localhost:8080
         * http://localhost:8080/en
         * http://localhost:8080/fr
         * http://localhost:8080/de-DE
         */
        $parsedUrl = parse_url($newTokenResponse->getRedirectUrl());

        if (!$parsedUrl) {
            throw RoutingException::languageNotFound($languageId);
        }

        $routerContext = $this->router->getContext();
        $routerContext->setHttpPort($parsedUrl['port'] ?? 80);
        $routerContext->setMethod('GET');
        $routerContext->setHost($parsedUrl['host']);
        $routerContext->setBaseUrl(rtrim($parsedUrl['path'] ?? '', '/'));

        if ($this->requestStack->getMainRequest()) {
            $this->requestStack->getMainRequest()
                ->attributes->set(RequestTransformer::SALES_CHANNEL_BASE_URL, '');
        }

        $url = $this->router->generate($route, $params, Router::ABSOLUTE_URL);

        return new RedirectResponse($url);
    }

    /**
     * @param array<string, mixed> $params
     */
    private function routeTargetExists(string $route, array $params): bool
    {
        try {
            $this->router->generate($route, $params);

            return true;
        } catch (RouteNotFoundException) {
            return false;
        }
    }
}

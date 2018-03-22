<?php
declare(strict_types=1);
/**
 * Shopware 5
 * Copyright (c) shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

namespace Shopware\Storefront\Session;

use Shopware\Api\Seo\Struct\SeoUrlBasicStruct;
use Shopware\Context\Struct\StorefrontContext;
use Shopware\DbalIndexing\SeoUrl\DetailPageSeoUrlIndexer;
use Shopware\DbalIndexing\SeoUrl\ListingPageSeoUrlIndexer;
use Shopware\Framework\Routing\Router;
use Shopware\StorefrontApi\Context\ContextSubscriber;
use Shopware\StorefrontApi\Context\ContextTokenResolverInterface;
use Shopware\StorefrontApi\Context\StorefrontContextPersister;
use Shopware\StorefrontApi\Context\StorefrontContextService;
use Shopware\StorefrontApi\Firewall\CustomerUser;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\AuthenticationEvents;
use Symfony\Component\Security\Core\Event\AuthenticationEvent;

class ShopSubscriber implements EventSubscriberInterface
{
    /**
     * @var StorefrontContextPersister
     */
    private $contextPersister;

    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var ContextTokenResolverInterface
     */
    private $tokenResolver;

    public function __construct(
        StorefrontContextPersister $contextPersister,
        RequestStack $requestStack,
        ContextTokenResolverInterface $tokenResolver
    ) {
        $this->contextPersister = $contextPersister;
        $this->requestStack = $requestStack;
        $this->tokenResolver = $tokenResolver;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [
                ['startSession', 20],
                ['setSeoRedirect', 10],
                ['setActiveCategory', 0],
            ],
            KernelEvents::RESPONSE => [
                ['setShopCookie', 10],
            ],
            AuthenticationEvents::AUTHENTICATION_SUCCESS => [
                ['login', 0],
            ],
            AuthenticationEvents::AUTHENTICATION_FAILURE => [
                ['logout', 0],
            ],
        ];
    }

    public function logout()
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request || !$request->getSession()) {
            return;
        }

        if (!$this->isStorefrontRequest($request)) {
            return;
        }

        $this->contextPersister->save(
            $this->tokenResolver->resolve($request),
            [StorefrontContextService::CUSTOMER_ID => null]
        );
    }

    public function login(AuthenticationEvent $event)
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request || !$request->getSession()) {
            return;
        }

        if (!$this->isStorefrontRequest($request)) {
            return;
        }

        $token = $event->getAuthenticationToken();

        if (!($token->getUser() instanceof CustomerUser)) {
            return;
        }

        $this->contextPersister->save(
            $this->tokenResolver->resolve($request),
            [StorefrontContextService::CUSTOMER_ID => $token->getUser()->getId()]
        );
    }

    public function setSeoRedirect(GetResponseEvent $event): void
    {
        $request = $event->getRequest();

        if (!$this->isStorefrontRequest($request)) {
            return;
        }

        if (!$request->attributes->has(Router::SEO_REDIRECT_URL)) {
            return;
        }

        $url = $request->attributes->get(Router::SEO_REDIRECT_URL);

        if (!$url instanceof SeoUrlBasicStruct) {
            return;
        }

        $event->stopPropagation();
        $event->setResponse(new RedirectResponse($url->getSeoPathInfo()));
    }

    public function startSession(GetResponseEvent $event): void
    {
        $request = $event->getRequest();

        if (!$this->isStorefrontRequest($request)) {
            return;
        }

        if (!$request->hasPreviousSession()) {
            return;
        }

        $shopId = $request->attributes->get('_shop_id');
        if (empty($shopId)) {
            return;
        }

        if (!$request->getSession()) {
            return;
        }

        if ($request->getSession()->isStarted()) {
            return;
        }

        $request->getSession()->setName('session-' . $shopId);
        $request->getSession()->start();
        $request->getSession()->set('sessionId', $request->getSession()->getId());
    }

    public function setActiveCategory(GetResponseEvent $event)
    {
        $request = $event->getRequest();

        if (!$this->isStorefrontRequest($request)) {
            return;
        }

        $context = $request->attributes->get(ContextSubscriber::SHOP_CONTEXT_PROPERTY);

        $request->attributes->set('active_category_id', $this->getActiveCategoryId($request, $context));
    }

    public function setShopCookie(FilterResponseEvent $event)
    {
        $request = $event->getRequest();

        if (!$this->isStorefrontRequest($request)) {
            return;
        }
        if (!$request->attributes->has('_shop_id')) {
            return;
        }

        $event->getResponse()->headers->setCookie(new Cookie('shop', $request->attributes->get('_shop_id')));
        $event->getResponse()->headers->setCookie(new Cookie('currency', $request->attributes->get('_currency_id')));
    }

    private function getActiveCategoryId(Request $request, StorefrontContext $context)
    {
        $route = $request->attributes->get('_route');

        switch ($route) {
            case ListingPageSeoUrlIndexer::ROUTE_NAME:
                return $request->attributes->get('_route_params')['id'];

            case DetailPageSeoUrlIndexer::ROUTE_NAME:
            default:
                return $context->getShop()->getCategoryId();
        }
    }

    private function isStorefrontRequest(Request $request): bool
    {
        $type = $request->attributes->get(Router::REQUEST_TYPE_ATTRIBUTE);

        return $type === Router::REQUEST_TYPE_STOREFRONT;
    }
}

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

use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Routing\Router;
use Shopware\Storefront\Context\StorefrontContextServiceInterface;
use Shopware\Storefront\DetailPage\DetailPageUrlGenerator;
use Shopware\Storefront\ListingPage\ListingPageUrlGenerator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

class ShopSubscriber implements EventSubscriberInterface
{
    const SHOP_CONTEXT_PROPERTY = '_shop_context';

    /**
     * @var StorefrontContextServiceInterface
     */
    private $contextService;

    /**
     * @var Router
     */
    private $router;

    public function __construct(
        StorefrontContextServiceInterface $contextService,
        Router $router
    ) {
        $this->contextService = $contextService;
        $this->router = $router;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [
                ['startSession', 50],
                ['setSeoRedirect', 40],
                ['loadContext', 30],
                ['setActiveCategory', 20],
            ],
            KernelEvents::RESPONSE => [
                ['setShopCookie', 10],
            ],
            KernelEvents::EXCEPTION => [
                ['handleRouterException', 20],
            ],
        ];
    }

    public function handleRouterException(GetResponseForExceptionEvent $event)
    {
        switch (true) {
            case $event->getException() instanceof NotFoundHttpException:
                $event->setResponse(new RedirectResponse($this->router->generate('homepage')));
                break;
        }
    }

    public function setSeoRedirect(GetResponseEvent $event): void
    {
        $request = $event->getRequest();

        if (!$request->attributes->has(Router::SEO_REDIRECT_URL)) {
            return;
        }

        $event->stopPropagation();

        $event->setResponse(
            new RedirectResponse(
                $request->attributes->get(Router::SEO_REDIRECT_URL)
            )
        );
    }

    public function startSession(GetResponseEvent $event): void
    {
        $request = $event->getRequest();
        if (!$request->hasPreviousSession()) {
            return;
        }

        $shopId = $request->attributes->get('_shop_uuid');
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

    public function loadContext(GetResponseEvent $event)
    {
        $request = $event->getRequest();
        $shopUuid = $request->attributes->get('_shop_uuid');

        if (empty($shopUuid)) {
            return;
        }
        if ($request->attributes->has(self::SHOP_CONTEXT_PROPERTY)) {
            return;
        }

        $context = $this->contextService->getShopContext();
        $request->attributes->set(self::SHOP_CONTEXT_PROPERTY, $context);
    }

    public function setActiveCategory(GetResponseEvent $event)
    {
        $request = $event->getRequest();
        $context = $request->attributes->get(self::SHOP_CONTEXT_PROPERTY);

        if (!$context) {
            return;
        }

        $request->attributes->set('active_category_uuid', $this->getActiveCategoryUuid($request, $context));
    }

    public function setShopCookie(FilterResponseEvent $event)
    {
        $request = $event->getRequest();

        if (!$request->attributes->has('_shop_uuid')) {
            return;
        }

        $event->getResponse()->headers->setCookie(new Cookie('shop', $request->attributes->get('_shop_uuid')));
        $event->getResponse()->headers->setCookie(new Cookie('currency', $request->attributes->get('_currency_uuid')));
    }

    private function getActiveCategoryUuid(Request $request, ShopContext $context)
    {
        $route = $request->attributes->get('_route');
        
        switch ($route) {
            case ListingPageUrlGenerator::ROUTE_NAME:
                return $request->attributes->get('_route_params')['uuid'];

            case DetailPageUrlGenerator::ROUTE_NAME:
            default:
                return $context->getShop()->getCategory()->getUuid();
        }
    }
}

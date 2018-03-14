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
use Shopware\Storefront\Context\StorefrontContextServiceInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class ShopSubscriber implements EventSubscriberInterface
{
    public const SHOP_CONTEXT_PROPERTY = 'shop_context';

    /**
     * @var StorefrontContextServiceInterface
     */
    private $contextService;

    public function __construct(StorefrontContextServiceInterface $contextService)
    {
        $this->contextService = $contextService;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [
                ['startSession', 7],
                ['setSeoRedirect', 6],
                ['loadContext', 5],
                ['setActiveCategory', 4],
            ],
            KernelEvents::RESPONSE => [
                ['setShopCookie', 10],
            ],
        ];
    }

    public function setSeoRedirect(GetResponseEvent $event): void
    {
        $request = $event->getRequest();

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
        if ($request->attributes->get(Router::IS_API_REQUEST_ATTRIBUTE)) {
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

    public function loadContext(GetResponseEvent $event)
    {
        $request = $event->getRequest();
        $shopId = $request->attributes->get('_shop_id');

        if (empty($shopId)) {
            return;
        }
        if ($request->attributes->has(self::SHOP_CONTEXT_PROPERTY)) {
            return;
        }

        $context = $this->contextService->getStorefrontContext();
        $request->attributes->set(self::SHOP_CONTEXT_PROPERTY, $context);
    }

    public function setActiveCategory(GetResponseEvent $event)
    {
        $request = $event->getRequest();
        $context = $request->attributes->get(self::SHOP_CONTEXT_PROPERTY);

        if (!$context) {
            return;
        }

        $request->attributes->set('active_category_id', $this->getActiveCategoryId($request, $context));
    }

    public function setShopCookie(FilterResponseEvent $event)
    {
        $request = $event->getRequest();

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
}

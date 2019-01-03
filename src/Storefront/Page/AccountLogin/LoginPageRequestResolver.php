<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\AccountLogin;

use Shopware\Core\PlatformRequest;
use Shopware\Storefront\Framework\Page\PageRequestResolver;
use Shopware\Storefront\Pagelet\AccountLogin\LoginPageletRequest;
use Shopware\Storefront\Pagelet\AccountLogin\LoginPageletRequestEvent;
use Shopware\Storefront\Pagelet\CartInfo\CartInfoPageletRequest;
use Shopware\Storefront\Pagelet\CartInfo\CartInfoPageletRequestEvent;
use Shopware\Storefront\Pagelet\Currency\CurrencyPageletRequest;
use Shopware\Storefront\Pagelet\Currency\CurrencyPageletRequestEvent;
use Shopware\Storefront\Pagelet\Language\LanguagePageletRequest;
use Shopware\Storefront\Pagelet\Language\LanguagePageletRequestEvent;
use Shopware\Storefront\Pagelet\Navigation\NavigationPageletRequest;
use Shopware\Storefront\Pagelet\Navigation\NavigationPageletRequestEvent;
use Shopware\Storefront\Pagelet\Shopmenu\ShopmenuPageletRequest;
use Shopware\Storefront\Pagelet\Shopmenu\ShopmenuPageletRequestEvent;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class LoginPageRequestResolver extends PageRequestResolver
{
    public function supports(Request $request, ArgumentMetadata $argument): bool
    {
        return $argument->getType() === LoginPageRequest::class;
    }

    public function resolve(Request $request, ArgumentMetadata $argument): \Generator
    {
        $context = $this->requestStack
            ->getMasterRequest()
            ->attributes
            ->get(PlatformRequest::ATTRIBUTE_STOREFRONT_CONTEXT_OBJECT);

        $loginPageRequest = new LoginPageRequest();

        $navigationPageletRequest = new NavigationPageletRequest();
        $event = new NavigationPageletRequestEvent($request, $context, $navigationPageletRequest);
        $this->eventDispatcher->dispatch(NavigationPageletRequestEvent::NAME, $event);
        $loginPageRequest->setNavigationRequest($navigationPageletRequest);

        $currencyPageletRequest = new CurrencyPageletRequest();
        $event = new CurrencyPageletRequestEvent($request, $context, $currencyPageletRequest);
        $this->eventDispatcher->dispatch(CurrencyPageletRequestEvent::NAME, $event);
        $loginPageRequest->setCurrencyRequest($currencyPageletRequest);

        $cartInfoPageletRequest = new CartInfoPageletRequest();
        $event = new CartInfoPageletRequestEvent($request, $context, $cartInfoPageletRequest);
        $this->eventDispatcher->dispatch(CartInfoPageletRequestEvent::NAME, $event);
        $loginPageRequest->setCartInfoRequest($cartInfoPageletRequest);

        $languagePageletRequest = new LanguagePageletRequest();
        $event = new LanguagePageletRequestEvent($request, $context, $languagePageletRequest);
        $this->eventDispatcher->dispatch(LanguagePageletRequestEvent::NAME, $event);
        $loginPageRequest->setLanguageRequest($languagePageletRequest);

        $shopmenuPageletRequest = new ShopmenuPageletRequest();
        $event = new ShopmenuPageletRequestEvent($request, $context, $shopmenuPageletRequest);
        $this->eventDispatcher->dispatch(ShopmenuPageletRequestEvent::NAME, $event);
        $loginPageRequest->setShopmenuRequest($shopmenuPageletRequest);

        $loginPageletRequest = new LoginPageletRequest();
        $event = new LoginPageletRequestEvent($request, $context, $loginPageletRequest);
        $this->eventDispatcher->dispatch(LoginPageletRequestEvent::NAME, $event);
        $loginPageRequest->setLoginRequest($loginPageletRequest);

        $event = new LoginPageRequestEvent($request, $context, $loginPageRequest);
        $this->eventDispatcher->dispatch(LoginPageRequestEvent::NAME, $event);

        yield $event->getLoginPageRequest();
    }
}

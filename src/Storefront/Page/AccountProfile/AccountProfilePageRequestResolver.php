<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\AccountProfile;

use Shopware\Core\PlatformRequest;
use Shopware\Storefront\Framework\Page\PageRequestResolver;
use Shopware\Storefront\Pagelet\AccountProfile\AccountProfilePageletRequest;
use Shopware\Storefront\Pagelet\AccountProfile\AccountProfilePageletRequestEvent;
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

class AccountProfilePageRequestResolver extends PageRequestResolver
{
    public function supports(Request $request, ArgumentMetadata $argument): bool
    {
        return $argument->getType() === AccountProfilePageRequest::class;
    }

    public function resolve(Request $request, ArgumentMetadata $argument): \Generator
    {
        $context = $this->requestStack
            ->getMasterRequest()
            ->attributes
            ->get(PlatformRequest::ATTRIBUTE_STOREFRONT_CONTEXT_OBJECT);

        $accountprofilePageRequest = new AccountProfilePageRequest();

        $navigationPageletRequest = new NavigationPageletRequest();
        $event = new NavigationPageletRequestEvent($request, $context, $navigationPageletRequest);
        $this->eventDispatcher->dispatch(NavigationPageletRequestEvent::NAME, $event);
        $accountprofilePageRequest->setNavigationRequest($navigationPageletRequest);

        $currencyPageletRequest = new CurrencyPageletRequest();
        $event = new CurrencyPageletRequestEvent($request, $context, $currencyPageletRequest);
        $this->eventDispatcher->dispatch(CurrencyPageletRequestEvent::NAME, $event);
        $accountprofilePageRequest->setCurrencyRequest($currencyPageletRequest);

        $cartInfoPageletRequest = new CartInfoPageletRequest();
        $event = new CartInfoPageletRequestEvent($request, $context, $cartInfoPageletRequest);
        $this->eventDispatcher->dispatch(CartInfoPageletRequestEvent::NAME, $event);
        $accountprofilePageRequest->setCartInfoRequest($cartInfoPageletRequest);

        $languagePageletRequest = new LanguagePageletRequest();
        $event = new LanguagePageletRequestEvent($request, $context, $languagePageletRequest);
        $this->eventDispatcher->dispatch(LanguagePageletRequestEvent::NAME, $event);
        $accountprofilePageRequest->setLanguageRequest($languagePageletRequest);

        $shopmenuPageletRequest = new ShopmenuPageletRequest();
        $event = new ShopmenuPageletRequestEvent($request, $context, $shopmenuPageletRequest);
        $this->eventDispatcher->dispatch(ShopmenuPageletRequestEvent::NAME, $event);
        $accountprofilePageRequest->setShopmenuRequest($shopmenuPageletRequest);

        $accountprofilePageletRequest = new AccountProfilePageletRequest();
        $event = new AccountProfilePageletRequestEvent($request, $context, $accountprofilePageletRequest);
        $this->eventDispatcher->dispatch(AccountProfilePageletRequestEvent::NAME, $event);
        $accountprofilePageRequest->setAccountProfileRequest($accountprofilePageletRequest);

        $event = new AccountProfilePageRequestEvent($request, $context, $accountprofilePageRequest);
        $this->eventDispatcher->dispatch(AccountProfilePageRequestEvent::NAME, $event);

        yield $event->getAccountProfilePageRequest();
    }
}

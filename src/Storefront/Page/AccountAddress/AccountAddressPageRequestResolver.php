<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\AccountAddress;

use Shopware\Core\PlatformRequest;
use Shopware\Storefront\Framework\Page\PageRequestResolver;
use Shopware\Storefront\Pagelet\AccountAddress\AddressPageletRequest;
use Shopware\Storefront\Pagelet\AccountAddress\AddressPageletRequestEvent;
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

class AccountAddressPageRequestResolver extends PageRequestResolver
{
    public function supports(Request $request, ArgumentMetadata $argument): bool
    {
        return $argument->getType() === AccountAddressPageRequest::class;
    }

    public function resolve(Request $httpRequest, ArgumentMetadata $argument): \Generator
    {
        $context = $this->requestStack
            ->getMasterRequest()
            ->attributes
            ->get(PlatformRequest::ATTRIBUTE_STOREFRONT_CONTEXT_OBJECT);

        $request = new AccountAddressPageRequest();

        $this->eventDispatcher->dispatch(
            AccountAddressPageRequestEvent::NAME,
            new AccountAddressPageRequestEvent($httpRequest, $context, $request)
        );

        yield $request;
    }
}

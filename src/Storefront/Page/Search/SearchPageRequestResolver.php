<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Search;

use Shopware\Core\PlatformRequest;
use Shopware\Storefront\Pagelet\CartInfo\CartInfoPageletRequest;
use Shopware\Storefront\Pagelet\CartInfo\CartInfoPageletRequestEvent;
use Shopware\Storefront\Pagelet\Currency\CurrencyPageletRequest;
use Shopware\Storefront\Pagelet\Currency\CurrencyPageletRequestEvent;
use Shopware\Storefront\Pagelet\Language\LanguagePageletRequest;
use Shopware\Storefront\Pagelet\Language\LanguagePageletRequestEvent;
use Shopware\Storefront\Pagelet\Listing\ListingPageletRequestEvent;
use Shopware\Storefront\Pagelet\Navigation\NavigationPageletRequest;
use Shopware\Storefront\Pagelet\Navigation\NavigationPageletRequestEvent;
use Shopware\Storefront\Pagelet\Search\SearchPageletRequest;
use Shopware\Storefront\Pagelet\Search\SearchPageletRequestEvent;
use Shopware\Storefront\Pagelet\Shopmenu\ShopmenuPageletRequest;
use Shopware\Storefront\Pagelet\Shopmenu\ShopmenuPageletRequestEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Controller\ArgumentValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class SearchPageRequestResolver implements ArgumentValueResolverInterface
{
    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var RequestStack
     */
    private $requestStack;

    public function __construct(EventDispatcherInterface $eventDispatcher, RequestStack $requestStack)
    {
        $this->eventDispatcher = $eventDispatcher;
        $this->requestStack = $requestStack;
    }

    public function supports(Request $request, ArgumentMetadata $argument): bool
    {
        return $argument->getType() === SearchPageRequest::class;
    }

    public function resolve(Request $request, ArgumentMetadata $argument): ?\Generator
    {
        $searchPageRequest = new SearchPageRequest();

        $context = $this->requestStack
            ->getMasterRequest()
            ->attributes
            ->get(PlatformRequest::ATTRIBUTE_STOREFRONT_CONTEXT_OBJECT);

        $navigationPageletRequest = new NavigationPageletRequest();
        $event = new NavigationPageletRequestEvent($request, $context, $navigationPageletRequest);
        $this->eventDispatcher->dispatch(NavigationPageletRequestEvent::NAME, $event);
        $searchPageRequest->setNavigationRequest($navigationPageletRequest);

        $currencyPageletRequest = new CurrencyPageletRequest();
        $event = new CurrencyPageletRequestEvent($request, $context, $currencyPageletRequest);
        $this->eventDispatcher->dispatch(CurrencyPageletRequestEvent::NAME, $event);
        $searchPageRequest->setCurrencyRequest($currencyPageletRequest);

        $cartInfoPageletRequest = new CartInfoPageletRequest();
        $event = new CartInfoPageletRequestEvent($request, $context, $cartInfoPageletRequest);
        $this->eventDispatcher->dispatch(CartInfoPageletRequestEvent::NAME, $event);
        $searchPageRequest->setCartInfoRequest($cartInfoPageletRequest);

        $languagePageletRequest = new LanguagePageletRequest();
        $event = new LanguagePageletRequestEvent($request, $context, $languagePageletRequest);
        $this->eventDispatcher->dispatch(LanguagePageletRequestEvent::NAME, $event);
        $searchPageRequest->setLanguageRequest($languagePageletRequest);

        $shopmenuPageletRequest = new ShopmenuPageletRequest();
        $event = new ShopmenuPageletRequestEvent($request, $context, $shopmenuPageletRequest);
        $this->eventDispatcher->dispatch(ShopmenuPageletRequestEvent::NAME, $event);
        $searchPageRequest->setShopmenuRequest($shopmenuPageletRequest);

        $searchPageletRequest = new SearchPageletRequest();
        $event = new SearchPageletRequestEvent($request, $context, $searchPageletRequest);
        $this->eventDispatcher->dispatch(SearchPageletRequestEvent::NAME, $event);
        $event = new ListingPageletRequestEvent($request, $context, $searchPageletRequest);
        $this->eventDispatcher->dispatch(ListingPageletRequestEvent::NAME, $event);
        $searchPageRequest->setSearchRequest($searchPageletRequest);

        $event = new SearchPageRequestEvent($request, $context, $searchPageRequest);
        $this->eventDispatcher->dispatch(SearchPageRequestEvent::NAME, $event);

        yield $event->getSearchPageRequest();
    }
}

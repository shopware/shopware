<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\AccountAddress;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Storefront\Pagelet\AccountAddress\AccountAddressPageletLoader;
use Shopware\Storefront\Pagelet\Header\HeaderPageletLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class AccountAddressPageLoader
{
    /**
     * @var AccountAddressPageletLoader
     */
    private $accountAddressPageletLoader;

    /**
     * @var HeaderPageletLoader
     */
    private $headerPageletLoader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(
        AccountAddressPageletLoader $accountAddressPageletLoader,
        HeaderPageletLoader $headerPageletLoader,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->accountAddressPageletLoader = $accountAddressPageletLoader;
        $this->headerPageletLoader = $headerPageletLoader;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @param AccountAddressPageRequest $request
     * @param CheckoutContext           $context
     *
     * @throws \Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException
     *
     * @return AccountAddressPageStruct
     */
    public function load(AccountAddressPageRequest $request, CheckoutContext $context): AccountAddressPageStruct
    {
        $page = new AccountAddressPageStruct();
        $page->setAccountAddress(
            $this->accountAddressPageletLoader->load($request->getAddressRequest(), $context)
        );

        $page->setHeader(
            $this->headerPageletLoader->load($request->getHeaderRequest(), $context)
        );

        $this->eventDispatcher->dispatch(
            AccountAddressPageLoadedEvent::NAME,
            new AccountAddressPageLoadedEvent($page, $context, $request)
        );

        return $page;
    }
}

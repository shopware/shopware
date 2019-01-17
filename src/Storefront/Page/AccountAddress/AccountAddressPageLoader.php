<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\AccountAddress;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Routing\InternalRequest;
use Shopware\Storefront\Pagelet\AccountAddress\AccountAddressPageletLoader;
use Shopware\Storefront\Pagelet\ContentHeader\ContentHeaderPageletLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class AccountAddressPageLoader
{
    /**
     * @var AccountAddressPageletLoader
     */
    private $accountAddressPageletLoader;

    /**
     * @var ContentHeaderPageletLoader
     */
    private $headerPageletLoader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(
        AccountAddressPageletLoader $accountAddressPageletLoader,
        ContentHeaderPageletLoader $headerPageletLoader,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->accountAddressPageletLoader = $accountAddressPageletLoader;
        $this->headerPageletLoader = $headerPageletLoader;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function load(InternalRequest $request, CheckoutContext $context): AccountAddressPageStruct
    {
        $page = new AccountAddressPageStruct();
        $page->setAccountAddress(
            $this->accountAddressPageletLoader->load($request, $context)
        );

        $page->setHeader(
            $this->headerPageletLoader->load($request, $context)
        );

        $this->eventDispatcher->dispatch(
            AccountAddressPageLoadedEvent::NAME,
            new AccountAddressPageLoadedEvent($page, $context, $request)
        );

        return $page;
    }
}

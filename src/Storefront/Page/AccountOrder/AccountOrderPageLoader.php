<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\AccountOrder;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Storefront\Pagelet\AccountOrder\AccountOrderPageletLoader;
use Shopware\Storefront\Pagelet\ContentHeader\ContentHeaderPageletLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class AccountOrderPageLoader
{
    /**
     * @var AccountOrderPageletLoader
     */
    private $accountOrderPageletLoader;

    /**
     * @var ContentHeaderPageletLoader
     */
    private $headerPageletLoader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(
        AccountOrderPageletLoader $accountOrderPageletLoader,
        ContentHeaderPageletLoader $headerPageletLoader,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->accountOrderPageletLoader = $accountOrderPageletLoader;
        $this->headerPageletLoader = $headerPageletLoader;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @param AccountOrderPageRequest $request
     * @param CheckoutContext         $context
     *
     * @throws \Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException
     *
     * @return AccountOrderPageStruct
     */
    public function load(AccountOrderPageRequest $request, CheckoutContext $context): AccountOrderPageStruct
    {
        $page = new AccountOrderPageStruct();
        $page->setAccountOrder(
            $this->accountOrderPageletLoader->load($request->getAccountOrderRequest(), $context)
        );

        $page->setHeader(
            $this->headerPageletLoader->load($request->getHeaderRequest(), $context)
        );

        $this->eventDispatcher->dispatch(
            AccountOrderPageLoadedEvent::NAME,
            new AccountOrderPageLoadedEvent($page, $context, $request)
        );

        return $page;
    }
}

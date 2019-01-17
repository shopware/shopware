<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\AccountPaymentMethod;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Storefront\Pagelet\AccountPaymentMethod\AccountPaymentMethodPageletLoader;
use Shopware\Storefront\Pagelet\ContentHeader\ContentHeaderPageletLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class AccountPaymentMethodPageLoader
{
    /**
     * @var AccountPaymentMethodPageletLoader
     */
    private $accountPaymentMethodPageletLoader;

    /**
     * @var ContentHeaderPageletLoader
     */
    private $headerPageletLoader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(
        AccountPaymentMethodPageletLoader $accountPaymentMethodPageletLoader,
        ContentHeaderPageletLoader $headerPageletLoader,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->accountPaymentMethodPageletLoader = $accountPaymentMethodPageletLoader;
        $this->headerPageletLoader = $headerPageletLoader;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @param AccountPaymentMethodPageRequest $request
     * @param CheckoutContext                 $context
     *
     * @return AccountPaymentMethodPageStruct
     */
    public function load(AccountPaymentMethodPageRequest $request, CheckoutContext $context): AccountPaymentMethodPageStruct
    {
        $page = new AccountPaymentMethodPageStruct();
        $page->setAccountPaymentMethod(
            $this->accountPaymentMethodPageletLoader->load($request->getAccountPaymentMethodRequest(), $context)
        );

        $page->setHeader(
            $this->headerPageletLoader->load($request->getHeaderRequest(), $context)
        );

        $this->eventDispatcher->dispatch(
            AccountPaymentMethodPageLoadedEvent::NAME,
            new AccountPaymentMethodPageLoadedEvent($page, $context, $request)
        );

        return $page;
    }
}

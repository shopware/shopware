<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\AccountPaymentMethod;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Routing\InternalRequest;
use Shopware\Storefront\Pagelet\AccountPaymentMethod\AccountPaymentMethodPageletLoader;
use Shopware\Storefront\Pagelet\Header\HeaderPageletLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class AccountPaymentMethodPageLoader
{
    /**
     * @var AccountPaymentMethodPageletLoader
     */
    private $accountPaymentMethodPageletLoader;

    /**
     * @var HeaderPageletLoader
     */
    private $headerPageletLoader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(
        AccountPaymentMethodPageletLoader $accountPaymentMethodPageletLoader,
        HeaderPageletLoader $headerPageletLoader,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->accountPaymentMethodPageletLoader = $accountPaymentMethodPageletLoader;
        $this->headerPageletLoader = $headerPageletLoader;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function load(InternalRequest $request, CheckoutContext $context): AccountPaymentMethodPageStruct
    {
        $page = new AccountPaymentMethodPageStruct();
        $page->setAccountPaymentMethod(
            $this->accountPaymentMethodPageletLoader->load($request, $context)
        );

        $page->setHeader(
            $this->headerPageletLoader->load($request, $context)
        );

        $this->eventDispatcher->dispatch(
            AccountPaymentMethodPageLoadedEvent::NAME,
            new AccountPaymentMethodPageLoadedEvent($page, $context, $request)
        );

        return $page;
    }
}

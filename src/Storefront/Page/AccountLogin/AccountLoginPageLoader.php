<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\AccountLogin;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Routing\InternalRequest;
use Shopware\Storefront\Pagelet\AccountLogin\AccountLoginPageletLoader;
use Shopware\Storefront\Pagelet\ContentHeader\ContentHeaderPageletLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class AccountLoginPageLoader
{
    /**
     * @var AccountLoginPageletLoader
     */
    private $accountLoginPageletLoader;

    /**
     * @var ContentHeaderPageletLoader
     */
    private $headerPageletLoader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(
        AccountLoginPageletLoader $accountLoginPageletLoader,
        ContentHeaderPageletLoader $headerPageletLoader,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->accountLoginPageletLoader = $accountLoginPageletLoader;
        $this->headerPageletLoader = $headerPageletLoader;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function load(InternalRequest $request, CheckoutContext $context): AccountLoginPageStruct
    {
        $page = new AccountLoginPageStruct();
        $page->setAccountLogin(
            $this->accountLoginPageletLoader->load($request, $context)
        );

        $page->setHeader(
            $this->headerPageletLoader->load($request, $context)
        );

        $this->eventDispatcher->dispatch(
            AccountLoginPageLoadedEvent::NAME,
            new AccountLoginPageLoadedEvent($page, $context, $request)
        );

        return $page;
    }
}

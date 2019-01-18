<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Login;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Routing\InternalRequest;
use Shopware\Storefront\Pagelet\Login\LoginPageletLoader;
use Shopware\Storefront\Pagelet\Header\HeaderPageletLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class LoginPageLoader
{
    /**
     * @var LoginPageletLoader
     */
    private $accountLoginPageletLoader;

    /**
     * @var HeaderPageletLoader
     */
    private $headerPageletLoader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(
        LoginPageletLoader $accountLoginPageletLoader,
        HeaderPageletLoader $headerPageletLoader,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->accountLoginPageletLoader = $accountLoginPageletLoader;
        $this->headerPageletLoader = $headerPageletLoader;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function load(InternalRequest $request, CheckoutContext $context): LoginPageStruct
    {
        $page = new LoginPageStruct();
        $page->setAccountLogin(
            $this->accountLoginPageletLoader->load($request, $context)
        );

        $page->setHeader(
            $this->headerPageletLoader->load($request, $context)
        );

        $this->eventDispatcher->dispatch(
            LoginPageLoadedEvent::NAME,
            new LoginPageLoadedEvent($page, $context, $request)
        );

        return $page;
    }
}

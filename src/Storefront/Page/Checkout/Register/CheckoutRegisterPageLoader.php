<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Checkout\Register;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Customer\Storefront\AccountService;
use Shopware\Core\Framework\Routing\InternalRequest;
use Shopware\Storefront\Framework\Page\PageLoaderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class CheckoutRegisterPageLoader implements PageLoaderInterface
{
    /**
     * @var PageLoaderInterface
     */
    private $pageWithHeaderLoader;
    /**
     * @var AccountService
     */
    private $accountService;
    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(
        PageLoaderInterface $pageWithHeaderLoader,
        AccountService $accountService,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->pageWithHeaderLoader = $pageWithHeaderLoader;
        $this->accountService = $accountService;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function load(InternalRequest $request, CheckoutContext $context): CheckoutRegisterPage
    {
        $page = $this->pageWithHeaderLoader->load($request, $context);

        $page = CheckoutRegisterPage::createFrom($page);

        $page->setCountries(
            $this->accountService->getCountryList($context)
        );

        $page->setSalutations($this->accountService->getSalutationList($context));

        $addressId = $request->optionalGet('addressId');
        if ($addressId) {
            $address = $this->accountService->getAddressById((string) $addressId, $context);
            $page->setAddress($address);
        }

        $this->eventDispatcher->dispatch(
            CheckoutRegisterPageLoadedEvent::NAME,
            new CheckoutRegisterPageLoadedEvent($page, $context, $request)
        );

        return $page;
    }
}

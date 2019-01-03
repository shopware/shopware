<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\AccountProfile;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Context;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\HttpFoundation\Request;

class AccountProfilePageRequestEvent extends Event
{
    public const NAME = 'accountprofile.page.request.event';

    /**
     * @var CheckoutContext
     */
    protected $context;

    /**
     * @var Request
     */
    protected $request;

    /**
     * @var AccountProfilePageRequest
     */
    protected $accountprofilePageRequest;

    public function __construct(Request $request, CheckoutContext $context, AccountProfilePageRequest $accountprofilePageRequest)
    {
        $this->context = $context;
        $this->request = $request;
        $this->accountprofilePageRequest = $accountprofilePageRequest;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->context->getContext();
    }

    public function getCheckoutContext(): CheckoutContext
    {
        return $this->context;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function getAccountProfilePageRequest(): AccountProfilePageRequest
    {
        return $this->accountprofilePageRequest;
    }
}

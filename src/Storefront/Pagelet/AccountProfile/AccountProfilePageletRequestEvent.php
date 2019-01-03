<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\AccountProfile;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Symfony\Component\HttpFoundation\Request;

class AccountProfilePageletRequestEvent extends NestedEvent
{
    public const NAME = 'accountprofile.pagelet.request.event';

    /**
     * @var CheckoutContext
     */
    protected $context;

    /**
     * @var Request
     */
    protected $request;

    /**
     * @var AccountProfilePageletRequest
     */
    protected $accountprofilePageletRequest;

    public function __construct(Request $request, CheckoutContext $context, AccountProfilePageletRequest $accountprofilePageletRequest)
    {
        $this->context = $context;
        $this->request = $request;
        $this->accountprofilePageletRequest = $accountprofilePageletRequest;
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

    public function getAccountProfilePageletRequest(): AccountProfilePageletRequest
    {
        return $this->accountprofilePageletRequest;
    }
}

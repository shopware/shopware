<?php declare(strict_types=1);

namespace Shopware\Storefront\Action\AccountProfile;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Symfony\Component\HttpFoundation\Request;

class AccountProfileSaveRequestEvent extends NestedEvent
{
    public const NAME = 'profile.save.request';

    /**
     * @var Request
     */
    private $request;

    /**
     * @var CheckoutContext
     */
    private $context;

    /**
     * @var AccountProfileSaveRequest
     */
    private $profileSaveRequest;

    public function __construct(Request $request, CheckoutContext $context, AccountProfileSaveRequest $profileSaveRequest)
    {
        $this->request = $request;
        $this->context = $context;
        $this->profileSaveRequest = $profileSaveRequest;
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

    public function getProfileSaveRequest(): AccountProfileSaveRequest
    {
        return $this->profileSaveRequest;
    }
}

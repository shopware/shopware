<?php declare(strict_types=1);

namespace Shopware\Storefront\Action\AccountEmail;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Symfony\Component\HttpFoundation\Request;

class AccountEmailSaveRequestEvent extends NestedEvent
{
    public const NAME = 'email.save.request';

    /**
     * @var Request
     */
    private $request;

    /**
     * @var CheckoutContext
     */
    private $context;

    /**
     * @var AccountEmailSaveRequest
     */
    private $emailSaveRequest;

    public function __construct(Request $request, CheckoutContext $context, AccountEmailSaveRequest $emailSaveRequest)
    {
        $this->request = $request;
        $this->context = $context;
        $this->emailSaveRequest = $emailSaveRequest;
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

    public function getEmailSaveRequest(): AccountEmailSaveRequest
    {
        return $this->emailSaveRequest;
    }
}

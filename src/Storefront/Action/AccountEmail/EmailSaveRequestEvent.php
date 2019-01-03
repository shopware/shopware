<?php declare(strict_types=1);

namespace Shopware\Storefront\Action\AccountEmail;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Symfony\Component\HttpFoundation\Request;

class EmailSaveRequestEvent extends NestedEvent
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
     * @var \Shopware\Storefront\Action\AccountEmail\EmailSaveRequest
     */
    private $emailSaveRequest;

    public function __construct(Request $request, CheckoutContext $context, EmailSaveRequest $emailSaveRequest)
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

    public function getEmailSaveRequest(): EmailSaveRequest
    {
        return $this->emailSaveRequest;
    }
}

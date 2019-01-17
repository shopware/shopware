<?php declare(strict_types=1);

namespace Shopware\Storefront\Action\AccountPassword;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Symfony\Component\HttpFoundation\Request;

class AccountPasswordSaveRequestEvent extends NestedEvent
{
    public const NAME = 'password.save.request';

    /**
     * @var Request
     */
    private $request;

    /**
     * @var CheckoutContext
     */
    private $context;

    /**
     * @var AccountPasswordSaveRequest
     */
    private $passwordSaveRequest;

    public function __construct(Request $request, CheckoutContext $context, AccountPasswordSaveRequest $passwordSaveRequest)
    {
        $this->request = $request;
        $this->context = $context;
        $this->passwordSaveRequest = $passwordSaveRequest;
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

    public function getPasswordSaveRequest(): AccountPasswordSaveRequest
    {
        return $this->passwordSaveRequest;
    }
}

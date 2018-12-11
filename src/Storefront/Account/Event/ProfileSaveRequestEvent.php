<?php declare(strict_types=1);

namespace Shopware\Storefront\Account\Event;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Storefront\Account\Page\ProfileSaveRequest;
use Symfony\Component\HttpFoundation\Request;

class ProfileSaveRequestEvent extends NestedEvent
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
     * @var ProfileSaveRequest
     */
    private $profileSaveRequest;

    public function __construct(Request $request, CheckoutContext $context, ProfileSaveRequest $profileSaveRequest)
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

    public function getProfileSaveRequest(): ProfileSaveRequest
    {
        return $this->profileSaveRequest;
    }
}

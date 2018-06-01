<?php declare(strict_types=1);

namespace Shopware\Storefront\Event;

use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Application\Context\Struct\StorefrontContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Storefront\Page\Account\EmailSaveRequest;
use Symfony\Component\HttpFoundation\Request;

class EmailSaveRequestEvent extends NestedEvent
{
    public const NAME = 'email.save.request';

    /**
     * @var Request
     */
    private $request;

    /**
     * @var StorefrontContext
     */
    private $context;

    /**
     * @var EmailSaveRequest
     */
    private $emailSaveRequest;

    public function __construct(Request $request, StorefrontContext $context, EmailSaveRequest $emailSaveRequest)
    {
        $this->request = $request;
        $this->context = $context;
        $this->emailSaveRequest = $emailSaveRequest;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
    {
        return $this->context->getApplicationContext();
    }

    public function getStorefrontContext(): StorefrontContext
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

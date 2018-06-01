<?php declare(strict_types=1);

namespace Shopware\Storefront\Event;

use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Application\Context\Struct\StorefrontContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Storefront\Page\Account\PasswordSaveRequest;
use Symfony\Component\HttpFoundation\Request;

class PasswordSaveRequestEvent extends NestedEvent
{
    public const NAME = 'password.save.request';

    /**
     * @var Request
     */
    private $request;

    /**
     * @var StorefrontContext
     */
    private $context;

    /**
     * @var PasswordSaveRequest
     */
    private $passwordSaveRequest;

    public function __construct(Request $request, StorefrontContext $context, PasswordSaveRequest $passwordSaveRequest)
    {
        $this->request = $request;
        $this->context = $context;
        $this->passwordSaveRequest = $passwordSaveRequest;
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

    public function getPasswordSaveRequest(): PasswordSaveRequest
    {
        return $this->passwordSaveRequest;
    }
}

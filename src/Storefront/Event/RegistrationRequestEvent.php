<?php declare(strict_types=1);

namespace Shopware\Storefront\Event;

use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Application\Context\Struct\StorefrontContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Storefront\Page\Account\RegistrationRequest;
use Symfony\Component\HttpFoundation\Request;

class RegistrationRequestEvent extends NestedEvent
{
    public const NAME = 'registration.request';

    /**
     * @var Request
     */
    private $request;

    /**
     * @var StorefrontContext
     */
    private $context;

    /**
     * @var RegistrationRequest
     */
    private $registrationRequest;

    public function __construct(Request $request, StorefrontContext $context, RegistrationRequest $registrationRequest)
    {
        $this->request = $request;
        $this->context = $context;
        $this->registrationRequest = $registrationRequest;
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

    public function getRegistrationRequest(): RegistrationRequest
    {
        return $this->registrationRequest;
    }
}

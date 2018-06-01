<?php declare(strict_types=1);

namespace Shopware\Storefront\Event;

use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Application\Context\Struct\StorefrontContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Storefront\Page\Account\AddressSaveRequest;
use Symfony\Component\HttpFoundation\Request;

class AddressSaveRequestEvent extends NestedEvent
{
    public const NAME = 'address.save.request';

    /**
     * @var Request
     */
    private $request;

    /**
     * @var StorefrontContext
     */
    private $context;

    /**
     * @var AddressSaveRequest
     */
    private $addressSaveRequest;

    public function __construct(Request $request, StorefrontContext $context, AddressSaveRequest $addressSaveRequest)
    {
        $this->request = $request;
        $this->context = $context;
        $this->addressSaveRequest = $addressSaveRequest;
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

    public function getAddressSaveRequest(): AddressSaveRequest
    {
        return $this->addressSaveRequest;
    }
}

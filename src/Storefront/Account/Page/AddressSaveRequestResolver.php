<?php declare(strict_types=1);

namespace Shopware\Storefront\Account\Page;

use Shopware\Core\PlatformRequest;
use Shopware\Storefront\Account\Event\AddressSaveRequestEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Controller\ArgumentValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class AddressSaveRequestResolver implements ArgumentValueResolverInterface
{
    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var RequestStack
     */
    private $requestStack;

    public function __construct(EventDispatcherInterface $eventDispatcher, RequestStack $requestStack)
    {
        $this->eventDispatcher = $eventDispatcher;
        $this->requestStack = $requestStack;
    }

    public function supports(Request $request, ArgumentMetadata $argument): bool
    {
        return $argument->getType() === AddressSaveRequest::class;
    }

    public function resolve(Request $request, ArgumentMetadata $argument)
    {
        $pageRequest = new AddressSaveRequest();

        $pageRequest->setHttpRequest($request);

        $context = $this->requestStack
            ->getMasterRequest()
            ->attributes
            ->get(PlatformRequest::ATTRIBUTE_STOREFRONT_CONTEXT_OBJECT);

        $event = new AddressSaveRequestEvent($request, $context, $pageRequest);
        $this->eventDispatcher->dispatch(AddressSaveRequestEvent::NAME, $event);

        yield $event->getAddressSaveRequest();
    }
}

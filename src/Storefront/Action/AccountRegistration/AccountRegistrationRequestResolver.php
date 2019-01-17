<?php declare(strict_types=1);

namespace Shopware\Storefront\Action\AccountRegistration;

use Shopware\Core\PlatformRequest;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Controller\ArgumentValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class AccountRegistrationRequestResolver implements ArgumentValueResolverInterface
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
        return $argument->getType() === AccountRegistrationRequest::class;
    }

    public function resolve(Request $request, ArgumentMetadata $argument): ?\Generator
    {
        $registrationRequest = new AccountRegistrationRequest();

        $context = $this->requestStack
            ->getMasterRequest()
            ->attributes
            ->get(PlatformRequest::ATTRIBUTE_STOREFRONT_CONTEXT_OBJECT);

        $event = new AccountRegistrationRequestEvent($request, $context, $registrationRequest);
        $this->eventDispatcher->dispatch(AccountRegistrationRequestEvent::NAME, $event);

        yield $event->getRegistrationRequest();
    }
}

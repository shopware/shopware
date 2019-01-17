<?php declare(strict_types=1);

namespace Shopware\Storefront\Action\AccountEmail;

use Shopware\Core\PlatformRequest;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Controller\ArgumentValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class AccountEmailSaveRequestResolver implements ArgumentValueResolverInterface
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
        return $argument->getType() === AccountEmailSaveRequest::class;
    }

    public function resolve(Request $request, ArgumentMetadata $argument): ?\Generator
    {
        $emailSaveRequest = new AccountEmailSaveRequest();

        $context = $this->requestStack
            ->getMasterRequest()
            ->attributes
            ->get(PlatformRequest::ATTRIBUTE_STOREFRONT_CONTEXT_OBJECT);

        $event = new AccountEmailSaveRequestEvent($request, $context, $emailSaveRequest);
        $this->eventDispatcher->dispatch(AccountEmailSaveRequestEvent::NAME, $event);

        yield $event->getEmailSaveRequest();
    }
}

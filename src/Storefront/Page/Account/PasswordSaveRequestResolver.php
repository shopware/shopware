<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account;

use Shopware\PlatformRequest;
use Shopware\Storefront\Event\PasswordSaveRequestEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Controller\ArgumentValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class PasswordSaveRequestResolver implements ArgumentValueResolverInterface
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
        return $argument->getType() === PasswordSaveRequest::class;
    }

    public function resolve(Request $request, ArgumentMetadata $argument)
    {
        $passwordSaveRequest = new PasswordSaveRequest();

        $context = $this->requestStack
            ->getMasterRequest()
            ->attributes
            ->get(PlatformRequest::ATTRIBUTE_STOREFRONT_CONTEXT_OBJECT);

        $event = $this->eventDispatcher->dispatch(
            PasswordSaveRequestEvent::NAME,
            new PasswordSaveRequestEvent($request, $context, $passwordSaveRequest)
        );

        yield $event->getPasswordSaveRequest();
    }
}

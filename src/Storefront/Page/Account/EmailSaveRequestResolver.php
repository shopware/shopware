<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account;

use Shopware\PlatformRequest;
use Shopware\Storefront\Event\EmailSaveRequestEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Controller\ArgumentValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class EmailSaveRequestResolver implements ArgumentValueResolverInterface
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
        return $argument->getType() === EmailSaveRequest::class;
    }

    public function resolve(Request $request, ArgumentMetadata $argument)
    {
        $emailSaveRequest = new EmailSaveRequest();

        $context = $this->requestStack
            ->getMasterRequest()
            ->attributes
            ->get(PlatformRequest::ATTRIBUTE_STOREFRONT_CONTEXT_OBJECT);

        $event = $this->eventDispatcher->dispatch(
            EmailSaveRequestEvent::NAME,
            new EmailSaveRequestEvent($request, $context, $emailSaveRequest)
        );

        yield $event->getEmailSaveRequest();
    }
}

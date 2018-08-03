<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Listing;

use Shopware\Core\PlatformRequest;
use Shopware\Storefront\Event\ListingPageRequestEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Controller\ArgumentValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class ListingPageRequestResolver implements ArgumentValueResolverInterface
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

    public function supports(Request $request, ArgumentMetadata $argument)
    {
        return $argument->getType() === ListingPageRequest::class;
    }

    public function resolve(Request $request, ArgumentMetadata $argument)
    {
        $pageRequest = new ListingPageRequest();

        $context = $this->requestStack
            ->getMasterRequest()
            ->attributes
            ->get(PlatformRequest::ATTRIBUTE_STOREFRONT_CONTEXT_OBJECT);

        $event = new ListingPageRequestEvent($request, $context, $pageRequest);
        $this->eventDispatcher->dispatch(ListingPageRequestEvent::NAME, $event);

        yield $event->getListingPageRequest();
    }
}

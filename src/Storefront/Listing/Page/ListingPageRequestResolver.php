<?php declare(strict_types=1);

namespace Shopware\Storefront\Listing\Page;

use Shopware\Core\PlatformRequest;
use Shopware\Storefront\Framework\Page\PageRequestResolver;
use Shopware\Storefront\Listing\Event\ListingPageRequestEvent;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class ListingPageRequestResolver extends PageRequestResolver
{
    public function supports(Request $request, ArgumentMetadata $argument): bool
    {
        return $argument->getType() === ListingPageRequest::class;
    }

    public function resolve(Request $request, ArgumentMetadata $argument): \Generator
    {
        $pageRequest = new ListingPageRequest();

        $pageRequest->setHttpRequest($request);
        $context = $this->requestStack
            ->getMasterRequest()
            ->attributes
            ->get(PlatformRequest::ATTRIBUTE_STOREFRONT_CONTEXT_OBJECT);

        $event = new ListingPageRequestEvent($request, $context, $pageRequest);
        $this->eventDispatcher->dispatch(ListingPageRequestEvent::NAME, $event);

        yield $event->getListingPageRequest();
    }
}

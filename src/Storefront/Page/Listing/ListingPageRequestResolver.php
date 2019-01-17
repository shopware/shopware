<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Listing;

use Shopware\Core\PlatformRequest;
use Shopware\Storefront\Framework\Page\PageRequestResolver;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class ListingPageRequestResolver extends PageRequestResolver
{
    public function supports(Request $httpRequest, ArgumentMetadata $argument): bool
    {
        return $argument->getType() === ListingPageRequest::class;
    }

    public function resolve(Request $httpRequest, ArgumentMetadata $argument): \Generator
    {
        $context = $this->requestStack
            ->getMasterRequest()
            ->attributes
            ->get(PlatformRequest::ATTRIBUTE_STOREFRONT_CONTEXT_OBJECT);

        $request = new ListingPageRequest();

        $this->eventDispatcher->dispatch(
            ListingPageRequestEvent::NAME,
            new ListingPageRequestEvent($httpRequest, $context, $request)
        );

        yield $request;
    }
}

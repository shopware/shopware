<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Search;

use Shopware\Core\PlatformRequest;
use Shopware\Storefront\Framework\Page\PageRequestResolver;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class SearchPageletRequestResolver extends PageRequestResolver
{
    public function supports(Request $request, ArgumentMetadata $argument): bool
    {
        return $argument->getType() === SearchPageletRequest::class;
    }

    public function resolve(Request $request, ArgumentMetadata $argument): \Generator
    {
        $context = $this->requestStack
            ->getMasterRequest()
            ->attributes
            ->get(PlatformRequest::ATTRIBUTE_STOREFRONT_CONTEXT_OBJECT);

        $searchPageletRequest = new SearchPageletRequest();

        $event = new SearchPageletRequestEvent($request, $context, $searchPageletRequest);
        $this->eventDispatcher->dispatch(SearchPageletRequestEvent::NAME, $event);

        yield $event->getSearchPageletRequest();
    }
}

<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Navigation;

use Shopware\Core\PlatformRequest;
use Shopware\Storefront\Framework\Page\PageRequestResolver;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class NavigationPageletRequestResolver extends PageRequestResolver
{
    public function supports(Request $request, ArgumentMetadata $argument): bool
    {
        return $argument->getType() === NavigationPageletRequest::class;
    }

    public function resolve(Request $request, ArgumentMetadata $argument): \Generator
    {
        $pageRequest = new NavigationPageletRequest();

        $context = $this->requestStack
            ->getMasterRequest()
            ->attributes
            ->get(PlatformRequest::ATTRIBUTE_STOREFRONT_CONTEXT_OBJECT);

        $event = new NavigationPageletRequestEvent($request, $context, $pageRequest);
        $this->eventDispatcher->dispatch(NavigationPageletRequestEvent::NAME, $event);

        yield $event->getPageletRequest();
    }
}

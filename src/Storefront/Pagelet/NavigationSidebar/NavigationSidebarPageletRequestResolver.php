<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\NavigationSidebar;

use Shopware\Core\PlatformRequest;
use Shopware\Storefront\Framework\Page\PageRequestResolver;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class NavigationSidebarPageletRequestResolver extends PageRequestResolver
{
    public function supports(Request $request, ArgumentMetadata $argument): bool
    {
        return $argument->getType() === NavigationSidebarPageletRequest::class;
    }

    public function resolve(Request $request, ArgumentMetadata $argument): \Generator
    {
        $context = $this->requestStack
            ->getMasterRequest()
            ->attributes
            ->get(PlatformRequest::ATTRIBUTE_STOREFRONT_CONTEXT_OBJECT);

        $navigationsidebarPageletRequest = new NavigationSidebarPageletRequest();

        $event = new NavigationSidebarPageletRequestEvent($request, $context, $navigationsidebarPageletRequest);
        $this->eventDispatcher->dispatch(NavigationSidebarPageletRequestEvent::NAME, $event);

        yield $event->getNavigationSidebarPageletRequest();
    }
}

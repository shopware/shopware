<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\ContentHome;

use Shopware\Core\PlatformRequest;
use Shopware\Storefront\Framework\Page\PageRequestResolver;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class ContentHomePageletRequestResolver extends PageRequestResolver
{
    public function supports(Request $request, ArgumentMetadata $argument): bool
    {
        return $argument->getType() === ContentHomePageletRequest::class;
    }

    public function resolve(Request $request, ArgumentMetadata $argument): \Generator
    {
        $context = $this->requestStack
            ->getMasterRequest()
            ->attributes
            ->get(PlatformRequest::ATTRIBUTE_STOREFRONT_CONTEXT_OBJECT);

        $contentHomePageletRequest = new ContentHomePageletRequest();

        $event = new ContentHomePageletRequestEvent($request, $context, $contentHomePageletRequest);
        $this->eventDispatcher->dispatch(ContentHomePageletRequestEvent::NAME, $event);

        yield $event->getContentHomePageletRequest();
    }
}

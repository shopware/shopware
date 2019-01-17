<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\ContentLanguage;

use Shopware\Core\PlatformRequest;
use Shopware\Storefront\Framework\Page\PageRequestResolver;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class ContentLanguagePageletRequestResolver extends PageRequestResolver
{
    public function supports(Request $request, ArgumentMetadata $argument): bool
    {
        return $argument->getType() === ContentLanguagePageletRequest::class;
    }

    public function resolve(Request $request, ArgumentMetadata $argument): \Generator
    {
        $context = $this->requestStack
            ->getMasterRequest()
            ->attributes
            ->get(PlatformRequest::ATTRIBUTE_STOREFRONT_CONTEXT_OBJECT);

        $contentLanguagePageletRequest = new ContentLanguagePageletRequest();

        $event = new ContentLanguagePageletRequestEvent($request, $context, $contentLanguagePageletRequest);
        $this->eventDispatcher->dispatch(ContentLanguagePageletRequestEvent::NAME, $event);

        yield $event->getContentLanguagePageletRequest();
    }
}

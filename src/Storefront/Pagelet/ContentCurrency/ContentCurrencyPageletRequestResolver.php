<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\ContentCurrency;

use Shopware\Core\PlatformRequest;
use Shopware\Storefront\Framework\Page\PageRequestResolver;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class ContentCurrencyPageletRequestResolver extends PageRequestResolver
{
    public function supports(Request $request, ArgumentMetadata $argument): bool
    {
        return $argument->getType() === ContentCurrencyPageletRequest::class;
    }

    public function resolve(Request $request, ArgumentMetadata $argument): \Generator
    {
        $context = $this->requestStack
            ->getMasterRequest()
            ->attributes
            ->get(PlatformRequest::ATTRIBUTE_STOREFRONT_CONTEXT_OBJECT);

        $contentCurrencyPageletRequest = new ContentCurrencyPageletRequest();

        $event = new ContentCurrencyPageletRequestEvent($request, $context, $contentCurrencyPageletRequest);
        $this->eventDispatcher->dispatch(ContentCurrencyPageletRequestEvent::NAME, $event);

        yield $event->getContentCurrencyPageletRequest();
    }
}

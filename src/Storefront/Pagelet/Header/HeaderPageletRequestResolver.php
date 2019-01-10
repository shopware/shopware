<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Header;

use Shopware\Core\PlatformRequest;
use Shopware\Storefront\Framework\Page\PageRequestResolver;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class HeaderPageletRequestResolver extends PageRequestResolver
{
    public function supports(Request $request, ArgumentMetadata $argument): bool
    {
        return $argument->getType() === HeaderPageletRequestResolver::class;
    }

    public function resolve(Request $httpRequest, ArgumentMetadata $argument): \Generator
    {
        $context = $this->requestStack
            ->getMasterRequest()
            ->attributes
            ->get(PlatformRequest::ATTRIBUTE_STOREFRONT_CONTEXT_OBJECT);

        $request = new HeaderPageletRequest();

        $event = new HeaderPageletRequestEvent($httpRequest, $context, $request);
        $this->eventDispatcher->dispatch(HeaderPageletRequestEvent::NAME, $event);

        yield $request;
    }
}

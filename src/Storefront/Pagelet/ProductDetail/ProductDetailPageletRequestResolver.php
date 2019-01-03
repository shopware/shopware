<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\ProductDetail;

use Shopware\Core\PlatformRequest;
use Shopware\Storefront\Framework\Page\PageRequestResolver;
use Shopware\Storefront\Page\ProductDetail\ProductDetailPageRequestEvent;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class ProductDetailPageletRequestResolver extends PageRequestResolver
{
    public function supports(Request $request, ArgumentMetadata $argument): bool
    {
        return $argument->getType() === ProductDetailPageletRequest::class;
    }

    public function resolve(Request $request, ArgumentMetadata $argument): \Generator
    {
        $context = $this->requestStack
            ->getMasterRequest()
            ->attributes
            ->get(PlatformRequest::ATTRIBUTE_STOREFRONT_CONTEXT_OBJECT);

        $detailPageletRequest = new ProductDetailPageletRequest();

        $event = new ProductDetailPageletRequestEvent($request, $context, $detailPageletRequest);
        $this->eventDispatcher->dispatch(ProductDetailPageRequestEvent::NAME, $event);

        yield $event->getDetailPageletRequest();
    }
}

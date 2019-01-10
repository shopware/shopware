<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\CheckoutPaymentMethod;

use Shopware\Core\PlatformRequest;
use Shopware\Storefront\Framework\Page\PageRequestResolver;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class CheckoutPaymentMethodPageletRequestResolver extends PageRequestResolver
{
    public function supports(Request $request, ArgumentMetadata $argument): bool
    {
        return $argument->getType() === CheckoutPaymentMethodPageletRequest::class;
    }

    public function resolve(Request $request, ArgumentMetadata $argument): \Generator
    {
        $context = $this->requestStack
            ->getMasterRequest()
            ->attributes
            ->get(PlatformRequest::ATTRIBUTE_STOREFRONT_CONTEXT_OBJECT);

        $checkoutPaymentMethodPageletRequest = new CheckoutPaymentMethodPageletRequest();

        $event = new CheckoutPaymentMethodPageletRequestEvent($request, $context, $checkoutPaymentMethodPageletRequest);
        $this->eventDispatcher->dispatch(CheckoutPaymentMethodPageletRequestEvent::NAME, $event);

        yield $event->getCheckoutPaymentMethodPageletRequest();
    }
}

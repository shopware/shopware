<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\AccountPaymentMethod;

use Shopware\Core\PlatformRequest;
use Shopware\Storefront\Framework\Page\PageRequestResolver;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class AccountPaymentMethodPageletRequestResolver extends PageRequestResolver
{
    public function supports(Request $request, ArgumentMetadata $argument): bool
    {
        return $argument->getType() === AccountPaymentMethodPageletRequest::class;
    }

    public function resolve(Request $request, ArgumentMetadata $argument): \Generator
    {
        $context = $this->requestStack
            ->getMasterRequest()
            ->attributes
            ->get(PlatformRequest::ATTRIBUTE_STOREFRONT_CONTEXT_OBJECT);

        $accountPaymentMethodPageletRequest = new AccountPaymentMethodPageletRequest();

        $event = new AccountPaymentMethodPageletRequestEvent($request, $context, $accountPaymentMethodPageletRequest);
        $this->eventDispatcher->dispatch(AccountPaymentMethodPageletRequestEvent::NAME, $event);

        yield $event->getAccountPaymentMethodPageletRequest();
    }
}

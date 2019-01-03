<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\AccountOrder;

use Shopware\Core\PlatformRequest;
use Shopware\Storefront\Framework\Page\PageRequestResolver;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class AccountOrderPageletRequestResolver extends PageRequestResolver
{
    public function supports(Request $request, ArgumentMetadata $argument): bool
    {
        return $argument->getType() === AccountOrderPageletRequest::class;
    }

    public function resolve(Request $request, ArgumentMetadata $argument): \Generator
    {
        $context = $this->requestStack
            ->getMasterRequest()
            ->attributes
            ->get(PlatformRequest::ATTRIBUTE_STOREFRONT_CONTEXT_OBJECT);

        $accountorderPageletRequest = new AccountOrderPageletRequest();

        $event = new AccountOrderPageletRequestEvent($request, $context, $accountorderPageletRequest);
        $this->eventDispatcher->dispatch(AccountOrderPageletRequestEvent::NAME, $event);

        yield $event->getAccountOrderPageletRequest();
    }
}

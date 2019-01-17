<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\AccountOrder;

use Shopware\Core\PlatformRequest;
use Shopware\Storefront\Framework\Page\PageRequestResolver;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class AccountOrderPageRequestResolver extends PageRequestResolver
{
    public function supports(Request $httpRequest, ArgumentMetadata $argument): bool
    {
        return $argument->getType() === AccountOrderPageRequest::class;
    }

    public function resolve(Request $httpRequest, ArgumentMetadata $argument): \Generator
    {
        $context = $this->requestStack
            ->getMasterRequest()
            ->attributes
            ->get(PlatformRequest::ATTRIBUTE_STOREFRONT_CONTEXT_OBJECT);

        $request = new AccountOrderPageRequest();

        $this->eventDispatcher->dispatch(
            AccountOrderPageRequestEvent::NAME,
            new AccountOrderPageRequestEvent($httpRequest, $context, $request)
        );

        yield $request;
    }
}

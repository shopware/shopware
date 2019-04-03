<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing;

use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Context\CheckoutContextServiceInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

class SalesChannelRequestContextResolver implements RequestContextResolverInterface
{
    /**
     * @var RequestContextResolverInterface
     */
    private $decorated;

    /**
     * @var CheckoutContextServiceInterface
     */
    private $contextService;

    public function __construct(
        RequestContextResolverInterface $decorated,
        CheckoutContextServiceInterface $contextService
    ) {
        $this->decorated = $decorated;
        $this->contextService = $contextService;
    }

    public function resolve(SymfonyRequest $master, SymfonyRequest $request): void
    {
        if (!$master->attributes->has(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID)) {
            $this->decorated->resolve($master, $request);

            return;
        }

        if (!$master->headers->has(PlatformRequest::HEADER_CONTEXT_TOKEN)) {
            $master->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, Uuid::randomHex());
        }

        $contextToken = $master->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN);
        $salesChannelId = $master->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID);

        // sub requests can use the context of the master request
        if ($master->attributes->has(PlatformRequest::ATTRIBUTE_STOREFRONT_CONTEXT_OBJECT)) {
            $context = $master->attributes->get(PlatformRequest::ATTRIBUTE_STOREFRONT_CONTEXT_OBJECT);
        } else {
            $context = $this->contextService->get(
                $salesChannelId,
                $contextToken,
                $master->headers->get(PlatformRequest::HEADER_LANGUAGE_ID)
            );
        }

        $request->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, $context->getContext());
        $request->attributes->set(PlatformRequest::ATTRIBUTE_STOREFRONT_CONTEXT_OBJECT, $context);
    }

    public function handleCheckoutContext(Request $request, Request $master, string $salesChannelId, string $contextToken): void
    {
        // sub requests can use the context of the master request
        if ($master->attributes->has(PlatformRequest::ATTRIBUTE_STOREFRONT_CONTEXT_OBJECT)) {
            $context = $master->attributes->get(PlatformRequest::ATTRIBUTE_STOREFRONT_CONTEXT_OBJECT);
        } else {
            $context = $this->contextService->get($salesChannelId, $contextToken, $request->headers->get(PlatformRequest::HEADER_LANGUAGE_ID));
        }

        $request->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, $context->getContext());
        $request->attributes->set(PlatformRequest::ATTRIBUTE_STOREFRONT_CONTEXT_OBJECT, $context);
    }
}

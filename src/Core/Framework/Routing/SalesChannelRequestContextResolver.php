<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing;

use Shopware\Core\Framework\Routing\Event\SalesChannelContextResolvedEvent;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

class SalesChannelRequestContextResolver implements RequestContextResolverInterface
{
    /**
     * @var RequestContextResolverInterface
     */
    private $decorated;

    /**
     * @var SalesChannelContextServiceInterface
     */
    private $contextService;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(
        RequestContextResolverInterface $decorated,
        SalesChannelContextServiceInterface $contextService,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->decorated = $decorated;
        $this->contextService = $contextService;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function resolve(SymfonyRequest $master, SymfonyRequest $request): void
    {
        if (!$master->attributes->has(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID)) {
            $this->decorated->resolve($master, $request);

            return;
        }

        if (!$master->headers->has(PlatformRequest::HEADER_CONTEXT_TOKEN)) {
            $master->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, Random::getAlphanumericString(32));
        }

        $contextToken = $master->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN);
        $salesChannelId = $master->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID);

        // sub requests can use the context of the master request
        if ($master->attributes->has(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT)) {
            $context = $master->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT);
        } else {
            $context = $this->contextService->get(
                $salesChannelId,
                $contextToken,
                $master->headers->get(PlatformRequest::HEADER_LANGUAGE_ID),
                null
            );
        }

        $request->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, $context->getContext());
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $context);

        $this->eventDispatcher->dispatch(
            new SalesChannelContextResolvedEvent($context)
        );
    }

    public function handleSalesChannelContext(Request $request, Request $master, string $salesChannelId, string $contextToken): void
    {
        // sub requests can use the context of the master request
        if ($master->attributes->has(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT)) {
            $context = $master->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT);
        } else {
            $context = $this->contextService->get($salesChannelId, $contextToken, $request->headers->get(PlatformRequest::HEADER_LANGUAGE_ID), null);
        }

        $request->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, $context->getContext());
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $context);
    }
}

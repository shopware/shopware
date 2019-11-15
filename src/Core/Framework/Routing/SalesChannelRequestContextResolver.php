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

    public function resolve(SymfonyRequest $request): void
    {
        if ($request->attributes->has(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT)) {
            return;
        }

        if (!$request->attributes->has(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID)) {
            $this->decorated->resolve($request);

            return;
        }

        if (!$request->headers->has(PlatformRequest::HEADER_CONTEXT_TOKEN)) {
            $request->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, Random::getAlphanumericString(32));
        }

        $contextToken = $request->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN);
        $salesChannelId = $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID);
        $language = $request->headers->get(PlatformRequest::HEADER_LANGUAGE_ID);

        $context = $this->contextService->get(
            $salesChannelId,
            $contextToken,
            $language
        );

        $request->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, $context->getContext());
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $context);

        $this->eventDispatcher->dispatch(
            new SalesChannelContextResolvedEvent($context)
        );
    }

    public function handleSalesChannelContext(Request $request, string $salesChannelId, string $contextToken): void
    {
        $language = $request->headers->get(PlatformRequest::HEADER_LANGUAGE_ID);

        $context = $this->contextService
            ->get($salesChannelId, $contextToken, $language);

        $request->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, $context->getContext());
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $context);
    }
}

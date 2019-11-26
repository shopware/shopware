<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing;

use Shopware\Core\Framework\Routing\Event\SalesChannelContextResolvedEvent;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

class SalesChannelRequestContextResolver implements RequestContextResolverInterface
{
    use RouteScopeCheckTrait;

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

    /**
     * @var SalesChannelContext[]
     */
    private $cache = [];

    /**
     * @var RouteScopeRegistry
     */
    private $routeScopeRegistry;

    public function __construct(
        RequestContextResolverInterface $decorated,
        SalesChannelContextServiceInterface $contextService,
        EventDispatcherInterface $eventDispatcher,
        RouteScopeRegistry $routeScopeRegistry
    ) {
        $this->decorated = $decorated;
        $this->contextService = $contextService;
        $this->eventDispatcher = $eventDispatcher;
        $this->routeScopeRegistry = $routeScopeRegistry;
    }

    public function resolve(SymfonyRequest $request): void
    {
        if (!$request->attributes->has(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID)) {
            $this->decorated->resolve($request);

            return;
        }

        if (!$this->isRequestScoped($request, SalesChannelContextRouteScopeDependant::class)) {
            return;
        }

        if (!$request->headers->has(PlatformRequest::HEADER_CONTEXT_TOKEN)) {
            $request->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, Random::getAlphanumericString(32));
        }

        $contextToken = $request->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN);
        $salesChannelId = $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID);
        $language = $request->headers->get(PlatformRequest::HEADER_LANGUAGE_ID);

        $cacheKey = $salesChannelId . $contextToken . $language;

        if (!empty($this->cache[$cacheKey])) {
            $context = $this->cache[$cacheKey];
        } else {
            $context = $this->contextService->get(
                $salesChannelId,
                $contextToken,
                $language
            );
        }

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

    protected function getScopeRegistry(): RouteScopeRegistry
    {
        return $this->routeScopeRegistry;
    }
}

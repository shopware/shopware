<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Routing\Event\SalesChannelContextResolvedEvent;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Uuid;
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

    /**
     * @var Connection
     */
    private $connection;

    public function __construct(
        RequestContextResolverInterface $decorated,
        SalesChannelContextServiceInterface $contextService,
        EventDispatcherInterface $eventDispatcher,
        RouteScopeRegistry $routeScopeRegistry,
        Connection $connection
    ) {
        $this->decorated = $decorated;
        $this->contextService = $contextService;
        $this->eventDispatcher = $eventDispatcher;
        $this->routeScopeRegistry = $routeScopeRegistry;
        $this->connection = $connection;
    }

    public function resolve(SymfonyRequest $request): void
    {
        $salesChannelId = $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID);
        $validRequest = $this->isRequestScoped($request, SalesChannelContextRouteScopeDependant::class);

        if (!$validRequest) {
            $this->decorated->resolve($request);
            return;
        }

        if ($salesChannelId === null) {
            $salesChannelId = $this->getSalesChannelIdFromDomain($request->getSchemeAndHttpHost());
        }

        if (!$request->headers->has(PlatformRequest::HEADER_CONTEXT_TOKEN)) {
            $request->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, Random::getAlphanumericString(32));
        }

        $contextToken = $request->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN);
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

    private function getSalesChannelIdFromDomain(string $domain): ?string
    {
        /** @var string|false $salesChannelId */
        $salesChannelId = $this->connection->createQueryBuilder()
            ->select('sales_channel_id')
            ->from('sales_channel_domain')
            ->andWhere('url = :domain')
            ->setParameter('domain', $domain)
            ->execute()
            ->fetchColumn();

        if ($salesChannelId !== false) {
            return Uuid::fromBytesToHex($salesChannelId);
        }

        return null;
    }

    protected function getScopeRegistry(): RouteScopeRegistry
    {
        return $this->routeScopeRegistry;
    }
}

<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Framework\AgenticDiscovery\AgenticDiscoveryDocumentType;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\PlatformRequest;
use Shopware\Storefront\Framework\Routing\StorefrontRouteScope;
use Shopware\Storefront\Page\AgenticDiscovery\AgenticDiscoveryPageLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Serves the four public agentic discovery documents per storefront
 * sales-channel domain:
 *
 *  - /agents.md                          — agent operating manual (Markdown)
 *  - /llms.txt                           — short LLM curator overview (Markdown)
 *  - /llms-full.txt                      — extended LLM overview (Markdown)
 *  - /sitemap_agentic_discovery.xml      — AI-focused sitemap (XML)
 *
 * All routes are anonymous GETs and are aggressively cached at the reverse
 * proxy via explicit `Cache-Control: public, s-maxage=...` headers. Cache
 * invalidation is event-driven: the
 * `AgenticDiscoveryCacheInvalidationSubscriber` reacts to writes on
 * `agentic_discovery_sales_channel_config` and invalidates the corresponding
 * cache tag, so merchant changes propagate immediately while well-behaved
 * crawlers (GPTBot, ClaudeBot, PerplexityBot) hit the cache.
 *
 * A per-IP rate limiter (`RateLimiter::AGENTIC_DISCOVERY`, 60 req/min sliding
 * window) provides defense-in-depth for cache misses; well-behaved agents
 * issue at most one request per document per refresh interval and never trip
 * it.
 *
 * 404 is returned (instead of an error response) when the `AGENTIC_DISCOVERY`
 * feature flag is off or no active discovery configuration matches the
 * request domain, so probing agents see a clean signal without leaking
 * whether the feature is configured at all.
 *
 * The route family deliberately mirrors what Shopify ships natively on every
 * storefront so any standards-compliant agent can resolve the same paths
 * across platforms.
 *
 * @experimental stableVersion:v6.8.0 feature:AGENTIC_DISCOVERY
 *
 * @internal
 *
 * @codeCoverageIgnore — covered by integration tests
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StorefrontRouteScope::ID], 'auth_required' => false])]
#[Package('framework')]
class AgenticDiscoveryController extends StorefrontController
{
    /**
     * @internal
     */
    public const REQUEST_ATTRIBUTE_AGENTIC_DISCOVERY = '_agentic_discovery_cache';
    /**
     * Browser/crawler cache lifetime. Kept short so merchant edits propagate
     * quickly even on agents that ignore reverse-proxy invalidation.
     */
    private const CACHE_MAX_AGE = 300;

    /**
     * Reverse-proxy cache lifetime. Long enough to absorb crawler storms but
     * short enough that an external CDN keeps the documents fresh without
     * relying solely on tag-based invalidation.
     */
    private const CACHE_S_MAX_AGE = 3600;

    public function __construct(
        private readonly AgenticDiscoveryPageLoader $pageLoader,
        private readonly RateLimiter $rateLimiter,
    ) {
    }

    #[Route(
        path: '/agents.md',
        name: 'frontend.agentic_discovery.agents_md',
        defaults: [
            '_format' => 'txt',
            PlatformRequest::ATTRIBUTE_HTTP_CACHE => true,
        ],
        methods: [Request::METHOD_GET]
    )]
    public function agentsMd(Request $request, Context $context): Response
    {
        return $this->renderDocument(
            $request,
            $context,
            AgenticDiscoveryDocumentType::AGENTS_MD,
            '@Storefront/storefront/page/agentic-discovery/agents.md.twig',
            'text/markdown; charset=utf-8'
        );
    }

    #[Route(
        path: '/llms.txt',
        name: 'frontend.agentic_discovery.llms_txt',
        defaults: [
            '_format' => 'txt',
            PlatformRequest::ATTRIBUTE_HTTP_CACHE => true,
        ],
        methods: [Request::METHOD_GET]
    )]
    public function llmsTxt(Request $request, Context $context): Response
    {
        return $this->renderDocument(
            $request,
            $context,
            AgenticDiscoveryDocumentType::LLMS_TXT,
            '@Storefront/storefront/page/agentic-discovery/llms.txt.twig',
            'text/markdown; charset=utf-8'
        );
    }

    #[Route(
        path: '/llms-full.txt',
        name: 'frontend.agentic_discovery.llms_full_txt',
        defaults: [
            '_format' => 'txt',
            PlatformRequest::ATTRIBUTE_HTTP_CACHE => true,
        ],
        methods: [Request::METHOD_GET]
    )]
    public function llmsFullTxt(Request $request, Context $context): Response
    {
        return $this->renderDocument(
            $request,
            $context,
            AgenticDiscoveryDocumentType::LLMS_FULL_TXT,
            '@Storefront/storefront/page/agentic-discovery/llms-full.txt.twig',
            'text/markdown; charset=utf-8'
        );
    }

    #[Route(
        path: '/sitemap_agentic_discovery.xml',
        name: 'frontend.agentic_discovery.sitemap',
        defaults: [
            '_format' => 'xml',
            PlatformRequest::ATTRIBUTE_HTTP_CACHE => true,
        ],
        methods: [Request::METHOD_GET]
    )]
    public function agenticSitemap(Request $request, Context $context): Response
    {
        return $this->renderDocument(
            $request,
            $context,
            AgenticDiscoveryDocumentType::AGENTIC_SITEMAP,
            '@Storefront/storefront/page/agentic-discovery/sitemap-agentic-discovery.xml.twig',
            'application/xml; charset=utf-8'
        );
    }

    private function renderDocument(
        Request $request,
        Context $context,
        AgenticDiscoveryDocumentType $type,
        string $template,
        string $contentType
    ): Response {
        if (!Feature::isActive('AGENTIC_DISCOVERY')) {
            return new Response(null, Response::HTTP_NOT_FOUND);
        }

        // Per-IP rate limit applies to cache misses only; the reverse proxy
        // absorbs cache hits before they reach PHP. Client IP includes
        // X-Forwarded-For when running behind a trusted proxy.
        $this->rateLimiter->ensureAcceptedIfConfigured(
            RateLimiter::AGENTIC_DISCOVERY,
            (string) $request->getClientIp()
        );

        $page = $this->pageLoader->load($type, $request, $context);
        if ($page === null) {
            return new Response(null, Response::HTTP_NOT_FOUND);
        }

        $response = $this->render($template, ['page' => $page]);
        $response->headers->set('content-type', $contentType);

        // Mark this response for the AgenticDiscoveryCacheSubscriber so it
        // overwrites the storefront's `private` cache policy after Shopware's
        // CacheResponseSubscriber has run. We deliberately do not set the
        // headers here because Shopware's subscriber runs at priority -1500
        // on KernelEvents::RESPONSE and would clobber them.
        $request->attributes->set(
            self::REQUEST_ATTRIBUTE_AGENTIC_DISCOVERY,
            [
                'salesChannelId' => $page->getManifest()->getSalesChannelId(),
                'maxAge' => self::CACHE_MAX_AGE,
                'sMaxAge' => self::CACHE_S_MAX_AGE,
            ]
        );

        return $response;
    }
}

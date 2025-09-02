<?php declare(strict_types=1);

namespace Shopware\Core;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\ContextAwareCacheHeadersService;

#[Package('framework')]
final class PlatformRequest
{
    /**
     * Response Headers
     */
    public const HEADER_FRAME_OPTIONS = 'x-frame-options';

    /**
     * Context headers
     */
    public const HEADER_CONTEXT_TOKEN = 'sw-context-token';
    public const HEADER_ACCESS_KEY = 'sw-access-key';
    public const HEADER_LANGUAGE_ID = 'sw-language-id';
    public const HEADER_CURRENCY_ID = 'sw-currency-id';
    public const HEADER_INHERITANCE = 'sw-inheritance';
    public const HEADER_VERSION_ID = 'sw-version-id';
    public const HEADER_INCLUDE_SEO_URLS = 'sw-include-seo-urls';
    public const HEADER_SKIP_TRIGGER_FLOW = 'sw-skip-trigger-flow';
    public const HEADER_APP_INTEGRATION_ID = 'sw-app-integration-id';
    public const HEADER_APP_USER_ID = 'sw-app-user-id';
    public const HEADER_INDEXING_BEHAVIOR = 'indexing-behavior';
    public const HEADER_INDEXING_SKIP = 'indexing-skip';
    public const HEADER_FORCE_CACHE_INVALIDATE = 'sw-force-cache-invalidate';

    public const HEADER_MEASUREMENT_WEIGHT_UNIT = 'sw-measurement-weight-unit';

    public const HEADER_MEASUREMENT_LENGTH_UNIT = 'sw-measurement-length-unit';

    /**
     * API Expectation headers to check requirements are fulfilled
     */
    public const HEADER_EXPECT_PACKAGES = 'sw-expect-packages';

    /**
     * Context hash header used for cache differentiation on user-independent routes, @see ContextAwareCacheHeadersService
     *
     * The hash is used to differentiate cache entries for the same route when the context
     * changes (e.g., different language, currency, or rules). This ensures that
     * user-independent routes like product listings, category pages, and search results
     * are properly cached per context variation while maintaining cache efficiency.
     *
     * The header is automatically added to Store API responses and included in the Vary
     * header together with HEADER_LANGUAGE_ID and HEADER_CURRENCY_ID to ensure proper
     * cache separation by reverse proxies and CDNs.
     *
     * Header has to be include in every request to ensure cache hits.
     */
    public const HEADER_CONTEXT_HASH = 'sw-context-hash';

    /**
     * Context attributes
     */
    public const ATTRIBUTE_CONTEXT_OBJECT = 'sw-context';
    public const ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT = 'sw-sales-channel-context';
    public const ATTRIBUTE_SALES_CHANNEL_ID = 'sw-sales-channel-id';
    public const ATTRIBUTE_IMITATING_USER_ID = 'sw-imitating-user-id';

    public const ATTRIBUTE_ACL = '_acl';
    public const ATTRIBUTE_CAPTCHA = '_captcha';
    public const ATTRIBUTE_ROUTE_SCOPE = '_routeScope';
    public const ATTRIBUTE_ENTITY = '_entity';
    public const ATTRIBUTE_NO_STORE = '_noStore';
    public const ATTRIBUTE_HTTP_CACHE = '_httpCache';
    public const ATTRIBUTE_CONTEXT_TOKEN_REQUIRED = '_contextTokenRequired';
    public const ATTRIBUTE_LOGIN_REQUIRED = '_loginRequired';
    public const ATTRIBUTE_LOGIN_REQUIRED_ALLOW_GUEST = '_loginRequiredAllowGuest';
    public const ATTRIBUTE_IS_ALLOWED_IN_MAINTENANCE = 'allow_maintenance';

    /**
     * CSP
     */
    public const ATTRIBUTE_CSP_NONCE = '_cspNonce';

    /**
     * OAuth attributes
     */
    public const ATTRIBUTE_OAUTH_ACCESS_TOKEN_ID = 'oauth_access_token_id';
    public const ATTRIBUTE_OAUTH_CLIENT_ID = 'oauth_client_id';
    public const ATTRIBUTE_OAUTH_USER_ID = 'oauth_user_id';
    public const ATTRIBUTE_OAUTH_SCOPES = 'oauth_scopes';

    public const FALLBACK_SESSION_NAME = 'session-';

    private function __construct()
    {
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core;

use Shopware\Core\Framework\Log\Package;

#[Package('core')]
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

    /**
     * Sync controller headers
     */
    public const HEADER_FAIL_ON_ERROR = 'fail-on-error';
    public const HEADER_SINGLE_OPERATION = 'single-operation';
    public const HEADER_INDEXING_BEHAVIOR = 'indexing-behavior';
    public const HEADER_INDEXING_SKIP = 'indexing-skip';

    /**
     * This header is used in the administration to get all fields
     *
     * @deprecated tag:v6.6.0 - Will be removed as it is not used and has no effect anymore
     */
    public const HEADER_IGNORE_DEPRECATIONS = 'sw-api-compatibility';

    /**
     * API Expectation headers to check requirements are fulfilled
     */
    public const HEADER_EXPECT_PACKAGES = 'sw-expect-packages';

    /**
     * Context attributes
     */
    public const ATTRIBUTE_CONTEXT_OBJECT = 'sw-context';
    public const ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT = 'sw-sales-channel-context';
    public const ATTRIBUTE_SALES_CHANNEL_ID = 'sw-sales-channel-id';

    public const ATTRIBUTE_ACL = '_acl';
    public const ATTRIBUTE_CAPTCHA = '_captcha';
    public const ATTRIBUTE_ROUTE_SCOPE = '_routeScope';
    public const ATTRIBUTE_ENTITY = '_entity';
    public const ATTRIBUTE_NO_STORE = '_noStore';
    public const ATTRIBUTE_HTTP_CACHE = '_httpCache';
    public const ATTRIBUTE_CONTEXT_TOKEN_REQUIRED = '_contextTokenRequired';
    public const ATTRIBUTE_LOGIN_REQUIRED = '_loginRequired';
    public const ATTRIBUTE_LOGIN_REQUIRED_ALLOW_GUEST = '_loginRequiredAllowGuest';

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

    private function __construct()
    {
    }
}

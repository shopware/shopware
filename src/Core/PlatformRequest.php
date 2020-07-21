<?php declare(strict_types=1);

namespace Shopware\Core;

final class PlatformRequest
{
    // should be increased with every major version
    public const API_VERSION = 3;

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

    /**
     * Sync controller headers
     */
    public const HEADER_FAIL_ON_ERROR = 'fail-on-error';
    public const HEADER_SINGLE_OPERATION = 'single-operation';
    public const HEADER_INDEXING_BEHAVIOR = 'indexing-behavior';

    /**
     * This header is used in the administration to get all fields
     */
    public const HEADER_IGNORE_DEPRECATIONS = 'sw-api-compatibility';

    /**
     * Context attributes
     */
    public const ATTRIBUTE_CONTEXT_OBJECT = 'sw-context';
    public const ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT = 'sw-sales-channel-context';
    public const ATTRIBUTE_SALES_CHANNEL_ID = 'sw-sales-channel-id';
    public const ATTRIBUTE_ROUTE_SCOPE = '_routeScope';
    public const ATTRIBUTE_CONTEXT_TOKEN_REQUIRED = '_contextTokenRequired';

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

<?php declare(strict_types=1);

namespace Shopware\Core;

final class PlatformRequest
{
    // should be increased with every major version
    public const API_VERSION = 1;

    /**
     * Response Headers
     */
    public const HEADER_FRAME_OPTIONS = 'x-frame-options';

    /**
     * Context headers
     */
    public const HEADER_CONTEXT_TOKEN = 'x-sw-context-token';
    public const HEADER_ACCESS_KEY = 'x-sw-access-key';
    public const HEADER_LANGUAGE_ID = 'x-sw-language-id';
    public const HEADER_INHERITANCE = 'x-sw-inheritance';
    public const HEADER_VERSION_ID = 'x-sw-version-id';

    /**
     * Context attributes
     */
    public const ATTRIBUTE_CONTEXT_OBJECT = 'x-sw-context';
    public const ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT = 'x-sw-sales-channel-context';
    public const ATTRIBUTE_SALES_CHANNEL_ID = 'x-sw-sales-channel-id';

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

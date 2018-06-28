<?php declare(strict_types=1);

namespace Shopware\Core;

final class PlatformRequest
{
    // should be increased with every major version
    public const API_VERSION = 1;

    /**
     * Context headers
     */
    public const HEADER_CONTEXT_TOKEN = 'x-sw-context-token';
    public const HEADER_TENANT_ID = 'x-sw-tenant-id';

    /**
     * Context attributes
     */
    public const ATTRIBUTE_CONTEXT_OBJECT = 'x-sw-context';
    public const ATTRIBUTE_STOREFRONT_CONTEXT_OBJECT = 'x-sw-storefront-context';

    /**
     * OAuth attributes
     */
    public const ATTRIBUTE_OAUTH_ACCESS_TOKEN_ID = 'oauth_access_token_id';
    public const ATTRIBUTE_OAUTH_CLIENT_ID = 'oauth_client_id';
    public const ATTRIBUTE_OAUTH_USER_ID = 'oauth_user_id';
    public const ATTRIBUTE_OAUTH_SCOPES = 'oauth_scopes';
}

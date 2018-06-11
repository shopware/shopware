<?php declare(strict_types=1);

namespace Shopware\Core;

final class PlatformRequest
{
    // should be increased with every major version
    public const API_VERSION = 1;
    public const HEADER_ACCESS_KEY = 'x-sw-access-key';
    public const HEADER_TOUCHPOINT_TOKEN = 'x-sw-touchpoint-token';
    public const HEADER_CONTEXT_TOKEN = 'x-sw-context-token';
    public const HEADER_TENANT_ID = 'x-sw-tenant-id';
    public const ATTRIBUTE_CONTEXT_OBJECT = 'x-sw-context';
    public const ATTRIBUTE_STOREFRONT_CONTEXT_OBJECT = 'x-sw-storefront-context';
}
